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
        {data: 'uri', visible: false},
        {data: 'associations', visible: false}
    ]);
})(jQuery);
