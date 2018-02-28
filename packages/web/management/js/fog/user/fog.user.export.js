(function($) {
    var exportBtn = $('#export'),
        exportForm = $('#export-form'),
        exportModal = $('#exportModal'),
        exportModalConfirm = $('#confirmExportModal'),
        passwordField = $('#exportPassword'),
        cancelExport = $('#closeExportModal'),
        exportAction = exportForm.prop('action'),
        exportTable = $('#user-export-table');

    function disableButtons(disable) {
        exportBtn.prop('disabled', disable);
    }

    onSelect = function(event) {
    }

    exportsTable = Common.registerTable(exportTable, onSelect, {
        buttons: [
            'copy',
            {
                extend: 'csv',
                header: false,
            },
            'excel',
            'print',
            'colvis'
        ],
        lengthMenu: [
            [10, 25, 50, 100, -1],
            [10, 25, 50, 100, 'All'],
        ],
        order: [
            [0, 'asc']
        ],
        columns: [
            {data: 'name'},
            {data: 'password'},
            {data: 'createdTime'},
            {data: 'createdBy'},
            {data: 'type'},
            {data: 'display'},
            {data: 'api'},
            {data: 'token'}
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
            }
        ],
        csv: {
            header: false
        },
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
        exportsTable.search(Common.search).draw();
    }
})(jQuery);
