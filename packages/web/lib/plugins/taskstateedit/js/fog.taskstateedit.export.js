(function($) {
    $('#taskstateedit-export-table').registerExportTable([
        {data: 'name'},
        {data: 'description'},
        {data: 'order', visible: false},
        {data: 'icon', visible: false}
    ]);
})(jQuery);
