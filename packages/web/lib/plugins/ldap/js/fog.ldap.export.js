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
        // No bindPwd: AddLDAPAPI::stripBindPassword() drops it from the column
        // set (and so from the header row) via LDAP_EXPORT_ITEMS.
        {data: 'grpSearchDN', visible: false},
        {data: 'useGroupMatch', visible: false},
        {data: 'displayNameOn', visible: false},
        {data: 'displayNameAttr', visible: false},
        {data: 'isLdaps', visible: false},
        {data: 'allowapi', visible: false}
    ]);
})(jQuery);
