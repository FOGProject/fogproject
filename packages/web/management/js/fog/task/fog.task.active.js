(function($) {
    var reloadinterval,
        cancelSelected = $('#cancel-selected'),
        pauseReload = $('#pause-refresh'),
        resumeReload = $('#resume-refresh');

    function disableButtons(disable) {
        cancelSelected.prop('disabled', disable);
    }
    function onSelect(selected) {
        var disabled = selected.count() == 0;
        disableButtons(disabled);
    }
    function reload(callback, userpaging) {
        if (reloadinterval) {
            clearTimeout(reloadinterval);
        }
        table.ajax.reload(callback, userpaging);
        reloadinterval = setTimeout(reload, 5000);
    }

    disableButtons(true);
    var table = Common.registerTable($('#active-tasks-table'), onSelect, {
        order: [
            [0, 'asc']
        ],
        columns: [
            {data: 'hostname'},
            {data: 'imagename'},
            {data: 'createdBy'},
            {data: 'tasktypename'},
            {data: 'taskstatename'},
            {data: 'percent'}
        ],
        rowId: 'id',
        columnDefs: [
            {
                responsivePriority: -1,
                render: function(data, type, row) {
                    return '<a href="../management/index.php?node=host&sub=edit&id=' + row.hostid + '">' + data + '</a>';
                },
                targets: 0
            },
            {
                responsivePriority: 0,
                render: function(data, type, row) {
                    return '<a href="../management/index.php?node=image&sub=edit&id=' + row.imageid + '">' + data + '</a>';
                },
                targets: 1
            },
            {
                responsivePriority: 1,
                render: function(data, type, row) {
                    return '<a href="../management/index.php?node=user&sub=edit&id=' + row.userid + '">' + data + '</a>';
                },
                targets: 2
            },
            {
                render: function(data, type, row) {
                    return row.tasktypename
                        + ' <i class="fa fa-' + row.tasktypeicon + '"></i> '
                },
                targets: 3
            },
            {
                render: function(data, type, row) {
                    return row.taskstatename
                        + ' <i class="fa fa-' + row.taskstateicon + '"></i> '
                },
                targets: 4
            },
            {
                render: function(data, type, row) {
                    if (data) {
                        data = parseInt(data);
                    } else {
                        data = parseInt(row.pct);
                    }
                    return row.timeElapsed
                        + ' / '
                        + row.timeRemaining
                        + ' '
                        + row.dataCopied
                        + ' of '
                        + row.dataTotal
                        + ' ('
                        + row.bpm
                        + '/min)'
                        + '<div class="progress progress-md active">'
                        + '<div class="progress-bar progress-bar-success progress-bar-striped" role="progressbar" aria-valuenow="'
                        + data
                        + '" aria-valuemin="0" aria-valuemax="100" style="width:'
                        + data
                        + '%">'
                        + data
                        + '%'
                        + '</div>'
                        + '</div>';
                },
                targets: 5
            }
        ],
        serverSide: true,
        ajax: {
            url: '../management/index.php?node='+Common.node+'&sub=getActiveTasks',
            type: 'post'
        }
    });

    if (Common.search && Common.search.length > 0) {
        table.search(Common.search).draw();
    }

    cancelSelected.on('click', function() {
        cancelSelected.prop('disabled', true);
        pauseReload.prop('disabled', true);
        resumeReload.prop('disabled', true);
        clearTimeout(reloadinterval);
        var rows = table.rows({selected: true}),
            toRemove = Common.getSelectedIds(table),
            method = cancelSelected.attr('method'),
            action = cancelSelected.attr('action'),
            opts = {
                cancelconfirm: 1,
                tasks: toRemove
            };
        Common.apiCall(method, action, opts, function(err) {
            cancelSelected.prop('disabled', false);
            pauseReload.prop('disabled', false);
            reload(null, false);
            if (err) {
                return;
            }
            table.draw(false);
            table.rows({selected: true}).deselect();
        });
    });
    reload(null, false);
    pauseReload.prop('disabled', false);
    resumeReload.prop('disabled', true);
    pauseReload.on('click', function() {
        pauseReload.prop('disabled', true);
        resumeReload.prop('disabled', false);
        clearTimeout(reloadinterval);
    });
    resumeReload.on('click', function() {
        resumeReload.prop('disabled', false);
        pauseReload.prop('disabled', false);
        reload(null, false);
    });
})(jQuery);
