(function($) {
    $('#ldap-export-table').registerExportTable([
        {data: 'name'},
        {data: 'description', visible: false},
        {data: 'createdBy', visible: false},
        {data: 'createdTime', visible: false},
        {data: 'address'},
        {data: 'port', visible: false},
        {data: 'searchDN', visible: false},
        {data: 'userNamAttr', visible: false},
        {data: 'grpNamAttr'},
        {data: 'grpMemberAttr', visible: false},
        {data: 'searchScope', visible: false},
        {data: 'bindDN', visible: false},
        // Carried so an exported server re-imports able to bind. The export
        // is an admin-only migration format and already carries user.password,
        // storagenode.pass/key and host.ADPass; Route::$sensitiveFields keeps
        // bindPwd out of API listings, which is a different trust context.
        {data: 'bindPwd', visible: false},
        {data: 'grpSearchDN', visible: false},
        {data: 'useGroupMatch', visible: false},
        {data: 'displayNameOn', visible: false},
        {data: 'displayNameAttr', visible: false},
        {data: 'isLdaps', visible: false},
        {data: 'allowapi', visible: false}
    ]);
})(jQuery);
