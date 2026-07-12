(function($) {
    $('#image-export-table').registerExportTable([
        {data: 'name'},
        {data: 'description', visible: false},
        {data: 'path'},
        {data: 'createdTime', visible: false},
        {data: 'createdBy', visible: false},
        {data: 'building', visible: false},
        {data: 'size', visible: false},
        {data: 'imageTypeID', visible: false},
        {data: 'imagePartitionTypeID', visible: false},
        {data: 'osID', visible: false},
        {data: 'deployed', visible: false},
        {data: 'format', visible: false},
        {data: 'magnet', visible: false},
        {data: 'protected', visible: false},
        {data: 'compress', visible: false},
        {data: 'isEnabled', visible: false},
        {data: 'toReplicate', visible: false},
        {data: 'srvsize', visible: false},
        {data: 'associations', visible: false}
    ]);
})(jQuery);
