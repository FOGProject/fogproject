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
        generalForm = $('#role-general-form'),
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
    // PERMISSIONS TAB
    var permForm = $('#role-permission-form'),
        permFormBtn = $('#permission-send'),
        permAll = $('#role-perm-all'),
        permBoxes = $('.role-perm-box');

    // While the administrator toggle is on, the matrix is display-only:
    // disabled inputs don't submit, so the POST carries allperm alone.
    function applyAllPerm() {
        if (permAll.is(':checked')) {
            permBoxes.prop('checked', true).prop('disabled', true);
        } else {
            permBoxes.prop('disabled', false);
        }
    }

    permForm.on('submit', function(e) {
        e.preventDefault();
    });
    permAll.on('change', applyAllPerm);
    applyAllPerm();
    permFormBtn.on('click', function() {
        permFormBtn.prop('disabled', true);
        permForm.processForm(function(err) {
            permFormBtn.prop('disabled', false);
            if (err) {
                return;
            }
        });
    });

    // ---------------------------------------------------------------
    // USER ASSOCIATION TAB
    var roleUserUpdateBtn = $('#role-user-send'),
        roleUserRemoveBtn = $('#role-user-remove'),
        roleUserDeleteConfirmBtn = $('#confirmuserDeleteModal');

    function disableUserButtons(disable) {
        roleUserUpdateBtn.prop('disabled', disable);
        roleUserRemoveBtn.prop('disabled', disable);
    }

    function onUserSelect(selected) {
        var disabled = selected.count() == 0;
        disableUserButtons(disabled);
    }

    roleUserUpdateBtn.on('click', function(e) {
        e.preventDefault();
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            toAdd = $.getSelectedIds(roleUsersTable),
            opts = {
                confirmadd: 1,
                additems: toAdd
            };
        $.apiCall(method,action,opts,function(err) {
            disableUserButtons(false);
            if (err) {
                return;
            }
            roleUsersTable.draw(false);
            roleUsersTable.rows({selected: true}).deselect();
        });
    });

    roleUserRemoveBtn.on('click', function(e) {
        e.preventDefault();
        $('#userDelModal').modal('show');
    });

    var roleUsersTable = $('#role-user-table').registerTable(onUserSelect, {
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
                    return '<div class="form-check">'
                        + '<input type="checkbox" class="associated" name="associate[]" id="roleUserAssoc_'
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

    roleUserDeleteConfirmBtn.on('click', function(e) {
        $.deleteAssociated(roleUsersTable, roleUserUpdateBtn.attr('action'), function(err) {
            $('#userDelModal').modal('hide');
            if (err) {
                return;
            }
            roleUsersTable.draw(false);
            roleUsersTable.rows({selected: true}).deselect();
        });
    });

    roleUsersTable.on('draw', function() {
        Common.iCheck('#role-user-table input');
        $('#role-user-table input.associated').on('change', onRoleUserCheckboxSelect);
        onUserSelect(roleUsersTable.rows({selected: true}));
    });

    var onRoleUserCheckboxSelect = function(e) {
        $.checkItemUpdate(roleUsersTable, this, e, roleUserUpdateBtn);
    };

    if (Common.search && Common.search.length > 0) {
        roleUsersTable.search(Common.search).draw();
    }
});
