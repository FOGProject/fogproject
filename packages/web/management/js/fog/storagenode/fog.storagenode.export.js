(function($) {
    var exportTable = Common.registerTable($('#storagenode-export-table'), Common.onSelect, {
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
        lengthMenu: [
            [10, 25, 50, 100, -1],
            [10, 25, 50, 100, 'All']
        ],
        order: [
            [0, 'asc']
        ],
        columns: [
            {data: 'name'},
            {data: 'description'},
            {data: 'isMaster'},
            {data: 'storagegroupID'},
            {data: 'isEnabled'},
            {data: 'isGraphEnabled'},
            {data: 'path'},
            {data: 'ftppath'},
            {data: 'bitrate'},
            {data: 'snapinpath'},
            {data: 'sslpath'},
            {data: 'ip'},
            {data: 'maxClients'},
            {data: 'user'},
            {data: 'pass'},
            {data: 'key'},
            {data: 'interface'},
            {data: 'bandwidth'},
            {data: 'webroot'}
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
                targets: 8,
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
