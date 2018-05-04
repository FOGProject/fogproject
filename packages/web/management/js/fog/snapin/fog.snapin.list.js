(function($) {
    var deleteSelected = $('#deleteSelected'),
        packval = $('#snapinpack').val(),
        ACTION_VAL = -1;
        createnewBtn = $('#createnew'),
        createnewModal = $('#createnewModal'),
        createForm = $('#create-form'),
        createnewSendBtn = $('#send');

    function disableButtons(disable) {
        deleteSelected.prop('disabled', disable);
    }
    function onSelect(selected) {
        var disabled = selected.count() == 0;
        disableButtons(disabled);
    }

    disableButtons(true);
    var table = Common.registerTable($('#dataTable'), onSelect, {
        order: [
            [2, 'asc']
        ],
        columns: [
            {data: 'mainlink'},
            {data: 'protected'},
            {data: 'isEnabled'},
            {data: 'packtype'}
        ],
        rowId: 'id',
        columnDefs: [
            {
                responsivePriority: -1,
                targets: 0,
            },
            {
                render: function(data, type, row) {
                    var lock = '<span class="label label-warning"><i class="fa fa-lock fa-1x"></i></span>';
                    var unlock = '<span class="label label-danger"><i class="fa fa-unlock fa-fx"></i></span>';
                    if (row.protected > 0) {
                        return lock;
                    } else {
                        return unlock;
                    }
                },
                targets: 1
            },
            {
                render: function(data, type, row) {
                    var enabled = '<span class="label label-success"><i class="fa fa-check-circle"></i></span>';
                    var disabled = '<span class="label label-danger"><i class="fa fa-times-circle"></i></span>';
                    if (row.isEnabled > 0) {
                        return enabled;
                    } else {
                        return disabled;
                    }
                },
                targets: 2
            },
            {
                responsivePriority: 0,
                render: function(data, type, row) {
                    var enabled = '<span class="label label-success"><i class="fa fa-check-circle"></i></span>';
                    var disabled = '<span class="label label-danger"><i class="fa fa-times-circle"></i></span>';
                    if (data > 0) {
                        return enabled;
                    } else {
                        return disabled;
                    }
                },
                targets: 3
            }
        ],
        processing: true,
        serverSide: true,
        ajax: {
            url: '../management/index.php?node='+Common.node+'&sub=list',
            type: 'post'
        }
    });

    if (Common.search && Common.search.length > 0) {
        table.search(Common.search).draw();
    }

    createnewModal.registerModal(Common.createModalShow, Common.createModalHide);
    createnewBtn.on('click', function(e) {
        e.preventDefault();
        createnewModal.modal('show');
    });
    createnewSendBtn.on('click', function(e) {
        e.preventDefault();
        Common.processForm(createForm, function(err) {
            if (err) {
                return;
            }
            table.draw(false);
            createnewModal.modal('hide');
        });
    });

    // Setup the changer as a function so I'm not typing
    // the same information twice in the same file.
    var packchanger = function(packval) {
        switch (packval) {
            case '0':
                $('.packnotemplate').removeClass('hidden');
                $('.packtemplate').addClass('hidden');
                break;
            case '1':
                $('.packnotemplate').addClass('hidden');
                $('.packtemplate').removeClass('hidden');
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
    deleteSelected.on('click', function() {
        disableButtons(true);
        Common.deleteSelected(table, function(err) {
            // if we couldn't delete the items, enable the buttons
            // as the rows still exist and are selected.
            if (err) {
                disableButtons(false);
            }
        });
    });
})(jQuery);
