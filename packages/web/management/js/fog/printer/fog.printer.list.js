(function($) {
    var deleteSelected = $('#deleteSelected'),
        createnewBtn = $('#createnew'),
        createnewModal = $('#createnewModal'),
        createForm = $('#create-form'),
        createnewSendBtn = $('#send'),
        printertype = $('#printertype');

    // Show only the selected type's section. Hidden sections are disabled so
    // their inputs stay out of the submitted FormData and out of validation.
    function showType(type) {
        $('.printer-type-section').each(function() {
            var section = $(this),
                match = section.hasClass(type);
            section.toggleClass('d-none', !match);
            section.find(':input').prop('disabled', !match);
        });
    }

    function disableButtons(disable) {
        deleteSelected.prop('disabled', disable);
    }
    function onSelect(selected) {
        var disabled = selected.count() == 0;
        disableButtons(disabled);
    }

    disableButtons(true);
    var table = $('#dataTable').registerTable(onSelect, {
        order: [
            [0, 'asc']
        ],
        columns: [
            {data: 'mainlink'},
            {data: 'config'},
            {data: 'model'},
            {data: 'port'},
            {data: 'file'},
            {data: 'ip'},
            {data: 'configFile'}
        ],
        rowId: 'id',
        columnDefs: [
            {
                responsivePriority: -1,
                targets: 0
            },
            {
                responsivePriority: 0,
                render: function(data, type, row) {
                    return row.config == 'Local' ? 'TCP/IP' : data;
                },
                targets: 1
            }
        ],
        processing: true,
        serverSide: true,
        ajax: {
            url: '../management/index.php?node='+Common.node+'&sub=list',
            type: 'post'
        }
    });

    if (Common.search && Common.search.length > 0) {
        table.search(Common.search).draw();
    }

    createnewModal.registerModal(Common.createModalShow, Common.createModalHide);
    createnewBtn.on('click', function(e) {
        e.preventDefault();
        createnewModal.modal('show');
    });
    createnewSendBtn.on('click', function(e) {
        e.preventDefault();
        createForm.processForm(function(err) {
            if (err) {
                return;
            }
            table.draw(false);
            createnewModal.modal('hide');
        }, ':input:visible');
    });
    showType(printertype.val().toLowerCase());
    printertype.on('change', function(e) {
        e.preventDefault();
        showType(printertype.val().toLowerCase());
    });
    deleteSelected.on('click', function() {
        disableButtons(true);
        $.deleteSelected(table, function(err) {
            // if we couldn't delete the items, enable the buttons
            // as the rows still exist and are selected.
            if (err) {
                disableButtons(false);
            }
        });
    });
})(jQuery);
