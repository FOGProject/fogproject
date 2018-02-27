(function($) {
    var addToGroup = $('#addSelectedToGroup'),
        deleteSelected = $('#deleteSelected'),
        deleteModal = $('#deleteModal'),
        passwordField = $('#deletePassword'),
        confirmDelete = $('#confirmDeleteModal'),
        cancelDelete = $('#closeDeleteModal'),
        numHostString = confirmDelete.val();

    function disableButtons (disable) {
        addToGroup.prop('disabled', disable);
        deleteSelected.prop('disabled', disable);
    }
    function onSelect (selected) {
        var disabled = selected.count() == 0;
        disableButtons(disabled);
    }

    // Common ping codes.
    var pingcodes = [0, 6, 110],
        pingstring = [];

    // Callback to set our ping string array.
    function pingStatusCallback(retstring, code) {
        return pingstring[code] = retstring;
    }

    $.each(pingcodes, function(index, value) {
        $.ajax({
            data: {code: value},
            dataType: 'json',
            type: 'post',
            url: '../management/index.php?sub=getSocketCodeStr',
            success: function(response) {
                pingStatusCallback(response.data, value);
            },
        });
    });

    disableButtons(true);
    var table = Common.registerTable($('#dataTable'), onSelect, {
        order: [
            [0, 'asc']
        ],
        columns: [
            {data: 'name'},
            {data: 'primac'},
            {
                data: 'pingstatus',
                defaultContent: pingstring[6]
            },
            {data: 'deployed'},
            {data: 'imagename'},
            {data: 'description'}
        ],
        rowId: 'id',
        columnDefs: [
            {
                responsivePriority: -1,
                render: function (data, type, row) {
                    return '<a href="../management/index.php?node=host&sub=edit&id=' + row.id + '">' + data + '</a>';
                },
                targets: 0
            },
            {
                responsivePriority: 0,
                targets: 1
            },
            {
                //searching: false,
                render: function (data, type, row) {
                    return pingstring[data];
                },
                targets: 2
            },
            {
                render: function (data, type, row) {
                    return (data === '0000-00-00 00:00:00') ? '' : data;
                },
                targets: 3
            },
            {
                render: function (data, type, row) {
                    if (data === null) {
                        return '';
                    }
                    return '<a href="../management/index.php?node=image&sub=edit&id=' + row.imageID + '">' + data + '</a>';
                },
                targets: 4
            }
        ],
        processing: true,
        serverSide: true,
        ajax: {
            url: '../management/index.php?node=host&sub=list',
            type: 'POST'
        }
    });

    if (Common.search && Common.search.length > 0) {
        table.search(Common.search).draw();
    }

    deleteSelected.on('click',function() {
        disableButtons(true);
        confirmDelete.val(numHostString.format(''));
        Common.massDelete(null, function(err) {
            if (err.status == 401) {
                deleteModal.modal('show');
            } else {
                onSelect(table.rows({selected: true}));
            }
        }, table);
    });
})(jQuery);
