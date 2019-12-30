$(function() {
    // ---------------------------------------------------------------
    // GENERAL TAB
    var originalName = $('#role').val(),
        updateName = function(newName) {
            var e = $('#pageTitle'),
                text = e.text();
            text = text.replace(': ' + originalName, ': ' + newName);
            document.title = text;
            e.text(text);
        },
        generalForm = $('#accesscontrol-general-form'),
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
    var accesscontrolRuleUpdateBtn = $('#accesscontrol-rule-send'),
        accesscontrolRuleRemoveBtn = $('#accesscontrol-rule-remove'),
        accesscontrolRuleDeleteConfirmBtn = $('#confirmruleDeleteModal');

    function disableRuleButtons(disable) {
        accesscontrolRuleUpdateBtn.prop('disabled', disable);
        accesscontrolRuleRemoveBtn.prop('disabled', disable);
    }

    function onRuleSelect(selected) {
        var disabled = selected.count() == 0;
        disableRuleButtons(disabled);
    }

    accesscontrolRuleUpdateBtn.on('click', function(e) {
        e.preventDefault();
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            rows = accesscontrolRulesTable.rows({selected: true}),
            toAdd = $.getSelectedIds(accesscontrolRulesTable),
            opts = {
                confirmadd: 1,
                additems: toAdd
            };
        $.apiCall(method,action,opts,function(err) {
            disableRuleButtons(false);
            if (err) {
                return;
            }
            accesscontrolRulesTable.draw(false);
            accesscontrolRulesTable.rows({selected: true}).deselect();
        });
    });

    accesscontrolRuleRemoveBtn.on('click', function(e) {
        $('#ruleDelModal').modal('show');
    });

    var accesscontrolRulesTable = $('#accesscontrol-rule-table').registerTable(onRuleSelect, {
        order: [
            [1, 'asc'],
            [0, 'asc']
        ],
        columns: [
            {data: 'mainLink'},
            {data: 'association'},
        ],
        rowId: 'id',
        columnDefs: [
            {
                render: function(data, type, row) {
                    var checkval = '';
                    if (row.association === 'associated') {
                        checkval = ' checked';
                    }
                    return '<div class="checkbox">'
                        + '<input type="checkbox" class="associated" name="associate[]" id="accesscontrolRuleAssoc_'
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
            url: '../management/index.php?node='+Common.node+'&sub=getRulesList&id='+Common.id,
            type: 'post'
        }
    });

    accesscontrolRuleDeleteConfirmBtn.on('click', function(e) {
        $.deleteAssociated(accesscontrolRulesTable, accesscontrolRuleUpdateBtn.attr('action'), function(err) {
            $('#ruleDelModal').modal('hide');
            if (err) {
                return;
            }
            accesscontrolRulesTable.draw(false);
            accesscontrolRulesTable.rows({selected: true}).deselect();
        });
    });

    accesscontrolRulesTable.on('draw', function() {
        Common.iCheck('#accesscontrol-rule-table input');
        $('#accesscontrol-rule-table input.associated').on('ifChanged', onAccesscontrolRuleCheckboxSelect);
        onRuleSelect(accesscontrolRulesTable.rows({selected: true}));
    });

    var onAccesscontrolRuleCheckboxSelect = function(e) {
        $.checkItemUpdate(accesscontrolRulesTable, this, e, accesscontrolRuleUpdateBtn);
    };

    // ---------------------------------------------------------------
    // USER ASSOCIATION TAB
    var accesscontrolUserUpdateBtn = $('#accesscontrol-user-send'),
        accesscontrolUserRemoveBtn = $('#accesscontrol-user-remove'),
        accesscontrolUserDeleteConfirmBtn = $('#confirmuserDeleteModal');

    function disableUserButtons(disable) {
        accesscontrolUserUpdateBtn.prop('disabled', disable);
        accesscontrolUserRemoveBtn.prop('disabled', disable);
    }

    function onUserSelect(selected) {
        var disabled = selected.count() == 0;
        disableUserButtons(disabled);
    }

    accesscontrolUserUpdateBtn.on('click', function(e) {
        e.preventDefault();
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            rows = accesscontrolUsersTable.rows({selected: true}),
            toAdd = $.getSelectedIds(accesscontrolUsersTable),
            opts = {
                confirmadd: 1,
                additems: toAdd
            };
        $.apiCall(method,action,opts,function(err) {
            disableUserButtons(false);
            if (err) {
                return;
            }
            accesscontrolUsersTable.draw(false);
            accesscontrolUsersTable.rows({selected: true}).deselect();
        });
    });

    accesscontrolUserRemoveBtn.on('click', function(e) {
        $('#userDelModal').modal('show');
    });

    var accesscontrolUsersTable = $('#accesscontrol-user-table').registerTable(onUserSelect, {
        order: [
            [1, 'asc'],
            [0, 'asc']
        ],
        columns: [
            {data: 'mainLink'},
            {data: 'association'},
        ],
        rowId: 'id',
        columnDefs: [
            {
                render: function(data, type, row) {
                    var checkval = '';
                    if (row.association === 'associated') {
                        checkval = ' checked';
                    }
                    return '<div class="checkbox">'
                        + '<input type="checkbox" class="associated" name="associate[]" id="accesscontrolUserAssoc_'
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
            url: '../management/index.php?node='+Common.node+'&sub=getUsersList&id='+Common.id,
            type: 'post'
        }
    });

    accesscontrolUserDeleteConfirmBtn.on('click', function(e) {
        $.deleteAssociated(accesscontrolUsersTable, accesscontrolUserUpdateBtn.attr('action'), function(err) {
            $('#userDelModal').modal('hide');
            if (err) {
                return;
            }
            accesscontrolUsersTable.draw(false);
            accesscontrolUsersTable.rows({selected: true}).deselect();
        });
    });

    accesscontrolUsersTable.on('draw', function() {
        Common.iCheck('#accesscontrol-user-table input');
        $('#accesscontrol-user-table input.associated').on('ifChanged', onAccesscontrolUserCheckboxSelect);
        onUserSelect(accesscontrolUsersTable.rows({selected: true}));
    });

    var onAccesscontrolUserCheckboxSelect = function(e) {
        $.checkItemUpdate(accesscontrolUsersTable, this, e, accesscontrolUserUpdateBtn);
    };

    if (Common.search && Common.search.length > 0) {
        accesscontrolRulesTable.search(Common.search).draw();
        accesscontrolUsersTable.search(Common.search).draw();
    }
});
