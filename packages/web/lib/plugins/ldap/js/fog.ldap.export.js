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
        {data: 'adminGroup'},
        {data: 'userGroup', visible: false},
        {data: 'searchScope', visible: false},
        {data: 'bindDN', visible: false},
        {data: 'grpSearchDN', visible: false},
        {data: 'useGroupMatch', visible: false},
        {data: 'displayNameOn', visible: false},
        {data: 'displayNameAttr', visible: false}
    ]);
})(jQuery);
