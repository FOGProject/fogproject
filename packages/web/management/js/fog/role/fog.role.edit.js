$(function() {
    // ---------------------------------------------------------------
    // GENERAL TAB
    $.registerGeneralTab({
        nameInputSel: '#role',
        formSel: '#role-general-form'
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
    var roleUsersTable = $.registerAssociationTab({
        slug: 'role-user',
        item: 'user',
        sub: 'getUsersList'
    });

    // ---------------------------------------------------------------
    // USER GROUP ASSOCIATION TAB
    var roleUserGroupsTable = $.registerAssociationTab({
        slug: 'role-usergroup',
        item: 'usergroup',
        sub: 'getUserGroupsList'
    });

    if (Common.search && Common.search.length > 0) {
        roleUsersTable.search(Common.search).draw();
        roleUserGroupsTable.search(Common.search).draw();
    }
});
