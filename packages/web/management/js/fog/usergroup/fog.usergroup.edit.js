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

    // ---------------------------------------------------------------
    // SITE TAB
    // Single dropdown, so registerSelectTab rather than the grid wiring.
    // node:'site' adds the create-and-select button when the user holds
    // site.create; without it the tab is just the select and Update.
    $.registerSelectTab({
        slug: 'usergroup-site',
        send: 'site-send',
        node: 'site'
    });
});
