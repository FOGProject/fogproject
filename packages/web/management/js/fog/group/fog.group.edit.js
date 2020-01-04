(function($) {
    // ---------------------------------------------------------------
    // GROUP NAME UPDATE
    var originalName = $('#group').val(),
        updateName = function(newName) {
            var e = $('#pageTitle'),
                text = e.text();
            text = text.replace(': ' + originalName, ': ' + newName);
            document.title = text;
            e.text(text);
        },
        generalForm = $('#group-general-form'),
        generalFormBtn = $('#general-send'),
        generalDeleteBtn = $('#general-delete'),
        generalDeleteModal = $('#deleteModal'),
        generalDeleteModalConfirm = $('#confirmDeleteModal'),
        generalDeleteModalCancel = $('#closeDeleteModal'),
        resetEncryptionBtn = $('#reset-encryption-data'),
        resetEncryptionModal = $('#resetencryptionmodal'),
        resetEncryptionCancelBtn = $('#resetencryptionCancel'),
        resetEncryptionConfirmBtn = $('#resetencryptionConfirm');

    // Mask for product key.
    $('#key').inputmask({mask: Common.masks.productKey});

    // ---------------------------------------------------------------
    // GENERAL TAB
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
            updateName($('#group').val());
            originalName = $('#group').val();
        });
    });
    generalDeleteBtn.on('click', function() {
        generalDeleteModal.modal('show');
    });
    $('#andHosts').on('ifChecked', function() {
        opts = {
            andHosts: 1
        };
    }).on('ifUnchecked', function() {
        opts = {};
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

    // Reset encryption confirmation modal.
    resetEncryptionBtn.on('click', function(e) {
        e.preventDefault();
        resetEncryptionModal.modal('show');
    });

    // Modal Confirmed
    resetEncryptionConfirmBtn.on('click', function(e) {
        e.preventDefault();
        // Reset our encryption data.
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            opts = {
                groupid: Common.id
            };
        $.apiCall(method, action, opts, function(err) {
            generalFormBtn.prop('disabled', false);
            generalDeleteBtn.prop('disabled', false);
            resetEncryptionBtn.prop('disabled', false);
            if (err) {
                return;
            }
            resetEncryptionModal.modal('hide');
        });
    });

    // Group Image
    var groupImageUpdateBtn = $('#group-image-send');

    function disableImageButtons(disable) {
        groupImageUpdateBtn.prop('disabled', disable);
    }

    groupImageUpdateBtn.on('click', function(e) {
        console.log('here');
        e.preventDefault();
        disableImageButtons(true);
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            image = $('#image option:selected').val(),
            opts = {
                confirmimage: 1,
                image: image
            };
        $.apiCall(method,action,opts,function(err) {
            disableImageButtons(false);
            if (err) {
                return;
            }
        });
    });

    // ---------------------------------------------------------------
    // TASKS TAB
    var taskItem = $('.taskitem'),
        taskModal = $('#task-modal');
    taskItem.on('click', function(e) {
        e.preventDefault();
        var taskName = $(this).text();
        var method = $(this).attr('href');

        // Show Modal loading
        $('.task-name').text('Loading...');
        $('#task-form-holder').html("Loading, please wait...");
        $('#task-modal .modal-dialog').setLoading(true);
        taskModal.modal('show'); // NOTE: If you remove modal loading UI, you will need to put this after the HTML is added.
        // END: Show modal loading

        // Interrupt AJAX if modal closed
        var req;
        taskModal.on('hidden.bs.modal', function() {
            if(req != null){
                req.abort();
            }
        });
        // END: Interrupt AJAX if modal closed

        Pace.track(function() {
            req = $.ajax({
                type: 'get',
                url: method,
                dataType: 'json',
                success: function(data, textStatus, jqXHR) {
                    $('#task-form-holder').html($.parseHTML(data.msg));

                    // Hide modal loading
                    req = null;
                    $('#task-modal .modal-dialog').setLoading(false);
                    $('.task-name').text(taskName);
                    // END: Hide modal loading

                    var scheduleType = $('input[name="scheduleType"]'),
                        groupDeployForm = '#group-deploy-form',
                        minutes = $('#cronMin', $(groupDeployForm)),
                        hours = $('#cronHour', $(groupDeployForm)),
                        dom = $('#cronDom', $(groupDeployForm)),
                        month = $('#cronMonth', $(groupDeployForm)),
                        dow = $('#cronDow', $(groupDeployForm)),
                        createTaskBtn = $('#tasking-send');
                    Common.iCheck('#task-form-holder input');

                    $('#checkdebug').on('ifChecked', function(e) {
                        e.preventDefault();
                        $('.hideFromDebug,.delayedinput,.croninput').addClass('hidden');
                        $('.instant').iCheck('check');
                    }).on('ifUnchecked', function(e) {
                        e.preventDefault();
                        $('.hideFromDebug').removeClass('hidden');
                    });
                    $('input[name="scheduleType"]').on('ifClicked', function(e) {
                        e.preventDefault();
                        switch (this.value) {
                            case 'instant':
                                $('.delayedinput,.croninput').addClass('hidden');
                                break;
                            case 'single':
                                $('.delayedinput').removeClass('hidden');
                                $('.croninput').addClass('hidden');
                                $('#delayedinput').datetimepicker('show');
                                break;
                            case 'cron':
                                $('.delayedinput').addClass('hidden');
                                $('.croninput').removeClass('hidden');
                                break;
                        }
                    });
                    $('#tasking-send').on('click', function(e) {
                        e.stopImmediatePropagation();
                        $(groupDeployForm).processForm(function(err) {
                            if (err) {
                                return;
                            }
                            taskModal.modal('hide');
                        });
                    });
                    taskModal.on('hide.bs.modal', function(e) {
                        $(groupDeployForm).remove();
                        $('#task-form-holder').empty();
                    });
                    $('#delayedinput').datetimepicker({format: 'YYYY-MM-DD HH:mm:ss'});
                    $('.fogcron').cron({
                        initial: '* * * * *',
                        onChange: function() {
                            vals = $(this).cron('value').split(' ');
                            minutes.val(vals[0]);
                            hours.val(vals[1]);
                            dom.val(vals[2]);
                            month.val(vals[3]);
                            dow.val(vals[4]);
                        }
                    });
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    if(textStatus == 'abort') return; // Do not show error message on abort.
                    $.notifyFromAPI(jqXHR.responseJSON, jqXHR);
                }
            });
        });
    });

    // FOG CLIENT AREA
    // ---------------------------------------------------------------
    // ACTIVE DIRECTORY TAB
    var ADForm = $('#active-directory-form'),
        ADFormBtn = $('#ad-send'),
        ADClearBtn = $('#ad-clear'),
        ADJoinDomain = $('#adEnabled');

    ADJoinDomain.on('ifChanged', function(e) {
        e.preventDefault();
        $(this).iCheck('update');
        if (!this.checked) {
            return;
        }
        var indomain = $('#adDomain'),
            inou = $('#adOU'),
            inuser = $('#adUsername'),
            inpass = $('#adPassword');
        if (indomain.val() && inou.val() && inuser.val() && inpass.val()) {
            return;
        }
        Pace.ignore(function() {
            $.get('../management/index.php?sub=adInfo', function(data) {
                if (!indomain.val()) {
                    indomain.val(data.domainname);
                }
                if (!inou.val()) {
                    inou.val(data.ou)
                }
                if (!inuser.val()) {
                    inuser.val(data.domainuser);
                }
                if (!inpass.val()) {
                    inpass.val(data.domainpass);
                }
            }, 'json');
        });
    });

    ADForm.on('submit',function(e) {
        e.preventDefault();
    });
    ADFormBtn.on('click',function() {
        ADFormBtn.prop('disabled', true);
        ADClearBtn.prop('disabled', true);
        ADForm.processForm(function(err) {
            ADFormBtn.prop('disabled', false);
            ADClearBtn.prop('disabled', false);
        });
    });
    ADClearBtn.on('click',function() {
        ADClearBtn.prop('disabled', true);
        ADFormBtn.prop('disabled', true);

        var restoreMap = [];
        ADForm.find('input[type="text"], input[type="password"], textarea').each(function(i, e) {
            restoreMap.push({checkbox: false, e: e, val: $(e).val()});
            $(e).val('');
            $(e).prop('disabled', true);
        });
        ADForm.find('input[type=checkbox]').each(function(i, e) {
            restoreMap.push({checkbox: true, e: e, val: $(e).iCheck('update')[0].checked});
            $(e).iCheck('uncheck');
            $(e).iCheck('disable');
        });

        ADForm.find('input[type=text], input[type=password], textarea').val('');
        ADForm.find('input[type=checkbox]').iCheck('uncheck');

        ADForm.processForm(function(err) {
            ADClearBtn.prop('disabled', false);
            ADFormBtn.prop('disabled', false);
            for (var i = 0; i < restoreMap.length; i++) {
                field = restoreMap[i];
                if (field.checkbox) {
                    if (err) {
                        $(field.e).iCheck((field.val ? 'check' : 'uncheck'));
                    }
                    $(field.e).iCheck('enable');
                } else {
                    if (err) {
                        $(field.e).val(field.val);
                    }
                    $(field.e).prop('disabled', false);
                }
            }
        });
    });

    // ASSOCIATIONS
    // ---------------------------------------------------------------
    // HOST ASSOCIATION TAB
    var groupHostUpdateBtn = $('#group-host-send'),
        groupHostRemoveBtn = $('#group-host-remove'),
        groupHostDeleteConfirmBtn = $('#confirmhostDeleteModal');

    function disableHostButtons(disable) {
        groupHostUpdateBtn.prop('disabled', disable);
        groupHostRemoveBtn.prop('disabled', disable);
    }

    function onHostSelect(selected) {
        var disabled = selected.count() == 0;
        disableHostButtons(disabled);
    }

    groupHostUpdateBtn.on('click', function(e) {
        e.preventDefault();
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            rows = groupHostsTable.rows({selected: true}),
            toAdd = $.getSelectedIds(groupHostsTable),
            opts = {
                confirmadd: 1,
                additems: toAdd
            };
        $.apiCall(method,action,opts,function(err) {
            disableHostButtons(false);
            if (err) {
                return;
            }
            groupHostsTable.draw(false);
            groupHostsTable.rows({selected: true}).deselect();
        });
    });

    groupHostRemoveBtn.on('click', function(e) {
        e.preventDefault();
        $('#hostDelModal').modal('show');
    });

    var groupHostsTable = $('#group-host-table').registerTable(onHostSelect, {
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
                        + '<input type="checkbox" class="associated" name="associate[]" id="groupHostAssoc_'
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

    groupHostDeleteConfirmBtn.on('click', function(e) {
        $.deleteAssociated(groupHostsTable, groupHostUpdateBtn.attr('action'), function(err) {
            $('#hostDelModal').modal('hide');
            if (err) {
                return;
            }
            groupHostsTable.draw(false);
            groupHostsTable.rows({selected: true}).deselect();
        });
    });

    groupHostsTable.on('draw', function() {
        Common.iCheck('#group-host-table input');
        $('#group-host-table input.associated').on('ifChanged', onGroupHostCheckboxSelect);
        onHostSelect(groupHostsTable.rows({selected: true}));
    })

    var onGroupHostCheckboxSelect = function(e) {
        $.checkItemUpdate(groupHostsTable, this, e, groupHostUpdateBtn);
    };

    // ---------------------------------------------------------------
    // PRINTER TAB
    //
    // Association Area
    var groupPrinterUpdateBtn = $('#group-printer-send'),
        groupPrinterRemoveBtn = $('#group-printer-remove'),
        groupPrinterDeleteConfirmBtn = $('#confirmprinterDeleteModal');

    function disablePrinterButtons(disable) {
        groupPrinterUpdateBtn.prop('disabled', disable);
        groupPrinterRemoveBtn.prop('disabled', disable);
    }

    function onPrinterSelect(selected) {
        var disabled = selected.count() == 0;
        disablePrinterButtons(disabled);
    }

    groupPrinterUpdateBtn.on('click', function(e) {
        e.preventDefault();
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            rows = groupPrintersTable.rows({selected: true}),
            toAdd = $.getSelectedIds(groupPrintersTable),
            opts = {
                confirmadd: 1,
                additems: toAdd
            };
        $.apiCall(method,action,opts,function(err) {
            disablePrinterButtons(false);
            if (err) {
                return;
            }
            setTimeout(groupPrinterDefaultSelectorUpdate, 1000);
        });
    });

    groupPrinterRemoveBtn.on('click', function(e) {
        e.preventDefault();
        $('#printerDelModal').modal('show');
    });

    var groupPrintersTable = $('#group-printer-table').registerTable(onPrinterSelect, {
        order: [
            [0, 'asc']
        ],
        columns: [
            {data: 'mainlink'}
        ],
        rowId: 'id',
        processing: true,
        serverSide: true,
        ajax: {
            url: '../management/index.php?node='
                + Common.node
                + '&sub=getPrintersList&id='
                + Common.id,
            type: 'post'
        }
    });

    groupPrinterDeleteConfirmBtn.on('click', function(e) {
        $.deleteAssociated(groupPrintersTable, groupPrinterUpdateBtn.attr('action'), function(err) {
            $('#printerDelModal').modal('hide');
            if (err) {
                return;
            }
            setTimeout(groupPrinterDefaultSelectorUpdate, 1000);
        });
    });

    groupPrintersTable.on('draw', function() {
        onPrinterSelect(groupPrintersTable.rows({selected: true}));
        groupPrinterDefaultSelectorUpdate();
    });

    // Default area
    var groupPrinterDefaultUpdateBtn = $('#group-printer-default-send'),
        groupPrinterDefaultSelector = $('#printerselector'),
        groupPrinterDefaultSelectorUpdate = function() {
            var url = '../management/index.php?node='
                + Common.node
                + '&sub=getPrintersSelect&printerID='
                + $('#printer option:selected').val();
            Pace.ignore(function() {
                groupPrinterDefaultSelector.html('');
                $.get(url, function(data) {
                    groupPrinterDefaultSelector.html(data.content);
                    groupPrinterDefaultUpdateBtn.prop('disabled', data.disablebtn);
                }, 'json');
            });
        };

    function disablePrinterDefaultButtons(disable) {
        groupPrinterDefaultUpdateBtn.prop('disabled', disable);
    }

    groupPrinterDefaultSelectorUpdate();

    groupPrinterDefaultUpdateBtn.on('click', function(e) {
        e.preventDefault();
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            opts = {
                confirmdefault: 1,
                default: $('#printer option:selected').val()
            };
        $.apiCall(method,action,opts,function(err) {
            disablePrinterDefaultButtons(false);
            if (err) {
                return;
            }
        });
    });

    // Config area
    var groupPrinterConfigBtn = $('#printer-config-send');

    groupPrinterConfigBtn.on('click', function(e) {
        e.preventDefault();
        groupPrinterConfigBtn.prop('disabled', true);
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            opts = {
                confirmlevelup: 1,
                level: $('.checked input[name="level"]').val()
            };
        $.apiCall(method,action,opts,function(err) {
            groupPrinterConfigBtn.prop('disabled', false);
            if (err) {
                return;
            }
        });
    });

    // ---------------------------------------------------------------
    // SNAPINS TAB
    var groupSnapinUpdateBtn = $('#group-snapin-send'),
        groupSnapinRemoveBtn = $('#group-snapin-remove'),
        groupSnapinDeleteConfirmBtn = $('#confirmsnapinDeleteModal');

    function disableSnapinButtons(disable) {
        groupSnapinUpdateBtn.prop('disabled', disable);
        groupSnapinRemoveBtn.prop('disabled', disable);
    }

    function onSnapinSelect(selected) {
        var disabled = selected.count() == 0;
        disableSnapinButtons(disabled);
    }

    groupSnapinUpdateBtn.on('click', function(e) {
        e.preventDefault();
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            rows = groupSnapinsTable.rows({selected: true}),
            toAdd = $.getSelectedIds(groupSnapinsTable),
            opts = {
                confirmadd: 1,
                additems: toAdd
            };
        $.apiCall(method,action,opts,function(err) {
            disableSnapinButtons(false);
            if (err) {
                return;
            }
            groupSnapinsTable.draw(false);
            groupSnapinsTable.rows({selected: true}).deselect();
        });
    });

    groupSnapinRemoveBtn.on('click', function(e) {
        e.preventDefault();
        $('#snapinDelModal').modal('show');
    });

    var groupSnapinsTable = $('#group-snapin-table').registerTable(onSnapinSelect, {
        order: [
            [0, 'asc']
        ],
        columns: [
            {data: 'mainlink'}
        ],
        rowId: 'id',
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

    groupSnapinDeleteConfirmBtn.on('click', function(e) {
        $.deleteAssociated(groupSnapinsTable, groupSnapinUpdateBtn.attr('action'), function(err) {
            $('#snapinDelModal').modal('hide');
            if (err) {
                return;
            }
            groupSnapinsTable.draw(false);
            groupSnapinsTable.rows({selected: true}).deselect();
        });
    });

    groupSnapinsTable.on('draw', function() {
        onSnapinSelect(groupSnapinsTable.rows({selected: true}));
    });

    // ---------------------------------------------------------------
    // SERVICE TAB
    var modulesEnableBtn = $('#modules-enable'),
        modulesDisableBtn = $('#modules-disable'),
        modulesUpdateBtn = $('#modules-update'),
        modulesDispBtn = $('#displayman-send'),
        modulesAloBtn = $('#alo-send');

    function onModulesDisable(selected) {
        var disabled = selected.count() == 0;
        modulesDisableBtn.prop('disabled', disabled);
    }
    function onModulesEnable(selected) {
        var disabled = selected.count() != 0;
        modulesEnableBtn.prop('disabled', disabled);
    }

    var modulesTable = $('#modules-to-update').registerTable(onModulesEnable, {
        columns: [
            {data: 'name'},
            {data: 'association'}
        ],
        rowId: 'id',
        columnDefs: [
            {
                responsivePriority: -1,
                render: function(data, type, row) {
                    return row.name
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
                        + '<input type="checkbox" class="associated" name="associate[]" id="moduleAssoc_'
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
            + '&sub=getModulesList&id='
            + Common.id,
            type: 'post'
        }
    });
    modulesTable.on('draw', function() {
        Common.iCheck('#modules-to-update input');
    });

    modulesUpdateBtn.on('click', function(e) {
        e.preventDefault();
        $(this).prop('disabled', true);
        var method = modulesUpdateBtn.attr('method'),
            action = modulesUpdateBtn.attr('action'),
            toEnable = [],
            toDisable = [],
            opts = {
                enablemodulessel: 1,
                disablemodulessel: 1,
                enablemodules: toEnable,
                disablemodules: toDisable
            };
        $('#modules-to-update').find('.associated').each(function() {
            if ($(this).is(':checked')) {
                toEnable.push($(this).val());
            } else if (!$(this).is(':checked')) {
                toDisable.push($(this).val());
            }
        });
        $.apiCall(method,action,opts,function(err) {
            modulesUpdateBtn.prop('disabled', false);
            if (!err) {
                modulesTable.draw(false);
                modulesTable.rows({selected: true}).deselect();
            }
        });
    });
    modulesEnableBtn.on('click', function(e) {
        e.preventDefault();
        $('#modules-to-update_wrapper .buttons-select-all').trigger('click');
        $('#modules-to-update_wrapper .associated').iCheck('cleck');
        $(this).prop('disabled', true);
        modulesDisableBtn.prop('disabled', false);
        var method = modulesEnableBtn.attr('method'),
            action = modulesEnableBtn.attr('action'),
            rows = modulesTable.rows({selected: true}),
            toEnable = $.getSelectedIds(modulesTable),
            opts = {
                enablemodulessel: 1,
                enablemodules: toEnable
            };
        $.apiCall(method,action,opts,function(err) {
            if (err) {
                modulesEnableBtn.prop('disabled', false);
                return;
            }
            $('#modules-to-update').find('.associated').each(function() {
                if ($.inArray($(this).val(), toEnable) != -1) {
                    $(this).iCheck('check');
                }
            });
            modulesTable.draw(false);
            modulesTable.rows({selected: true}).deselect();
        });
    });
    modulesDisableBtn.on('click', function(e) {
        e.preventDefault();
        $('#modules-to-update_wrapper .buttons-select-none').trigger('click');
        $('#modules-to-update_wrapper .associated').iCheck('uncheck');
        $(this).prop('disabled', true);
        modulesEnableBtn.prop('disabled', false);
        var method = modulesDisableBtn.attr('method'),
            action = modulesDisableBtn.attr('action'),
            rows = modulesTable.rows({selected: true}),
            toDisable = [],
            opts = {
                disablemodulessel: 1,
                disablemodules: toDisable
            };
        $('#modules-to-update').find('.associated').each(function() {
            if (!$(this).is(':checked')) {
                toDisable.push($(this).val());
            }
        });
        $.apiCall(method,action,opts,function(err) {
            if (err) {
                modulesDisableBtn.prop('disabled', false);
                return;
            }
            $('#modules-to-update').find('.associated').each(function() {
                if ($.inArray($(this).val(), toDisable) != -1) {
                    $(this).iCheck('uncheck');
                }
            });
            modulesTable.draw(false);
            modulesTable.rows({selected: true}).deselect();
        });
    });
    modulesDispBtn.on('click', function(e) {
        e.preventDefault();
        var form = $('#group-dispman');
        form.processForm();
    });
    modulesAloBtn.on('click', function(e) {
        e.preventDefault();
        var form = $('#group-alo');
        form.processForm();
    });

    // ---------------------------------------------------------------
    // POWER MANAGEMENT TAB
    var powermanagementForm = $('#group-powermanagement-cron-form'),
        powermanagementFormBtn = $('#powermanagement-send'),
        powermanagementDeleteBtn = $('#powermanagement-delete'),
        powermanagementDeleteModal = $('#deletepowermanagementmodal'),
        powermanagementDeleteCancelBtn = $('#deletepowermanagementCancel'),
        powermanagementDeleteConfirmBtn = $('#deletepowermanagementConfirm'),
        ondemandModalBtn = $('#ondemandBtn'),
        ondemandModalConfirmBtn = $('#ondemandCreateBtn'),
        scheduleModalBtn = $('#scheduleBtn'),
        scheduleModalConfirmBtn = $('#scheduleCreateBtn'),
        // Insert Form cron elements.
        minutes = $('.cronmin', powermanagementForm),
        hours = $('.cronhour', powermanagementForm),
        dom = $('.crondom', powermanagementForm),
        month = $('.cronmonth', powermanagementForm),
        dow = $('.crondow', powermanagementForm),
        ondemand = $('#scheduleOnDemand', powermanagementForm),
        action = $('.pmaction', powermanagementForm);

    $('.fogcron').cron({
        initial: '* * * * *',
        onChange: function() {
            vals = $(this).cron('value').split(' ');
            minutes.val(vals[0]);
            hours.val(vals[1]);
            dom.val(vals[2]);
            month.val(vals[3]);
            dow.val(vals[4]);
        }
    });
    // When On Demand checked remove the cron layout.
    ondemand.on('ifChecked', function(e) {
        $(this).parents('.box-body').find('.form-group:eq(0)').find(':input').prop('disabled', true);
    });
    ondemand.on('ifUnchecked', function(e) {
        $(this).parents('.box-body').find('.form-group:eq(0)').find(':input').prop('disabled', false);
    });

    powermanagementForm.on('submit', function(e) {
        e.preventDefault();
    });
    powermanagementFormBtn.on('click', function() {
        powermanagementFormBtn.prop('disabled', true);
        powermanagementForm.processForm(function(err) {
            powermanagementFormBtn.prop('disabled', false);
            if (err) {
                return;
            }
            minutes.val('');
            hours.val('');
            dom.val('');
            month.val('');
            dow.val('');
            action.val('');
            specialCrons.val('');
            ondemand.iCheck('uncheck');
        });
    });
    // Powermanagement delete confirmation modal.
    powermanagementDeleteBtn.on('click', function(e) {
        e.preventDefault();
        powermanagementDeleteModal.modal('show');
    });

    // Modal Confirmed
    powermanagementDeleteConfirmBtn.on('click', function(e) {
        e.preventDefault();
        // Our Powermanagement Items.
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            opts = {
                pmdelete: 1
            };
        $.apiCall(method,action,opts,function(err) {
            if (err) {
                return;
            }
            powermanagementDeleteModal.modal('hide');
        });
    });
    // New ondemand element.
    ondemandModalBtn.on('click', function(e) {
        e.preventDefault();
        $('#ondemandModal').modal('show');
    });
    ondemandModalConfirmBtn.on('click', function(e) {
        e.preventDefault();
        var form = $('#group-powermanagement-instant-form');
        form.processForm(function(err) {
            if (err) {
                return;
            }
            $('#ondemandModal').modal('hide');
        });
    });
    // New scheduled element.
    scheduleModalBtn.on('click', function(e) {
        e.preventDefault();
        $('#scheduleModal').modal('show');
    });
    scheduleModalConfirmBtn.on('click', function(e) {
        e.preventDefault();
        var form = $('#group-powermanagement-cron-form');
        form.processForm(function(err) {
            if (err) {
                return;
            }
            $('#scheduleModal').modal('hide');
        });
    });
    if (Common.search && Common.search.length > 0) {
        groupHostsTable.search(Common.search).draw();
        groupPrintersTable.search(Common.search).draw();
        groupSnapinsTable.search(Common.search).draw();
        modulesTable.search(Common.search).draw();
    }
})(jQuery)
