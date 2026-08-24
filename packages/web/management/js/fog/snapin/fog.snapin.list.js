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
            {data: 'protected'},
            {data: 'isEnabled'},
            {data: 'packtype'}
        ],
        rowId: 'id',
        columnDefs: [
            {
                responsivePriority: -1,
                targets: 0,
            },
            {
                render: function(data, type, row) {
                    var lock = '<span class="badge bg-warning"><i class="fas fa-lock fa-1x"></i></span>';
                    var unlock = '<span class="badge bg-danger"><i class="fas fa-unlock fa-fx"></i></span>';
                    if (row.protected > 0) {
                        return lock;
                    } else {
                        return unlock;
                    }
                },
                targets: 1
            },
            {
                render: function(data, type, row) {
                    var enabled = '<span class="badge bg-success"><i class="fas fa-circle-check"></i></span>';
                    var disabled = '<span class="badge bg-danger"><i class="fas fa-circle-xmark"></i></span>';
                    if (row.isEnabled > 0) {
                        return enabled;
                    } else {
                        return disabled;
                    }
                },
                targets: 2
            },
            {
                responsivePriority: 0,
                render: function(data, type, row) {
                    var enabled = '<span class="badge bg-success"><i class="fas fa-circle-check"></i></span>';
                    var disabled = '<span class="badge bg-danger"><i class="fas fa-circle-xmark"></i></span>';
                    if (data > 0) {
                        return enabled;
                    } else {
                        return disabled;
                    }
                },
                targets: 3
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

    // Shared command-builder UI (fog.common.js), scoped to the create form.
    // No .packhide elements here, so packHide stays off. wirePackTypes IS set:
    // this modal renders the same _addFields() form the add page does, so it
    // has a "Snapin Pack Template" select, and leaving it unwired meant picking
    // a pack template here silently did nothing to Run With / Run With Args.
    createForm.initSnapinCommandUI({wirePackTypes: true});
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
