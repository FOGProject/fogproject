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

    // Refresh the "PLUGIN OPTIONS" sidebar section after a plugin's
    // installed/active state changes, so new items appear (and removed ones
    // disappear) without a full page reload. Only the inner HTML of
    // .plugin-options is replaced; the parent <ul data-lte-toggle="treeview">
    // is left intact so AdminLTE's delegated treeview handler keeps working.
    function refreshSidebar() {
        $.get('../management/index.php?node=plugin&sub=sidebar')
            .done(function(html) {
                $('.plugin-options').html(html);
            });
    }
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
    // Fixed layout, so the header's percentage widths actually decide the
    // columns. registerTable already passes autoWidth:false, which stops
    // DataTables measuring but leaves the browser's content-driven sizing in
    // charge -- and that let the longest Description dictate the whole table.
    // The class also clips an over-long cell to an ellipsis. That is required
    // here rather than cosmetic: this table keeps the scroller (virtual
    // scrolling), which sizes its viewport from a UNIFORM row height, so rows
    // have to stay one line. Wrapping Description would give variable-height
    // rows and break the scroller's maths. The full text is on the cell's
    // title attribute instead (see createdCell below).
    $('#dataTable').addClass('fog-table-fixed');
    var table = $('#dataTable').registerTable(onSelect, {
        // This list has only five short columns and a small, fixed row set,
        // so the responsive collapse never helps here -- it just hides four
        // columns behind a per-row expander at full width and makes the
        // expander fight the row-click selection. Keep every column visible.
        responsive: false,
        // NOTE: no makeColumnsResizable() call here yet. With the scroller on,
        // the header you actually see is a CLONE in .dt-scroll-head, and this
        // table's own thead is hidden and used only for sizing -- so the drag
        // strips attach where nobody can reach them. Making it work means
        // driving the clone and keeping the two tables' widths in step, which
        // is a bigger job than this page. The helper stays in fog.common.js
        // for a non-scrolling table, where it is verified working.
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
                // Surface the "Update available" action on the plugin name,
                // the leftmost and most visible column, so it's obvious which
                // plugin needs the update without scanning across to Installed.
                render: function(data, type, row) {
                    // Keep sorting/searching keyed on the plain name only.
                    if (type !== 'display') {
                        return data;
                    }
                    if (row.needsupdate > 0) {
                        return data + ' <button type="button" class="btn btn-warning btn-sm plugin-update-btn" data-id="'+row.id+'" title="Apply pending database update"><i class="fa fa-exclamation-triangle"></i> Update available</button>';
                    }
                    return data;
                },
                responsivePriority: -1,
                targets: 0
            },
            {
                responsivePriority: 0,
                // Description is clipped to one line, so put the whole of it
                // on the cell as a tooltip -- otherwise the tail is simply
                // unreadable rather than merely out of the way.
                createdCell: function(td, cellData) {
                    $(td).attr('title', cellData);
                },
                targets: 1
            },
            {
                render: function(data, type, row) {
                    var enabled = '<span class="badge bg-success"><i class="fa fa-check-circle"></i></span>';
                    var disabled = '<span class="badge bg-danger"><i class="fa fa-times-circle"></i></span>';
                    if (data > 0) {
                        return enabled;
                    } else {
                        return disabled;
                    }
                },
                targets: 3
            },
            {
                // Installed status only; the "Update available" action now
                // rides on the always-visible name column above.
                render: function(data, type, row) {
                    var enabled = '<span class="badge bg-success"><i class="fa fa-check-circle"></i></span>';
                    var disabled = '<span class="badge bg-danger"><i class="fa fa-times-circle"></i></span>';
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
            refreshSidebar();
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
            refreshSidebar();
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
            refreshSidebar();
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
            refreshSidebar();
        });
    });
})(jQuery);
