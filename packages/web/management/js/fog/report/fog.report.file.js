(function($) {

    reportString = window.atob(Common.f);
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
            break;
        // Host List
        case 'host list':
            break;
        // Hosts and users
        case 'hosts and users':
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
