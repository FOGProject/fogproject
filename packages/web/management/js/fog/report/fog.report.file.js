(function($) {

    var reportString = window.atob(Common.f),
        reportButtons = [
            {
                extend: 'copy',
                text: '<i class="fa fa-copy"></i> Copy'
            },
            {
                extend: 'csv',
                text: '<i class="fa fa-file-excel-o"></i> CSV'
            },
            {
                extend: 'excel',
                text: '<i class="fa fa-file-excel-o"></i> Excel'
            },
            {
                extend: 'print',
                text: '<i class="fa fa-print"></i> Print'
            },
            {
                extend: 'colvis',
                text: '<i class="fa fa-columns"></i> Column Visibility'
            },
            {
                text: '<i class="fa fa-refresh"></i> Refresh',
                action: function(e, dt, node, config) {
                    dt.clear().draw();
                    dt.ajax.reload();
                }
            }
        ];

    // This will call our respective calls
    // to report the requested data.
    switch (reportString) {
        // Files Deleted List
        case 'file deleter':
            var fileTable = $('#filedeleterlist-table'),
              table = fileTable.registerTable(null, {
                order: [
                  [3, 'desc']
                ],
                  rowGroup: {
                      dataSrc: function(row) {
                          return moment(row.createdTime, moment.ISO_8601).format('MMM DD YYYY');
                      }
                  },
                  buttons: reportButtons,
                  columns: [
                      {data: 'path'},
                      {data: 'pathtype'},
                      {data: 'taskstatename'},
                      {data: 'createdTime'},
                      {data: 'completedTime'},
                      {data: 'createdBy'}
                  ],
                  rowId: 'id',
                  processing: true,
                  serverSide: true,
                  select: false,
                  ajax: {
                      url: '../management/index.php?node=report&sub=getList&f='
                          + Common.f,
                      type: 'post'
                  }
              });
            break;
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
                    select: false,
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
                        [0, 'asc'],
                        [2, 'desc']
                    ],
                    buttons: reportButtons,
                    columns: [
                        {data: 'mainlink'},
                        {data: 'primac'},
                        {data: 'deployed'},
                        {data: 'imageLink'},
                        {data: 'name'}
                    ],
                    columnDefs: [
                        {
                            orderData: [4],
                            targets: [0]
                        },
                        {
                            targets: [4],
                            visible: false,
                            searchable: false
                        }
                    ],
                    rowId: 'id',
                    processing: true,
                    serverSide: true,
                    select: false,
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
                        [1, 'asc']
                    ],
                    buttons: reportButtons,
                    columns: [
                        {data: 'username'},
                        {data: 'hostLink'},
                        {data: 'createdTime'},
                        {data: 'hostname'}
                    ],
                    columnDefs: [
                        {
                            orderData: [3],
                            targets: [0]
                        },
                        {
                            targets: [3],
                            visible: false,
                            searchable: false
                        }
                    ],
                    rowGroup: {
                        dataSrc: 'hostLink'
                    },
                    rowId: 'id',
                    processing: true,
                    serverSide: true,
                    select: false,
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
                        [2, 'desc'],
                        [0, 'asc']
                    ],
                    buttons: reportButtons,
                    columns: [
                        {data: 'hostLink'},
                        {data: 'start'},
                        {data: 'finish'},
                        {data: 'diff'},
                        {data: 'imageLink'},
                        {data: 'type'},
                        {data: 'hostname'}
                    ],
                    columnDefs: [
                        {
                            orderData: [6],
                            targets: [0]
                        },
                        {
                            targets: [6],
                            visible: false,
                            searchable: false
                        }
                    ],
                    rowGroup: {
                        dataSrc: 'hostLink'
                    },
                    rowId: 'id',
                    processing: true,
                    serverSide: true,
                    select: false,
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
                        {data: 'hostLink'}, // 00
                        // User set information
                        {data: 'primaryUser'},
                        {data: 'other1'},
                        {data: 'other2'},
                        // System
                        {data: 'sysman'}, // 01
                        {data: 'sysproduct'}, // 02 visible
                        {data: 'sysversion'}, // 03
                        {data: 'sysserial'}, // 04 visible
                        {data: 'sysuuid'}, // 05 visible
                        {data: 'systype'}, // 06
                        // BIOS
                        {data: 'biosversion'}, // 07
                        {data: 'biosvendor'}, // 08
                        {data: 'biosdate'}, // 09
                        // Motherboard
                        {data: 'mbman'}, // 10
                        {data: 'mbproductname'}, // 11
                        {data: 'mbversion'}, // 12
                        {data: 'mbserial'}, // 13
                        {data: 'mbasset'}, // 14
                        // CPU
                        {data: 'cpuman'}, // 15
                        {data: 'cpuversion'}, // 16
                        {data: 'cpucurrent'}, // 17
                        {data: 'cpumax'}, // 18
                        // Memory
                        {data: 'mem'}, // 19 visible
                        // Hard Disk
                        {data: 'hdmodel'}, // 20
                        {data: 'hdserial'}, // 21
                        {data: 'hdfirmware'}, // 22
                        // Case
                        {data: 'caseman'}, // 23
                        {data: 'casever'}, // 24
                        {data: 'caseserial'}, // 25
                        {data: 'caseasset'}, // 26
                        // GPU
                        {data: 'gpuvendors'}, // 27
                        {data: 'gpuproducts'}, // 28
                        // name of host
                        {data: 'hostname'}, // 29 Not visible
                    ],
                    columnDefs: [
                        {targets: [0, 5, 7, 8, 22], visible: true },
                        {targets: '_all', visible: false},
                    ],
                    rowId: 'id',
                    processing: true,
                    serverSide: true,
                    select: false,
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
                    select: false,
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
                    select: false,
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
                    select: false,
                    ajax: {
                        url: '../management/index.php?node=report&sub=getList&f='
                            + Common.f,
                        type: 'post'
                    }
                });
            break;
    }
})(jQuery);
