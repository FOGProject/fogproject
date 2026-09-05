(function($) {
    $('#snapin-export-table').registerExportTable([
        {data: 'name'},
        {data: 'description', visible: false},
        {data: 'file'},
        {data: 'args', visible: false},
        {data: 'createdTime', visible: false},
        {data: 'createdBy', visible: false},
        {data: 'reboot', visible: false},
        {data: 'shutdown', visible: false},
        {data: 'runWith', visible: false},
        {data: 'runWithArgs', visible: false},
        {data: 'protected', visible: false},
        {data: 'isEnabled', visible: false},
        {data: 'toReplicate', visible: false},
        {data: 'hide', visible: false},
        {data: 'timeout', visible: false},
        {data: 'returnCodes', visible: false},
        {data: 'packtype', visible: false},
        {data: 'hash', visible: false},
        {data: 'size', visible: false},
        {data: 'anon3', visible: false},
        {data: 'associations', visible: false}
    ]);
})(jQuery);
