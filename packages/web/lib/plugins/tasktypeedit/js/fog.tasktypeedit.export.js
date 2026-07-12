(function($) {
    $('#tasktypeedit-export-table').registerExportTable([
        {data: 'name'},
        {data: 'description', visible: false},
        {data: 'icon', visible: false},
        {data: 'kernel', visible: false},
        {data: 'kernelArgs', visible: false},
        {data: 'type', visible: false},
        {data: 'isAdvanced', visible: false},
        {data: 'access'},
        {data: 'initrd', visible: false}
    ]);
})(jQuery);
