(function($) {

    var reportString = window.atob(Common.f),
        reportButtons = [
            'copy',
            'csv',
            'excel',
            {
                extend: 'pdfHtml5',
                download: 'open',
                alignment: 'left',
                customize: function (doc) {
                    doc.content[1].table.widths =
                        Array(doc.content[1].table.body[0].length + 1).join('*').split('');
                }
            },
            'print',
            'colvis'
        ];

    // This will call our respective calls
    // to report the requested data.
    switch (reportString) {
        // Equipment Loan
        case 'equipment loan':
            var userSelBtn = $('#selectuser'),
                userSelector = $('#user'),
                downloadPdfBtn = $('#downloadpdf'),
                printPdfBtn = $('#printpdf'),
                equipForm = $('#equipmentloan-form'),
                docDefinition = {};

            function disableEquipBtns(disable) {
                userSelBtn.prop('disabled', disable);
                downloadPdfBtn.prop('disabled', disable);
                printPdfBtn.prop('disabled', disable);
                if (disable === true) {
                    downloadPdfBtn.addClass('hidden');
                    printPdfBtn.addClass('hidden');
                }
            }

            equipForm.on('submit', function(e) {
                e.preventDefault();
            });
            userSelector.on('change', function() {
                disableEquipBtns(true);
                userSelBtn.trigger('click');
            });
            userSelBtn.on('click', function(e) {
                e.preventDefault();
                downloadPdfBtn.addClass('hidden');
                printPdfBtn.addClass('hidden');
                disableEquipBtns(true);
                Common.processForm(equipForm, function(err, data) {
                    disableEquipBtns(false);
                    if (err) {
                        downloadPdfBtn.addClass('hidden');
                        printPdfBtn.addClass('hidden');
                        return;
                    }
                    downloadPdfBtn.removeClass('hidden');
                    //printPdfBtn.removeClass('hidden');
                    docDefinition = data._data;

                    docDefinition.header = function(currentPage, pageCount) {
                        return {
                            text: docDefinition.head,
                            alignment: 'center'
                        };
                    };

                    docDefinition.footer = function(currentPage, pageCount) {
                        return {
                            text: currentPage.toString() + ' of ' + pageCount,
                            alignment: 'center'
                        };
                    };
                });
            });
            downloadPdfBtn.on('click', function(e) {
                e.preventDefault();
                pdfMake.createPdf(docDefinition).download(docDefinition.filename);
            });
            printPdfBtn.on('click', function(e) {
                e.preventDefault();
                pdfMake.createPdf(docDefinition).print();
            });
            break;
        // History Report
        case 'history report':
            var historyTable = $('#history-table'),
                table = Common.registerTable(historyTable, null, {
                    order: [
                        [1, 'desc']
                    ],
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
                        url: '../management/index.php?node='
                            + Common.node
                            + '&sub=getHistoryList&f='
                            + Common.f,
                        type: 'post'
                    }
                });
            break;
        // Host List
        case 'host list':
            break;
        // Hosts and users
        case 'hosts and users':
            var userloginTable = $('#userlogin-table'),
                table = Common.registerTable(userloginTable, null, {
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
                        url: '../management/index.php?node='
                            + Common.node
                            + '&sub=getUserloginList&f='
                            + Common.f,
                        type: 'post'
                    }
                });
            break;
        // Imaging Log
        case 'imaging log':
            break;
        // Inventory Report
        case 'inventory report':
            break;
        // Pending MAC
        case 'pending mac list':
            break;
        // Product Keys
        case 'product keys':
            break;
        // Snapin Log
        case 'snapin log':
            break;
        // User Tracking
        case 'user tracking':
            break;
    }
})(jQuery);
