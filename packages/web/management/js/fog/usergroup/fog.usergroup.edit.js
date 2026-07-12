$(function() {
    // ---------------------------------------------------------------
    // GENERAL TAB
    $.registerGeneralTab({
        nameInputSel: '#usergroup',
        formSel: '#usergroup-general-form'
    });

    // ---------------------------------------------------------------
    // MEMBERS TAB
    var membersTable = $.registerAssociationTab({
        slug: 'usergroup-member',
        item: 'user',
        sub: 'getUsersList'
    });

    // ---------------------------------------------------------------
    // ROLES TAB
    var rolesTable = $.registerAssociationTab({
        slug: 'usergroup-role',
        item: 'role',
        sub: 'getRolesList'
    });

    if (Common.search && Common.search.length > 0) {
        membersTable.search(Common.search).draw();
    }
});
