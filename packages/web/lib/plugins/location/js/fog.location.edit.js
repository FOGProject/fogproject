$(function() {
    // ---------------------------------------------------------------
    // GENERAL TAB
    var nodeSelector = $('#storagenode'),
        groupSelector = $('#storagegroup');

    $.registerGeneralTab({
        nameInputSel: '#location',
        formSel: '#location-general-form'
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
        e.preventDefault();
        $('#hostDelModal').modal('show');
    });

    var locationHostsTable = $('#location-host-table').registerTable(onHostSelect, {
        order: [
            [1, 'asc'],
            [0, 'asc']
        ],
        columns: [
            {data: 'mainLink'},
            {data: 'association'},
        ],
        rowId: 'id',
        columnDefs: [
            {
                render: function(data, type, row) {
                    var checkval = '';
                    if (row.association === 'associated') {
                        checkval = ' checked';
                    }
                    return '<div class="form-check">'
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
        $('#location-host-table input.associated').on('change', onLocationHostCheckboxSelect);
        onHostSelect(locationHostsTable.rows({selected: true}));
    });

    var onLocationHostCheckboxSelect = function(e) {
        $.checkItemUpdate(locationHostsTable, this, e, locationHostUpdateBtn);
    };

    if (Common.search && Common.search.length > 0) {
        locationHostsTable.search(Common.search).draw();
    }
});
