(function($) {
    $('#capone-export-table').registerExportTable([
        {data: 'imageID'},
        {data: 'osID', visible: false},
        {data: 'key'}
    ]);
})(jQuery);
