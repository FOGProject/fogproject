$(function() {
    // ---------------------------------------------------------------
    // GENERAL TAB
    $.registerGeneralTab({
        nameInputSel: '#site',
        formSel: '#site-general-form'
    });
    // ---------------------------------------------------------------
    // HOST ASSOCIATION TAB
    var siteHostsTable = $.registerAssociationTab({
        slug: 'site-host',
        item: 'host',
        sub: 'getHostsList'
    });

    // ---------------------------------------------------------------
    // USER ASSOCIATION TAB
    var siteUsersTable = $.registerAssociationTab({
        slug: 'site-user',
        item: 'user',
        sub: 'getUsersList'
    });

    // ---------------------------------------------------------------
    // GROUP ASSOCIATION TAB
    var siteGroupsTable = $.registerAssociationTab({
        slug: 'site-group',
        item: 'group',
        sub: 'getGroupsList'
    });

    // ---------------------------------------------------------------
    // USER GROUP ASSOCIATION TAB
    var siteUserGroupsTable = $.registerAssociationTab({
        slug: 'site-usergroup',
        item: 'usergroup',
        sub: 'getUserGroupsList'
    });

    if (Common.search && Common.search.length > 0) {
        siteHostsTable.search(Common.search).draw();
        siteUsersTable.search(Common.search).draw();
        siteGroupsTable.search(Common.search).draw();
        siteUserGroupsTable.search(Common.search).draw();
    }
});
