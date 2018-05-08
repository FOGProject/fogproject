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
    var table = $('#active-multicast-table').registerTable(onSelect, {
        order: [
            [0, 'asc']
        ],
        columns: [
            {data: 'name'},
            {data: 'clients'},
            {data: 'starttime'},
            {data: 'taskstatename'}
        ],
        rowId: 'id',
        columnDefs: [
            {
                render: function(data, type, row) {
                    return '<i class="fa fa-' + row.taskstateicon + '"></i>';
                },
                targets: 3
            }
        ],
        serverSide: true,
        ajax: {
            url: '../management/index.php?node='+Common.node+'&sub=getActiveMulticastTasks',
            type: 'post'
        }
    });

    if (Common.search && Common.search.length > 0) {
        table.search(Common.search).draw();
    }

    cancelSelected.on('click', function() {
        cancelSelected.prop('disabled', true);
        var rows = table.rows({selected: true}),
            toRemove = $.getSelectedIds(table),
            opts = {
                'cancelconfirm': '1',
                'tasks': toRemove
            };
        $.apiCall(cancelSelected.attr('method'), cancelSelected.attr('action'), opts, function(err) {
            if (!err) {
                table.draw(false);
                table.rows({selected: true}).deselect();
            } else {
                cancelSelected.prop('disabled', false);
            }
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
