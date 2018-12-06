(function($) {
    var deleteSelected = $('#deleteSelected'),
        createnewBtn = $('#createnew'),
        createnewModal = $('#createnewModal'),
        createForm = $('#create-form'),
        createnewSendBtn = $('#send');

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
            {data: 'description'},
            {data: 'default'},
            {data: 'regMenu'},
            {data: 'hotkey'},
            {data: 'keysequence'}
        ],
        rowId: 'id',
        columnDefs: [
            {
                responsivePriority: -1,
                targets: 0
            },
            {
                render: function(data, type, row) {
                    if (data > 0) {
                        var label = 'success',
                            check = 'check-circle';
                    } else {
                        var label = 'danger',
                            check = 'times-circle';
                    }
                    return '<span class="label label-'
                        + label
                        + '"><i class="fa fa-'
                        + check
                        + '"></i></span>';
                },
                targets: 2
            },
            {
                render: function(data, type, row) {
                    if (data > 0) {
                        var label = 'success',
                            check = 'check-circle';
                    } else {
                        var label = 'danger',
                            check = 'times-circle';
                    }
                    return '<span class="label label-'
                        + label
                        + '"><i class="fa fa-'
                        + check
                        + '"></i></span>';
                },
                targets: 4
            }
        ],
        processing: true,
        serverSide: true,
        ajax: {
            url: '../management/index.php?node='
                + Common.node
                + '&sub=getMenuList',
            type: 'post'
        },
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
        });
    });
    deleteSelected.on('click', function() {
        disableButtons(true);
        $.deleteSelected(table, function(err) {
            // if we couldn't delete the items, enable the buttons
            // as the rows still exist and are selected.
            if (err) {
                disableButtons(false);
            }
            createnewBtn.prop('disabled', false);
        });
    });
})(jQuery);
