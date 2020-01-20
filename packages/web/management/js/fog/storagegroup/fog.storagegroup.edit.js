(function($) {
    // ----------------------------------------------------
    // GENERAL TAB
    var originalName = $('#storagegroup').val(),
        updateName = function(newName) {
            var e = $('#pageTitle'),
                text = e.text();
            text = text.replace(": " + originalName, ": " + newName);
            e.text(text);
        },
        generalForm = $('#storagegroup-general-form'),
        generalFormBtn = $('#general-send'),
        generalDeleteBtn = $('#general-delete'),
        generalDeleteModal = $('#deleteModal'),
        generalDeleteModalConfirm = $('#confirmDeleteModal'),
        generalDeleteModalCancel = $('#closeDeleteModal');

    generalForm.on('submit', function(e) {
        e.preventDefault();
    });
    generalFormBtn.on('click', function(e) {
        generalFormBtn.prop('disabled', true);
        generalDeleteBtn.prop('disabled', true);
        generalForm.processForm(function(err) {
            generalFormBtn.prop('disabled', false);
            generalDeleteBtn.prop('disabled', false);
            if (err) {
                return;
            }
            updateName($('#storagegroup').val());
            originalName = $('#storagegroup').val();
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

    // Associations
    // ----------------------------------------------------
    // IMAGE TAB

    // Image Associations
    var storagegroupImageUpdateBtn = $('#storagegroup-image-send'),
        storagegroupImageRemoveBtn = $('#storagegroup-image-remove'),
        storagegroupImageDeleteConfirmBtn = $('#confirmimageDeleteModal');

    function disableImageButtons(disable) {
        storagegroupImageUpdateBtn.prop('disabled', disable);
        storagegroupImageRemoveBtn.prop('disabled', disable);
    }

    function onImageSelect(selected) {
        var disabled = selected.count() == 0;
        disableImageButtons(disabled);
    }

    storagegroupImageUpdateBtn.on('click', function(e) {
        e.preventDefault();
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            rows = storagegroupImagesTable.rows({selected: true}),
            toAdd = $.getSelectedIds(storagegroupImagesTable),
            opts = {
                confirmadd: 1,
                additems: toAdd
            };
        $.apiCall(method,action,opts,function(err) {
            disableImageButtons(false);
            if (err) {
                return;
            }
            storagegroupImagesTable.draw(false);
            storagegroupImagesTable.rows({selected: true}).deselect();
        });
    });

    storagegroupImageRemoveBtn.on('click', function(e) {
        e.preventDefault();
        $('#imageDelModal').modal('show');
    });

    var storagegroupImagesTable = $('#storagegroup-image-table').registerTable(onImageSelect, {
        order: [
            [1, 'asc'],
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
                    return '<div class="checkbox">'
                        + '<input type="checkbox" class="associated" name="associate[]" id="storagegroupImageAssoc_'
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

    storagegroupImageDeleteConfirmBtn.on('click', function(e) {
        $.deleteAssociated(storagegroupImagesTable, storagegroupImageUpdateBtn.attr('action'), function(err) {
            $('#imageDelModal').modal('hide');
            if (err) {
                return;
            }
            storagegroupImagesTable.draw(false);
            storagegroupImagesTable.rows({selected: true}).deselect();
        });
    });

    storagegroupImagesTable.on('draw', function(e) {
        Common.iCheck('#storagegroup-image-table input');
        $('#storagegroup-image-table input.associated').on('ifChanged', onStoragegroupImageCheckboxSelect);
        onImageSelect(storagegroupImagesTable.rows({selected: true}));
        storagegroupImagesPrimaryTable.draw(false);
    });

    var onStoragegroupImageCheckboxSelect = function(e) {
        $.checkItemUpdate(storagegroupImagesTable, this, e, storagegroupImageUpdateBtn);
    }

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
            rows = storagegroupImagesPrimaryTable.rows({selected: true}),
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
            [1, 'asc'],
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
                    console.log(data);
                    if (data >= 1) {
                        checkval = ' checked';
                    }
                    return '<div class="checkbox">'
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
            rows = storagegroupImagesPrimaryTable.rows({selected: true}),
            opts = {
                confirmdelprimary: 1,
                remitems: rows.ids().toArray()
            };
        $.apiCall(method,action,opts,function(err) {
            $('#unsetImagePrimaryModal').modal('hide');
            if (err) {
                return;
            }
            storagegroupImagesTable.draw(false);
            storagegroupImagesPrimaryTable.draw(false);
            storagegroupImagesPrimaryTable.rows({selected: true}).deselect();
        });
    });

    storagegroupImagesPrimaryTable.on('draw', function(e) {
        Common.iCheck('#storagegroup-image-primary-table input');
        $('#storagegroup-image-primary-table input.primary').on('ifChanged', onStoragegroupImagePrimaryCheckboxSelect);
        onImagePrimarySelect(storagegroupImagesPrimaryTable.rows({selected: true}));
    });

    var onStoragegroupImagePrimaryCheckboxSelect = function(e) {
        $(this).iCheck('update');
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
    }

    // ----------------------------------------------------
    // SNAPIN TAB

    // Snapin Associations
    var storagegroupSnapinUpdateBtn = $('#storagegroup-snapin-send'),
        storagegroupSnapinRemoveBtn = $('#storagegroup-snapin-remove'),
        storagegroupSnapinDeleteConfirmBtn = $('#confirmsnapinDeleteModal');

    function disableSnapinButtons(disable) {
        storagegroupSnapinUpdateBtn.prop('disabled', disable);
        storagegroupSnapinRemoveBtn.prop('disabled', disable);
    }

    function onSnapinSelect(selected) {
        var disabled = selected.count() == 0;
        disableSnapinButtons(disabled);
    }

    storagegroupSnapinUpdateBtn.on('click', function(e) {
        e.preventDefault();
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            rows = storagegroupSnapinsTable.rows({selected: true}),
            toAdd = $.getSelectedIds(storagegroupSnapinsTable),
            opts = {
                confirmadd: 1,
                additems: toAdd
            };
        $.apiCall(method,action,opts,function(err) {
            disableSnapinButtons(false);
            if (err) {
                return;
            }
            storagegroupSnapinsTable.draw(false);
            storagegroupSnapinsTable.rows({selected: true}).deselect();
        });
    });

    storagegroupSnapinRemoveBtn.on('click', function(e) {
        e.preventDefault();
        $('#snapinDelModal').modal('show');
    });

    var storagegroupSnapinsTable = $('#storagegroup-snapin-table').registerTable(onSnapinSelect, {
        order: [
            [1, 'asc'],
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
                    return '<div class="checkbox">'
                        + '<input type="checkbox" class="associated" name="associate[]" id="storagegroupSnapinAssoc_'
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

    storagegroupSnapinDeleteConfirmBtn.on('click', function(e) {
        $.deleteAssociated(storagegroupSnapinsTable, storagegroupSnapinUpdateBtn.attr('action'), function(err) {
            $('#snapinDelModal').modal('hide');
            if (err) {
                return;
            }
            storagegroupSnapinsTable.draw(false);
            storagegroupSnapinsTable.rows({selected: true}).deselect();
        });
    });

    storagegroupSnapinsTable.on('draw', function(e) {
        Common.iCheck('#storagegroup-snapin-table input');
        $('#storagegroup-snapin-table input.associated').on('ifChanged', onStoragegroupSnapinCheckboxSelect);
        onSnapinSelect(storagegroupSnapinsTable.rows({selected: true}));
        storagegroupSnapinsPrimaryTable.draw(false);
    });

    var onStoragegroupSnapinCheckboxSelect = function(e) {
        $.checkItemUpdate(storagegroupSnapinsTable, this, e, storagegroupSnapinUpdateBtn);
    }

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
            rows = storagegroupSnapinsPrimaryTable.rows({selected: true}),
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
            [1, 'asc'],
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
                    console.log(data);
                    if (data >= 1) {
                        checkval = ' checked';
                    }
                    return '<div class="checkbox">'
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
            rows = storagegroupSnapinsPrimaryTable.rows({selected: true}),
            opts = {
                confirmdelprimary: 1,
                remitems: rows.ids().toArray()
            };
        $.apiCall(method,action,opts,function(err) {
            $('#unsetSnapinPrimaryModal').modal('hide');
            if (err) {
                return;
            }
            storagegroupSnapinsTable.draw(false);
            storagegroupSnapinsPrimaryTable.draw(false);
            storagegroupSnapinsPrimaryTable.rows({selected: true}).deselect();
        });
    });

    storagegroupSnapinsPrimaryTable.on('draw', function(e) {
        Common.iCheck('#storagegroup-snapin-primary-table input');
        $('#storagegroup-snapin-primary-table input.primary').on('ifChanged', onStoragegroupSnapinPrimaryCheckboxSelect);
        onSnapinPrimarySelect(storagegroupSnapinsPrimaryTable.rows({selected: true}));
    });

    var onStoragegroupSnapinPrimaryCheckboxSelect = function(e) {
        $(this).iCheck('update');
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
    }

    // ----------------------------------------------------
    // STORAGE NODE TAB

    // Storage Node Associations
    var storagegroupStoragenodeUpdateBtn = $('#storagegroup-storagenode-send'),
        storagegroupStoragenodeRemoveBtn = $('#storagegroup-storagenode-remove'),
        storagegroupStoragenodeDeleteConfirmBtn = $('#confirmstoragenodeDeleteModal');

    function disableStoragenodeButtons(disable) {
        storagegroupStoragenodeUpdateBtn.prop('disabled', disable);
        storagegroupStoragenodeRemoveBtn.prop('disabled', disable);
    }

    function onStoragenodeSelect(selected) {
        var disabled = selected.count() == 0;
        disableStoragenodeButtons(disabled);
    }

    storagegroupStoragenodeUpdateBtn.on('click', function(e) {
        e.preventDefault();
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            rows = storagegroupStoragenodeTable.rows({selected: true}),
            toAdd = $.getSelectedIds(storagegroupStoragenodesTable),
            opts = {
                confirmadd: 1,
                additems: toAdd
            };
        $.apiCall(method,action,opts,function(err) {
            disableStoragenodeButtons(false);
            if (err) {
                return;
            }
            storagegroupStoragenodesTable.draw(false);
            storagegroupStoragenodesTable.rows({selected: true}).deselect();
            setTimeout(storagegroupStoragenodeMasterSelectorUpdate, 1000);
        });
    });

    storagegroupStoragenodeRemoveBtn.on('click', function(e) {
        e.preventDefault();
        $('#storagenodeDelModal').modal('show');
    });

    var storagegroupStoragenodesTable = $('#storagegroup-storagenode-table').registerTable(onStoragenodeSelect, {
        order: [
            [1, 'asc'],
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
                    return '<div class="checkbox">'
                        + '<input type="checkbox" class="associated" name="associate[]" id="storagegroupStoragenodeAssoc_'
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
                + '&sub=getStoragenodesList&id='
                + Common.id,
            type: 'post'
        }
    });

    storagegroupStoragenodeDeleteConfirmBtn.on('click', function(e) {
        $.deleteAssociated(storagegroupStoragenodesTable, storagegroupStoragenodeUpdateBtn.attr('action'), function(err) {
            $('#storagenodeDelModal').modal('hide');
            if (err) {
                return;
            }
            storagegroupStoragenodesTable.draw(false);
            storagegroupStoragenodesTable.rows({selected: true}).deselect();
            setTimeout(storagegroupStoragenodeMasterSelectorUpdate, 1000);
        });
    });

    storagegroupStoragenodesTable.on('draw', function(e) {
        Common.iCheck('#storagegroup-storagenode-table input');
        $('#storagegroup-storagenode-table input.associated').on('ifChanged', onStoragegroupStoragenodeCheckboxSelect);
        onStoragenodeSelect(storagegroupStoragenodesTable.rows({selected: true}));
    });

    var onStoragegroupStoragenodeCheckboxSelect = function(e) {
        $.checkItemUpdate(storagegroupStoragenodesTable, this, e, storagegroupStoragenodeUpdateBtn);
        setTimeout(storagegroupStoragenodeMasterSelectorUpdate, 1000);
    }

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
