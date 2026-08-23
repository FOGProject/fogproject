(function($) {
    $.registerListPage({
        order: [
            [0, 'asc']
        ],
        columns: [
            {data: 'mainlink'},
            {data: 'display'},
            {data: 'api'},
            {data: 'apionly'}
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
                render: function(data, type, row) {
                    var enabled = '<span class="badge bg-success"><i class="fa fa-check-circle"></i></span>';
                    var disabled = '<span class="badge bg-danger"><i class="fa fa-times-circle"></i></span>';
                    if (data > 0) {
                        return enabled;
                    } else {
                        return disabled;
                    }
                },
                targets: 2
            },
            {
                // Deliberately NOT the check/cross pair used for API? above.
                // A cross there means "this is switched off", which is a
                // state somebody may want to change. Neither value here is
                // wrong: an API-only account is not a broken account, it is
                // a different KIND of account, so the pair is "stands out"
                // against "ordinary" rather than "good" against "bad".
                //
                // Same shape as the protected column on images and snapins,
                // which is the established two-badge pattern in these grids.
                // fa-key rather than fa-robot because the bundled Font
                // Awesome is 4.7.0 and has no fa-robot.
                render: function(data, type, row) {
                    var apiOnly = '<span class="badge bg-warning">'
                        + '<i class="fa fa-key"></i></span>';
                    var interactive = '<span class="badge bg-secondary">'
                        + '<i class="fa fa-user"></i></span>';
                    if (data > 0) {
                        return apiOnly;
                    }
                    return interactive;
                },
                targets: 3
            }
        ]
    });
})(jQuery);
