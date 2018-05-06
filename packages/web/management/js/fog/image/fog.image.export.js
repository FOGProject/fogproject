(function($) {
    var exportTable = $('#image-export-table').registerTable(null, {
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
            {data: 'path'}, // 2
            {data: 'createdTime'}, // 3
            {data: 'createdBy'}, // 4
            {data: 'building'}, // 5
            {data: 'size'}, // 6
            {data: 'imageTypeID'}, // 7
            {data: 'imagePartitionTypeID'}, // 8
            {data: 'osID'}, // 9
            {data: 'deployed'}, // 10
            {data: 'format'}, // 11
            {data: 'magnet'}, // 12
            {data: 'protected'}, // 13
            {data: 'compress'}, // 14
            {data: 'isEnabled'}, // 15
            {data: 'toReplicate'}, // 16
            {data: 'srvsize'} // 17
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
