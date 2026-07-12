(function($) {
    $.registerListPage({
        columns: [
            {data: 'serverURL'},
            {data: 'topicEndpoint'}
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
