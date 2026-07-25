$(function() {
    // ---------------------------------------------------------------
    // GENERAL TAB
    $.registerGeneralTab({
        nameInputSel: '#ldapgroup',
        formSel: '#ldapgroup-general-form'
    });

    // ---------------------------------------------------------------
    // ROLE ASSOCIATION TAB
    var ldapGroupRolesTable = $.registerAssociationTab({
        slug: 'ldapgroup-role',
        item: 'role',
        sub: 'getRolesList'
    });

    // ---------------------------------------------------------------
    // USER GROUP ASSOCIATION TAB
    var ldapGroupUserGroupsTable = $.registerAssociationTab({
        slug: 'ldapgroup-usergroup',
        item: 'usergroup',
        sub: 'getUserGroupsList'
    });

    if (Common.search && Common.search.length > 0) {
        ldapGroupRolesTable.search(Common.search).draw();
        ldapGroupUserGroupsTable.search(Common.search).draw();
    }
});
