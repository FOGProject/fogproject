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
    // Fixed layout, clipping and column resizing all come from registerTable()
    // now -- every list page gets them, so there is nothing to set up here.
    // This page's header widths (18/32/10/20/10/10, set in
    // pluginmanagement.page) are what the fixed layout then honours, instead
    // of the longest Description dictating the whole table.
    var table = $('#dataTable').registerTable(onSelect, {
        // This list has only six short columns and a small, fixed row set,
        // so the responsive collapse never helps here -- it just hides four
        // columns behind a per-row expander at full width and makes the
        // expander fight the row-click selection. Keep every column visible.
        responsive: false,
        order: [
            [0, 'asc']
        ],
        columns: [
            {data: 'name'},
            {data: 'description'},
            {data: 'version'},
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
                    // Not a button: there is nothing the admin can do to this
                    // plugin from here. It states why activating it will be
                    // refused, so the refusal isn't the first they hear of it.
                    if (row.incompatible) {
                        return data + ' <span class="badge bg-danger" title="'+$('<div/>').text(row.incompatible).html()+'"><i class="fa fa-ban"></i> Incompatible</span>';
                    }
                    return data;
                },
                responsivePriority: -1,
                targets: 0
            },
            {
                responsivePriority: 0,
                targets: 1
            },
            {
                // A plugin that has never declared a version reads '' from
                // pVersion; an em dash says "didn't say" rather than looking
                // like a rendering fault.
                render: function(data, type, row) {
                    if (type !== 'display') {
                        return data;
                    }
                    return data ? data : '<span class="text-muted">&mdash;</span>';
                },
                targets: 2
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
                targets: 4
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
                targets: 5
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
    // ---- Upload a plugin archive -------------------------------------
    // Two steps on purpose. The first POST only unpacks the archive somewhere
    // the autoloader does not look and reports what is inside it; nothing is
    // installed until the second POST confirms the token. That is what makes
    // the checksum and manifest shown here worth reading -- they describe the
    // thing that is about to be installed, not the file name the browser sent.
    var uploadBtn = $('#plugin-upload'),
        uploadModalEl = $('#plugin-uploadModal'),
        uploadForm = $('#plugin-upload-form'),
        uploadPreview = $('#plugin-upload-preview'),
        uploadSend = $('#plugin-upload-send'),
        stagedToken = null;

    function resetUpload() {
        stagedToken = null;
        uploadPreview.addClass('d-none').empty();
        uploadForm.removeClass('d-none').empty();
        uploadSend.prop('disabled', false).text(uploadSend.data('label') || 'Upload');
    }
    function esc(s) {
        return $('<div/>').text(s === undefined || s === null ? '' : s).html();
    }
    function row(label, value) {
        return '<dt class="col-sm-3">' + esc(label) + '</dt>'
            + '<dd class="col-sm-9">' + esc(value) + '</dd>';
    }
    uploadSend.data('label', uploadSend.text());

    uploadBtn.on('click', function(e) {
        e.preventDefault();
        resetUpload();
        // Fetched each time the modal opens, so the "switch this on first"
        // message reflects the server as it is now.
        uploadForm.load('../management/index.php?node=plugin&sub=installArchive');
        uploadModalEl.modal('show');
    });
    uploadModalEl.on('hidden.bs.modal', resetUpload);

    uploadSend.on('click', function(e) {
        e.preventDefault();
        var btn = $(this);
        // Second click: the archive is already staged, so confirm it.
        if (stagedToken) {
            btn.prop('disabled', true);
            $.apiCall(
                'post',
                '../management/index.php?node=plugin&sub=installArchiveCommit',
                {token: stagedToken},
                function(err) {
                    btn.prop('disabled', false);
                    if (err) {
                        return;
                    }
                    uploadModalEl.modal('hide');
                    table.draw(false);
                }
            );
            return;
        }
        var file = $('#pluginarchive')[0];
        if (!file || !file.files.length) {
            return;
        }
        var data = new FormData();
        data.append('pluginarchive', file.files[0]);
        btn.prop('disabled', true);
        // processData=false is apiCall's FormData mode. Going through it
        // rather than a hand-rolled $.ajax is what gets the upload progress
        // bar, the error toast and the CSRF header without repeating any of
        // them here.
        $.apiCall(
            'post',
            '../management/index.php?node=plugin&sub=installArchive',
            data,
            function(err, res) {
                btn.prop('disabled', false);
                if (err || !res || !res.token) {
                    return;
                }
                var m = res.manifest || {};
                stagedToken = res.token;
                uploadForm.addClass('d-none');
                uploadPreview.removeClass('d-none').html(
                    (res.upgrade
                        ? '<div class="alert alert-warning">'
                            + esc(res.name)
                            + ' is already installed here. Confirming will replace its files.'
                            + '</div>'
                        : '')
                    + '<dl class="row mb-0">'
                    + row('Plugin', res.name)
                    + row('Version', m.version || '—')
                    + row('Author', m.author || '—')
                    + row('Homepage', m.homepage || '—')
                    + row('Requires FOG', (m.fog_min || 'any') + ' — ' + (m.fog_max || 'any'))
                    + row('Requires plugins', (m.requires && m.requires.length) ? m.requires.join(', ') : '—')
                    + row('Description', m.description || '—')
                    + row('Files', res.files)
                    + row('SHA-256', res.sha256)
                    + '</dl>'
                );
                btn.text('Install ' + res.name);
            },
            false
        );
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
