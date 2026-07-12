(function($) {
    $.registerListPage({
        order: [
            [0, 'asc']
        ],
        columns: [
            {data: 'mainlink'},
            {data: 'key'}
        ],
        rowId: 'id',
        columnDefs: [
            {
                responsivePriority: -1,
                targets: 0
            }
        ]
    });
})(jQuery);
