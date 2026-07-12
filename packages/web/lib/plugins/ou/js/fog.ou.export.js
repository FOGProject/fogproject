(function($) {
    $('#ou-export-table').registerExportTable([
        {data: 'name'},
        {data: 'description', visible: false},
        {data: 'createdBy', visible: false},
        {data: 'createdTime', visible: false},
        {data: 'ou'}
    ]);
})(jQuery);
