(function($) {

    var sessionCreateBtn = $('#session-create'),
        sessionCreateForm = $('#session-create-form'),
        sessionResumeBtn = $('#session-resume'),
        sessionPauseBtn = $('#session-pause'),
        sessionCancelBtn = $('#session-cancel'),
        sessionModal = $('#cancelModal'),
        sessionConfirmBtn = $('#confirmModalBtn'),
        sessionModalCancelBtn = $('#cancelModalBtn'),
        sessionTable = $('#multicast-sessions-table'),
        reloadinterval;

    function reload(callback, userpaging) {
        if (reloadinterval) {
            clearTimeout(reloadinterval);
        }
        sessionsTable.ajax.reload(callback, userpaging);
        reloadinterval = setTimeout(reload, 5000);
    }

    function sessionCreateButtons(disable) {
        sessionCreateBtn.prop('disabled', disable);
    }

    function sessionTableButtons(disable) {
        sessionCancelBtn.prop('disabled', disable);
    }

    sessionCreateForm.on('submit', function(e) {
        e.preventDefault();
    });

    sessionCreateBtn.on('click', function(e) {
        e.preventDefault();
        sessionTableButtons(true);
        sessionCreateButtons(true);
        Common.processForm(sessionCreateForm, function(err) {
            sessionTableButtons(false);
            sessionCreateButtons(false);
            clearTimeout(reloadinterval);
            if (err) {
                return;
            }
            $('#sessionname, #sessioncount, #sessiontimeout, #image').val('');
            $('#image').select2({width: '100%'});
            sessionsTable.draw(false);
            sessionsTable.rows({selected: true}).deselect();
            reload(null, false);
        });
    });

    function onSessionSelect(selected) {
        var disabled = selected.count() == 0;
        sessionTableButtons(disabled);
    }

    var sessionsTable = Common.registerTable(sessionTable, onSessionSelect, {
        order: [
            [0, 'asc']
        ],
        columns: [
            {data: 'name'},
            {data: 'image'},
            {data: 'clients'},
            {data: 'percent'}
        ],
        rowId: 'id',
        columnDefs: [
            {
                responsivePriority: -1,
                render: function(data, type, row) {
                    return '<a href="../management/index.php?node='
                        + Common.node
                        + 'sub=edit&id='
                        + row.imageid
                        + '">'
                        + row.imagename
                        + '</a>';
                },
                targets: 1
            },
            {
                render: function(data, type, row) {
                    data = parseInt(data);
                    return '<div class="progress progress-md active">'
                        + '<div class="progress-bar progress-bar-success progress-bar-striped" role="progressbar" aria-valuenow="'
                        + data
                        + '" aria-valuemin="0" aria-valuemax="100" style="width:'
                        + data
                        + '%">'
                        + data
                        + '%</div>'
                        + '</div>';
                },
                targets: 3
            }
        ],
        serverSide: true,
        ajax: {
            url: '../management/index.php?node='
            + Common.node
            + '&sub=getSessionsList',
            type: 'post'
        }
    });

    if (Common.search && Common.search.length > 0) {
        sessionsTable.search(Common.search).draw();
    }

    sessionCancelBtn.on('click', function(e) {
        sessionPauseBtn.prop('disabled', true);
        sessionResumeBtn.prop('disabled', true);
        sessionCancelBtn.prop('disable', true);
        clearTimeout(reloadinterval);
        var rows = sessionsTable.rows({selected: true}),
            toRemove = Common.getSelectedIds(sessionsTable),
            method = sessionCancelBtn.attr('method'),
            action = sessionCancelBtn.attr('action'),
            opts = {
                cancelconfirm: 1,
                tasks: toRemove
            };
        Common.apiCall(method, action, opts, function(err) {
            sessionCancelBtn.prop('disabled', false);
            sessionPauseBtn.prop('disabled', false);
            sessionResumeBtn.prop('disabled', true);
            if (err) {
                return;
            }
            sessionsTable.draw(false);
            sessionsTable.rows({selected: true}).deselect();
            reload(null, false);
        });
    });

    reload(null, false);
    sessionPauseBtn.prop('disabled', false);
    sessionResumeBtn.prop('disabled', true);
    sessionCancelBtn.prop('disabled', true);
    sessionPauseBtn.on('click', function(e) {
        sessionPauseBtn.prop('disabled', true);
        sessionResumeBtn.prop('disabled', false);
        clearTimeout(reloadinterval);
    });
    sessionResumeBtn.on('click', function(e) {
        sessionPauseBtn.prop('disabled', false);
        sessionResumeBtn.prop('disabled', true);
        reload(null, false);
    });
})(jQuery);
