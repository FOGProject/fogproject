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
        };

    // Mask for product key.
    $('#key').inputmask({mask: Common.masks.productKey});

    // ---------------------------------------------------------------
    // GENERAL TAB
    var generalForm = $('#group-general-form'),
        generalFormBtn = $('#general-send'),
        generalDeleteBtn = $('#general-delete'),
        generalDeleteModal = $('#deleteModal'),
        generalDeleteModalConfirm = $('#confirmDeleteModal'),
        generalDeleteModalCancel = $('#closeDeleteModal'),
        resetEncryptionBtn = $('#reset-encryption-data'),
        resetEncryptionModal = $('#resetencryptionmodal'),
        resetEncryptionCancelBtn = $('#resetencryptionCancel'),
        resetEncryptionConfirmBtn = $('#resetencryptionConfirm');

    generalForm.on('submit',function(e) {
        e.preventDefault();
    });
    generalFormBtn.on('click',function() {
        generalFormBtn.prop('disabled', true);
        generalDeleteBtn.prop('disabled', true);
        Common.processForm(generalForm, function(err) {
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
        Common.apiCall(method, action, opts, function(err) {
            generalFormBtn.prop('disabled', false);
            generalDeleteBtn.prop('disabled', false);
            resetEncryptionBtn.prop('disabled', false);
            if (err) {
                return;
            }
            resetEncryptionModal.modal('hide');
        });
    });

    // ---------------------------------------------------------------
    // TASKS TAB
    var taskItem = $('.taskitem'),
        taskModal = $('#task-modal');
    taskItem.on('click', function(e) {
        e.preventDefault();
        var method = $(this).attr('href');
        Pace.track(function() {
            $.ajax({
                type: 'get',
                url: method,
                dataType: 'json',
                success: function(data, textStatus, jqXHR) {
                    taskModal.modal('show');
                    $('#task-form-holder').html($.parseHTML(data.msg));
                    var scheduleType = $('input[name="scheduleType"]'),
                        groupDeployForm = $('#group-deploy-form'),
                        minutes = $('#cronMin', groupDeployForm),
                        hours = $('#cronHour', groupDeployForm),
                        dom = $('#cronDom', groupDeployForm),
                        month = $('#cronMonth', groupDeployForm),
                        dow = $('#cronDow', groupDeployForm),
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
                        Common.processForm(groupDeployForm, function(err) {
                            if (err) {
                                return;
                            }
                            taskModal.modal('hide');
                        });
                    });
                    taskModal.on('hide.bs.modal', function(e) {
                        groupDeployForm.remove();
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
                    Common.notifyFromApI(jqXHR.responseJSON, true);
                }
            });
        });
    });

    // ---------------------------------------------------------------
    // ACTIVE DIRECTORY TAB
    var ADForm = $('#active-directory-form'),
        ADFormBtn = $('#ad-send'),
        ADClearBtn = $('#ad-clear'),
        ADJoinDomain = $('#adEnabled');

    ADJoinDomain.on('ifClicked', function(e) {
        e.preventDefault();
        $(this).prop('checked', !this.checked);
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
        Common.processForm(ADForm);
    });
    ADClearBtn.on('click',function() {
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

        Common.processForm(ADForm, function(err) {
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
            ADClearBtn.prop('disabled', false);
            ADFormBtn.prop('disabled', false);
        });
    });

    // ---------------------------------------------------------------
    // HOST MEMBERSHIP TAB
    var hostsAddBtn = $('#hosts-add'),
        hostsRemoveBtn = $('#hosts-remove');

    hostsAddBtn.prop('disabled', true);
    hostsRemoveBtn.prop('disabled', true);

    function onHostsSelect (selected) {
        var disabled = selected.count() == 0;
        hostsAddBtn.prop('disabled', disabled);
        hostsRemoveBtn.prop('disabled', disabled);
    }

    var hostsTable = Common.registerTable($('#group-hosts-table'), onHostsSelect, {
        columns: [
            {data: 'name'},
            {data: 'associated'}
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
                        + '<input type="checkbox" class="associated" name="associate[]" id="hostAssoc_'
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
    hostsTable.on('draw', function() {
        Common.iCheck('#group-hosts-table input');
    });

    hostsAddBtn.on('click', function() {
        hostsAddBtn.prop('disabled', true);
        hostsRemoveBtn.prop('disabled', true);
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            rows = hostsTable.rows({selected: true}),
            toAdd = Common.getSelectedIds(hostsTable),
            opts = {
                updatehosts: 1,
                host: toAdd
            };
        Common.apiCall(method,action,opts,function(err) {
            hostsAddBtn.prop('disabled', false);
            hostsRemoveBtn.prop('disabled', false);
            if (err) {
                return;
            }
            hostsTable.draw(false);
            hostsTable.rows({selected: true}).deselect();
        });
    });

    hostsRemoveBtn.on('click', function() {
        hostsAddBtn.prop('disabled', true);
        hostsRemoveBtn.prop('disabled', true);
        $('#hostDelModal').modal('show');
    });
    $('#confirmhostDeleteModal').on('click', function(e) {
        Common.deleteAssociated(hostsTable, hostsRemoveBtn.attr('action'), function(err) {
            hostsAddBtn.prop('disabled', false);
            hostsRemoveBtn.prop('disabled', false);
            if (err) {
                return;
            }
            $('#hostDelModal').modal('hide');
        });
    });

    if (Common.search && Common.search.length > 0) {
        hostsTable.search(Common.search).draw();
    }

    // ---------------------------------------------------------------
    // PRINTER TAB
    var printerConfigForm = $('#printer-config-form'),
        printerConfigBtn = $('#printer-config-send'),
        printerAddBtn = $('#printer-add'),
        printerDefaultBtn = $('#printer-default'),
        printerRemoveBtn = $('#printer-remove'),
        DEFAULT_PRINTER_ID = -1;

    printerAddBtn.prop('disabled', true);
    printerRemoveBtn.prop('disabled', true);

    function onPrintersSelect (selected) {
        var disabled = selected.count() == 0;
        printerAddBtn.prop('disabled', disabled);
        printerRemoveBtn.prop('disabled', disabled);
    }

    var printersTable = Common.registerTable($('#group-printers-table'), onPrintersSelect, {
        order: [
            [1, 'asc']
        ],
        columns: [
            {data: 'isDefault'},
            {data: 'name'},
            {data: 'config'}
        ],
        rowId: 'id',
        columnDefs: [
            {
                responsivePriority: -1,
                render: function(data, type, row) {
                    var checkval = '';
                    if (row.isDefault) {
                        checkval = ' checked';
                    }
                    return '<div class="radio">'
                        + '<input belongsto="defaultPrinters" type="radio" class="default" name="default" id="printer_'
                        + row.id
                        + '" value="' + row.id + '"'
                        + ' wasoriginaldefault="'
                        + checkval
                        + '"'
                        + checkval
                        + '/>'
                        + '</div>'
                },
                targets: 0,
            },
            {
                responsivePriority: 0,
                render: function(data, type, row) {
                    return '<a href="../management/index.php?node=printer&sub=edit&id='
                        + row.id
                        + '">'
                        + data
                        + '</a>';
                },
                targets: 1
            },
            {
                render: function(data, type, row) {
                    return row.config == 'Local' ? 'TCP/IP' : row.config;
                },
                targets: 2
            },
        ],
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

    printersTable.on('draw', function() {
        Common.iCheck('#group-printers input');
        $('.default').on('ifClicked', onRadioSelect);
    });

    var onRadioSelect = function(event) {
        if ($(this).attr('belongsto') === 'defaultPrinters') {
            var id = parseInt($(this).val());
            if (DEFAULT_PRINTER_ID === -1
                && $(this).attr('wasoriginaldefault') === ' checked'
            ) {
                DEFAULT_PRINTER_ID = id;
            }
            if (id === DEFAULT_PRINTER_ID) {
                $(this).iCheck('uncheck');
                DEFAULT_PRINTER_ID = 0;
            } else {
                DEFAULT_PRINTER_ID = id;
            }
            printerDefaultBtn.prop('disabled', false);
        }
    };

    // Setup default printer watcher
    $('.default').on('ifClicked', onRadioSelect);

    printerDefaultBtn.on('click', function() {
        printerAddBtn.prop('disabled', true);
        printerRemoveBtn.prop('disabled', true);

        var method = printerDefaultBtn.attr('method'),
            action = printerDefaultBtn.attr('action'),
            opts = {
                defaultsel: 1,
                'default': DEFAULT_PRINTER_ID
            };
        Common.apiCall(method,action,opts,function(err) {
            printerDefaultBtn.prop('disabled', !err);
            onPrintersSelect(printersTable.rows({selected: true}));
        });
    });

    printerConfigForm.serialize2 = printerConfigForm.serialize;
    printerConfigForm.serialize = function() {
        return printerConfigForm.serialize2() + '&levelup';
    };
    printerConfigForm.on('submit',function(e) {
        e.preventDefault();
    });
    printerConfigBtn.on('click', function() {
        Common.processForm(printerConfigForm);
    });
    printerAddBtn.on('click', function() {
        var method = printerAddBtn.attr('method'),
            action = printerAddBtn.attr('action'),
            rows = printersTable.rows({selected: true}),
            toAdd = Common.getSelectedIds(printersTable),
            opts = {
                updateprinters: 1,
                printer: toAdd
            };

        Common.apiCall(method, action, opts, function(err) {
            if (err) {
                return;
            }
            printersTable.draw(false);
            printersTable.rows({selected: true}).deselect();
        });
    });

    printerRemoveBtn.on('click', function() {
        $('#printerDelModal').modal('show');
    });
    $('#confirmprinterDeleteModal').on('click', function(e) {
        Common.deleteAssociated(printersTable, printerRemoveBtn.attr('action'), function(err) {
            if (err) {
                return;
            }
            $('#printerDelModal').modal('hide');
        });
    });

    if (Common.search && Common.search.length > 0) {
        printersTable.search(Common.search).draw();
    }

    // ---------------------------------------------------------------
    // SNAPINS TAB
    var snapinsAddBtn = $('#snapins-add'),
        snapinsRemoveBtn = $('#snapins-remove');

    snapinsAddBtn.prop('disabled', true);
    snapinsRemoveBtn.prop('disabled', true);

    function onSnapinSelect (selected) {
        var disabled = selected.count() == 0;
        snapinsAddBtn.prop('disabled', disabled);
        snapinsRemoveBtn.prop('disabled', disabled);
    }

    var snapinsTable = Common.registerTable($('#group-snapins-table'), onSnapinSelect, {
        columns: [
            {data: 'name'},
            {data: 'createdTime'}
        ],
        rowId: 'id',
        columnDefs: [
            {
                responsivePriority: -1,
                render: function(data, type, row) {
                    return '<a href="../management/index.php?node=snapin&sub=edit&id='
                        + row.id
                        + '">'
                        + data
                        + '</a>';
                },
                targets: 0
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
    snapinsTable.on('draw', function() {
        Common.iCheck('#group-snapins-table input');
    });

    snapinsAddBtn.on('click', function() {
        var method = snapinsAddBtn.attr('method'),
            action = snapinsAddBtn.attr('action'),
            rows = snapinsTable.rows({selected: true}),
            toAdd = Common.getSelectedIds(snapinsTable),
            opts = {
                updatesnapins: 1,
                snapin: toAdd
            };
        Common.apiCall(method, action, opts, function(err) {
            if (err) {
                return;
            }
            snapinsTable.draw(false);
            snapinsTable.rows({selected: true}).deselect();
        });
    });

    snapinsRemoveBtn.on('click', function() {
        snapinsAddBtn.prop('disabled', true);
        snapinsRemoveBtn.prop('disabled', true);
        $('#snapinDelModal').modal('show');
    });
    $('#confirmsnapinDeleteModal').on('click', function(e) {
        Common.deleteAssociated(snapinsTable, snapinsRemoveBtn.attr('action'), function(err) {
            if (err) {
                snapinsAddBtn.prop('disabled', false);
                snapinsRemoveBtn.prop('disabled', false);
                return;
            }
            $('#snapinDelModal').modal('hide');
        });
    });
    if (Common.search && Common.search.length > 0) {
        snapinsTable.search(Common.search).draw();
    }

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

    var modulesTable = Common.registerTable($("#modules-to-update"), onModulesEnable, {
        columns: [
            {data: 'name'}
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
                    return '<div class="checkbox">'
                    + '<input type="checkbox" class="associated" name="associate[]" id="moduleAssoc_'
                    + row.id
                    + '" value="'
                    + row.id
                    + '"/>'
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
        Common.apiCall(method,action,opts,function(err) {
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
            toEnable = Common.getSelectedIds(modulesTable),
            opts = {
                enablemodulessel: 1,
                enablemodules: toEnable
            };
        Common.apiCall(method,action,opts,function(err) {
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
        Common.apiCall(method,action,opts,function(err) {
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
        Common.processForm(form);
    });
    modulesAloBtn.on('click', function(e) {
        e.preventDefault();
        var form = $('#group-alo');
        Common.processForm(form);
    });
    if (Common.search && Common.search.length > 0) {
        modulesTable.search(Common.search).draw();
    }

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
        Common.processForm(powermanagementForm, function(err) {
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
        Common.apiCall(method,action,opts,function(err) {
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
        Common.processForm(form, function(err) {
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
        Common.processForm(form, function(err) {
            if (err) {
                return;
            }
            $('#scheduleModal').modal('hide');
        });
    });
})(jQuery)
