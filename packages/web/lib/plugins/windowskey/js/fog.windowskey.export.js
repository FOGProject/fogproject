(function($) {
    var exportTable = Common.registerTable($('#windowskey-export-table'), null, {
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
            {data: 'key'} // 4
        ],
        columnsDef: [
            {
                targets: 1,
                visible: false
            },
            {
                targets: 2,
                visible: false
            },
            {
                targets: 3,
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
