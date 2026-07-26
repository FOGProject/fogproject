(function($) {
    $('#storagegroup-export-table').registerExportTable([
        {data: 'name'},
        {data: 'description'},
        {data: 'trustedcidrs', visible: false}
    ]);
})(jQuery);
