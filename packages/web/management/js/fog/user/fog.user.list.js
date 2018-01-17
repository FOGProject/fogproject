(function($) {
    var deleteSelected = $('#deleteSelected');
    var deleteModal = $('#deleteModal');
    var passwordField = $('#deletePassword');
    var confirmDelete = $('#confirmDeleteModal');
    var cancelDelete = $('#closeDeleteModal');

    var numUserString = confirmDelete.val();

    function disableButtons(disable) {
        deleteSelected.prop('disabled', disable);
    }
    function onSelect(selected) {
        var disabled = selected.count() == 0;
        disableButtons(disabled);
    }

    disableButtons(true);
    var table = Common.registerTable($('#dataTable'), onSelect, {
        order: [
            [0, 'asc']
        ],
        columns: [
            {data: 'name'},
            {data: 'display'},
            {data: 'api'}
        ],
        rowId: 'id',
        columnDefs: [
            {
                responsivePriority: -1,
                render: function(data, type, row) {
                    return '<a href="../management/index.php?node='+Common.node+'&sub=edit&id=' + row.id + '">' + data + '</a>';
                },
                targets: 0,
            },
            {
                responsivePriority: 0,
                targets: 1
            },
            {
                render: function(data, type, row) {
                    var enabled = '<span class="label label-success"><i class="fa fa-check-circle"></i></span>';
                    var disabled = '<span class="label label-danger"><i class="fa fa-times-circle"></i></span>';
                    if (data > 0) {
                        return enabled;
                    } else {
                        return disabled;
                    }
                },
                targets: 2
            }
        ],
        processing: true,
        serverSide: true,
        ajax: {
            url: '../management/index.php?node='+Common.node+'&sub=list',
            type: 'POST'
        }
    });

    if (Common.search && Common.search.length > 0) {
        table.search(Common.search).draw();
    }

    deleteSelected.click(function() {
        disableButtons(true);
        confirmDelete.val(numUserString.format(''));
        Common.massDelete(null, function(err) {
            if (err.status == 401) {
                deleteModal.modal('show');
            } else {
                onSelect(table.rows({selected: true}));
            }
        }, table);
    });
})(jQuery);
