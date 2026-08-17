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

    // ---------------------------------------------------------------
    // GRANTED TO TABS
    // The opposite direction from the four above: those say which objects
    // this site contains, these say who the site is given to. Same picker
    // and the same list of names, so they get their own sub -- the
    // `association` column has to come from the grant table or the tab
    // ticks boxes from the wrong relation.
    var siteGrantRolesTable = $.registerAssociationTab({
        slug: 'site-grantrole',
        item: 'role',
        sub: 'getGrantRolesList'
    });

    var siteGrantUserGroupsTable = $.registerAssociationTab({
        slug: 'site-grantusergroup',
        item: 'usergroup',
        sub: 'getGrantUserGroupsList'
    });

    // ---------------------------------------------------------------
    // CREATE AND ASSOCIATE
    // Each association tab can create the thing it associates without
    // leaving the page. These are grid tabs, so the modal posts additems[]
    // straight back through the tab's own table.
    $.registerCreateAndAssociate('site-host', siteHostsTable);
    $.registerCreateAndAssociate('site-user', siteUsersTable);
    $.registerCreateAndAssociate('site-group', siteGroupsTable);
    $.registerCreateAndAssociate('site-usergroup', siteUserGroupsTable);
    $.registerCreateAndAssociate('site-grantrole', siteGrantRolesTable);
    $.registerCreateAndAssociate(
        'site-grantusergroup',
        siteGrantUserGroupsTable
    );

    if (Common.search && Common.search.length > 0) {
        siteHostsTable.search(Common.search).draw();
        siteUsersTable.search(Common.search).draw();
        siteGroupsTable.search(Common.search).draw();
        siteUserGroupsTable.search(Common.search).draw();
        siteGrantRolesTable.search(Common.search).draw();
        siteGrantUserGroupsTable.search(Common.search).draw();
    }
});
