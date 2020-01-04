(function($) {
    // ---------------------------------------------------------------
    // GENERAL TAB
    var originalName = $('#snapin').val(),
        updateName = function(newName) {
            var e = $('#pageTitle'),
                text = e.text();
            text = text.replace(': ' + originalName, ': ' + newName);
            document.title = text;
            e.text(text);
        },
        generalForm = $('#snapin-general-form'),
        generalFormBtn = $('#general-send'),
        generalDeleteBtn = $('#general-delete'),
        generalDeleteModal = $('#deleteModal'),
        generalDeleteModalConfirm = $('#confirmDeleteModal'),
        generalDeleteModalCancel = $('#closeDeleteModal'),
        packval = $('#snapinpack').val(),
        ACTION_VAL = -1,
        // Setup the changer as a function so I'm not typing
        // the same information twice in the same file.
        packchanger = function(packval) {
            switch (packval) {
                case '0':
                    $('.packnotemplate').removeClass('hidden');
                    $('.packtemplate').addClass('hidden');
                    $('.packhide').addClass('hidden');
                    break;
                case '1':
                    $('.packnotemplate').addClass('hidden');
                    $('.packtemplate').removeClass('hidden');
                    $('.packhide').removeClass('hidden');
                    break;
            }
        },
        // Allow radio to change properly but also be unset as maybe
        // the user doesn't want an action to occur after the snapin
        // completes.
        onRadioSelect = function(event) {
            var action = $(this).val();
            if (ACTION_VAL === -1) {
                ACTION_VAL = action;
            }
            if (action === ACTION_VAL) {
                $(this).iCheck('uncheck');
                ACTION_VAL = 0;
            } else {
                ACTION_VAL = action;
            }
        },
        updateCmdStore = function() {
            if (typeof $('.cmdlet3').val() === 'undefined') {
                return;
            }
            var cmd1 = $('.cmdlet1').val(),
                cmd2 = $('.cmdlet2').val(),
                cmd3 = $('.cmdlet3').val(),
                cmd4 = $('.cmdlet4').val(),
                test = $('[type="file"]');
            if (test.length < 1) {
                cmd3 = $('select.cmdlet3').val();
            } else {
                test = test[0].files.length;
                if (test < 1) {
                    cmd3 = $('select.cmdlet3').val();
                } else {
                    cmd3 = $('[type="file"]')[0].files[0].name;
                }
                if ($('#snapinpack').val() == 1) {
                    cmd = '';
                }
            }
            var snapCMD = [cmd1,cmd2,cmd3,cmd4];
            $('.snapincmd').val(snapCMD.join(' '));
        };

    generalForm.on('submit',function(e) {
        e.preventDefault();
    });
    generalFormBtn.on('click', function() {
        generalFormBtn.prop('disabled', true);
        generalDeleteBtn.prop('disabled', true);
        generalForm.processForm(function(err) {
            generalFormBtn.prop('disabled', false);
            generalDeleteBtn.prop('disabled', false);
            if (err)
                return;
            updateName($('#snapin').val());
            originalName = $('#snapin').val();
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
        $.apiCall(method, action, opts, function(err) {
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

    // Should we delete the file itself?
    $('#andFile').on('ifChecked', function() {
        opts = {
            andFile: 1
        };
    }).on('ifUnchecked', function() {
        opts = {};
    });

    // Make sure selectors are select2 friendly
    packchanger(packval);
    // Make the change when the snapin pack selector changes.
    $('#snapinpack').on('change', function() {
        packchanger($(this).val());
    });
    $('#argTypes').on('change', function() {
        var option = $('option:selected', this),
            value = option.attr('value'),
            rwarg = option.attr('rwargs'),
            args = option.attr('args'),
            rwinp = $('input[name=rw]'),
            rwainp = $('input[name=rwa]'),
            argsinp = $('input[name=args]');
        if (value) {
            rwinp.val(value);
        }
        rwainp.val(rwarg);
        argsinp.val(args);
        updateCmdStore();
    });
    $('#packTypes').on('change', function() {
        var option = $('option:selected', this),
            file = option.attr('file'),
            args = option.attr('args'),
            rwinp = $('input[name=rw]'),
            rwainp = $('input[name=rwa]');
        rwinp.val(file);
        rwainp.val(args);
    });

    // Setup action radio selector
    $('.snapin-action').on('ifClicked', onRadioSelect);

    updateCmdStore();
    $('.cmdlet1,.cmdlet2,.cmdlet3,.cmdlet4').on('change keyup', function(e) {
        e.preventDefault();
        updateCmdStore();
    });
    $('.cmdlet3').on('change blur', function(e) {
        updateCmdStore();
    });
    $('#snapinfileexist').select2({width: '100%'});
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
            rows = snapinHostsTable.rows({selected: true}),
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
                    return '<div class="checkbox">'
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
        $('#snapin-host-table input.associated').on('ifChanged', onSnapinHostCheckboxSelect);
        onHostSelect(snapinHostsTable.rows({selected: true}));
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
            rows = snapinStoragegroupsTable.rows({selected: true}),
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
            setTimeout(snapinStoragegroupsPrimarySelectorUpdate, 1000);
        });
    });

    snapinStoragegroupRemoveBtn.on('click', function(e) {
        e.preventDefault();
        $('#storagegroupDelModal').modal('show');
    });

    var snapinStoragegroupsTable = $('#snapin-storagegroup-table').registerTable(onStoragegroupSelect, {
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
        $('#snapin-storagegroup-table input.associated').on('ifChanged', onSnapinStoragegroupCheckboxSelect);
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
