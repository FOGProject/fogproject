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
        removeBtn = $('#remove'),
        updateBtn = $('#update');

    function disableButtons(disable) {
        activateBtn.prop('disabled', disable);
        installBtn.prop('disabled', disable);
        deactivateBtn.prop('disabled', disable);
        removeBtn.prop('disabled', disable);
        updateBtn.prop('disabled', disable);
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
                    var update = '<button type="button" class="btn btn-warning btn-xs plugin-update-btn" data-id="'+row.id+'" title="Apply pending database update"><i class="fa fa-exclamation-triangle"></i> Update available</button>';
                    if (data > 0) {
                        return row.needsupdate > 0 ? update : enabled;
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
            toActivate = $.getSelectedIds(table),
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
            toDeactivate = $.getSelectedIds(table),
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
            toInstall = $.getSelectedIds(table),
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
    // Bulk update: apply pending schema migrations to all selected plugins.
    updateBtn.on('click', function(e) {
        e.preventDefault();
        disableButtons(true);
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            rows = table.rows({selected: true}),
            toUpdate = $.getSelectedIds(table),
            opts = {
                plugins: toUpdate,
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
    // Per-row one-click upgrade: the "Update available" badge applies that
    // plugin's pending schema migrations via the same (non-destructive)
    // upgrade endpoint the bulk Update button uses.
    $('#dataTable').on('click', '.plugin-update-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var btn = $(this),
            id = btn.data('id'),
            method = updateBtn.attr('method'),
            action = updateBtn.attr('action'),
            opts = {
                plugins: [id],
                btnpressed: 1
            };
        btn.prop('disabled', true);
        $.apiCall(method, action, opts, function(err) {
            if (err) {
                btn.prop('disabled', false);
                return;
            }
            table.draw(false);
        });
    });
    removeBtn.on('click', function(e) {
        e.preventDefault();
        disableButtons(true);
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            rows = table.rows({selected: true}),
            toRemove = $.getSelectedIds(table),
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
