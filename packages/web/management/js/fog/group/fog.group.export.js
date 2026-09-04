(function($) {
    $('#group-export-table').registerExportTable([
        {data: 'name'},
        {data: 'description', visible: false},
        {data: 'order', visible: false},
        {data: 'createdBy', visible: false},
        {data: 'createdTime', visible: false},
        {data: 'building', visible: false},
        {data: 'kernel'},
        {data: 'kernelArgs'},
        {data: 'kernelDevice'},
        {data: 'init'},
        {data: 'associations', visible: false}
    ]);
})(jQuery);
