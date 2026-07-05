(function($) {
    // ----------------------------------------------------
    // GENERAL TAB
    var originalName = $('#user').val(),
        originalDisplayName = $('.fog-user').text();

    var updateName = function(newName) {
        var e = $('#pageTitle'),
            text = e.text();
        text = text.replace(": " + originalName, ": " + newName);
        document.title = text;
        e.text(text);
    };

    var updateDisplayName = function(newName) {
        var e = $('.fog-user'),
            text = e.text();
        text = text.replace(originalDisplayName, newName)
        e.text(text);
    };

    var generalForm = $('#user-general-form'),
        generalFormBtn = $('#general-send'),
        generalDeleteBtn = $('#general-delete'),
        generalDeleteModal = $('#deleteModal'),
        generalDeleteModalConfirm = $('#confirmDeleteModal'),
        generalDeleteModalCancel = $('#closeDeleteModal');

    generalForm.on('submit',function(e) {
        e.preventDefault();
    });
    generalFormBtn.on('click', function(e) {
        generalFormBtn.prop('disabled', true);
        generalDeleteBtn.prop('disabled', true);
        generalForm.processForm(function(err) {
            generalFormBtn.prop('disabled', false);
            generalDeleteBtn.prop('disabled', false);
            if (err) {
                return;
            }
            newName = $('#user').val().trim();
            anchorFields = getQueryParams($('.fog-user').attr('href'));
            foguser = {
                node: anchorFields['node'],
                sub: anchorFields['sub'],
                id: anchorFields['id']
            };
            if (Common.id == foguser.id) {
                newDisplay = $('#display').val().trim();
                if (!newDisplay) {
                    newDisplay = newName;
                }
                updateDisplayName(newDisplay);
                originalDisplayName = newDisplay;
            }
            updateName(newName);
            originalName = newName;
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

    // ----------------------------------------------------
    // PASSWORD TAB
    var passwordForm = $('#user-changepw-form'),
        passwordFormBtn = $('#changepw-send');

    passwordForm.on('submit',function(e) {
        e.preventDefault();
    });
    passwordFormBtn.on('click', function(e) {
        passwordFormBtn.prop('disabled', true);
        passwordForm.processForm(function(err) {
            passwordFormBtn.prop('disabled', false);
            if (err) {
                return;
            }
            $('.password1-input, .password2-input').val('');
        });
    });

    // ----------------------------------------------------
    // API TAB
    var apiForm = $('#user-api-form'),
        apiFormBtn = $('#api-send');

    apiForm.on('submit',function(e) {
        e.preventDefault();
    });
    apiFormBtn.on('click', function(e) {
        apiFormBtn.prop('disabled', true);
        apiForm.processForm(function(err) {
            apiFormBtn.prop('disabled', false);
            if (err) {
                return;
            }
        });
    });

    $('.resettoken').on('click', function(e) {
        e.preventDefault();
        Pace.ignore(function() {
            $.ajax({
                url: '../status/newtoken.php',
                dataType: 'json',
                success: function(data) {
                    $('.token').val(data);
                },
                error: function(jqXHR, textStatus, errorThrown) {
                }
            });
        });
    });

    // ----------------------------------------------------
    // ROLE ASSOCIATION TAB
    var userRoleUpdateBtn = $('#user-role-send'),
        userRoleRemoveBtn = $('#user-role-remove'),
        userRoleDeleteConfirmBtn = $('#confirmroleDeleteModal');

    function disableRoleButtons(disable) {
        userRoleUpdateBtn.prop('disabled', disable);
        userRoleRemoveBtn.prop('disabled', disable);
    }

    function onRoleSelect(selected) {
        var disabled = selected.count() == 0;
        disableRoleButtons(disabled);
    }

    userRoleUpdateBtn.on('click', function(e) {
        e.preventDefault();
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            toAdd = $.getSelectedIds(userRolesTable),
            opts = {
                confirmadd: 1,
                additems: toAdd
            };
        $.apiCall(method,action,opts,function(err) {
            disableRoleButtons(false);
            if (err) {
                return;
            }
            userRolesTable.draw(false);
            userRolesTable.rows({selected: true}).deselect();
        });
    });

    userRoleRemoveBtn.on('click', function(e) {
        e.preventDefault();
        $('#roleDelModal').modal('show');
    });

    var userRolesTable = $('#user-role-table').registerTable(onRoleSelect, {
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
                        + '<input type="checkbox" class="associated" name="associate[]" id="userRoleAssoc_'
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
            url: '../management/index.php?node='+Common.node+'&sub=getRolesList&id='+Common.id,
            type: 'post'
        }
    });

    userRoleDeleteConfirmBtn.on('click', function(e) {
        $.deleteAssociated(userRolesTable, userRoleUpdateBtn.attr('action'), function(err) {
            $('#roleDelModal').modal('hide');
            if (err) {
                return;
            }
            userRolesTable.draw(false);
            userRolesTable.rows({selected: true}).deselect();
        });
    });

    userRolesTable.on('draw', function() {
        Common.iCheck('#user-role-table input');
        $('#user-role-table input.associated').on('change', onUserRoleCheckboxSelect);
        onRoleSelect(userRolesTable.rows({selected: true}));
    });

    var onUserRoleCheckboxSelect = function(e) {
        $.checkItemUpdate(userRolesTable, this, e, userRoleUpdateBtn);
    };

    // ----------------------------------------------------
    // GROUP ASSOCIATION TAB
    var userGroupUpdateBtn = $('#user-group-send'),
        userGroupRemoveBtn = $('#user-group-remove'),
        userGroupDeleteConfirmBtn = $('#confirmusergroupDeleteModal');

    function disableGroupButtons(disable) {
        userGroupUpdateBtn.prop('disabled', disable);
        userGroupRemoveBtn.prop('disabled', disable);
    }

    function onGroupSelect(selected) {
        var disabled = selected.count() == 0;
        disableGroupButtons(disabled);
    }

    userGroupUpdateBtn.on('click', function(e) {
        e.preventDefault();
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            toAdd = $.getSelectedIds(userGroupsTable),
            opts = {
                confirmadd: 1,
                additems: toAdd
            };
        $.apiCall(method,action,opts,function(err) {
            disableGroupButtons(false);
            if (err) {
                return;
            }
            userGroupsTable.draw(false);
            userGroupsTable.rows({selected: true}).deselect();
        });
    });

    userGroupRemoveBtn.on('click', function(e) {
        e.preventDefault();
        $('#usergroupDelModal').modal('show');
    });

    var userGroupsTable = $('#user-group-table').registerTable(onGroupSelect, {
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
                        + '<input type="checkbox" class="associated" name="associate[]" id="userGroupAssoc_'
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
            url: '../management/index.php?node='+Common.node+'&sub=getGroupsList&id='+Common.id,
            type: 'post'
        }
    });

    userGroupDeleteConfirmBtn.on('click', function(e) {
        $.deleteAssociated(userGroupsTable, userGroupUpdateBtn.attr('action'), function(err) {
            $('#usergroupDelModal').modal('hide');
            if (err) {
                return;
            }
            userGroupsTable.draw(false);
            userGroupsTable.rows({selected: true}).deselect();
        });
    });

    userGroupsTable.on('draw', function() {
        Common.iCheck('#user-group-table input');
        $('#user-group-table input.associated').on('change', onUserGroupCheckboxSelect);
        onGroupSelect(userGroupsTable.rows({selected: true}));
    });

    var onUserGroupCheckboxSelect = function(e) {
        $.checkItemUpdate(userGroupsTable, this, e, userGroupUpdateBtn);
    };
})(jQuery);
