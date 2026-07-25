$(function() {
    // ---------------------------------------------------------------
    // GENERAL TAB
    $.registerGeneralTab({
        nameInputSel: '#ou',
        formSel: '#ou-general-form'
    });

    // ---------------------------------------------------------------
    // HOST TAB
    var ouHostsTable = $.registerAssociationTab({
        slug: 'ou-host',
        item: 'host',
        sub: 'getHostsList',
        columns: [
            {data: 'name'},
            {data: 'association'}
        ],
        columnDefs: [
            {
                responsivePriority: -1,
                // Aisle 097: this tab supplies its own column-0 render, so it
                // bypasses the server-side escape in FOGController's mainLink
                // formatter and concatenated the name in raw. Mitigated today
                // only by isHostnameSafe() on the write side -- do not rely on
                // that staying the sole gate.
                render: function(data, type, row) {
                    return '<a href="../management/index.php?node=host&sub=edit&id='
                        + row.id
                        + '">'
                        + $.escapeHtml(data)
                        + '</a>';
                },
                targets: 0
            }
        ]
    });

    if (Common.search && Common.search.length > 0) {
        ouHostsTable.search(Common.search).draw();
    }
});
