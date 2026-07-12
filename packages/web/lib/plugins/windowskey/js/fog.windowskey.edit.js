$(function() {
    // ---------------------------------------------------------------
    // GENERAL TAB
    $.registerGeneralTab({
        nameInputSel: '#windowskey',
        formSel: '#windowskey-general-form'
    });
    // ---------------------------------------------------------------
    // IMAGE TAB
    var windowskeyImagesTable = $.registerAssociationTab({
        slug: 'windowskey-image',
        item: 'image',
        sub: 'getImagesList',
        order: [[0, 'asc']],
        columns: [
            {data: 'name'},
            {data: 'association'}
        ],
        columnDefs: [
            {
                responsivePriority: -1,
                render: function(data, type, row) {
                    return '<a href="../management/index.php?node=image&sub=edit&id='
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
        windowskeyImagesTable.search(Common.search).draw();
    }
});
