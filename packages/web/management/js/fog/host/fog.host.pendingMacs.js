(function($) {
    // Approve
    var approveSelected = $('#approve'),
        approveModal = $('#approveModal'),
        confirmApprove = $('#confirmApproveModal'),
        cancelApprove = $('#cancelApprovalModal'),
        // Delete
        deleteSelected = $('#delete'),
        deleteModal = $('#deleteModal'),
        passwordField = $('#deletePassword'),
        confirmDelete = $('#confirmDeleteModal'),
        cancelDelete = $('#closeDeleteModal'),
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
            [1, 'asc']
        ],
        columns: [
            {data: 'mac'},
            {data: 'hostname'}
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
    confirmDelete.on('click', function(e) {
        e.preventDefault();
        cancelDelete.prop('disabled', true);
        confirmDelete.prop('disabled', true);
        Common.massDelete(passwordField.val(), function(err) {
            if (err) {
                headerText = deleteModal.find('.modal-header').text();
                deleteModal.modal('show');
                deleteModal.find('.modal-header').text(err.responseJSON.error);
                setTimeout(function() {
                    deleteModal.find('.modal-header').text(headerText);
                    passwordField.val('');
                    confirmDelete.prop('disabled', false);
                }, 2000);
            } else {
                deleteModal.modal('hide');
                disableButtons(false);
                table.draw(false);
                table.rows({selected: true}).deselect();
            }
        }, table);
    });

    deleteSelected.on('click',function(e) {
        e.preventDefault();
        disableButtons(true);
        confirmDelete.trigger('click');
    });

    approveSelected.on('click', function() {
        disableButtons(true);
        var rows = table.rows({selected: true}),
            toApprove = Common.getSelectedIds(table),
            opts = {
                approvepending: 1,
                pending: toApprove
            };
        Common.apiCall(method,action,opts,function(err) {
            disableButtons(false);
            if (err) {
                return;
            }
            table.draw(false);
            table.rows({selected: true}).deselect();
        });
    });
})(jQuery);
