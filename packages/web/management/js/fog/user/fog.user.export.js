(function($) {
    $('#user-export-table').registerExportTable([
        {data: 'name'},
        {data: 'password', visible: false},
        {data: 'createdTime', visible: false},
        {data: 'createdBy', visible: false},
        {data: 'type', visible: false},
        {data: 'display'},
        {data: 'api', visible: false},
        {data: 'token', visible: false},
        {data: 'authsource', visible: false}
    ]);
})(jQuery);
