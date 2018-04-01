(function($) {
    // ---------------------------------------------------------------
    // GENERAL TAB
    var originalName = $("#host").val(),
        updateName = function(newName) {
            var e = $("#pageTitle"),
                text = e.text();
            text = text.replace(": " + originalName, ": " + newName);
            document.title = text;
            e.text(text);
        };

    $('#host').inputmask({mask: Common.masks.hostname, repeat: 15});
    $('#mac').inputmask({mask: Common.masks.mac});
    $('#key').inputmask({mask: Common.masks.productKey});

    var generalForm = $('#host-general-form'),
        generalFormBtn = $('#general-send'),
        generalDeleteBtn = $('#general-delete'),
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
            updateName($('#host').val())
            originalName = $('#host').val();
        });
    });
    generalDeleteBtn.on('click',function() {
        generalFormBtn.prop('disabled', true);
        generalDeleteBtn.prop('disabled', true);
        Common.massDelete(
            null,
            function(err) {
                if (err) {
                    generalDeleteBtn.prop('disabled', false);
                    generalFormBtn.prop('disabled', false);
                    return;
                }
                window.location = '../management/index.php?node='+Common.node+'&sub=list';
            });
    });

    // Reset encryption confirmation modal.
    resetEncryptionBtn.on('click', function(e) {
        e.preventDefault();
        // Set our general form buttons disabled.
        $(this).prop('disabled', true);
        generalFormBtn.prop('disabled', true);
        generalDeleteBtn.prop('disabled', true);

        // Enable our modal buttons.
        resetEncryptionConfirmBtn.prop('disabled', false);
        resetEncryptionCancelBtn.prop('disabled', false);

        // Display the reset encryption modal
        resetEncryptionModal.modal('show');
    });

    // Modal cancelled
    resetEncryptionCancelBtn.on('click', function(e) {
        e.preventDefault();

        // Set our modal buttons disabled.
        $(this).prop('disabled', true);
        resetEncryptionConfirmBtn.prop('disabled', true);

        // Enable our general form buttons.
        generalFormBtn.prop('disabled', false);
        generalDeleteBtn.prop('disabled', false);
        resetEncryptionBtn.prop('disabled', false);

        // Hide the modal
        resetEncryptionModal.modal('hide');
    });

    // Modal Confirmed
    resetEncryptionConfirmBtn.on('click', function(e) {
        e.preventDefault();

        // Set our modal buttons disabled.
        $(this).prop('disabled', true);
        resetEncryptionCancelBtn.prop('disabled', true);


        // Reset our encryption data.
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            opts = {
                id: Common.id
            };
        Common.apiCall(method,action,opts,function(err) {
            if (!err) {
                // Enable our general form buttons.
                generalFormBtn.prop('disabled', false);
                generalDeleteBtn.prop('disabled', false);
                resetEncryptionBtn.prop('disabled', false);
                // Hide modal
                resetEncryptionModal.modal('hide');
            }
        });
    });

    // ---------------------------------------------------------------
    // MAC ADDRESS TAB
    var newmacForm = $('#macaddress-add-form'),
        newmacAddBtn = $('#newmac-send'),
        newmacField = $('#newMac'),
        macTable = $('#host-macaddresses-table'),
        macUpdateBtn = $('#macaddress-table-update'),
        macDeleteBtn = $('#macaddress-table-delete');

    macUpdateBtn.prop('disabled', true);
    macDeleteBtn.prop('disabled', true);

    // Make sure we have masking set for mac add field.
    newmacField.inputmask({mask: Common.masks.mac});
    newmacForm.on('submit', function(e) {
        e.preventDefault();
    });
    newmacAddBtn.on('click', function() {
        $(this).prop('disabled', true);
        Common.processForm(newmacForm, function(err) {
            newmacAddBtn.prop('disabled', false);
            if (err) {
                return;
            }
            newmacField.val('');
            macsTable.draw(false);
            macsTable.rows({selected: true}).deselect();
        });
    });
    function onMacsSelect(selected) {
        var disabled = selected.count() == 0;
        macUpdateBtn.prop('disabled', disabled);
        macDeleteBtn.prop('disabled', disabled);
    }

    var macsTable = Common.registerTable(macTable, onMacsSelect, {
        order: [
            [0, 'asc']
        ],
        columns: [
            {data: 'mac'},
            {data: 'primary'},
            {data: 'imageIgnore'},
            {data: 'clientIgnore'},
            {data: 'pending'}
        ],
        rowId: 'id',
        columnDefs: [
            {
                responsivePriority: -1,
                render: function(data, type, row) {
                    return data;
                    //return '<input type="text" name="macs[]" macrefid="'
                    //    + row.id
                    //    + '" value="'
                    //    + data
                    //    + '" class="form-control macs" required/>';
                },
                targets: 0
            },
            {
                render: function(data, type, row) {
                    var checkval = '';
                    if (data > 0) {
                        checkval = ' checked';
                    }
                    return '<div class="radio">'
                        + '<input belongsto="primaryMacs" type="radio" class="primary" name="primary" id="mac_'
                        + row.id
                        + '" value="'
                        + row.id
                        + '" wasoriginalprimary="'
                        + checkval
                        + '" '
                        + checkval
                        + '/>'
                        + '</div>';
                },
                targets: 1
            },
            {
                render: function(data, type, row) {
                    var checkval = '';
                    if (data > 0) {
                        checkval = ' checked';
                    }
                    return '<div class="checkbox">'
                        + '<input type="checkbox" class="imageIgnore" name="imageIgnore[]" id="imageIgnore_'

                        + row.id
                        + '" value="'
                        + row.id
                        + '"'
                        + checkval
                        + '/>'
                        + '</div>';
                },
                targets: 2
            },
            {
                render: function(data, type, row) {
                    var checkval = '';
                    if (data > 0) {
                        checkval = ' checked';
                    }
                    return '<div class="checkbox">'
                        + '<input type="checkbox" class="clientIgnore" name="clientIgnore[]" id="clientIgnore_'

                        + row.id
                        + '" value="'
                        + row.id
                        + '"'
                        + checkval
                        + '/>'
                        + '</div>';
                },
                targets: 3
            },
            {
                render: function(data, type, row) {
                    var checkval = '';
                    if (data > 0) {
                        checkval = ' checked';
                    }
                    return '<div class="checkbox">'
                        + '<input type="checkbox" class="pending" name="pending[]" id="pending_'

                        + row.id
                        + '" value="'
                        + row.id
                        + '"'
                        + checkval
                        + '/>'
                        + '</div>';
                },
                targets: 4
            }
        ],
        processing: true,
        serverSide: true,
        ajax: {
            url: '../management/index.php?node='+Common.node+'&sub=getMacaddressesList&id='+Common.id,
            type: 'post'
        }
    });

    // Make our Mac addresses editable, but restricted to MAC Address formats.
    macsTable.on('draw',function() {
        Common.iCheck('#host-macaddresses-table input.primary');
        Common.iCheck('#host-macaddresses-table input.imageIgnore');
        Common.iCheck('#host-macaddresses-table input.clientIgnore');
        Common.iCheck('#host-macaddresses-table input.pending');
        $('#host-macaddresses-table input.primary').on('ifClicked', onMacsRadioSelect);
        $('#host-macaddresses-table input.imageIgnore').on('ifClicked', onMacsCheckboxSelect);
        $('#host-macaddresses-table input.clientIgnore').on('ifClicked', onMacsCheckboxSelect);
        $('#host-macaddresses-table input.pending').on('ifClicked', onMacsCheckboxSelect);
    });
    macUpdateBtn.prop('disabled', true);

    var onMacsRadioSelect = function(event) {
        macUpdateBtn.prop('disabled', true);
        macDeleteBtn.prop('disabled', true);
        if($(this).attr('belongsto') === 'primaryMacs') {
            var id = parseInt($(this).val()),
                method = macUpdateBtn.attr('method'),
                action = macUpdateBtn.attr('action'),
                opts = {
                    updateprimary: 1,
                    primary: id
                };
            Common.apiCall(method,action,opts,function(err) {
                macUpdateBtn.prop('disabled', false);
                macDeleteBtn.prop('disabled', false);
                macsTable.rows({selected: true}).deselect();
                if (err) {
                    macsTable.draw(false);
                }
            });
        }
    };
    var onMacsCheckboxSelect = function(event) {
        $(this).prop('checked', !this.checked);
        macUpdateBtn.prop('disabled', true);
        macDeleteBtn.prop('disabled', true);
        var imageIgnore = [],
            clientIgnore = [],
            pending = [];
        $('.imageIgnore').each(function() {
            if (this.checked) {
                imageIgnore.push(this.value);
            }
        });
        $('.clientIgnore').each(function() {
            if (this.checked) {
                clientIgnore.push(this.value);
            }
        });
        $('.pending').each(function() {
            if (this.checked) {
                pending.push(this.value);
            }
        });
        var id = parseInt($(this).val()),
            method = macUpdateBtn.attr('method'),
            action = macUpdateBtn.attr('action'),
            opts = {
                updatechecks: 1,
                imageIgnore: imageIgnore,
                clientIgnore: clientIgnore,
                pending: pending
            };
        Common.apiCall(method,action,opts,function(err) {
            macUpdateBtn.prop('disabled', false);
            macDeleteBtn.prop('disabled', false);
            if (err) {
                macsTable.draw(false);
            }
            macsTable.rows({selected: true}).deselect();
        });
    };

    // Setup primary mac watcher.
    $('#host-macaddresses-table input.primary').on('ifClicked', onMacsRadioSelect);
    // Setup checkbox watchers.
    $('#host-macaddresses-table input.imageIgnore').on('ifClicked', onMacsCheckboxSelect);
    $('#host-macaddresses-table input.clientIgnore').on('ifClicked', onMacsCheckboxSelect);
    $('#host-macaddresses-table input.pending').on('ifClicked', onMacsCheckboxSelect);

    if (Common.search && Common.search.length > 0) {
        macsTable.search(Common.search).draw();
    }

    // ---------------------------------------------------------------
    // TASKING TAB
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
                        hostDeployForm = $('#host-deploy-form'),
                        minutes = $('#cronMin', hostDeployForm),
                        hours = $('#cronHour', hostDeployForm),
                        dom = $('#cronDom', hostDeployForm),
                        month = $('#cronMonth', hostDeployForm),
                        dow = $('#cronDow', hostDeployForm),
                        createTaskBtn = $('#tasking-send');
                    Common.iCheck('#task-form-holder input');

                    $(document).on('ifChecked', '#checkdebug', function(e) {
                        e.preventDefault();
                        $('.hideFromDebug,.delayedinput,.croninput').addClass('hidden');
                        $('.instant').iCheck('check');
                    }).on('ifUnchecked', '#checkdebug', function(e) {
                        e.preventDefault();
                        $('.hideFromDebug').removeClass('hidden');
                    }).on('ifClicked', 'input[name="scheduleType"]', function(e) {
                        e.preventDefault();
                        switch (this.value) {
                            case 'instant':
                                $('.delayedinput,.croninput').addClass('hidden');
                                break;
                            case 'single':
                                $('.delayedinput').removeClass('hidden');
                                $('.croninput').addClass('hidden');
                                break;
                            case 'cron':
                                $('.delayedinput').addClass('hidden');
                                $('.croninput').removeClass('hidden');
                                break;
                        }
                    }).on('click', '#tasking-send', function(e) {
                        e.preventDefault();
                        Common.processForm(hostDeployForm, function(err) {
                            if (err) {
                                return;
                            }
                            hostDeployForm.remove();
                            $('#task-form-holder').html('');
                            taskModal.modal('hide');
                        });
                    }).on('click', '#tasking-close', function(e) {
                        e.preventDefault();
                        hostDeployForm.remove();
                        $('#task-form-holder').html('');
                        taskModal.modal('hide');
                    });
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
                    Common.notifyFromAPI(jqXHR.responseJSON, true);
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
        ADFormBtn.prop('disabled', true);
        ADClearBtn.prop('disabled', true);
        Common.processForm(ADForm, function(err) {
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

        Common.processForm(ADForm, function(err) {
            for (var i = 0; i < restoreMap.length; i++) {
                field = restoreMap[i];
                if (field.checkbox) {
                    if (err) $(field.e).iCheck((field.val ? 'check' : 'uncheck'));
                    $(field.e).iCheck('enable');
                } else {
                    if (err) $(field.e).val(field.val);
                    $(field.e).prop('disabled', false);
                }
            }
            ADClearBtn.prop('disabled', false);
            ADFormBtn.prop('disabled', false);
        });
    });

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

    function onPrintersSelect(selected) {
        var disabled = selected.count() == 0;
        printerAddBtn.prop('disabled', disabled);
        printerRemoveBtn.prop('disabled', disabled);
    }

    var printersTable = Common.registerTable($('#host-printers-table'), onPrintersSelect, {
        order: [
            [1, 'asc']
        ],
        columns: [
            {data: 'isDefault'},
            {data: 'name'},
            {data: 'config'},
            {data: 'association'}
        ],
        rowId: 'id',
        columnDefs: [
            {
                responsivePriority: -1,
                render: function(data, type, row) {
                    var checkval = '';
                    if (data > 0) {
                        checkval = ' checked';
                    }
                    return '<div class="radio">'
                        + '<input belongsto="defaultPrinters" type="radio" class="default" name="default" id="printer_'
                        + row.id
                        + '" value="' + row.id + '"'
                        + ' wasoriginaldefault="'
                        + checkval
                        + '" '
                        + checkval
                        + '/>'
                        + '</div>';
                },
                targets: 0,
            },
            {
                responsivePriority: 0,
                render: function(data, type, row) {
                    return '<a href="../management/index.php?node=printer&sub=edit&id=' + row.id + '">' + data + '</a>';
                },
                targets: 1
            },
            {
                render: function(data, type, row) {
                    return row.config == 'Local' ? 'TCP/IP' : row.config;
                },
                targets: 2
            },
            {
                render: function(data, type, row) {
                    var checkval = '';
                    if (row.association === 'associated') {
                        checkval = ' checked';
                    }
                    return '<div class="checkbox">'
                        + '<input type="checkbox" class="associated" name="associate[]" id="printerAssoc_'
                        + row.id
                        + '" value="' + row.id + '"'
                        + checkval
                        + '/>'
                        + '</div>';
                },
                targets: 3
            }
        ],
        processing: true,
        serverSide: true,
        ajax: {
            url: '../management/index.php?node='+Common.node+'&sub=getPrintersList&id='+Common.id,
            type: 'post'
        }
    });

    printersTable.on('draw', function() {
        Common.iCheck('#host-printers input');
        $('#host-printers-table input.default').on('ifClicked', onRadioSelect);
    });
    printerDefaultBtn.prop('disabled', true);

    var onRadioSelect = function(event) {
        if($(this).attr('belongsto') === 'defaultPrinters') {
            var id = parseInt($(this).val());
            if(DEFAULT_PRINTER_ID === -1 && $(this).attr('wasoriginaldefault') === ' checked') {
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

    printerDefaultBtn.on('click',function() {
        printerAddBtn.prop('disabled', true);
        printerRemoveBtn.prop('disabled', true);

        var method = printerDefaultBtn.attr('method'),
            action = printerDefaultBtn.attr('action'),
            opts = {
                defaultsel: 1,
                default: DEFAULT_PRINTER_ID
            };
        Common.apiCall(method,action, opts, function(err) {
            printerDefaultBtn.prop('disabled', !err);
            onPrintersSelect(printersTable.rows({selected: true}));
        });
    });

    printerConfigForm.serialize2 = printerConfigForm.serialize;
    printerConfigForm.serialize = function() {
        return printerConfigForm.serialize2() + '&levelup';
    }
    printerConfigForm.on('submit',function(e) {
        e.preventDefault();
    });
    printerConfigBtn.on('click', function() {
        printerConfigBtn.prop('disabled', true);
        Common.processForm(printerConfigForm, function(err) {
            printerConfigBtn.prop('disabled', false);
            if (err) {
                return;
            }
            printersTable.draw(false);
            printersTable.rows({selected: true}).deselect();
        });
    });
    printerAddBtn.on('click',function() {
        printerAddBtn.prop('disabled', true);

        var method = printerAddBtn.attr('method'),
            action = printerAddBtn.attr('action'),
            rows = printersTable.rows({selected: true}),
            toAdd = Common.getSelectedIds(printersTable),
            opts = {
                updateprinters: 1,
                printer: toAdd
            };

        Common.apiCall(method,action,opts,function(err) {
            printerAddBtn.prop('disabled', false);
            if (err) {
                return;
            }
            $('#host-printers-table').find('.default:disabled').each(function() {
                if ($.inArray($(this).val(), toAdd) != -1) {
                    $(this).prop('disabled', false);
                    Common.iCheck(this);
                }
            });
            $('#host-printers-table').find('.associated').each(function() {
                if ($.inArray($(this).val(), toAdd) != -1) {
                    $(this).iCheck('check');
                }
            });
            printersTable.draw(false);
            printersTable.rows({selected: true}).deselect();
        });
    });

    printerRemoveBtn.on('click',function() {
        printerAddBtn.prop('disabled', true);
        printerRemoveBtn.prop('disabled', true);
        printerDefaultBtn.prop('disabled', true);

        var method = printerRemoveBtn.attr('method'),
            action = printerRemoveBtn.attr('action'),
            rows = printersTable.rows({selected: true}),
            toRemove = Common.getSelectedIds(printersTable),
            opts = {
                printdel: 1,
                printerRemove: toRemove
            };

        Common.apiCall(method,action,opts,function(err) {
            printerDefaultBtn.prop('disabled', false);
            printerRemoveBtn.prop('disabled', false);
            if (err) {
                return;
            }
            printersTable.draw(false);
            printersTable.rows({selected: true}).deselect();
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

    function onSnapinsSelect(selected) {
        var disabled = selected.count() == 0;
        snapinsAddBtn.prop('disabled', disabled);
        snapinsRemoveBtn.prop('disabled', disabled);
    }

    var snapinsTable = Common.registerTable($('#host-snapins-table'), onSnapinsSelect, {
        columns: [
            {data: 'name'},
            {data: 'createdTime'},
            {data: 'association'}
        ],
        rowId: 'id',
        columnDefs: [
            {
                responsivePriority: -1,
                render: function(data, type, row) {
                    return '<a href="../management/index.php?node=snapin&sub=edit&id=' + row.id +'">' + data + '</a>';
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
                        + '<input type="checkbox" class="associated" name="associate[]" id="snapinAssoc_'
                        + row.id
                        + '" value="' + row.id + '"'
                        + checkval
                        + '/>'
                        + '</div>';
                },
                targets: 2
            }
        ],
        processing: true,
        serverSide: true,
        ajax: {
            url: '../management/index.php?node='+Common.node+'&sub=getSnapinsList&id='+Common.id,
            type: 'post'
        }
    });
    snapinsTable.on('draw', function() {
        Common.iCheck('#host-snapins input');
    });

    snapinsAddBtn.on('click',function() {
        snapinsAddBtn.prop('disabled', true);
        snapinsRemoveBtn.prop('disabled', true);

        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            rows = snapinsTable.rows({selected: true}),
            toAdd = Common.getSelectedIds(snapinsTable),
            opts = {
                updatesnapins: 1,
                snapin: toAdd
            };
        Common.apiCall(method,action,opts,function(err) {
            snapinsAddBtn.prop('disabled', false);
            snapinsRemoveBtn.prop('disabled', false);
            if (err) {
                return;
            }
            snapinsTable.draw(false);
            snapinsTable.rows({selected: true}).deselect();
        });
    });

    snapinsRemoveBtn.on('click',function() {
        snapinsRemoveBtn.prop('disabled', true);
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            rows = snapinsTable.rows({selected: true}),
            toRemove = Common.getSelectedIds(snapinsTable),
            opts = {
                snapdel: 1,
                snapinRemove: toRemove
            };
        Common.apiCall(method,action,opts,function(err) {
            snapinsAddBtn.prop('disabled', false);
            snapinsRemoveBtn.prop('disabled', false);
            if (err) {
                return;
            }
            snapinsTable.draw(false);
            snapinsTable.rows({selected: true}).deselect();
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
        modulesAloBtn = $('#alo-send'),
        modulesEnforceBtn = $('#enforcebtn');

    function onModulesDisable(selected) {
        var disabled = selected.count() == 0;
        modulesDisableBtn.prop('disabled', disabled);
    }
    function onModulesEnable(selected) {
        var disabled = selected.count() == 0;
        modulesEnableBtn.prop('disabled', disabled);
    }

    var modulesTable = Common.registerTable($("#modules-to-update"), onModulesEnable, {
        columns: [
            {data: 'name'},
            {data: 'association'}
        ],
        rowId: 'id',
        columnDefs: [
            {
                responsivePriority: -1,
                render: function(data, type, row) {
                    return row.name;
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
            url: '../management/index.php?node='+Common.node+'&sub=getModulesList&id='+Common.id,
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
            if (err) {
                return;
            }
            modulesTable.draw(false);
            modulesTable.rows({selected: true}).deselect();
        });
    });
    modulesEnableBtn.on('click', function(e) {
        e.preventDefault();
        $('#modules-to-update_wrapper .buttons-select-all').trigger('click');
        $('#modules-to-update_wrapper .associated').iCheck('check');
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
            modulesEnableBtn.prop('disabled', false);
            if (err) {
                return;
            }
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
        var method = modulesEnableBtn.attr('method'),
            action = modulesEnableBtn.attr('action'),
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
            modulesDisableBtn.prop('disabled', false);
            if (err) {
                return;
            }
            modulesTable.draw(false);
            modulesTable.rows({selected: true}).deselect();
        });
    });
    modulesDispBtn.on('click', function(e) {
        e.preventDefault();
        var form = $('#host-dispman');
        modulesDispBtn.prop('disabled', true);
        Common.processForm(form, function(err) {
            modulesDispBtn.prop('disabled', false);
        });
    });
    modulesAloBtn.on('click', function(e) {
        e.preventDefault();
        var form = $('#host-alo');
        modulesAloBtn.prop('disabled', true);
        Common.processForm(form, function(err) {
            modulesAloBtn.prop('disabled', false);
        });
    });
    modulesEnforceBtn.on('click', function(e) {
        e.preventDefault();
        var form = $('#host-enforce');
        modulesEnforceBtn.prop('disabled', true);
        Common.processForm(form, function(err) {
            modulesEnforceBtn.prop('disabled', false);
        });
    });
    if (Common.search && Common.search.length > 0) {
        modulesTable.search(Common.search).draw();
    }
    // ---------------------------------------------------------------
    // POWER MANAGMENT TAB

    // The form Control elements of Power Management.
    var powermanagementForm = $('#host-powermanagement-cron-form'),
        powermanagementFormBtn = $('#powermanagement-send'),
        // Insert Form cron elements.
        minutes = $('.cronmin', powermanagementForm),
        hours = $('.cronhour', powermanagementForm),
        dom = $('.crondom', powermanagementForm),
        month = $('.cronmonth', powermanagementForm),
        dow = $('.crondow', powermanagementForm),
        instantModal = $('#ondemandModal'),
        instantBtn = $('#ondemandBtn'),
        instantModalCancelBtn = $('#ondemandCancelBtn'),
        instantModalCreateBtn = $('#ondemandCreateBtn'),
        scheduleModal = $('#scheduleCreate'),
        scheduleBtn = $('#scheduleCreateBtn'),
        scheduleModalCancelBtn = $('#scheduleCancelBtn'),
        scheduleModalCreateBtn = $('#scheduleCreateBtn')

    // FOG Cron
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

    // The Power Management List element.
    function onPMSelect(selected) {
        var disable = selected.count() == 0;
    }

    var powermanagementTable = Common.registerTable($("#host-powermanagement-table"), onPMSelect, {
        columns: [
            {data: 'id'},
            {data: 'action'}
        ],
        columnDefs: [
            {
                targets: 0,
                render: function(data, type, row) {
                    return row.min
                        + ' '
                        + row.hour
                        + ' '
                        + row.dom
                        + ' '
                        + row.month
                        + ' '
                        + row.dow;
                }
            }
        ],
        rowId: 'id',
        processing: true,
        serverSide: true,
        ajax: {
            url: '../management/index.php?node='
            + Common.node
            + '&sub=getPowermanagementList&id='
            + Common.id,
            type: 'post'
        }
    });

    if (Common.search && Common.search.length > 0) {
        powermanagementTable.search(Common.search).draw();
    }
    // ---------------------------------------------------------------
    // GROUP MEMBERSHIP TAB

    var groupsAddBtn = $('#groups-add'),
        groupsRemoveBtn = $('#groups-remove');

    groupsAddBtn.prop('disabled', true);
    groupsRemoveBtn.prop('disabled', true);

    function onGroupsSelect(selected) {
        var disabled = selected.count() == 0;
        groupsAddBtn.prop('disabled', disabled);
        groupsRemoveBtn.prop('disabled', disabled);
    }

    var groupsTable = Common.registerTable($('#host-groups-table'), onGroupsSelect, {
        columns: [
            {data: 'name'},
            {data: 'association'}
        ],
        rowId: 'id',
        columnDefs: [
            {
                responsivePriority: -1,
                render: function(data, type, row) {
                    return '<a href="../management/index.php?node=group&sub=edit&id='
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
                        + '<input type="checkbox" class="associated" name="associate[]" id="snapinAssoc_'
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
            + '&sub=getGroupsList&id='
            + Common.id,
            type: 'post'
        }
    });
    groupsTable.on('draw', function() {
        Common.iCheck('#host-groups-table input');
    });

    groupsAddBtn.on('click',function() {
        groupsAddBtn.prop('disabled', true);
        groupsRemoveBtn.prop('disabled', true);

        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            rows = groupsTable.rows({selected: true}),
            toAdd = Common.getSelectedIds(groupsTable),
            opts = {
                updategroups: 1,
                group: toAdd
            };
        Common.apiCall(method,action,opts,function(err) {
            groupsAddBtn.prop('disabled', false);
            groupsRemoveBtn.prop('disabled', false);
            if (err) {
                return;
            }
            groupsTable.draw(false);
            groupsTable.rows({selected: true}).deselect();
        });
    });

    groupsRemoveBtn.on('click',function() {
        groupsRemoveBtn.prop('disabled', true);
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            rows = groupsTable.rows({selected: true}),
            toRemove = Common.getSelectedIds(groupsTable),
            opts = {
                groupdel: 1,
                groupRemove: toRemove
            };
        Common.apiCall(method,action,opts,function(err) {
            groupsAddBtn.prop('disabled', false);
            groupsRemoveBtn.prop('disabled', false);
            if (err) {
                return;
            }
            groupsTable.draw(false);
            groupsTable.rows({selected: true}).deselect();
        });
    });
    if (Common.search && Common.search.length > 0) {
        groupsTable.search(Common.search).draw();
    }

    // ---------------------------------------------------------------
    // INVENTORY TAB
    var inventoryForm = $('#host-inventory-form'),
        inventoryFormBtn = $('#inventory-send');
    inventoryFormBtn.on('click', function(e) {
        e.preventDefault();
        $(this).prop('disabled', true);
        Common.processForm(inventoryForm, function(err) {
            inventoryFormBtn.prop('disabled', false);
        });
    });

    // ---------------------------------------------------------------
    // LOGIN HISTORY TAB
    var loginTable = Common.registerTable($('#host-login-table'), null, {
        columns: [
            {data: 'datetime'},
            {data: 'action'},
            {data: 'username'},
            {data: 'description'}
        ],
        rowId: 'id',
        processing: true,
        serverSide: true,
        ajax: {
            url: '../management/index.php?node='
            + Common.node
            + '&sub=getLoginHist&id='
            + Common.id,
            type: 'post'
        }
    });
    if (Common.search && Common.search.length > 0) {
        loginTable.search(Common.search).draw();
    }
    // ---------------------------------------------------------------
    // IMAGE HISTORY TAB
    var imageTable = Common.registerTable($('#host-image-table'), null, {
        columns: [
            {data: 'createdBy'},
            {data: 'start'},
            {data: 'finish'},
            {data: 'duration'},
            {data: 'image'},
            {data: 'type'}
        ],
        rowId: 'id',
        processing: true,
        serverSide: true,
        ajax: {
            url: '../management/index.php?node='
            + Common.node
            + '&sub=getImageHist&id='
            + Common.id,
            type: 'post'
        }
    });
    if (Common.search && Common.search.length > 0) {
        imageTable.search(Common.search).draw();
    }
})(jQuery);
