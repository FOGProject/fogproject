(function($) {
    // ---------------------------------------------------------------
    // GENERAL TAB
    var opts = {};

    // Shall we delete the snapin file as well?
    $('#andFile').on('change', function(e) {
        e.preventDefault();
        if (!this.checked) {
            return;
        }
        opts = {andFile: 1};
    });

    $.registerGeneralTab({
        nameInputSel: '#snapin',
        formSel: '#snapin-general-form',
        deleteOpts: function() {
            $('#andFile').trigger('change');
            return opts;
        }
    });

    // Shared command-builder UI (fog.common.js). Edit form has .packhide
    // elements and wires #packTypes.
    $.initSnapinCommandUI({packHide: true, wirePackTypes: true});
    // ASSOCIATIONS
    // ---------------------------------------------------------------
    // HOST TAB
    var snapinHostUpdateBtn = $('#snapin-host-send'),
        snapinHostRemoveBtn = $('#snapin-host-remove'),
        snapinHostDeleteConfirmBtn = $('#confirmhostDeleteModal');

    function disableHostButtons(disable) {
        snapinHostUpdateBtn.prop('disabled', disable);
        snapinHostRemoveBtn.prop('disabled', disable);
    }

    function onHostSelect(selected) {
        var disabled = selected.count() == 0;
        disableHostButtons(disabled);
    }

    snapinHostUpdateBtn.on('click', function(e) {
        e.preventDefault();
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            toAdd = $.getSelectedIds(snapinHostsTable),
            opts = {
                confirmadd: 1,
                additems: toAdd
            };
        $.apiCall(method,action,opts,function(err) {
            disableHostButtons(false);
            if (err) {
                return;
            }
            snapinHostsTable.draw(false);
            snapinHostsTable.rows({selected:true}).deselect();
        });
    });

    snapinHostRemoveBtn.on('click', function(e) {
        e.preventDefault();
        $('#hostDelModal').modal('show');
    });

    var snapinHostsTable = $('#snapin-host-table').registerTable(onHostSelect, {
        order: [
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
                        + '<input type="checkbox" class="associated" name="associate[]" id="snapinHostAssoc_'
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

    snapinHostDeleteConfirmBtn.on('click', function(e) {
        $.deleteAssociated(snapinHostsTable, snapinHostUpdateBtn.attr('action'), function(err) {
            $('#hostDelModal').modal('hide');
            if (err) {
                return;
            }
            snapinHostsTable.draw(false);
            snapinHostsTable.rows({selected: true}).deselect();
        });
    });

    snapinHostsTable.on('draw', function() {
        Common.iCheck('#snapin-host-table input');
        $('#snapin-host-table input.associated').on('change', onSnapinHostCheckboxSelect);
        onHostSelect(snapinHostsTable.rows({selected: true}));
        snapinStoragegroupPrimarySelectorUpdate();
    });

    var onSnapinHostCheckboxSelect = function(e) {
        $.checkItemUpdate(snapinHostsTable, this, e, snapinHostUpdateBtn);
    };

    // ---------------------------------------------------------------
    // STORAGEGROUP TAB
    //
    // Association area
    var snapinStoragegroupUpdateBtn = $('#snapin-storagegroup-send'),
        snapinStoragegroupRemoveBtn = $('#snapin-storagegroup-remove'),
        snapinStoragegroupDeleteConfirmBtn = $('#confirmstoragegroupDeleteModal');

    function disableStoragegroupButtons(disable) {
        snapinStoragegroupUpdateBtn.prop('disabled', disable);
        snapinStoragegroupRemoveBtn.prop('disabled', disable);
    }

    function onStoragegroupSelect(selected) {
        var disabled = selected.count() == 0;
        disableStoragegroupButtons(disabled);
    }

    snapinStoragegroupUpdateBtn.on('click', function(e) {
        e.preventDefault();
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            toAdd = $.getSelectedIds(snapinStoragegroupsTable),
            opts = {
                confirmadd: 1,
                additems: toAdd
            };
        $.apiCall(method,action,opts,function(err) {
            disableStoragegroupButtons(false);
            if (err) {
                return;
            }
            snapinStoragegroupsTable.draw(false);
            snapinStoragegroupsTable.rows({selected: true}).deselect();
            setTimeout(snapinStoragegroupPrimarySelectorUpdate, 1000);
        });
    });

    snapinStoragegroupRemoveBtn.on('click', function(e) {
        e.preventDefault();
        $('#storagegroupDelModal').modal('show');
    });

    var snapinStoragegroupsTable = $('#snapin-storagegroup-table').registerTable(onStoragegroupSelect, {
        order: [
            [0, 'asc']
        ],
        columns: [
            {data: 'mainLink'},
            {data: 'association'}
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
                        + '<input type="checkbox" class="associated" name="associate[]" id="snapinStoragegroupAssoc_'
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
                + '&sub=getStoragegroupsList&id='
                + Common.id,
            type: 'post'
        }
    });

    snapinStoragegroupDeleteConfirmBtn.on('click', function(e) {
        $.deleteAssociated(snapinStoragegroupsTable, snapinStoragegroupUpdateBtn.attr('action'), function(err) {
            $('#storagegroupDelModal').modal('hide');
            if (err) {
                return;
            }
            snapinStoragegroupsTable.draw(false);
            snapinStoragegroupsTable.rows({selected: true}).deselect();
            setTimeout(snapinStoragegroupPrimarySelectorUpdate, 1000);
        });
    });

    snapinStoragegroupsTable.on('draw', function() {
        Common.iCheck('#snapin-storagegroup-table input');
        $('#snapin-storagegroup-table input.associated').on('change', onSnapinStoragegroupCheckboxSelect);
        onStoragegroupSelect(snapinStoragegroupsTable.rows({selected: true}));
    });

    var onSnapinStoragegroupCheckboxSelect = function(e) {
        $.checkItemUpdate(snapinStoragegroupsTable, this, e, snapinStoragegroupUpdateBtn);
        setTimeout(snapinStoragegroupPrimarySelectorUpdate, 1000);
    };

    // Primary area
    var snapinStoragegroupPrimaryUpdateBtn = $('#snapin-storagegroup-primary-send'),
        snapinStoragegroupPrimarySelector = $('#storagegroupselector'),
        snapinStoragegroupPrimarySelectorUpdate = function() {
            var url = '../management/index.php?node='
                + Common.node
                + '&sub=getSnapinPrimaryStoragegroups&id='
                + Common.id;
            Pace.ignore(function() {
                snapinStoragegroupPrimarySelector.html('');
                $.get(url, function(data) {
                    snapinStoragegroupPrimarySelector.html(data.content);
                    snapinStoragegroupPrimaryUpdateBtn.prop('disabled', data.disablebtn);
                }, 'json');
            });
        };

    function disableStoragegroupPrimaryButtons(disable) {
        snapinStoragegroupPrimaryUpdateBtn.prop('disabled', disable);
    }

    snapinStoragegroupPrimarySelectorUpdate();

    snapinStoragegroupPrimaryUpdateBtn.on('click', function(e) {
        e.preventDefault();
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            opts = {
                confirmprimary: 1,
                primary: $('#storagegroup option:selected').val()
            };
        $.apiCall(method,action,opts,function(err) {
            disableStoragegroupPrimaryButtons(false);
            if (err) {
                return;
            }
        });
    });

    if (Common.search && Common.search.length > 0) {
        snapinStoragegroupsTable.search(Common.search).draw();
        snapinHostsTable.search(Common.search).draw();
    }
})(jQuery);
