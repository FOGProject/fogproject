/**
 * Hello World list page (sub=list).
 *
 * Registers the standard list page via $.registerListPage: a server-side
 * DataTable (hits ?node=helloworld&sub=list), the "create new" modal
 * (submits to addPost via processForm), and bulk delete. The `columns[].data`
 * keys match the column data emitted by the list endpoint: 'mainlink' (the
 * linked name) and any model field by its friendly name (here 'description').
 * Column order matches $headerData on the page.
 */
(function($) {
    $.registerListPage({
        order: [
            [0, 'asc']
        ],
        columns: [
            {data: 'mainlink'},
            {data: 'description'}
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
