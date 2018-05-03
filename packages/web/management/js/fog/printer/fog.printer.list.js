(function($) {
    var deleteSelected = $('#deleteSelected'),
        createnewBtn = $('#createnew'),
        createnewModal = $('#createnewModal'),
        createForm = $('#printer-create-form'),
        createnewSendBtn = $('#send'),
        printertype = $('#printertype'),
        printercopy = $('#printercopy'),
        type = printertype.val().toLowerCase();

    function disableButtons(disable) {
        deleteSelected.prop('disabled', disable);
    }
    function onSelect(selected) {
        var disabled = selected.count() == 0;
        disableButtons(disabled);
    }

    disableButtons(true);
    var table = Common.registerTable($('#dataTable'), onSelect, {
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

    createFormModalShow = function() {
        createForm[0].reset();
        $(':input:first').trigger('focus');
        $(':input:not(textarea)').on('keypress', function(e) {
            if (e.which == 13) {
                createnewSendBtn.trigger('click');
            }
        });
    };

    createFormModalHide = function() {
        createForm[0].reset();
        $(':input').off('keypress');
    };

    Common.registerModal(createnewModal, createFormModalShow, createFormModalHide);
    createnewBtn.on('click', function(e) {
        e.preventDefault();
        createnewModal.modal('show');
    });
    createnewSendBtn.on('click', function(e) {
        e.preventDefault();
        Common.processForm(createForm, function(err) {
            if (err) {
                return;
            }
            table.draw(false);
            createnewModal.modal('hide');
        });
    });
    // Hides the fields not currently selected.
    $('.network,.iprint,.cups,.local').addClass('hidden');
    $('.'+type).removeClass('hidden');
    // On change hide all the fields and show the appropriate type.
    printertype.on('change', function(e) {
        e.preventDefault();
        type = printertype.val().toLowerCase();
        $('.network,.iprint,.cups,.local').addClass('hidden');
        $('.'+type).removeClass('hidden');
    });
    // Setup all fields to match when/where appropriate
    $('[name="printer"]').on('change', function() {
        var val = $(this).val();
        $(this).each(function() {
            $('[name="printer"]').val(val);
        });
    });
    $('[name="description"]').on('change', function() {
        var val = $(this).val();
        $(this).each(function() {
            $('[name="description"]').val(val);
        });
    });
    $('[name="inf"]').on('change', function() {
        var val = $(this).val();
        $(this).each(function() {
            $('[name="inf"]').val(val);
        });
    });
    $('[name="port"]').on('change', function() {
        var val = $(this).val();
        $(this).each(function() {
            $('[name="port"]').val(val);
        });
    });
    $('[name="ip"]').on('change', function() {
        var val = $(this).val();
        $(this).each(function() {
            $('[name="ip"]').val(val);
        });
    });
    $('[name="model"]').on('change', function() {
        var val = $(this).val();
        $(this).each(function() {
            $('[name="model"]').val(val);
        });
    });
    $('[name="configFile"]').on('change', function() {
        var val = $(this).val();
        $(this).each(function() {
            $('[name="configFile"]').val(val);
        });
    });
    deleteSelected.on('click', function() {
        disableButtons(true);
        Common.deleteSelected(table, function(err) {
            // if we couldn't delete the items, enable the buttons
            // as the rows still exist and are selected.
            if (err) {
                disableButtons(false);
            }
        });
    });
})(jQuery);
