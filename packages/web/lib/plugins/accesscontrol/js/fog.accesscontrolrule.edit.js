$(function() {
    // ---------------------------------------------------------------
    // GENERAL TAB
    var originalName = $('#type').val()
        + '-'
        + $('#value').val(),
        updateName = function(newName) {
            var e = $('#pageTitle'),
                text = e.text();
            text = text.replace(': ' + originalName, ': ' + newName);
            document.title = text;
            e.text(text);
        },
        generalForm = $('#accesscontrolrule-general-form'),
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
            name = $('#type').val()
                + '-'
                + $('#value').val();
            updateName(name);
            originalName = name;
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
    // ROLE ASSOCIATION TAB
    var accesscontrolruleRoleUpdateBtn = $('#accesscontrolrule-role-send'),
        accesscontrolruleRoleRemoveBtn = $('#accesscontrolrule-role-remove'),
        accesscontrolruleRoleDeleteConfirmBtn = $('#confirmroleDeleteModal');

    function disableRoleButtons(disable) {
        accesscontrolruleRoleUpdateBtn.prop('disabled', disable);
        accesscontrolruleRoleRemoveBtn.prop('disabled', disable);
    }

    function onRoleSelect(selected) {
        var disabled = selected.count() == 0;
        disableRoleButtons(disabled);
    }

    accesscontrolruleRoleUpdateBtn.on('click', function(e) {
        e.preventDefault();
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            rows = accesscontrolruleRolesTable.rows({selected: true}),
            toAdd = $.getSelectedIds(accesscontrolruleRolesTable),
            opts = {
                confirmadd: 1,
                additems: toAdd
            };
        $.apiCall(method,action,opts,function(err) {
            disableRoleButtons(false);
            if (err) {
                return;
            }
            accesscontrolruleRolesTable.draw(false);
            accesscontrolruleRolesTable.rows({selected: true}).deselect();
        });
    });

    accesscontrolruleRoleRemoveBtn.on('click', function(e) {
        $('#roleDelModal').modal('show');
    });

    var accesscontrolruleRolesTable = $('#accesscontrolrule-role-table').registerTable(onRoleSelect, {
        order: [
            [1, 'asc'],
            [0, 'asc']
        ],
        columns: [
            {data: 'mainLink'},
            {data: 'association'}
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
                    + '<input type="checkbox" class="associated" name="associate[]" id="roleAssoc_'
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
            + '&sub=getRolesList&id='
            + Common.id,
            type: 'post'
        }
    });

    accesscontrolruleRoleDeleteConfirmBtn.on('click', function(e) {
        $.deleteAssociated(accesscontrolruleRolesTable, accesscontrolruleRoleUpdateBtn.attr('action'), function(err) {
            $('#roleDelModal').modal('hide');
            if (err) {
                return;
            }
            accesscontrolruleRolesTable.draw(false);
            accesscontrolruleRolesTable.rows({selected: true}).deselect();
        })
    });

    accesscontrolruleRolesTable.on('draw', function() {
        Common.iCheck('#accesscontrolrule-role-table input');
        $('#accesscontrolrule-role-table input.associated').on('ifChanged', onAccesscontrolruleRoleCheckboxSelect);
        onRoleSelect(accesscontrolruleRolesTable.rows({selected: true}));
    });

    var onAccesscontrolruleRoleCheckboxSelect = function(e) {
        $.checkItemUpdate(accesscontrolruleRolesTable, this, e, accesscontrolruleRoleUpdateBtn);
    };

    if (Common.search && Common.search.length > 0) {
        accesscontrolruleRolesTable.search(Common.search).draw();
    }
});
