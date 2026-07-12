(function($) {
    $('#module-export-table').registerExportTable([
        {data: 'name'},
        {data: 'description', visible: false},
        {data: 'shortName'},
        {data: 'isDefault', visible: false}
    ]);
})(jQuery);
