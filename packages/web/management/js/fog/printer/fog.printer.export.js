(function($) {
    var exportTable = Common.registerTable($('#printer-export-table'), Common.onSelect, {
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
            {data: 'port'},
            {data: 'file'},
            {data: 'model'},
            {data: 'config'},
            {data: 'configFile'},
            {data: 'ip'},
            {data: 'pAnon2'},
            {data: 'pAnon3'},
            {data: 'pAnon4'},
            {data: 'pAnon5'}
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
