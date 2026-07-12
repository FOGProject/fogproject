(function($) {
    $('#printer-export-table').registerExportTable([
        {data: 'name'},
        {data: 'description', visible: false},
        {data: 'port', visible: false},
        {data: 'file', visible: false},
        {data: 'model', visible: false},
        {data: 'config'},
        {data: 'configFile', visible: false},
        {data: 'ip', visible: false},
        {data: 'pAnon2', visible: false},
        {data: 'pAnon3', visible: false},
        {data: 'pAnon4', visible: false},
        {data: 'pAnon5', visible: false},
        {data: 'associations', visible: false}
    ]);
})(jQuery);
