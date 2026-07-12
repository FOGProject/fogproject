(function($) {
    $.registerListPage({
        columns: [
            {data: 'name'},
            {data: 'email'}
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
