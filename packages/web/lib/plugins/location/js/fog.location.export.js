(function($) {
    var exportTable = Common.registerTable($('#location-export-table'), null, {
        buttons: [
            'copy',
            {
                extend: 'csv',
                header: false
            },
            'excel',
            'print',
            'colvis'
        ],
        order: [
            [0, 'asc']
        ],
        columns: [
            {data: 'name'}, // 0
            {data: 'description'}, // 1
            {data: 'createdBy'}, // 2
            {data: 'createdTime'}, // 3
            {data: 'storagegroupID'}, // 4
            {data: 'storagenodeID'}, // 5
            {data: 'tftp'} // 6
        ],
        columnDefs: [
            {
                targets: 2,
                visible: false
            },
            {
                targets: 3,
                visible: false
            },
            {
                targets: 4,
                visible: false
            },
            {
                targets: 5,
                visible: false
            },
            {
                targets: 6,
                visible: false
            }
        ],
        rowId: 'id',
        processing: true,
        serverSide: true,
        ajax: {
            url: '../management/index.php?node='
            + Common.node
            + '&sub=getExportList',
            type: 'post'
        }
    });

    // Enable searching
    if (Common.search && Common.search.length > 0) {
        exportTable.search(Common.search).draw();
    }
})(jQuery);
