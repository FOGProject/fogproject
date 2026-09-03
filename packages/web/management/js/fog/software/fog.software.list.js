(function($) {
    var deleteSelected = $('#deleteSelected'),
        createnewBtn = $('#createnew'),
        createnewModal = $('#createnewModal'),
        createForm = $('#create-form'),
        createnewSendBtn = $('#send');

    // Backend labels are hardcoded rather than fetched, matching the design:
    // one option today (Chocolatey), a second is a row here not a redesign.
    var backendLabels = {choco: 'Chocolatey'};

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
            {data: 'backend'},
            {data: 'package'},
            {data: 'version'},
            {data: 'state'},
            {data: 'isEnabled'}
        ],
        rowId: 'id',
        columnDefs: [
            {
                responsivePriority: -1,
                targets: 0
            },
            {
                render: function(data, type, row) {
                    return backendLabels[data] || data;
                },
                targets: 1
            },
            {
                // '' is any version, 'latest' tracks the source, anything
                // else is a pinned version string shown as-is.
                render: function(data, type, row) {
                    if (data === 'latest') {
                        return 'Latest';
                    }
                    if (!data) {
                        return 'Any';
                    }
                    return data;
                },
                targets: 3
            },
            {
                render: function(data, type, row) {
                    return $.capitalizeFirstLetter(data || '');
                },
                targets: 4
            },
            {
                responsivePriority: 0,
                render: function(data, type, row) {
                    var enabled = '<span class="badge text-bg-success"><i class="fas fa-circle-check"></i></span>';
                    var disabled = '<span class="badge text-bg-danger"><i class="fas fa-circle-xmark"></i></span>';
                    if (data > 0) {
                        return enabled;
                    } else {
                        return disabled;
                    }
                },
                targets: 5
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
        });
    });
})(jQuery);
