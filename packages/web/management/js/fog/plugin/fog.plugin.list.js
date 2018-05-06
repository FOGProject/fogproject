(function($) {
    var deleteSelected = $('#deleteSelected'),
        deleteModal = $('#deleteModal'),
        passwordField = $('#deletePassword'),
        confirmDelete = $('#confirmDeleteModal'),
        cancelDelete = $('#closeDeleteModal'),
        numPluginString = confirmDelete.val(),
        activateBtn = $('#activate'),
        installBtn = $('#install'),
        deactivateBtn = $('#deactivate'),
        removeBtn = $('#remove');

    function disableButtons(disable) {
        activateBtn.prop('disabled', disable);
        installBtn.prop('disabled', disable);
        deactivateBtn.prop('disabled', disable);
        removeBtn.prop('disabled', disable);
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
            {data: 'name'},
            {data: 'description'},
            {data: 'location'},
            {data: 'state'},
            {data: 'installed'}
        ],
        rowId: 'id',
        createdRow: function(row, data, dataIndex) {
            $(row).attr('hash', data.hash);
        },
        columnDefs: [
            {
                responsivePriority: -1,
                targets: 0
            },
            {
                responsivePriority: 0,
                targets: 1
            },
            {
                render: function(data, type, row) {
                    var enabled = '<span class="label label-success"><i class="fa fa-check-circle"></i></span>';
                    var disabled = '<span class="label label-danger"><i class="fa fa-times-circle"></i></span>';
                    if (data > 0) {
                        return enabled;
                    } else {
                        return disabled;
                    }
                },
                targets: 3
            },
            {
                render: function(data, type, row) {
                    var enabled = '<span class="label label-success"><i class="fa fa-check-circle"></i></span>';
                    var disabled = '<span class="label label-danger"><i class="fa fa-times-circle"></i></span>';
                    if (data > 0) {
                        return enabled;
                    } else {
                        return disabled;
                    }
                },
                targets: 4
            }
        ],
        processing: true,
        serverSide: true,
        ajax: {
            url: '../management/index.php?node='+Common.node+'&sub=list',
            type: 'post'
        },
    });

    if (Common.search && Common.search.length > 0) {
        table.search(Common.search).draw();
    }

    activateBtn.on('click', function(e) {
        e.preventDefault();
        disableButtons(true);
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            rows = table.rows({selected: true}),
            toActivate = Common.getSelectedIds(table),
            opts = {
                plugins: toActivate,
                btnpressed: 1
            };
        $.apiCall(method, action, opts, function(err) {
            disableButtons(false);
            if (err) {
                return;
            }
            table.draw(false);
            table.rows({selected: true}).deselect();
        });
    });
    deactivateBtn.on('click', function(e) {
        e.preventDefault();
        disableButtons(true);
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            rows = table.rows({selected: true}),
            toDeactivate = Common.getSelectedIds(table),
            opts = {
                plugins: toDeactivate,
                btnpressed: 1
            };
        $.apiCall(method, action, opts, function(err) {
            disableButtons(false);
            if (err) {
                return;
            }
            table.draw(false);
            table.rows({selected: true}).deselect();
        });
    });
    installBtn.on('click', function(e) {
        e.preventDefault();
        disableButtons(true);
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            rows = table.rows({selected: true}),
            toInstall = Common.getSelectedIds(table),
            opts = {
                plugins: toInstall,
                btnpressed: 1
            };
        $.apiCall(method, action, opts, function(err) {
            disableButtons(false);
            if (err) {
                return;
            }
            table.draw(false);
            table.rows({selected: true}).deselect();
        });
    });
    removeBtn.on('click', function(e) {
        e.preventDefault();
        disableButtons(true);
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            rows = table.rows({selected: true}),
            toRemove = Common.getSelectedIds(table),
            opts = {
                plugins: toRemove,
                btnpressed: 1
            };
        $.apiCall(method, action, opts, function(err) {
            disableButtons(false);
            if (err) {
                return;
            }
            table.draw(false);
            table.rows({selected: true}).deselect();
        });
    });
})(jQuery);
