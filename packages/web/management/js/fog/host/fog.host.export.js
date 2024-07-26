(function($) {
    var exportTable = $('#host-export-table').registerTable(null, {
        buttons: exportButtons,
        order: [
            [1, 'asc']
        ],
        columns: [
            {data: 'primac'}, // 0
            {data: 'name'}, // 1
            {data: 'description'}, // 2
            {data: 'ip'}, // 3
            {data: 'imageID'}, // 4
            {data: 'building'}, // 5
            {data: 'createdTime'}, // 6
            {data: 'deployed'}, // 7
            {data: 'createdBy'}, // 8
            {data: 'useAD'}, // 9
            {data: 'ADDomain'}, // 10
            {data: 'ADOU'}, // 11
            {data: 'ADUser'}, // 12
            {data: 'ADPass'}, // 13
            {data: 'ADPassLegacy'}, // 14
            {data: 'productKey'}, // 15
            {data: 'printerLevel'}, // 16
            {data: 'kernelArgs'}, // 17
            {data: 'kernel'}, // 18
            {data: 'kernelDevice'}, // 19
            {data: 'init'}, // 20
            {data: 'pending'}, // 21
            {data: 'pub_key'}, // 22
            {data: 'sec_tok'}, // 23
            {data: 'sec_time'}, // 24
            {data: 'pingstatus'}, // 25
            {data: 'biosexit'}, // 26
            {data: 'efiexit'}, // 27
            {data: 'enforce'}, // 28
            {data: 'token'}, // 29
            {data: 'tokenlock'} // 30
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
            },
            {
                targets: 19,
                visible: false
            },
            {
                targets: 20,
                visible: false
            },
            {
                targets: 21,
                visible: false
            },
            {
                targets: 22,
                visible: false
            },
            {
                targets: 23,
                visible: false
            },
            {
                targets: 24,
                visible: false
            },
            {
                targets: 25,
                visible: false
            },
            {
                targets: 26,
                visible: false
            },
            {
                targets: 27,
                visible: false
            },
            {
                targets: 28,
                visible: false
            },
            {
                targets: 29,
                visible: false
            },
            {
                targets: 30,
                visible: false
            }
        ],
        rowId: 'id',
        processing: true,
        serverSide: true,
        select: false,
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
