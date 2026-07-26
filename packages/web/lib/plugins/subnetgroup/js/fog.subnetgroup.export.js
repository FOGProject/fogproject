(function($) {
    $('#subnetgroup-export-table').registerExportTable([
        {data: 'name'},
        {data: 'groupID'},
        {data: 'subnets', visible: false}
    ]);
})(jQuery);
