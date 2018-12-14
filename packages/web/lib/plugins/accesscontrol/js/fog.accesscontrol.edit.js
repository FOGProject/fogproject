$(function() {
    // Any special functions that can be commonized for this element.
    var onCheckboxSelect = function(event) {
    };
    // ---------------------------------------------------------------
    // GENERAL TAB
    var originalName = $('#role').val(),
        updateName = function(newName) {
            var e = $('#pageTitle'),
                text = e.text();
            text = text.replace(': ' + originalName, ': ' + newName);
            document.title = text;
            e.text(text);
        };

    var generalForm = $('#role-general-form'),
        generalFormBtn = $('#general-send'),
        generalDeleteBtn = $('#general-delete'),
        generalDeleteModal = $('#deleteModal'),
        generalDeleteModalConfirm = $('#confirmDeleteModal'),
        generalDeleteModalCancel = $('#closeDeleteModal');

    generalForm.on('submit',function(e) {
        e.preventDefault();
    });
    generalFormBtn.on('click',function() {
        generalFormBtn.prop('disabled', true);
        generalDeleteBtn.prop('disabled', true);
        generalForm.processForm(function(err) {
            generalFormBtn.prop('disabled', false);
            generalDeleteBtn.prop('disabled', false);
            if (err) {
                return;
            }
            updateName($('#role').val());
            originalName = $('#role').val();
        });
    });
    generalDeleteBtn.on('click', function() {
        generalDeleteModal.modal('show');
    });
    generalDeleteModalConfirm.on('click', function() {
        var method = 'post',
            action = '../management/index.php?node='
                + Common.node
                + '&sub=delete&id='
                + Common.id;
        $.apiCall(method, action, null, function(err) {
            if (err) {
                return;
            }
            setTimeout(function() {
                window.location = '../management/index.php?node='
                    + Common.node
                    + '&sub=list';
            }, 2000);
        });
    });

    // ---------------------------------------------------------------
    // RULE ASSOCIATION TAB
    var rulesAddBtn = $('#rules-add'),
        rulesRemoveBtn = $('#rules-remove');

    rulesAddBtn.prop('disabled', true);
    rulesRemoveBtn.prop('disabled', true);

    function onRulesSelect (selected) {
        var disabled = selected.count() == 0;
        rulesAddBtn.prop('disabled', disabled);
        rulesRemoveBtn.prop('disabled', disabled);
    }

    var rulesTable = $('#role-rules-table').registerTable(onRulesSelect, {
        order: [
            [4, 'asc'],
            [0, 'asc']
        ],
        columns: [
            {data: 'name'},
            {data: 'parent'},
            {data: 'value'},
            {data: 'node'},
            {data: 'association'}
        ],
        rowId: 'id',
        columnDefs: [
            {
                responsivePriority: -1,
                render: function(data, type, row) {
                    return '<a href="../management/index.php?node=accesscontrolrule&sub=edit&id='
                    + row.id
                    + '">'
                    + data
                    + '</a>';
                },
                targets: 0
            },
            {
                render: function(data, type, row) {
                    var checkval = '';
                    if (row.association === 'associated') {
                        checkval = ' checked';
                    }
                    return '<div class="checkbox">'
                    + '<input type="checkbox" class="associated" name="associate[]" id="ruleAssoc_'
                    + row.id
                    + '" value="' + row.id + '"'
                    + checkval
                    + '/>'
                    + '</div>';
                },
                targets: 4
            }
        ],
        processing: true,
        serverSide: true,
        ajax: {
            url: '../management/index.php?node='
            + Common.node
            + '&sub=getRulesList&id='
            + Common.id,
            type: 'post'
        }
    });

    rulesTable.on('draw', function() {
        Common.iCheck('#role-rules-table input');
    });

    rulesAddBtn.on('click', function() {
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            rows = rulesTable.rows({selected: true}),
            toAdd = $.getSelectedIds(rulesTable),
            opts = {
                updaterules: 1,
                rule: toAdd
            };
        $.apiCall(method,action,opts,function(err) {
            if (err) {
                return;
            }
            rulesTable.draw(false);
            rulesTable.rows({selected: true}).deselect();
        });
    });

    rulesRemoveBtn.on('click', function() {
        $('#ruleDelModal').modal('show');
    });
    $('#confirmruleDeleteModal').on('click', function(e) {
        $.deleteAssociated(rulesTable, rulesRemoveBtn.attr('action'), function(err) {
            if (err) {
                return;
            }
            $('#ruleDelModal').modal('hide');
            rulesTable.draw(false);
            rulesTable.rows({selected: true}).deselect();
        });
    });

    if (Common.search && Common.search.length > 0) {
        rulesTable.search(Common.search).draw();
    }

    // ---------------------------------------------------------------
    // USER ASSOCIATION TAB
    var usersAddBtn = $('#users-add'),
        usersRemoveBtn = $('#users-remove');

    usersAddBtn.prop('disabled', true);
    usersRemoveBtn.prop('disabled', true);

    function onUsersSelect (selected) {
        var disabled = selected.count() == 0;
        usersAddBtn.prop('disabled', disabled);
        usersRemoveBtn.prop('disabled', disabled);
    }

    var usersTable = $('#role-users-table').registerTable(onUsersSelect, {
        order: [
            [1, 'asc'],
            [0, 'asc']
        ],
        columns: [
            {data: 'name'},
            {data: 'association'}
        ],
        rowId: 'id',
        columnDefs: [
            {
                responsivePriority: -1,
                render: function(data, type, row) {
                    return '<a href="../management/index.php?node=user&sub=edit&id='
                    + row.id
                    + '">'
                    + data
                    + '</a>';
                },
                targets: 0
            },
            {
                render: function(data, type, row) {
                    var checkval = '';
                    if (row.association === 'associated') {
                        checkval = ' checked';
                    }
                    return '<div class="checkbox">'
                    + '<input type="checkbox" class="associated" name="associate[]" id="userAssoc_'
                    + row.id
                    + '" value="' + row.id + '"'
                    + checkval
                    + '/>'
                    + '</div>';
                },
                targets: 1
            }
        ],
        processing: true,
        serverSide: true,
        ajax: {
            url: '../management/index.php?node='
            + Common.node
            + '&sub=getUsersList&id='
            + Common.id,
            type: 'post'
        }
    });

    usersTable.on('draw', function() {
        Common.iCheck('#role-users-table input');
    });

    usersAddBtn.on('click', function() {
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            rows = usersTable.rows({selected: true}),
            toAdd = $.getSelectedIds(usersTable),
            opts = {
                updateusers: 1,
                user: toAdd
            };
        $.apiCall(method,action,opts,function(err) {
            if (err) {
                return;
            }
            usersTable.draw(false);
            usersTable.rows({selected: true}).deselect();
        });
    });

    usersRemoveBtn.on('click', function() {
        $('#userDelModal').modal('show');
    });
    $('#confirmuserDeleteModal').on('click', function(e) {
        $.deleteAssociated(usersTable, usersRemoveBtn.attr('action'), function(err) {
            if (err) {
                return;
            }
            $('#userDelModal').modal('hide');
            usersTable.draw(false);
            usersTable.rows({selected: true}).deselect();
        });
    });

    if (Common.search && Common.search.length > 0) {
        usersTable.search(Common.search).draw();
    }
});
