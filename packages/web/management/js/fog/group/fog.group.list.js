(function($) {
    $.registerListPage({
        order: [
            [0, 'asc']
        ],
        columns: [
            {data: 'mainlink'},
            {data: 'members'},
            {data: 'grants'}
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
                // Not sortable. The counts behind this column are primed one
                // page at a time rather than selected, so the server has no
                // expression to ORDER BY and a header click would re-request
                // the page and hand back the same order -- which reads as
                // the grid being broken. See the group arm of
                // Route::_gridColumns() for why it is not folded into the
                // list query instead.
                orderable: false,
                // Kept through the responsive collapse: "does this group
                // push software" is the question the column exists for, and
                // it does not stop mattering on a narrow window.
                responsivePriority: 1,
                render: function(data, type, row) {
                    // Display only. Sort, search and the CSV export get the
                    // server's plain comma-joined text back untouched --
                    // markup baked into the value is the GH-1446 failure,
                    // where registerExportTable() escapes each cell and the
                    // badges land in the CSV as literal '<span class="...'.
                    if (type !== 'display') {
                        return data;
                    }
                    var list = row.grants_list || [];
                    if (!list.length) {
                        return '';
                    }
                    // Escaped with the same helper every other JS-rendered
                    // cell uses. These strings are composed server-side
                    // because they are translated, not because they are
                    // trusted.
                    // text-bg-secondary, not bg-secondary: it pins the
                    // text color as well as the background, both
                    // !important, so the badge reads the same in either
                    // theme. See the host list's group chips for the rule
                    // in fog-default-ui that otherwise wins the color.
                    return $.map(list, function(grant) {
                        return '<span class="badge text-bg-secondary me-1">' +
                            $.escapeHtml(String(grant)) +
                            '</span>';
                    }).join('');
                },
                targets: 2
            }
        ]
    });
})(jQuery);
