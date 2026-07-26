(function($) {
    // The LDAP Groups tab this plugin injects onto the user group edit
    // page. See fog.ldap.role.edit.js for why 'url' and 'ownerID' are
    // needed instead of the derived endpoint.
    var ldapGroupsTable = $.registerAssociationTab({
        slug: 'usergroup-ldapgroup',
        item: 'ldapgroup',
        // mainLink / server / associated -- the server column is what tells
        // two same-named groups on different directories apart.
        columns: [
            {data: 'mainLink'},
            {data: 'ldapserver'},
            {data: 'association'}
        ],
        url: '../management/index.php?node=ldapgroup'
            + '&sub=getUserGroupFeedList&ownerID=' + Common.id
    });
    // "Create New LDAP Group" -- register a directory group without
    // leaving the page, then associate it here. The ldapgroup create
    // form is inert markup (see fog.ldapgroup.add.js, which only calls
    // wireCreateForm), so no onForm initialiser is needed.
    $.registerCreateAndAssociate('usergroup-ldapgroup', ldapGroupsTable);
})(jQuery);
