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
        generalDeleteModal = $('#deleteModal'),
        generalDeleteModalConfirm = $('#confirmDeleteModal'),
        generalDeleteModalCancel = $('#closeDeleteModal'),
        resetEncryptionBtn = $('#reset-encryption-data'),
        resetEncryptionModal = $('#resetencryptionmodal'),
        resetEncryptionCancelBtn = $('#resetencryptionCancel'),
        resetEncryptionConfirmBtn = $('#resetencryptionConfirm');

    generalForm.on('submit', function(e) {
        e.preventDefault();
    });
    generalFormBtn.on('click', function() {
        generalFormBtn.prop('disabled', true);
        generalDeleteBtn.prop('disabled', true);
        generalForm.processForm(function(err) {
            generalFormBtn.prop('disabled', false);
            generalDeleteBtn.prop('disabled', false);
            if (err) {
                return;
            }
            updateName($('#host').val())
            originalName = $('#host').val();
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
        $.apiCall(method,action,opts,function(err) {
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
        newMacModal = $('#macaddressModal'),
        newMacAddModalBtn = $('#macaddress-add'),
        newmacAddBtn = $('#newmac-send'),
        newmacField = $('#newMac'),
        macTable = $('#host-macaddresses-table'),
        macImageIgnoreBtn = $('#macaddress-table-update-image'),
        macImageUnignoreBtn = $('#macaddress-table-update-unimage'),
        macClientIgnoreBtn = $('#macaddress-table-update-client'),
        macClientUnignoreBtn = $('#macaddress-table-update-unclient'),
        macPendingBtn = $('#macaddress-table-update-pending'),
        macUnpendingBtn = $('#macaddress-table-update-unpending'),
        macDeleteBtn = $('#macaddress-table-delete');

    disableMacButtons(true);
    newMacAddModalBtn.on('click', function(e) {
        e.preventDefault();
        newMacModal.modal('show');
    });

    newMacModal.registerModal(
        function(e) {
            // Disable the add button initially
            newmacAddBtn.prop('disabled', true);

            // Clear and focus
            newmacField.val('').trigger('focus');

            // Setup the mask and effects of the mask.
            newmacField.inputmask(
                {
                    mask: Common.masks.mac,
                    oncleared: function() {
                        newmacAddBtn.prop('disabled', true);
                    },
                    onincomplete: function() {
                        newmacAddBtn.prop('disabled', true);
                    },
                    oncomplete: function() {
                        newmacAddBtn.prop('disabled', false);
                    }
                }
            );

            // On keypress, if enter submit if able.
            newmacField.on('keypress', function(e) {
                if (e.which == 13 && !newmacAddBtn.prop('disabled')) {
                    newmacAddBtn.trigger('click');
                }
            });
        },
        function(e) {
            newmacField.off('keypress');
            newmacField.val('');
            $(this).modal('hide');
        }
    );

    // Make sure we have masking set for mac add field.
    newmacForm.on('submit', function(e) {
        e.preventDefault();
    });
    newmacAddBtn.on('click', function() {
        $(this).prop('disabled', true);
        newmacForm.processForm(function(err) {
            newmacAddBtn.prop('disabled', false);
            if (err) {
                return;
            }
            newmacField.val('');
            newMacModal.modal('hide');
            macsTable.draw(false);
            macsTable.rows({selected: true}).deselect();
        });
    });
    function onMacsSelect(selected) {
        var disabled = selected.count() == 0;
        disableMacButtons(disabled);
    }
    function disableMacButtons(disable) {
        macImageIgnoreBtn.prop('disabled', disable);
        macImageIgnoreBtn.next('button').prop('disabled', disable);
        macImageUnignoreBtn.prop('disabled', disable);
        macClientIgnoreBtn.prop('disabled', disable);
        macClientUnignoreBtn.prop('disabled', disable);
        macPendingBtn.prop('disabled', disable);
        macUnpendingBtn.prop('disabled', disable);
        macDeleteBtn.prop('disabled', disable);
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
    disableMacButtons(true);

    var onMacsRadioSelect = function(event) {
        disableMacButtons(true);
        if($(this).attr('belongsto') === 'primaryMacs') {
            var id = parseInt($(this).val()),
                method = macImageIgnoreBtn.attr('method'),
                action = macImageIgnoreBtn.attr('action'),
                opts = {
                    updateprimary: 1,
                    primary: id
                };
            $.apiCall(method,action,opts,function(err) {
                disableMacButtons(false);
                macsTable.rows({selected: true}).deselect();
                if (err) {
                    macsTable.draw(false);
                }
            });
        }
    };
    var onMacsCheckboxSelect = function(event) {
        $(this).prop('checked', !this.checked);
        disableMacButtons(true);
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
            method = macImageIgnoreBtn.attr('method'),
            action = macImageIgnoreBtn.attr('action'),
            opts = {
                updatechecks: 1,
                imageIgnore: imageIgnore,
                clientIgnore: clientIgnore,
                pending: pending
            };
        $.apiCall(method,action,opts,function(err) {
            disableMacButtons(false);
            macsTable.draw(false);
            if (err) {
                return;
            }
            macsTable.rows({selected: true}).deselect();
        });
    };

    macDeleteBtn.on('click', function(e) {
        e.preventDefault();
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            opts = {
                toRemove: Common.getSelectedIds(macsTable),
                removeMacs: 1
            };
        $.apiCall(method, action, opts, function(err) {
            disableMacButtons(false);
            macsTable.draw(false);
            if (err) {
                return;
            }
            macsTable.rows({selected: true}).deselect();
        });
    });
    macImageIgnoreBtn.on('click', function(e) {
        e.preventDefault();
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            opts = {
                imageIgnore: Common.getSelectedIds(macsTable),
                markimageignore: 1
            };
        $.apiCall(method, action, opts, function(err) {
            disableMacButtons(false);
            macsTable.draw(false);
            if (err) {
                return;
            }
            macsTable.rows({selected: true}).deselect();
        });
    });
    macImageUnignoreBtn.on('click', function(e) {
        e.preventDefault();
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            opts = {
                imageIgnore: Common.getSelectedIds(macsTable),
                markimageunignore: 1
            };
        $.apiCall(method, action, opts, function(err) {
            disableMacButtons(false);
            macsTable.draw(false);
            if (err) {
                return;
            }
            macsTable.rows({selected: true}).deselect();
        });
    });
    macClientIgnoreBtn.on('click', function(e) {
        e.preventDefault();
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            opts = {
                clientIgnore: Common.getSelectedIds(macsTable),
                markclientignore: 1
            };
        $.apiCall(method, action, opts, function(err) {
            disableMacButtons(false);
            macsTable.draw(false);
            if (err) {
                return;
            }
            macsTable.rows({selected: true}).deselect();
        });
    });
    macClientUnignoreBtn.on('click', function(e) {
        e.preventDefault();
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            opts = {
                clientIgnore: Common.getSelectedIds(macsTable),
                markclientunignore: 1
            };
        $.apiCall(method, action, opts, function(err) {
            disableMacButtons(false);
            macsTable.draw(false);
            if (err) {
                return;
            }
            macsTable.rows({selected: true}).deselect();
        });
    });
    macPendingBtn.on('click', function(e) {
        e.preventDefault();
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            opts = {
                pending: Common.getSelectedIds(macsTable),
                markpending: 1
            };
        $.apiCall(method, action, opts, function(err) {
            disableMacButtons(false);
            macsTable.draw(false);
            if (err) {
                return;
            }
            macsTable.rows({selected: true}).deselect();
        });
    });
    macUnpendingBtn.on('click', function(e) {
        e.preventDefault();
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            opts = {
                pending: Common.getSelectedIds(macsTable),
                markunpending: 1
            };
        $.apiCall(method, action, opts, function(err) {
            disableMacButtons(false);
            macsTable.draw(false);
            if (err) {
                return;
            }
            macsTable.rows({selected: true}).deselect();
        });
    });

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
        var taskName = $(this).text();
        var method = $(this).attr('href');

        // Show Modal loading
        $('.task-name').text('Loading...');
        $('#task-form-holder').html("Loading, please wait...");
        Common.setLoading($('#task-modal .modal-dialog'), true);
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
                    Common.setLoading($('#task-modal .modal-dialog'), false);
                    $('.task-name').text(taskName);
                    // END: Hide modal loading

                    var scheduleType = $('input[name="scheduleType"]'),
                        hostDeployForm = $('#host-deploy-form'),
                        minutes = $('#cronMin', hostDeployForm),
                        hours = $('#cronHour', hostDeployForm),
                        dom = $('#cronDom', hostDeployForm),
                        month = $('#cronMonth', hostDeployForm),
                        dow = $('#cronDow', hostDeployForm),
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
                        hostDeployForm.processForm(function(err) {
                            if (err) {
                                return;
                            }
                            taskModal.modal('hide');
                        });
                    });
                    taskModal.on('hidden.bs.modal', function(e) {
                        hostDeployForm.remove();
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
                    taskModal.modal('hide');
                    $.notifyFromAPI(jqXHR.responseJSON, true);
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
        onPrintersSelect(printersTable.rows({selected: true}));
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
        $.apiCall(method,action, opts, function(err) {
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
        printerConfigForm.processForm(function(err) {
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

        $.apiCall(method,action,opts,function(err) {
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

    printerRemoveBtn.on('click', function() {
        $('#printerDelModal').modal('show');
    });
    $('#confirmprinterDeleteModal').on('click', function(e) {
        Common.deleteAssociated(printersTable, printerRemoveBtn.attr('action'), function(err) {
            if (err) {
                return;
            }
            $('#printerDelModal').modal('hide');
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
        $.apiCall(method,action,opts,function(err) {
            snapinsAddBtn.prop('disabled', false);
            snapinsRemoveBtn.prop('disabled', false);
            if (err) {
                return;
            }
            snapinsTable.draw(false);
            snapinsTable.rows({selected: true}).deselect();
        });
    });

    snapinsRemoveBtn.on('click', function() {
        $('#snapinDelModal').modal('show');
    });
    $('#confirmsnapinDeleteModal').on('click', function(e) {
        Common.deleteAssociated(snapinsTable, snapinsRemoveBtn.attr('action'), function(err) {
            if (err) {
                return;
            }
            $('#snapinDelModal').modal('hide');
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
                updatemodulessel: 1,
                enablemodules: toEnable,
            };
        $.each($('.associated:checked'), function() {
            toEnable.push(this.value);
        });
        $.apiCall(method,action,opts,function(err) {
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
        $.apiCall(method,action,opts,function(err) {
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
        $.apiCall(method,action,opts,function(err) {
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
        form.processForm(function(err) {
            modulesDispBtn.prop('disabled', false);
        });
    });
    modulesAloBtn.on('click', function(e) {
        e.preventDefault();
        var form = $('#host-alo');
        modulesAloBtn.prop('disabled', true);
        form.processForm(function(err) {
            modulesAloBtn.prop('disabled', false);
        });
    });
    modulesEnforceBtn.on('click', function(e) {
        e.preventDefault();
        var form = $('#host-enforce');
        modulesEnforceBtn.prop('disabled', true);
        form.processForm(function(err) {
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
        $.apiCall(method,action,opts,function(err) {
            groupsAddBtn.prop('disabled', false);
            groupsRemoveBtn.prop('disabled', false);
            if (err) {
                return;
            }
            groupsTable.draw(false);
            groupsTable.rows({selected: true}).deselect();
        });
    });

    groupsRemoveBtn.on('click', function() {
        $('#groupDelModal').modal('show');
    });
    $('#confirmgroupDeleteModal').on('click', function(e) {
        Common.deleteAssociated(groupsTable, groupsRemoveBtn.attr('action'), function(err) {
            if (err) {
                return;
            }
            $('#groupDelModal').modal('hide');
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
        inventoryForm.processForm(function(err) {
            inventoryFormBtn.prop('disabled', false);
        });
    });

    // ---------------------------------------------------------------
    // LOGIN HISTORY TAB
    var loginTable = Common.registerTable($('#host-login-table'), null, {
        columns: [
            {data: 'createdTime'},
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
