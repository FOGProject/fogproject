(function($) {
    var exportTable = Common.registerTable($('#snapin-export-table'), null, {
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
            {data: 'file'}, // 2
            {data: 'args'}, // 3
            {data: 'createdTime'}, // 4
            {data: 'createdBy'}, // 5
            {data: 'reboot'}, // 6
            {data: 'shutdown'}, // 7
            {data: 'runWith'}, // 8
            {data: 'runWithArgs'}, // 9
            {data: 'protected'}, // 10
            {data: 'isEnabled'}, // 11
            {data: 'toReplicate'}, // 12
            {data: 'hide'}, // 13
            {data: 'timeout'}, // 14
            {data: 'packtype'}, // 15
            {data: 'hash'}, // 16
            {data: 'size'}, // 17
            {data: 'anon3'} // 18
        ],
        columnDefs: [
            {
                targets: 1,
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
                targets: 7,
                visible: false
            },
            {
                targets: 8,
                visible: false
            },
            {
                targets: 9,
                visible: false
            },
            {
                targets: 10,
                visible: false
            },
            {
                targets: 11,
                visible: false
            },
            {
                targets: 12,
                visible: false
            },
            {
                targets: 13,
                visible: false
            },
            {
                targets: 14,
                visible: false
            },
            {
                targets: 15,
                visible: false
            },
            {
                targets: 16,
                visible: false
            },
            {
                targets: 17,
                visible: false
            },
            {
                targets: 18,
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
