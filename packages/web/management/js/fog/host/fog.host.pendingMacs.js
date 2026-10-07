(function($) {
    // Approve
    var approveSelected = $('#approve'),
        approveAll = $('#approveAll'),
        approveModal = $('#approveModal'),
        confirmApprove = $('#confirmApproveModal'),
        cancelApprove = $('#cancelApprovalModal'),
        // Delete
        deleteSelected = $('#delete'),
        // Form to work with.
        pendingForm = $('#mac-pending-form'),
        method = pendingForm.attr('method'),
        action = pendingForm.attr('action');

    function disableButtons (disable) {
        approveSelected.prop('disabled', disable);
        deleteSelected.prop('disabled', disable);
    }
    function onSelect (selected) {
        var disabled = selected.count() == 0;
        disableButtons(disabled);
    }

    disableButtons(true);
    var table = $('#dataTable').registerTable(onSelect, {
        order: [
            [0, 'asc']
        ],
        columns: [
            {data: 'hostLink'},
            {data: 'mac'}
        ],
        columnDefs: [
            {
                render: function (data, type, row) {
                    if (type !== 'display') {
                        return data;
                    }
                    return (data || '') + macVendorIcon(row.mac_vendor);
                },
                targets: 1
            }
        ],
        rowId: 'id',
        processing: true,
        serverSide: true,
        ajax: {
            url: '../management/index.php?node='
            + Common.node
            + '&sub=getPendingMacList',
            type: 'post'
        }
    });

    if (Common.search && Common.search.length > 0) {
        table.search(Common.search).draw();
    }

    disableButtons(true);
    deleteSelected.on('click', function(e) {
        disableButtons(true);
        $.deleteSelected(table, function(err) {
            if (err) {
                disableButtons(false);
            }
        }, {url: '../management/index.php?node=' + Common.node + '&sub=' + Common.sub});
    });

    approveSelected.on('click', function() {
        disableButtons(true);
        var rows = table.rows({selected: true}),
            toApprove = $.getSelectedIds(table),
            opts = {
                approvepending: 1,
                pending: toApprove
            };
        $.apiCall(method,action,opts,function(err) {
            disableButtons(false);
            if (err) {
                return;
            }
            table.draw(false);
            table.rows({selected: true}).deselect();
        });
    });

    // Select All only reaches the page the browser holds. Approve all
    // matching sends the grid's own request, unpaged, and the server
    // approves every row that request matches.
    approveAll.prop('disabled', true);
    table.on('draw', function() {
        approveAll.prop('disabled', table.page.info().recordsDisplay < 1);
    });
    approveAll.on('click', function() {
        approveModal.find('.modal-body').text(
            'Approve all '
            + table.page.info().recordsDisplay
            + ' pending MACs that match the current filter?'
        );
        approveModal.modal('show');
    });
    confirmApprove.on('click', function() {
        var opts = table.ajax.params();
        opts.start = 0;
        opts.length = -1;
        opts.approveall = 1;
        approveAll.prop('disabled', true);
        confirmApprove.prop('disabled', true);
        $.apiCall(method,action,opts,function() {
            confirmApprove.prop('disabled', false);
            approveModal.modal('hide');
            // The draw handler re-enables the button from the new count.
            table.draw(false);
            table.rows({selected: true}).deselect();
        });
    });
})(jQuery);
