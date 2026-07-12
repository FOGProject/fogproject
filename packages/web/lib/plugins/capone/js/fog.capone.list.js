(function($) {
    $.registerListPage({
        order: [
            [0, 'asc']
        ],
        columns: [
            {data: 'mainlink'},
            {data: 'imageLink'},
            {data: 'osid'},
            {data: 'key'}
        ],
        rowId: 'id',
        columnDefs: [
            {
                responsivePriority: -1,
                targets: 0
            },
            {
                responsivePriority: 0,
                targets: 1
            },
            {
                responsivePriority: 1,
                render: function(data, type, row) {
                    return row.osname;
                },
                targets: 2
            }
        ]
    });
})(jQuery);
