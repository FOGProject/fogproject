(function($) {
    $('#wolbroadcast-export-table').registerExportTable([
        {data: 'name'},
        {data: 'description', visible: false},
        {data: 'broadcast'}
    ]);
})(jQuery);
