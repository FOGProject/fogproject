(function($) {
    var deleteSelected = $('#deleteSelected'),
        deleteModal = $('#deleteModal'),
        passwordField = $('#deletePassword'),
        confirmDelete = $('#confirmDeleteModal'),
        cancelDelete = $('#closeDeleteModal'),
        numSnapinString = confirmDelete.val();

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
            [2, 'asc']
        ],
        columns: [
            {data: 'name'},
            {data: 'protected'},
            {data: 'isEnabled'},
            {data: 'packtype'}
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
                render: function(data, type, row) {
                    var lock = '<span class="label label-warning"><i class="fa fa-lock fa-1x"></i></span>';
                    var unlock = '<span class="label label-danger"><i class="fa fa-unlock fa-fx"></i></span>';
                    if (row.protected > 0) {
                        return lock;
                    } else {
                        return unlock;
                    }
                },
                targets: 1
            },
            {
                render: function(data, type, row) {
                    var enabled = '<span class="label label-success"><i class="fa fa-check-circle"></i></span>';
                    var disabled = '<span class="label label-danger"><i class="fa fa-times-circle"></i></span>';
                    if (row.isEnabled > 0) {
                        return enabled;
                    } else {
                        return disabled;
                    }
                },
                targets: 2
            },
            {
                responsivePriority: 0,
                render: function(data, type, row) {
                    var enabled = '<span class="label label-success"><i class="fa fa-check-circle"></i></span>';
                    var disabled = '<span class="label label-danger"><i class="fa fa-times-circle"></i></span>';
                    if (data > 0) {
                        return enabled;
                    } else {
                        return disabled;
                    }
                },
                targets: 3
            }
        ],
        processing: true,
        serverSide: true,
        ajax: {
            url: '../management/index.php?node='+Common.node+'&sub=list',
            type: 'post'
        }
    });

    if (Common.search && Common.search.length > 0) {
        table.search(Common.search).draw();
    }

    deleteSelected.on('click',function() {
        disableButtons(true);
        confirmDelete.val(numSnapinString.format(''));
        Common.massDelete(null, function(err) {
            if (err.status == 401) {
                deleteModal.modal('show');
            } else {
                onSelect(table.rows({selected: true}));
            }
        }, table);
    });
})(jQuery);
