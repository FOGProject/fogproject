(function($) {
    // The LDAP Groups tab this plugin injects onto the role edit page.
    //
    // 'url' is passed because the table cannot be served from the role
    // node: a plugin cannot add a sub method to a core page class, so the
    // list lives on the plugin's own node and the role arrives as ownerID
    // rather than id (id there would name an LDAP group instead).
    var ldapGroupsTable = $.registerAssociationTab({
        slug: 'role-ldapgroup',
        item: 'ldapgroup',
        // mainLink / server / associated -- the server column is what tells
        // two same-named groups on different directories apart.
        columns: [
            {data: 'mainLink'},
            {data: 'ldapserver'},
            {data: 'association'}
        ],
        url: '../management/index.php?node=ldapgroup'
            + '&sub=getRoleFeedList&ownerID=' + Common.id
    });
})(jQuery);
