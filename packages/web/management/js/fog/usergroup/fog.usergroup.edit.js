$(function() {
    // ---------------------------------------------------------------
    // GENERAL TAB
    $.registerGeneralTab({
        nameInputSel: '#usergroup',
        formSel: '#usergroup-general-form'
    });

    // ---------------------------------------------------------------
    // MEMBERS TAB
    var memberUpdateBtn = $('#usergroup-member-send'),
        memberRemoveBtn = $('#usergroup-member-remove'),
        memberDeleteConfirmBtn = $('#confirmuserDeleteModal');

    function disableMemberButtons(disable) {
        memberUpdateBtn.prop('disabled', disable);
        memberRemoveBtn.prop('disabled', disable);
    }

    function onMemberSelect(selected) {
        var disabled = selected.count() == 0;
        disableMemberButtons(disabled);
    }

    memberUpdateBtn.on('click', function(e) {
        e.preventDefault();
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            toAdd = $.getSelectedIds(membersTable),
            opts = {
                confirmadd: 1,
                additems: toAdd
            };
        $.apiCall(method,action,opts,function(err) {
            disableMemberButtons(false);
            if (err) {
                return;
            }
            membersTable.draw(false);
            membersTable.rows({selected: true}).deselect();
        });
    });

    memberRemoveBtn.on('click', function(e) {
        e.preventDefault();
        $('#userDelModal').modal('show');
    });

    var membersTable = $('#usergroup-member-table').registerTable(onMemberSelect, {
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
                        + '<input type="checkbox" class="associated" name="associate[]" id="userGroupMemberAssoc_'
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

    memberDeleteConfirmBtn.on('click', function(e) {
        $.deleteAssociated(membersTable, memberUpdateBtn.attr('action'), function(err) {
            $('#userDelModal').modal('hide');
            if (err) {
                return;
            }
            membersTable.draw(false);
            membersTable.rows({selected: true}).deselect();
        });
    });

    membersTable.on('draw', function() {
        Common.iCheck('#usergroup-member-table input');
        $('#usergroup-member-table input.associated').on('change', onMemberCheckboxSelect);
        onMemberSelect(membersTable.rows({selected: true}));
    });

    var onMemberCheckboxSelect = function(e) {
        $.checkItemUpdate(membersTable, this, e, memberUpdateBtn);
    };

    // ---------------------------------------------------------------
    // ROLES TAB
    var roleUpdateBtn = $('#usergroup-role-send'),
        roleRemoveBtn = $('#usergroup-role-remove'),
        roleDeleteConfirmBtn = $('#confirmroleDeleteModal');

    function disableRoleButtons(disable) {
        roleUpdateBtn.prop('disabled', disable);
        roleRemoveBtn.prop('disabled', disable);
    }

    function onRoleSelect(selected) {
        var disabled = selected.count() == 0;
        disableRoleButtons(disabled);
    }

    roleUpdateBtn.on('click', function(e) {
        e.preventDefault();
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            toAdd = $.getSelectedIds(rolesTable),
            opts = {
                confirmadd: 1,
                additems: toAdd
            };
        $.apiCall(method,action,opts,function(err) {
            disableRoleButtons(false);
            if (err) {
                return;
            }
            rolesTable.draw(false);
            rolesTable.rows({selected: true}).deselect();
        });
    });

    roleRemoveBtn.on('click', function(e) {
        e.preventDefault();
        $('#roleDelModal').modal('show');
    });

    var rolesTable = $('#usergroup-role-table').registerTable(onRoleSelect, {
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
                        + '<input type="checkbox" class="associated" name="associate[]" id="userGroupRoleAssoc_'
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

    roleDeleteConfirmBtn.on('click', function(e) {
        $.deleteAssociated(rolesTable, roleUpdateBtn.attr('action'), function(err) {
            $('#roleDelModal').modal('hide');
            if (err) {
                return;
            }
            rolesTable.draw(false);
            rolesTable.rows({selected: true}).deselect();
        });
    });

    rolesTable.on('draw', function() {
        Common.iCheck('#usergroup-role-table input');
        $('#usergroup-role-table input.associated').on('change', onRoleCheckboxSelect);
        onRoleSelect(rolesTable.rows({selected: true}));
    });

    var onRoleCheckboxSelect = function(e) {
        $.checkItemUpdate(rolesTable, this, e, roleUpdateBtn);
    };

    if (Common.search && Common.search.length > 0) {
        membersTable.search(Common.search).draw();
    }
});
