(function($) {
    var deleteSelected = $('#deleteSelected'),
        deleteModal = $('#deleteModal'),
        passwordField = $('#deletePassword'),
        confirmDelete = $('#confirmDeleteModal'),
        cancelDelete = $('#closeDeleteModal'),
        numGroupString = confirmDelete.val();

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
            {data: 'storagegroupname'},
            {data: 'storagenodename'},
            {data: 'tftp'}
        ],
        rowId: 'id',
        columnDefs: [
            {
                responsivePriority: -1,
                render: function(data, type, row) {
                    return '<a href="../management/index.php?node='
                        + Common.node
                        + '&sub=edit&id='
                        + row.id
                        + '">'
                        + data
                        + '</a>';
                },
                targets: 0,
            },
            {
                render: function(data, type, row) {
                    return '<a href="../management/index.php?node=storagegroup&sub=edit&id='
                        + row.storagegroupID
                        + '">'
                        + data
                        + '</a>';
                },
                targets: 1
            },
            {
                render: function(data, type, row) {
                    if (row.storagenodeID > 0) {
                        return '<a href="../management/index.php?node=storagenode&sub=edit&id='
                            + row.storagenodeID
                            + '">'
                            + data
                            + '</a>';
                    }
                    return '';
                },
                targets: 2
            },
            {
                render: function(data, type, row) {
                    var enabled = '<span class="label label-success"><i class="fa fa-check-circle"></i></span>',
                        disabled = '<span class="label label-success"><i class="fa fa-times-circle"></i></span>';
                    if (row.tftp > 0) {
                        return enabled;
                    }
                    return disabled;
                },
                targets: 3
            }
        ],
        processing: true,
        serverSide: true,
        ajax: {
            url: '../management/index.php?node='
            + Common.node
            + '&sub=list',
            type: 'post'
        }
    });

    if (Common.search && Common.search.length > 0) {
        table.search(Common.search).draw();
    }

    deleteSelected.on('click',function() {
        disableButtons(true);
        confirmDelete.val(numGroupString.format(''));
        Common.massDelete(null, function(err) {
            if (err.status == 401) {
                deleteModal.modal('show');
            } else {
                onSelect(table.rows({selected: true}));
            }
        }, table);
    });
})(jQuery);
