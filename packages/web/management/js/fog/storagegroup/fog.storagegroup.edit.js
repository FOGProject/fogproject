(function($) {
    // ----------------------------------------------------
    // GENERAL TAB
    $.registerGeneralTab({
        nameInputSel: '#storagegroup',
        formSel: '#storagegroup-general-form',
        updateTitle: false
    });

    // Associations
    // ----------------------------------------------------
    // IMAGE TAB

    // Image Associations
    var storagegroupImagesTable = $.registerAssociationTab({
        slug: 'storagegroup-image',
        item: 'image',
        sub: 'getImagesList',
        afterCommit: function() {
            // Keep the primary-image table in sync on add/remove/toggle
            // (previously only refreshed on remove).
            storagegroupImagesPrimaryTable.draw(false);
        }
    });

    // Image Primary Settings
    var storagegroupImagePrimaryUpdateBtn = $('#storagegroup-image-primary-send'),
        storagegroupImagePrimaryRemoveBtn = $('#storagegroup-image-primary-remove'),
        storagegroupImagePrimaryDeleteConfirmBtn = $('#confirmImagePrimaryDeleteModal');

    function disableImagePrimaryButtons(disable) {
        storagegroupImagePrimaryUpdateBtn.prop('disabled', disable);
        storagegroupImagePrimaryRemoveBtn.prop('disabled', disable);
    }

    function onImagePrimarySelect(selected) {
        var disabled = selected.count() == 0;
        disableImagePrimaryButtons(disabled);
    }

    storagegroupImagePrimaryUpdateBtn.on('click', function(e) {
        e.preventDefault();
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            toAdd = $.getSelectedIds(storagegroupImagesPrimaryTable),
            opts = {
                confirmaddprimary: 1,
                additems: toAdd
            };
        $.apiCall(method,action,opts,function(err) {
            disableImagePrimaryButtons(false);
            if (err) {
                return;
            }
            storagegroupImagesTable.draw(false);
            storagegroupImagesPrimaryTable.draw(false);
            storagegroupImagesPrimaryTable.rows({selected: true}).deselect();
        });
    });

    storagegroupImagePrimaryRemoveBtn.on('click', function(e) {
        e.preventDefault();
        $('#unsetImagePrimaryModal').modal('show');
    });

    var storagegroupImagesPrimaryTable = $('#storagegroup-image-primary-table').registerTable(onImagePrimarySelect, {
        order: [
            [0, 'asc']
        ],
        columns: [
            {data: 'mainLink'},
            {data: 'primary'}
        ],
        rowId: 'id',
        columnDefs: [
            {
                render: function(data, type, row) {
                    var checkval = '';
                    if (data >= 1) {
                        checkval = ' checked';
                    }
                    return '<div class="form-check">'
                        + '<input type="checkbox" class="primary" name="primary[]" id="storagegroupImagePrimary_'
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
                + '&sub=getImagesList&id='
                + Common.id,
            type: 'post'
        }
    });

    storagegroupImagePrimaryDeleteConfirmBtn.on('click', function(e) {
        var action = storagegroupImagePrimaryUpdateBtn.attr('action'),
            method = storagegroupImagePrimaryUpdateBtn.attr('method'),
            opts = {
                confirmdelprimary: 1,
                remitems: $.getSelectedIds(storagegroupImagesPrimaryTable)
            };
        $.apiCall(method,action,opts,function(err) {
            $('#unsetImagePrimaryModal').modal('hide');
            if (err) {
                return;
            }
            storagegroupImagesPrimaryTable.draw(false);
            storagegroupImagesPrimaryTable.rows({selected: true}).deselect();
        });
    });

    storagegroupImagesPrimaryTable.on('draw', function(e) {
        Common.iCheck('#storagegroup-image-primary-table input');
        $('#storagegroup-image-primary-table input.primary').on('change', onStoragegroupImagePrimaryCheckboxSelect);
        onImagePrimarySelect(storagegroupImagesPrimaryTable.rows({selected: true}));
    });

    var onStoragegroupImagePrimaryCheckboxSelect = function(e) {
        var method = storagegroupImagePrimaryUpdateBtn.attr('method'),
            action = storagegroupImagePrimaryUpdateBtn.attr('action'),
            opts = {};
        if (this.checked) {
            opts = {
                confirmaddprimary: 1,
                additems: [e.target.value]
            };
        } else {
            opts = {
                confirmdelprimary: 1,
                remitems: [e.target.value]
            };
        }
        $.apiCall(method,action,opts,function(err) {
            if (err) {
                return;
            }
            storagegroupImagesTable.draw(false);
            storagegroupImagesPrimaryTable.draw(false);
            storagegroupImagesPrimaryTable.rows({selected: true}).deselect();
        });
    };

    // ----------------------------------------------------
    // SNAPIN TAB

    // Snapin Associations
    var storagegroupSnapinsTable = $.registerAssociationTab({
        slug: 'storagegroup-snapin',
        item: 'snapin',
        sub: 'getSnapinsList',
        afterCommit: function() {
            // Keep the primary-snapin table in sync on add/remove/toggle
            // (previously only refreshed on remove).
            storagegroupSnapinsPrimaryTable.draw(false);
        }
    });

    // Snapin Primary Settings
    var storagegroupSnapinPrimaryUpdateBtn = $('#storagegroup-snapin-primary-send'),
        storagegroupSnapinPrimaryRemoveBtn = $('#storagegroup-snapin-primary-remove'),
        storagegroupSnapinPrimaryDeleteConfirmBtn = $('#confirmSnapinPrimaryDeleteModal');

    function disableSnapinPrimaryButtons(disable) {
        storagegroupSnapinPrimaryUpdateBtn.prop('disabled', disable);
        storagegroupSnapinPrimaryRemoveBtn.prop('disabled', disable);
    }

    function onSnapinPrimarySelect(selected) {
        var disabled = selected.count() == 0;
        disableSnapinPrimaryButtons(disabled);
    }

    storagegroupSnapinPrimaryUpdateBtn.on('click', function(e) {
        e.preventDefault();
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            toAdd = $.getSelectedIds(storagegroupSnapinsPrimaryTable),
            opts = {
                confirmaddprimary: 1,
                additems: toAdd
            };
        $.apiCall(method,action,opts,function(err) {
            disableSnapinPrimaryButtons(false);
            if (err) {
                return;
            }
            storagegroupSnapinsTable.draw(false);
            storagegroupSnapinsPrimaryTable.draw(false);
            storagegroupSnapinsPrimaryTable.rows({selected: true}).deselect();
        });
    });

    storagegroupSnapinPrimaryRemoveBtn.on('click', function(e) {
        e.preventDefault();
        $('#unsetSnapinPrimaryModal').modal('show');
    });

    var storagegroupSnapinsPrimaryTable = $('#storagegroup-snapin-primary-table').registerTable(onSnapinPrimarySelect, {
        order: [
            [0, 'asc']
        ],
        columns: [
            {data: 'mainLink'},
            {data: 'primary'}
        ],
        rowId: 'id',
        columnDefs: [
            {
                render: function(data, type, row) {
                    var checkval = '';
                    if (data >= 1) {
                        checkval = ' checked';
                    }
                    return '<div class="form-check">'
                        + '<input type="checkbox" class="primary" name="primary[]" id="storagegroupSnapinPrimary_'
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
                + '&sub=getSnapinsList&id='
                + Common.id,
            type: 'post'
        }
    });

    storagegroupSnapinPrimaryDeleteConfirmBtn.on('click', function(e) {
        var action = storagegroupSnapinPrimaryUpdateBtn.attr('action'),
            method = storagegroupSnapinPrimaryUpdateBtn.attr('method'),
            opts = {
                confirmdelprimary: 1,
                remitems: $.getSelectedIds(storagegroupSnapinsPrimaryTable)
            };
        $.apiCall(method,action,opts,function(err) {
            $('#unsetSnapinPrimaryModal').modal('hide');
            if (err) {
                return;
            }
            storagegroupSnapinsPrimaryTable.draw(false);
            storagegroupSnapinsPrimaryTable.rows({selected: true}).deselect();
        });
    });

    storagegroupSnapinsPrimaryTable.on('draw', function(e) {
        Common.iCheck('#storagegroup-snapin-primary-table input');
        $('#storagegroup-snapin-primary-table input.primary').on('change', onStoragegroupSnapinPrimaryCheckboxSelect);
        onSnapinPrimarySelect(storagegroupSnapinsPrimaryTable.rows({selected: true}));
    });

    var onStoragegroupSnapinPrimaryCheckboxSelect = function(e) {
        var method = storagegroupSnapinPrimaryUpdateBtn.attr('method'),
            action = storagegroupSnapinPrimaryUpdateBtn.attr('action'),
            opts = {};
        if (this.checked) {
            opts = {
                confirmaddprimary: 1,
                additems: [e.target.value]
            };
        } else {
            opts = {
                confirmdelprimary: 1,
                remitems: [e.target.value]
            };
        }
        $.apiCall(method,action,opts,function(err) {
            if (err) {
                return;
            }
            storagegroupSnapinsTable.draw(false);
            storagegroupSnapinsPrimaryTable.draw(false);
            storagegroupSnapinsPrimaryTable.rows({selected: true}).deselect();
        });
    };

    // ----------------------------------------------------
    // STORAGE NODE TAB

    // Storage Node Associations
    var storagegroupStoragenodesTable = $.registerAssociationTab({
        slug: 'storagegroup-storagenode',
        item: 'storagenode',
        sub: 'getStoragenodesList',
        afterCommit: function() {
            setTimeout(storagegroupStoragenodeMasterSelectorUpdate, 1000);
        }
    });

    // Master area
    var storagegroupStoragenodeMasterUpdateBtn = $('#storagegroup-storagenode-master-send'),
        storagegroupStoragenodeMasterSelector = $('#storagenodeselector'),
        storagegroupStoragenodeMasterSelectorUpdate = function() {
            var url = '../management/index.php?node='
                + Common.node
                + '&sub=getStoragegroupMasterStoragenodes&id='
                + Common.id;
            Pace.ignore(function() {
                storagegroupStoragenodeMasterSelector.html('');
                $.get(url, function(data) {
                    storagegroupStoragenodeMasterSelector.html(data.content);
                    storagegroupStoragenodeMasterUpdateBtn.prop('disabled', data.disablebtn);
                }, 'json');
            });
        };

    function disableStoragenodeMasterButtons(disable) {
        storagegroupStoragenodeMasterUpdateBtn.prop('disabled', disable);
    }

    storagegroupStoragenodeMasterSelectorUpdate();

    storagegroupStoragenodeMasterUpdateBtn.on('click', function(e) {
        e.preventDefault();
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            opts = {
                confirmmaster: 1,
                master: $('#storagenode option:selected').val()
            };
        $.apiCall(method,action,opts,function(err) {
            disableStoragenodeMasterButtons(false);
            if (err) {
                return;
            }
        });
    });

    if (Common.search && Common.search.length > 0) {
        storagegroupImagesTable.search(Common.search).draw();
        storagegroupImagesPrimaryTable.search(Common.search).draw();
        storagegroupSnapinsTable.search(Common.search).draw();
        storagegroupSnapinsPrimaryTable.search(Common.search).draw();
        storagegroupStoragenodesTable.search(Common.search).draw();
    }
})(jQuery);
