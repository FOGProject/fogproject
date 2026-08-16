(function($) {
    $.registerListPage({
        order: [
            [0, 'asc']
        ],
        columns: [
            {data: 'mainlink'},
            {data: 'hostcount'},
            {data: 'usercount'},
            {data: 'groupcount'},
            {data: 'usergroupcount'}
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
                responsivePriority: 0,
                targets: 2
            },
            {
                responsivePriority: 0,
                targets: 3
            },
            {
                responsivePriority: 0,
                targets: 4
            }
        ]
    });
})(jQuery);
