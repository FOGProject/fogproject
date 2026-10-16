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
    // TASK TYPES TAB
    // "All task types" stores tasktype.*, so while it is on the boxes are
    // display-only, the same way the administrator toggle works above.
    // A full access role renders everything disabled server side.
    var taskTypeForm = $('#role-tasktype-form'),
        taskTypeFormBtn = $('#tasktype-send'),
        taskTypeAll = $('#role-tasktype-all'),
        taskTypeBoxes = $('.role-tasktype-box');

    function applyTaskTypeAll() {
        if (taskTypeAll.is(':disabled')) {
            return;
        }
        if (taskTypeAll.is(':checked')) {
            taskTypeBoxes.prop('checked', true).prop('disabled', true);
        } else {
            taskTypeBoxes.prop('disabled', false);
        }
    }

    taskTypeForm.on('submit', function(e) {
        e.preventDefault();
    });
    taskTypeAll.on('change', applyTaskTypeAll);
    applyTaskTypeAll();
    taskTypeFormBtn.on('click', function() {
        taskTypeFormBtn.prop('disabled', true);
        taskTypeForm.processForm(function(err) {
            taskTypeFormBtn.prop('disabled', false);
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

    // ---------------------------------------------------------------
    // SITE GRANTS TAB
    // The other end of the Site page's "Granted To -> Roles" tab; both
    // write siteRoleGrants. Rendered only when the user holds site.view,
    // so guard on the table existing rather than assuming the tab is here.
    var roleSitesTable = null;
    if ($('#role-site').length > 0) {
        roleSitesTable = $.registerAssociationTab({
            slug: 'role-site',
            item: 'site',
            sub: 'getSitesList'
        });
    }

    if (Common.search && Common.search.length > 0) {
        roleUsersTable.search(Common.search).draw();
        roleUserGroupsTable.search(Common.search).draw();
        if (roleSitesTable) {
            roleSitesTable.search(Common.search).draw();
        }
    }
});
