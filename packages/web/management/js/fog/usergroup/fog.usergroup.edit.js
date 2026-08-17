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

    // ---------------------------------------------------------------
    // SITE GRANTS TAB
    // Not the tab above. That one picks the site this user group BELONGS
    // to; this one lists the sites its MEMBERS GET. Grid, not dropdown,
    // because a user group can grant several. Rendered only when the user
    // holds site.view, so guard on the table existing.
    var ugSiteGrantsTable = null;
    if ($('#usergroup-sitegrant').length > 0) {
        ugSiteGrantsTable = $.registerAssociationTab({
            slug: 'usergroup-sitegrant',
            item: 'site',
            sub: 'getSitesList'
        });
        if (Common.search && Common.search.length > 0) {
            ugSiteGrantsTable.search(Common.search).draw();
        }
    }
});
