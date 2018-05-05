(function($) {
    // Approve
    var approveSelected = $('#approve'),
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
    var table = Common.registerTable($('#dataTable'), onSelect, {
        order: [
            [0, 'asc']
        ],
        columns: [
            {data: 'hostname'},
            {data: 'mac'}
        ],
        rowId: 'id',
        columnDefs: [
            {
                responsivePriority: -1,
                render: function (data, type, row) {
                    return '<a href="../management/index.php?node=host&sub=edit&id='
                        + row.hostid
                        + '">'
                        + data
                        + '</a>';
                },
                targets: 0
            },
            {
                responsivePriority: 0,
                targets: 1
            }
        ],
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
        Common.deleteSelected(table, function(err) {
            if (err) {
                disableButtons(false);
            }
        });
    });

    approveSelected.on('click', function() {
        disableButtons(true);
        var rows = table.rows({selected: true}),
            toApprove = Common.getSelectedIds(table),
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
})(jQuery);
