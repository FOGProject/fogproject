$(function() {
    // ---------------------------------------------------------------
    // GENERAL TAB
    var originalName = $('#location').val(),
        updateName = function(newName) {
            var e = $('#pageTitle'),
                text = e.text();
            text = text.replace(': ' + originalName, ': ' + newName);
            document.title = text;
            e.text(text);
        },
        generalForm = $('#location-general-form'),
        generalFormBtn = $('#general-send'),
        generalDeleteBtn = $('#general-delete'),
        generalDeleteModal = $('#deleteModal'),
        generalDeleteModalConfirm = $('#confirmDeleteModal'),
        generalDeleteModalCancel = $('#closeDeleteModal'),
        nodeSelector = $('#storagenode'),
        groupSelector = $('#storagegroup');

    generalForm.on('submit',function(e) {
        e.preventDefault();
    });
    generalFormBtn.on('click',function() {
        generalFormBtn.prop('disabled', true);
        generalDeleteBtn.prop('disabled', true);
        generalForm.processForm(function(err) {
            generalFormBtn.prop('disabled', false);
            generalDeleteBtn.prop('disabled', false);
            if (err) {
                return;
            }
            updateName($('#location').val());
            originalName = $('#location').val();
        });
    });
    generalDeleteBtn.on('click', function() {
        generalDeleteModal.modal('show');
    });
    generalDeleteModalConfirm.on('click', function() {
        var method = 'post',
            action = '../management/index.php?node='
            + Common.node
            + '&sub=delete&id='
            + Common.id;
        $.apiCall(method, action, null, function(err) {
            if (err) {
                return;
            }
            setTimeout(function() {
                window.location = '../management/index.php?node='
                    + Common.node
                    + '&sub=list';
            }, 2000);
        });
    });
    // Sets the group selector for the selected node.
    nodeSelector.on('change focus focusout', function(e) {
        e.preventDefault();
        var nodeID = this.value;
        Pace.ignore(function() {
            $.ajax({
                url: '../management/index.php?sub=getStoragenode',
                data: {
                    nodeID: nodeID
                },
                dataType: 'json',
                type: 'post',
                success: function(data, textStatus, jqXHR) {
                    groupSelector.val(data.storagegroupID).select2({
                        width: '100%'
                    });
                }
            });
        });
    });
    // Resets the node selector of the selected group is not
    // the selected nodes storage group.
    groupSelector.on('change focus focusout', function(e) {
        e.preventDefault();
        var nodeID = nodeSelector.val(),
            groupID = this.value;
        Pace.ignore(function() {
            $.ajax({
                url: '../management/index.php?sub=getStoragegroup',
                data: {
                    groupID: groupID
                },
                dataType: 'json',
                type: 'post',
                success: function(data, textStatus, jqXHR) {
                    if ($.inArray(nodeID, data.allnodes) != -1) {
                        return;
                    }
                    nodeSelector.val('').select2({
                        width: '100%'
                    });
                }
            });
        });
    });

    // ---------------------------------------------------------------
    // HOST ASSOCIATION TAB
    var locationHostUpdateBtn = $('#location-host-send'),
        locationHostRemoveBtn = $('#location-host-remove'),
        locationHostDeleteConfirmBtn = $('#confirmhostDeleteModal');

    function disableHostButtons(disable) {
        locationHostUpdateBtn.prop('disabled', disable);
        locationHostRemoveBtn.prop('disabled', disable);
    }

    function onHostSelect(selected) {
        var disabled = selected.count() == 0;
        disableHostButtons(disabled);
    }

    locationHostUpdateBtn.on('click', function(e) {
        e.preventDefault();
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            rows = locationHostsTable.rows({selected: true}),
            toAdd = $.getSelectedIds(locationHostsTable),
            opts = {
                confirmadd: 1,
                additems: toAdd
            };
        $.apiCall(method,action,opts,function(err) {
            disableHostButtons(false);
            if (err) {
                return;
            }
            locationHostsTable.draw(false);
            locationHostsTable.rows({selected:true}).deselect();
        });
    });

    locationHostRemoveBtn.on('click', function(e) {
        $('#hostDelModal').modal('show');
    });

    var locationHostsTable = $('#location-host-table').registerTable(onHostSelect, {
        order: [
            [1, 'asc'],
            [0, 'asc']
        ],
        columns: [
            {data: 'name'},
            {data: 'association'},
        ],
        rowId: 'id',
        columnDefs: [
            {
                responsivePriority: -1,
                render: function(data, type, row) {
                    return '<a href="../management/index.php?node=host&sub=edit&id='
                        + row.id
                        + '">'
                        + data
                        + '</a>';
                },
                targets: 0
            },
            {
                render: function(data, type, row) {
                    var checkval = '';
                    if (row.association === 'associated') {
                        checkval = ' checked';
                    }
                    return '<div class="checkbox">'
                        + '<input type="checkbox" class="associated" name="associate[]" id="locationHostAssoc_'
                        + row.id
                        + '" value="' + row.id + '"'
                        + checkval
                        + '/>'
                        + '</div>';
                },
                targets: 1
            }
        ],
        processing: true,
        serverSide: true,
        ajax: {
            url: '../management/index.php?node='
                + Common.node
                + '&sub=getHostsList&id='
                + Common.id,
            type: 'post'
        }
    });

    locationHostDeleteConfirmBtn.on('click', function(e) {
        $.deleteAssociated(locationHostsTable, locationHostUpdateBtn.attr('action'), function(err) {
            $('#hostDelModal').modal('hide');
            if (err) {
                return;
            }
            locationHostsTable.draw(false);
            locationHostsTable.rows({selected: true}).deselect();
        });
    });

    locationHostsTable.on('draw', function() {
        Common.iCheck('#location-host-table input');
        $('#location-host-table input.associated').on('ifChanged', onLocationHostCheckboxSelect);
        onHostSelect(locationHostsTable.rows({selected: true}));
    });

    var onLocationHostCheckboxSelect = function(e) {
        $.checkItemUpdate(locationHostsTable, this, e, locationHostUpdateBtn);
    };

    if (Common.search && Common.search.length > 0) {
        locationHostsTable.search(Common.search).draw();
    }
});
