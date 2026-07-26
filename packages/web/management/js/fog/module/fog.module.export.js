(function($) {
    $('#module-export-table').registerExportTable([
        {data: 'name'},
        {data: 'shortName'},
        {data: 'description', visible: false},
        {data: 'isDefault', visible: false}
    ]);
})(jQuery);
