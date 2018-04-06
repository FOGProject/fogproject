(function($) {
    var exportTable = Common.registerTable($('#tasktype-export-table'), null, {
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
            {data: 'icon'}, // 2
            {data: 'kernel'}, // 3
            {data: 'kernelArgs'}, // 4
            {data: 'type'}, // 5
            {data: 'isAdvanced'}, // 6
            {data: 'access'}, // 7
            {data: 'initrd'} // 8
        ],
        columnDefs: [
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
            },
            {
                targets: 8,
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
