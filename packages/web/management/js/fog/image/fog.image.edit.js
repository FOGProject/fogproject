(function($) {
    // ---------------------------------------------------------------
    // GENERAL TAB
    var originalName = $('#image').val(),
        updateName = function(newName) {
            var e = $('#pageTitle'),
                text = e.text();
            text = text.replace(': ' + originalName, ': ' + newName);
            document.title = text;
            e.text(text);
        };

    var generalForm = $('#image-general-form'),
        generalFormBtn = $('#general-send'),
        generalDeleteBtn = $('#general-delete'),
        generalDeleteModal = $('#deleteModal'),
        generalDeleteModalConfirm = $('#confirmDeleteModal'),
        generalDeleteModalCancel = $('#closeDeleteModal');

    generalForm.on('submit',function(e) {
        e.preventDefault();
    });
    generalFormBtn.on('click', function() {
        generalFormBtn.prop('disabled', true);
        generalDeleteBtn.prop('disabled', true);
        Common.processForm(generalForm, function(err) {
            generalFormBtn.prop('disabled', false);
            generalDeleteBtn.prop('disabled', false);
            if (err)
                return;
            updateName($('#image').val());
            originalName = $('#image').val();
        });
    });
    generalDeleteBtn.on('click', function() {
        generalDeleteModal.modal('show');
    });
    $('#andFile').on('ifChecked', function() {
        opts = {
            andFile: 1
        };
    }).on('ifUnchecked', function() {
        opts = {};
    });
    generalDeleteModalConfirm.on('click', function() {
        var method = 'post',
            action = '../management/index.php?node='
                + Common.node
                + '&sub=delete&id='
                + Common.id,
            opts = {};
        Common.apiCall(method, action, opts, function(err) {
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

    // ---------------------------------------------------------------
    // STORAGEGROUPS TAB
    var storagegroupsAddBtn = $('#storagegroups-add'),
        storagegroupsRemoveBtn = $('#storagegroups-remove'),
        storagegroupsPrimaryBtn = $('#storagegroups-primary'),
        PRIMARY_GROUP_ID = -1;
    storagegroupsAddBtn.prop('disabled', true);
    storagegroupsRemoveBtn.prop('disabled', true);
    function onStoragegroupsSelect(selected) {
        var disabled = selected.count() == 0;
        storagegroupsAddBtn.prop('disabled', disabled);
        storagegroupsRemoveBtn.prop('disabled', disabled);
    }
    var storagegroupsTable = Common.registerTable($('#image-storagegroups-table'), onStoragegroupsSelect, {
        columns: [
            {data: 'name'},
            {data: 'primary'},
            {data: 'association'}
        ],
        rowId: 'id',
        columnDefs: [
            {
                responsivePriority: -1,
                render: function(data, type, row) {
                    return '<a href="../management/index.php?node=storagegroup&sub=edit&id='
                        + row.id
                        + '">'
                        + data
                        + '</a>';
                },
                targets: 0
            },
            {
                responsivePriority: 20000,
                render: function(data, type, row) {
                    var checkval = '';
                    if (row.primary > 0 && row.origID == Common.id) {
                        checkval = ' checked';
                    }
                    return '<div class="radio">'
                        + '<input belongsto="isPrimaryGroup'
                        + row.origID
                        + '" type="radio" class="primary" name="primary" id="group_'
                        + row.id
                        + '" value="'
                        + row.id
                        + '"'
                        + ' wasoriginalprimary="'
                        + checkval
                        + '" '
                        + checkval
                        + (row.origID != Common.id ? ' disabled' : '')
                        + '/>'
                        + '</div>';
                },
                targets: 1
            },
            {
                render: function(data, type, row) {
                    var checkval = '';
                    if (row.association === 'associated') {
                        checkval = ' checked';
                    }
                    return '<div class="checkbox">'
                        + '<input type="checkbox" class="associated" name="associate[]" id="storagegroupsAssoc_'
                        + row.id
                        + '" value="'
                        + row.id
                        + '"'
                        + checkval
                        + '/>'
                        + '</div>';
                },
                targets: 2
            }
        ],
        processing: true,
        ajax: {
            url: '../management/index.php?node='+Common.node+'&sub=getStoragegroupsList&id='+Common.id,
            type: 'post'
        }
    });
    storagegroupsTable.on('draw', function() {
        Common.iCheck('#image-storagegroups-table input');
        $('#image-storagegroups-table input.primary').on('ifClicked', onRadioSelect);
        $('#image-storagegroups-table input.associated').on('ifClicked', onCheckboxSelect);
    });
    var onRadioSelect = function(event) {
        var id = parseInt($(this).attr('value'));
        if ($(this).attr('belongsto') === 'isPrimaryGroup'+Common.id) {
            if (PRIMARY_GROUP_ID === -1 && $(this).attr('wasoriginalprimary') === ' checked') {
                PRIMARY_GROUP_ID = id;
            }
            if (id === PRIMARY_GROUP_ID) {
                PRIMARY_GROUP_ID = id;
            } else {
                PRIMARY_GROUP_ID = id;
            }
            storagegroupsPrimaryBtn.prop('disabled', false);
        }
    };
    var onCheckboxSelect = function(event) {
    };
    // Setup primary group watcher
    $('.primary').on('ifClicked', onRadioSelect);
    $('.associated').on('ifClicked', onCheckboxSelect);
    storagegroupsPrimaryBtn.on('click', function() {
        storagegroupsAddBtn.prop('disabled', true);
        storagegroupsRemoveBtn.prop('disabled', true);
        storagegroupsPrimaryBtn.prop('disabled', true);
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            opts = {
                'primarysel': '1',
                'primary': PRIMARY_GROUP_ID
            };
        Common.apiCall(method,action,opts,function(err) {
            storagegroupsPrimaryBtn.prop('disabled', !err);
            onStoragegroupsSelect(storagegroupsTable.rows({selected: true}));
            $('.primary[value='+PRIMARY_GROUP_ID+']').iCheck('check');
        });
    });
    storagegroupsAddBtn.on('click', function() {
        storagegroupsAddBtn.prop('disabled', true);
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            rows = storagegroupsTable.rows({selected: true}),
            toAdd = Common.getSelectedIds(storagegroupsTable),
            opts = {
                'updatestoragegroups': '1',
                'storagegroups': toAdd
            };
        Common.apiCall(method,action,opts,function(err) {
            if (!err) {
                storagegroupsTable.draw(false);
                storagegroupsTable.rows({selected: true}).deselect();
                // Unset the primary radio from disabled.
                $('#image-storagegroups-table').find('.primary').each(function() {
                    if ($.inArray($(this).val(), toAdd) != -1) {
                        $(this).prop('disabled', false);
                        Common.iCheck(this);
                    }
                });
                // Check the associated checkbox.
                $('#image-storagegroups-table').find('.associated').each(function() {
                    if ($.inArray($(this).val(), toAdd) != -1) {
                        $(this).iCheck('check');
                    }
                });
            } else {
                storagegroupsAddBtn.prop('disabled', false);
            }
        });
    });
    storagegroupsRemoveBtn.on('click', function() {
        storagegroupsAddBtn.prop('disabled', true);
        $(this).prop('disabled', true);
        $('#storagegroupDelModal').modal('show');
    });
    $('#confirmstoragegroupDeleteModal').on('click', function(e) {
        Common.deleteAssociated(storagegroupsTable, storagegroupsRemoveBtn.attr('action'), function(err) {
            if (err) {
                return;
            }
            $('#storagegroupDelModal').modal('hide');
        });
    });

    if (Common.search && Common.search.length > 0) {
        storagegroupsTable.search(Common.search).draw();
    }
    // ---------------------------------------------------------------
    // HOST TAB
    var hostAddBtn = $('#host-add'),
        hostRemoveBtn = $('#host-remove');
    hostAddBtn.prop('disabled', true);
    hostRemoveBtn.prop('disabled', true);
    function onHostSelect(selected) {
        var disabled = selected.count() == 0;
        hostAddBtn.prop('disabled', disabled);
        hostRemoveBtn.prop('disabled', disabled);
    }
    var hostTable = Common.registerTable($('#image-host-table'), onHostSelect, {
        columns: [
            {data: 'name'},
            {data: 'association'}
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
                        + '<input type="checkbox" class="associated" name="associate[]" id="memberAssoc_'
                        + row.id
                        + '" value="'
                        + row.id
                        + '"'
                        + checkval
                        + '/>'
                        + '</div>';
                },
                targets: 1
            }
        ],
        processing: true,
        ajax: {
            url: '../management/index.php?node='
                + Common.node
                + '&sub=getHostsList&id='
                + Common.id,
            type: 'post'
        }
    });
    hostTable.on('draw', function() {
        Common.iCheck('#image-host-table input');
    });
    hostAddBtn.on('click', function() {
        hostAddBtn.prop('disabled', true);
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            rows = hostTable.rows({selected: true}),
            toAdd = Common.getSelectedIds(hostTable),
            opts = {
                updatehost: '1',
                host: toAdd
            };
        Common.apiCall(method,action,opts,function(err) {
            if (!err) {
                hostTable.draw(false);
                hostTable.rows({selected: true}).deselect();
                $('#image-host-table').find('.associated').each(function() {
                    if ($.inArray($(this).val(), toAdd) != -1) {
                        $(this).iCheck('check');
                    }
                });
            } else {
                hostAddBtn.prop('disabled', false);
            }
        });
    });
    hostRemoveBtn.on('click', function() {
        hostAddBtn.prop('disabled', true);
        hostRemoveBtn.prop('disabled', true);
        $('#hostDelModal').modal('show');
    });
    $('#confirmhostDeleteModal').on('click', function(e) {
        Common.deleteAssociated(hostTable, hostRemoveBtn.attr('action'), function(err) {
            hostAddBtn.prop('disabled', false);
            hostRemoveBtn.prop('disabled', false);
            if (err) {
                return;
            }
            $('#hostDelModal').modal('hide');
        });
    });

    if (Common.search && Common.search.length > 0) {
        hostTable.search(Common.search).draw();
    }
    $('.slider').slider();
})(jQuery);
