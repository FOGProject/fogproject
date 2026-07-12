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
                render: function(data, type, row) {
                    return '<a href="../management/index.php?node=host&sub=edit&id='
                        + row.id
                        + '">'
                        + data
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
