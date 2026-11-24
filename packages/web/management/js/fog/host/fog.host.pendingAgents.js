(function($) {
    // Approve
    var approveSelected = $('#approve'),
        approveModal = $('#approveModal'),
        confirmApprove = $('#confirmApproveModal'),
        // Deny
        denySelected = $('#deny'),
        denyModal = $('#denyModal'),
        confirmDeny = $('#confirmDenyModal'),
        // Form to work with.
        pendingForm = $('#agent-pending-form'),
        method = pendingForm.attr('method'),
        action = pendingForm.attr('action');

    function disableButtons (disable) {
        approveSelected.prop('disabled', disable);
        denySelected.prop('disabled', disable);
    }
    function onSelect (selected) {
        var disabled = selected.count() == 0;
        disableButtons(disabled);
    }
    function esc (s) {
        return $('<div>').text(s == null ? '' : String(s)).html();
    }

    disableButtons(true);
    // Client-side table: the rows come from the same whitelisted payload
    // the admin JSON route serves (see HostManagement::getPendingAgentList),
    // fetched once and paged in the browser. There is no server-side
    // listem() for this class on purpose -- its rows carry key material.
    var table = $('#dataTable').registerTable(onSelect, {
        order: [
            [6, 'desc']
        ],
        columns: [
            {data: 'hostname'},
            {data: 'reason'},
            {data: 'os'},
            {data: 'agentVersion'},
            {data: 'remoteIP'},
            {data: 'identity'},
            {data: 'created'}
        ],
        columnDefs: [
            {
                // A request bound to an existing host links to it; an
                // unknown machine shows only the name it reported.
                render: function (data, type, row) {
                    if (type !== 'display') {
                        return data;
                    }
                    if (row.hostID > 0) {
                        return '<a href="../management/index.php?node=host&sub=edit&id='
                            + parseInt(row.hostID, 10) + '">' + esc(data) + '</a>';
                    }
                    return esc(data);
                },
                targets: 0
            },
            {
                // An identity conflict names the other hosts its MACs
                // belong to, so the admin can see who the conflict is with.
                render: function (data, type, row) {
                    var others = row.conflictHosts || [];
                    if (type !== 'display') {
                        return data + ' ' + others.map(function (h) {
                            return h.name;
                        }).join(' ');
                    }
                    // A host holds one agent seat per OS. new-seat is the
                    // second OS of a host that already has an agent.
                    // seat-removed is a key whose seat an admin removed.
                    var shown = data === 'new-seat'
                        ? 'New OS on a host that already has an agent'
                        : (data === 'seat-removed'
                            ? 'Agent seat removed by an admin'
                            : data);
                    if (!others.length) {
                        return esc(shown);
                    }
                    return esc(shown) + '<br><small class="text-muted">'
                        + 'MAC also on: '
                        + others.map(function (h) {
                            return '<a href="../management/index.php?node=host&sub=edit&id='
                                + parseInt(h.id, 10) + '">' + esc(h.name) + '</a>';
                        }).join(', ')
                        + '</small>';
                },
                targets: 1
            },
            {
                render: function (data, type, row) {
                    return esc(data) + (row.arch ? '/' + esc(row.arch) : '');
                },
                targets: 2
            },
            {
                // What the machine said it is. The serial is what an admin
                // can check against a label; the UUID is what the server
                // matched on.
                render: function (data, type) {
                    var id = data || {};
                    if (type !== 'display') {
                        return (id.system_serial || '') + ' ' + (id.system_uuid || '');
                    }
                    var parts = [];
                    if (id.system_serial) {
                        parts.push(esc(id.system_serial));
                    }
                    if (id.system_uuid) {
                        parts.push('<small class="text-muted">' + esc(id.system_uuid) + '</small>');
                    }
                    return parts.join('<br>');
                },
                targets: 5
            }
        ],
        rowId: 'id',
        processing: true,
        serverSide: false,
        ajax: {
            url: '../management/index.php?node='
            + Common.node
            + '&sub=getPendingAgentList',
            type: 'post'
        }
    });

    if (Common.search && Common.search.length > 0) {
        table.search(Common.search).draw();
    }

    function decide (which, modal, button) {
        disableButtons(true);
        var opts = {pending: $.getSelectedIds(table)};
        opts[which] = 1;
        $.apiCall(method, action, opts, function(err) {
            modal.modal('hide');
            disableButtons(false);
            // Redraw whatever happened: a partial failure has still
            // decided some rows, and the error toast names the rest.
            table.ajax.reload(null, false);
        });
        button.prop('disabled', false);
    }

    approveSelected.on('click', function() {
        approveModal.modal('show');
    });
    confirmApprove.on('click', function() {
        decide('approvepending', approveModal, confirmApprove);
    });
    denySelected.on('click', function() {
        denyModal.modal('show');
    });
    confirmDeny.on('click', function() {
        decide('denypending', denyModal, confirmDeny);
    });
})(jQuery);
