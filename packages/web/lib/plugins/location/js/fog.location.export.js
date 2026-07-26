(function($) {
    $('#location-export-table').registerExportTable([
        {data: 'name'},
        {data: 'description'},
        {data: 'createdBy', visible: false},
        {data: 'createdTime', visible: false},
        {data: 'storagegroupID', visible: false},
        {data: 'storagenodeID', visible: false},
        {data: 'tftp', visible: false},
        {data: 'protocol', visible: false}
    ]);
})(jQuery);
