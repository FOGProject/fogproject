(function($) {

    var sessionCreateBtn = $('#session-create'),
        sessionCreateForm = $('#session-create-form'),
        sessionCreateModal = $('#createModal'),
        sessionCreateModalConfirmBtn = $('#createConfirmModalBtn'),
        sessionCreateModalCancelBtn = $('#createCancelModalBtn'),
        // Cancel elements
        sessionCancelBtn = $('#session-cancel'),
        sessionModal = $('#cancelModal'),
        sessionModalConfirmBtn = $('#confirmModalBtn'),
        sessionModalCancelBtn = $('#cancelModalBtn'),
        // Main Table
        sessionTable = $('#multicast-sessions-table'),
        reloadinterval,
        sessionResumeBtn = $('#session-resume'),
        sessionPauseBtn = $('#session-pause');

    function reload(callback, userpaging) {
        if (reloadinterval) {
            clearTimeout(reloadinterval);
        }
        sessionsTable.draw(false);
        reloadinterval = setTimeout(reload, 5000);
    }

    function sessionTableButtons(disable) {
        sessionCancelBtn.prop('disabled', disable);
    }

    sessionCreateForm.on('submit', function(e) {
        e.preventDefault();
    });

    sessionCreateModalConfirmBtn.on('click', function(e) {
        e.preventDefault();
        Common.processForm(sessionCreateForm, function(err) {
            if (err) {
                return;
            }
            $('#sessionname, #sessioncount, #sessiontimeout, #image').val('');
            $('#image').select2({width: '100%'});
            sessionsTable.draw(false);
            sessionsTable.rows({selected: true}).deselect();
            sessionCreateModal.modal('hide');
        });
    });

    sessionCreateBtn.on('click', function(e) {
        e.preventDefault();
        sessionTableButtons(true)
        sessionCreateModal.modal('show');
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

    sessionsTable.on('draw', function() {
        sessionTableButtons(sessionsTable.rows({selected: true}));
    });

    if (Common.search && Common.search.length > 0) {
        sessionsTable.search(Common.search).draw();
    }

    sessionModalConfirmBtn.on('click', function(e) {
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
            sessionModal.modal('hide');
            sessionsTable.draw(false);
            sessionsTable.rows({selected: true}).deselect();
        });
    });

    sessionCancelBtn.on('click', function(e) {
        sessionModal.modal('show');
    });

    // Enable the reload elements. (Auto refresh)
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
