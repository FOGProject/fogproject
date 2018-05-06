(function($) {

    var reportString = window.atob(Common.f),
        reportButtons = [
            'copy',
            'csv',
            'excel',
            {
                extend: 'pdfHtml5',
                download: 'open',
                customize: function (doc) {
                    var colspan = doc.content[1].table.body[0].length,
                        widths = [];
                    $('table th').each(function() {
                        var width = $(this).outerWidth(),
                            percent = Math.round(width / $(this).parent().outerWidth() * 100);
                        widths.push(percent+'%');
                    });
                    doc.content[1].table.widths = widths;
                }
            },
            'print',
            'colvis'
        ];

    // This will call our respective calls
    // to report the requested data.
    switch (reportString) {
        // History Report
        case 'history report':
            var historyTable = $('#history-table'),
                table = historyTable.registerTable(null, {
                    order: [
                        [1, 'desc']
                    ],
                    rowGroup: {
                        dataSrc: function(row) {
                            return moment(row.createdTime, moment.ISO_8601).format('MMM DD YYYY');
                        }
                    },
                    buttons: reportButtons,
                    columns: [
                        {data: 'createdBy'},
                        {data: 'createdTime'},
                        {data: 'info'},
                        {data: 'ip'}
                    ],
                    rowId: 'id',
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: '../management/index.php?node=report&sub=getList&f='
                            + Common.f,
                        type: 'post'
                    }
                });
            break;
        // Host List
        case 'host list':
            var hostTable = $('#hostlist-table'),
                table = hostTable.registerTable(null, {
                    order: [
                        [0, 'asc']
                    ],
                    buttons: reportButtons,
                    columns: [
                        {data: 'mainlink'},
                        {data: 'primac'},
                        {data: 'deployed'},
                        {data: 'imageLink'}
                    ],
                    rowGroup: {
                        dataSrc: 'deployed'
                    },
                    rowId: 'id',
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: '../management/index.php?node=report&sub=getList&f='
                            + Common.f,
                        type: 'post'
                    }
                });
            break;
        // Hosts and users
        case 'hosts and users':
            var userloginTable = $('#userlogin-table'),
                table = userloginTable.registerTable(null, {
                    order: [
                        [0, 'asc']
                    ],
                    buttons: reportButtons,
                    columns: [
                        {data: 'username'},
                        {data: 'hostLink'},
                        {data: 'createdTime'}
                    ],
                    rowGroup: {
                        dataSrc: 'hostLink'
                    },
                    rowId: 'id',
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: '../management/index.php?node=report&sub=getList&f='
                            + Common.f,
                        type: 'post'
                    }
                });
            break;
        // Imaging Log
        case 'imaging log':
            var imagingLogTable = $('#imaginglog-table'),
                table = imagingLogTable.registerTable(null, {
                    order: [
                        [0, 'asc']
                    ],
                    buttons: reportButtons,
                    columns: [
                        {data: 'hostLink'},
                        {data: 'start'},
                        {data: 'finish'},
                        {data: 'diff'},
                        {data: 'imageLink'},
                        {data: 'type'}
                    ],
                    rowGroup: {
                        dataSrc: 'hostLink'
                    },
                    rowId: 'id',
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: '../management/index.php?node=report&sub=getList&f='
                            + Common.f,
                        type: 'post'
                    }
                });
            break;
        // Inventory Report
        case 'inventory report':
            var inventoryTable = $('#inventory-table'),
                table = inventoryTable.registerTable(null, {
                    order: [
                        [0, 'asc']
                    ],
                    buttons: reportButtons,
                    columns: [
                        {data: 'hostLink'},
                        {data: 'sysserial'},
                        {data: 'sysproduct'},
                        {data: 'sysuuid'}

                    ],
                    rowId: 'id',
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: '../management/index.php?node=report&sub=getList&f='
                            + Common.f,
                        type: 'post'
                    }
                });
            break;
        // Pending MAC
        case 'pending mac list':
            var pendingMacTable = $('#pendingmac-table'),
                table = pendingMacTable.registerTable(null, {
                    order: [
                        [0, 'asc']
                    ],
                    buttons: reportButtons,
                    columns: [
                        {data: 'hostLink'},
                        {data: 'mac'}
                    ],
                    rowGroup: {
                        dataSrc: 'hostLink'
                    },
                    rowId: 'id',
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: '../management/index.php?node=report&sub=getList&f='
                            + Common.f,
                        type: 'post'
                    }
                });
            break;
        // Product Keys
        case 'product keys':
            var hostTable = $('#hostkeys-table'),
                table = hostTable.registerTable(null, {
                    order: [
                        [0, 'asc']
                    ],
                    buttons: reportButtons,
                    columns: [
                        {data: 'mainlink'},
                        {data: 'primac'},
                        {data: 'productKey'}
                    ],
                    rowGroup: {
                        dataSrc: 'productKey'
                    },
                    rowId: 'id',
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: '../management/index.php?node=report&sub=getList&f='
                            + Common.f,
                        type: 'post'
                    }
                });
            break;
        // Snapin List
        case 'snapin list':
            var snapinTable = $('#snapinlist-table'),
                table = snapinTable.registerTable(null, {
                    order: [
                        [0, 'asc']
                    ],
                    buttons: reportButtons,
                    columns: [
                        {data: 'mainlink'},
                        {data: 'file'},
                        {data: 'args'}
                    ],
                    rowId: 'id',
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: '../management/index.php?node=report&sub=getList&f='
                            + Common.f,
                        type: 'post'
                    }
                });
            break;
    }
})(jQuery);
