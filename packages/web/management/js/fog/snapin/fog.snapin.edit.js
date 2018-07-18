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
        };

    var updateName = function(newName) {
        var e = $('#pageTitle'),
            text = e.text();
        text = text.replace(': ' + originalName, ': ' + newName);
        document.title = text;
        e.text(text);
    };

    var generalForm = $('#snapin-general-form'),
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
    var storagegroupsTable = $('#snapin-storagegroups-table').registerTable(onStoragegroupsSelect, {
        order: [
            [2, 'asc'],
            [0, 'asc']
        ],
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
        Common.iCheck('#snapin-storagegroups-table input');
        $('#snapin-storagegroups-table input.primary').on('ifClicked', onRadioSelectSG);
        $('#snapin-storagegroups-table input.associated').on('ifClicked', onCheckboxSelect);
    });
    var onRadioSelectSG = function(event) {
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
    $('.primary').on('ifClicked', onRadioSelectSG);
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
        $.apiCall(method,action,opts,function(err) {
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
            toAdd = $.getSelectedIds(storagegroupsTable),
            opts = {
                'updatestoragegroups': '1',
                'storagegroups': toAdd
            };
        $.apiCall(method,action,opts,function(err) {
            if (!err) {
                storagegroupsTable.draw(false);
                storagegroupsTable.rows({selected: true}).deselect();
                // Unset the primary radio from disabled.
                $('#snapin-storagegroups-table').find('.primary').each(function() {
                    if ($.inArray($(this).val(), toAdd) != -1) {
                        $(this).prop('disabled', false);
                        Common.iCheck(this);
                    }
                });
                // Check the associated checkbox.
                $('#snapin-storagegroups-table').find('.associated').each(function() {
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
        $('#storagegroupDelModal').modal('show');
    });
    $('#confirmstoragegroupDeleteModal').on('click', function(e) {
        $.deleteAssociated(storagegroupsTable, storagegroupsRemoveBtn.attr('action'), function(err) {
            if (err) {
                return;
            }
            $('#storagegroupDelModal').modal('hide');
            storagegroupsTable.draw(false);
            storagegroupsTable.rows({selected: true}).deselect();
        });
    });
    if (Common.search && Common.search.length > 0) {
        storagegroupsTable.search(Common.search).draw();
    }
    // ---------------------------------------------------------------
    // MEMBERSHIP TAB
    var membershipAddBtn = $('#membership-add'),
        membershipRemoveBtn = $('#membership-remove');
    membershipAddBtn.prop('disabled', true);
    membershipRemoveBtn.prop('disabled', true);
    function onMembershipSelect(selected) {
        var disabled = selected.count() == 0;
        membershipAddBtn.prop('disabled', disabled);
        membershipRemoveBtn.prop('disabled', disabled);
    }
    var membershipTable = $('#snapin-membership-table').registerTable(onMembershipSelect, {
        order: [
            [1, 'asc'],
            [0, 'asc']
        ],
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
            url: '../management/index.php?node='+Common.node+'&sub=getHostsList&id='+Common.id,
            type: 'post'
        }
    });
    membershipTable.on('draw', function() {
        Common.iCheck('#snapin-membership-table input');
        $('#snapin-membership-table input.associated').on('ifClicked', onCheckboxSelect);
    });
    membershipAddBtn.on('click', function() {
        membershipAddBtn.prop('disabled', true);
        membershipRemoveBtn.prop('disabled', true);
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            rows = membershipTable.rows({selected: true}),
            toAdd = $.getSelectedIds(membershipTable),
            opts = {
                'updatemembership': '1',
                'membership': toAdd
            };
        $.apiCall(method,action,opts,function(err) {
            membershipAddBtn.prop('disabled', false);
            membershipRemoveBtn.prop('disabled', false);
            if (err) {
                return;
            }
            $('#snapin-membership-table').find('.associated').each(function() {
                if ($.inArray($(this).val(), toAdd) != -1) {
                    $(this).iCheck('check');
                }
            });
            membershipTable.draw(false);
            membershipTable.rows({selected: true}).deselect();
        });
    });
    membershipRemoveBtn.on('click', function() {
        $('#hostDelModal').modal('show');
    });
    $('#confirmhostDeleteModal').on('click', function(e) {
        $.deleteAssociated(membershipTable, membershipRemoveBtn.attr('action'), function(err) {
            if (err) {
                return;
            }
            $('#hostDelModal').modal('hide');
            membershipTable.draw(false);
            membershipTable.rows({selected: true}).deselect();
        });
    });
    if (Common.search && Common.search.length > 0) {
        membershipTable.search(Common.search).draw();
    }
    var packval = $('#snapinpack').val(),
        ACTION_VAL = -1;
    // Setup the changer as a function so I'm not typing
    // the same information twice in the same file.
    var packchanger = function(packval) {
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
    };
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

    // Allow radio to change properly but also be unset as maybe
    // the user doesn't want an action to occur after the snapin
    // completes.
    var onRadioSelect = function(event) {
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
    };
    // Setup action radio selector
    $('.snapin-action').on('ifClicked', onRadioSelect);
    var updateCmdStore = function() {
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

    updateCmdStore();
    $('.cmdlet1,.cmdlet2,.cmdlet3,.cmdlet4').on('change keyup', function(e) {
        e.preventDefault();
        updateCmdStore();
    });
    $('.cmdlet3').on('change blur', function(e) {
        updateCmdStore();
    });
    $('#snapinfileexist').select2({width: '100%'});
})(jQuery);
