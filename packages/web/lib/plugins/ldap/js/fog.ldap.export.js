(function($) {
    var exportTable = $('#ldap-export-table').registerTable(null, {
        buttons: exportButtons,
        order: [
            [0, 'asc']
        ],
        columns: [
            {data: 'name'}, // 0
            {data: 'description'}, // 1
            {data: 'createdBy'}, // 2
            {data: 'createdTime'}, // 3
            {data: 'address'}, // 4
            {data: 'port'}, // 5
            {data: 'searchDN'}, // 6
            {data: 'userNamAttr'}, // 7
            {data: 'grpNamAttr'}, // 8
            {data: 'grpMemberAttr'}, // 9
            {data: 'adminGroup'}, // 10
            {data: 'userGroup'}, // 11
            {data: 'searchScope'}, // 12
            {data: 'bindDN'}, // 13
            {data: 'bindPwd'}, // 14
            {data: 'grpSearchDN'}, // 15
            {data: 'useGroupMatch'}, // 16
            {data: 'displayNameOn'}, // 17
            {data: 'displayNameAttr'} // 18
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
                targets: 9,
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
