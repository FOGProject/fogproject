(function($) {
    var addToGroup = $('#addSelectedToGroup'),
        deleteSelected = $('#deleteSelected'),
        groupModal = $('#addToGroupModal'),
        groupModalSelect = $('#groupSelect');

    function disableButtons (disable) {
        addToGroup.prop('disabled', disable);
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
            {data: 'name'},
            {data: 'primac'},
            {data: 'pingstatus'},
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

    deleteSelected.on('click', function() {
        disableButtons(true);
        Common.deleteSelected(table, function(err) {
            // if we couldn't delete the items, enable the buttons
            //   as the rows still exist and are selected
            if (err) {
                disableButtons(false);
            }
        });
    });

    function initGroupSelect(data) {
        groupModalSelect.select2({
            width: '100%',
            tags: true,
            data: data,
            placeholder: 'Select or create group',
            createTag: function (params) {
                return {
                    id: params.term,
                    text: params.term,
                    newOption: true
                }
            },
            templateResult: function (data) {
                if (!data.text.length) {
                    return;
                }
                var $result = $("<span></span>");
                
                $result.text(data.text);
                if (data.newOption) {
                    $result.append(" <em><b>(new)</b></em>");
                }
                return $result;
            }
        });
        groupModalSelect.val(null).trigger("change");
    }
    initGroupSelect();




    Common.registerModal(groupModal, 
        // On show
        function(e) {

        }, 
        // On close
        function(e) {
            // Clear the group selector and data
            initGroupSelect(null);
        }
    );

    groupModal.on('show.bs.modal', function(e) {
        groupModalSelect.prop('disabled', true);
        Pace.track(function(){
            $.ajax('', {
                type: 'GET',
                url: '../fog/group/names',
                async: true,
                success: function(res) {
                    var mappedData = $.map(res, function (obj) {
                        obj.text = obj.name;
                        return obj;
                    });
                    initGroupSelect(mappedData);
                    groupModalSelect.prop('disabled', false);
                },
                error: function(res) {
                    Common.notifyFromAPI(res.responseJSON, true);
                }
            });
        });
    });

    addToGroup.on('click', function() {
        groupModal.modal('show');
    });
})(jQuery);
