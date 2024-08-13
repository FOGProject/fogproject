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
        },
        generalForm = $('#host-general-form'),
        generalFormBtn = $('#general-send'),
        generalDeleteBtn = $('#general-delete'),
        generalDeleteModal = $('#deleteModal'),
        generalDeleteModalConfirm = $('#confirmDeleteModal'),
        generalDeleteModalCancel = $('#closeDeleteModal'),
        resetEncryptionBtn = $('#reset-encryption-data'),
        resetEncryptionModal = $('#resetencryptionmodal'),
        resetEncryptionCancelBtn = $('#resetencryptionCancel'),
        resetEncryptionConfirmBtn = $('#resetencryptionConfirm');

    // Input masking and validation checks
    $('#host').inputmask({mask: Common.masks.hostname, repeat: 15});
    $('#mac').inputmask({mask: Common.masks.mac});
    $('#key').inputmask({mask: Common.masks.productKey});

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
            // Enable our general form buttons.
            generalFormBtn.prop('disabled', false);
            generalDeleteBtn.prop('disabled', false);
            resetEncryptionBtn.prop('disabled', false);
            if (err) {
                return;
            }
            // Hide modal
            resetEncryptionModal.modal('hide');
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
    var macsTable = macTable.registerTable(onMacsSelect, {
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
                if (err) {
                    return;
                }
                macsTable.draw(false);
                macsTable.rows({selected: true}).deselect();
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
            if (err) {
                return;
            }
            macsTable.draw(false);
            macsTable.rows({selected: true}).deselect();
        });
    };

    macDeleteBtn.on('click', function(e) {
        e.preventDefault();
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            opts = {
                toRemove: $.getSelectedIds(macsTable),
                removeMacs: 1
            };
        $.apiCall(method, action, opts, function(err) {
            disableMacButtons(false);
            if (err) {
                return;
            }
            macsTable.draw(false);
            macsTable.rows({selected: true}).deselect();
        });
    });
    macImageIgnoreBtn.on('click', function(e) {
        e.preventDefault();
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            opts = {
                imageIgnore: $.getSelectedIds(macsTable),
                markimageignore: 1
            };
        $.apiCall(method, action, opts, function(err) {
            disableMacButtons(false);
            if (err) {
                return;
            }
            macsTable.draw(false);
            macsTable.rows({selected: true}).deselect();
        });
    });
    macImageUnignoreBtn.on('click', function(e) {
        e.preventDefault();
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            opts = {
                imageIgnore: $.getSelectedIds(macsTable),
                markimageunignore: 1
            };
        $.apiCall(method, action, opts, function(err) {
            disableMacButtons(false);
            if (err) {
                return;
            }
            macsTable.draw(false);
            macsTable.rows({selected: true}).deselect();
        });
    });
    macClientIgnoreBtn.on('click', function(e) {
        e.preventDefault();
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            opts = {
                clientIgnore: $.getSelectedIds(macsTable),
                markclientignore: 1
            };
        $.apiCall(method, action, opts, function(err) {
            disableMacButtons(false);
            if (err) {
                return;
            }
            macsTable.draw(false);
            macsTable.rows({selected: true}).deselect();
        });
    });
    macClientUnignoreBtn.on('click', function(e) {
        e.preventDefault();
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            opts = {
                clientIgnore: $.getSelectedIds(macsTable),
                markclientunignore: 1
            };
        $.apiCall(method, action, opts, function(err) {
            disableMacButtons(false);
            if (err) {
                return;
            }
            macsTable.draw(false);
            macsTable.rows({selected: true}).deselect();
        });
    });
    macPendingBtn.on('click', function(e) {
        e.preventDefault();
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            opts = {
                pending: $.getSelectedIds(macsTable),
                markpending: 1
            };
        $.apiCall(method, action, opts, function(err) {
            disableMacButtons(false);
            if (err) {
                return;
            }
            macsTable.draw(false);
            macsTable.rows({selected: true}).deselect();
        });
    });
    macUnpendingBtn.on('click', function(e) {
        e.preventDefault();
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            opts = {
                pending: $.getSelectedIds(macsTable),
                markunpending: 1
            };
        $.apiCall(method, action, opts, function(err) {
            disableMacButtons(false);
            if (err) {
                return;
            }
            macsTable.draw(false);
            macsTable.rows({selected: true}).deselect();
        });
    });

    // Setup primary mac watcher.
    $('#host-macaddresses-table input.primary').on('ifClicked', onMacsRadioSelect);
    // Setup checkbox watchers.
    $('#host-macaddresses-table input.imageIgnore').on('ifClicked', onMacsCheckboxSelect);
    $('#host-macaddresses-table input.clientIgnore').on('ifClicked', onMacsCheckboxSelect);
    $('#host-macaddresses-table input.pending').on('ifClicked', onMacsCheckboxSelect);

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
                        hostDeployForm = '#host-deploy-form',
                        minutes = $('#cronMin', $(hostDeployForm)),
                        hours = $('#cronHour', $(hostDeployForm)),
                        dom = $('#cronDom', $(hostDeployForm)),
                        month = $('#cronMonth', $(hostDeployForm)),
                        dow = $('#cronDow', $(hostDeployForm)),
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
                        $(hostDeployForm).processForm(function(err) {
                            if (err) {
                                return;
                            }
                            taskModal.modal('hide');
                        });
                    });
                    taskModal.on('hidden.bs.modal', function(e) {
                        $(hostDeployForm).remove();
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
                    $.notifyFromAPI(jqXHR.responseJSON, jqXHR);
                }
            });
        });
    });



    // ASSOCIATIONS
    // ---------------------------------------------------------------
    // GROUP ASSOCIATION TAB
    var hostGroupUpdateBtn = $('#host-group-send'),
        hostGroupRemoveBtn = $('#host-group-remove'),
        hostGroupDeleteConfirmBtn = $('#confirmgroupDeleteModal');

    function disableGroupButtons(disable) {
        hostGroupUpdateBtn.prop('disabled', disable);
        hostGroupRemoveBtn.prop('disabled', disable);
    }

    function onGroupSelect(selected) {
        var disabled = selected.count() == 0;
        disableGroupButtons(disabled);
    }

    hostGroupUpdateBtn.on('click', function(e) {
        e.preventDefault();
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            rows = hostGroupsTable.rows({selected: true}),
            toAdd = $.getSelectedIds(hostGroupsTable),
            opts = {
                confirmadd: 1,
                additems: toAdd
            };
        $.apiCall(method,action,opts,function(err) {
            disableGroupButtons(false);
            if (err) {
                return;
            }
            hostGroupsTable.draw(false);
            hostGroupsTable.rows({selected: true}).deselect();
        })
    });

    hostGroupRemoveBtn.on('click', function(e) {
        e.preventDefault();
        $('#groupDelModal').modal('show');
    });

    var hostGroupsTable = $('#host-group-table').registerTable(onGroupSelect, {
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
                        + '<input type="checkbox" class="associated" name="associate[]" id="hostGroupAssoc_'
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

    hostGroupDeleteConfirmBtn.on('click', function(e) {
        $.deleteAssociated(hostGroupsTable, hostGroupUpdateBtn.attr('action'), function(err) {
            $('#groupDelModal').modal('hide');
            if (err) {
                return;
            }
            hostGroupsTable.draw(false);
            hostGroupsTable.rows({selected: true}).deselect();
        });
    });

    hostGroupsTable.on('draw', function() {
        Common.iCheck('#host-group-table input');
        $('#host-group-table input.associated').on('ifChanged', onHostGroupCheckboxSelect);
        onGroupSelect(hostGroupsTable.rows({selected: true}));
    });

    var onHostGroupCheckboxSelect = function(e) {
        $.checkItemUpdate(hostGroupsTable, this, e, hostGroupUpdateBtn);
    };

    // ---------------------------------------------------------------
    // PRINTER TAB
    //
    // Association area
    var hostPrinterUpdateBtn = $('#host-printer-send'),
        hostPrinterRemoveBtn = $('#host-printer-remove'),
        hostPrinterDeleteConfirmBtn = $('#confirmprinterDeleteModal');

    function disablePrinterButtons(disable) {
        hostPrinterUpdateBtn.prop('disabled', disable);
        hostPrinterRemoveBtn.prop('disabled', disable);
    }

    function onPrinterSelect(selected) {
        var disabled = selected.count() == 0;
        disablePrinterButtons(disabled);
    }

    hostPrinterUpdateBtn.on('click', function(e) {
        e.preventDefault();
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            rows = hostPrintersTable.rows({selected: true}),
            toAdd = $.getSelectedIds(hostPrintersTable),
            opts = {
                confirmadd: 1,
                additems: toAdd
            };
        $.apiCall(method,action,opts,function(err) {
            disablePrinterButtons(false);
            if (err) {
                return;
            }
            hostPrintersTable.draw(false);
            hostPrintersTable.rows({selected: true}).deselect();
            setTimeout(hostPrinterDefaultSelectorUpdate, 1000);
        });
    });

    hostPrinterRemoveBtn.on('click', function(e) {
        e.preventDefault();
        $('#printerDelModal').modal('show');
    });

    var hostPrintersTable = $('#host-printer-table').registerTable(onPrinterSelect, {
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
                        + '<input type="checkbox" class="associated" name="associate[]" id="hostPrinterAssoc_'
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
                + '&sub=getPrintersList&id='
                + Common.id,
            type: 'post'
        }
    });

    hostPrinterDeleteConfirmBtn.on('click', function(e) {
        $.deleteAssociated(hostPrintersTable, hostPrinterUpdateBtn.attr('action'), function(err) {
            $('#printerDelModal').modal('hide');
            if (err) {
                return;
            }
            hostPrintersTable.draw(false);
            hostPrintersTable.rows({selected: true}).deselect();
            setTimeout(hostPrinterDefaultSelectorUpdate, 1000);
        });
    });

    hostPrintersTable.on('draw', function() {
        Common.iCheck('#host-printer-table input');
        $('#host-printer-table input.associated').on('ifChanged', onHostPrinterCheckboxSelect);
        onPrinterSelect(hostPrintersTable.rows({selected: true}));
        hostPrinterDefaultSelectorUpdate();
    });

    var onHostPrinterCheckboxSelect = function(e) {
        $.checkItemUpdate(hostPrintersTable, this, e, hostPrinterUpdateBtn);
        setTimeout(hostPrinterDefaultSelectorUpdate, 1000);
    };

    // Default area
    var hostPrinterDefaultUpdateBtn = $('#host-printer-default-send'),
        hostPrinterDefaultSelector = $('#printerselector'),
        hostPrinterDefaultSelectorUpdate = function() {
            var url = '../management/index.php?node='
                + Common.node
                + '&sub=getHostDefaultPrinters&id='
                + Common.id;
            Pace.ignore(function() {
                hostPrinterDefaultSelector.html('');
                $.get(url, function(data) {
                    hostPrinterDefaultSelector.html(data.content);
                    hostPrinterDefaultUpdateBtn.prop('disabled', data.disablebtn);
                }, 'json');
            });
        };

    function disablePrinterDefaultButtons(disable) {
        hostPrinterDefaultUpdateBtn.prop('disabled', disable);
    }

    hostPrinterDefaultSelectorUpdate();

    hostPrinterDefaultUpdateBtn.on('click', function(e) {
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
    var hostPrinterConfigBtn = $('#printer-config-send');

    hostPrinterConfigBtn.on('click', function(e) {
        e.preventDefault();
        hostPrinterConfigBtn.prop('disabled', true);
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            opts = {
                confirmlevelup: 1,
                level: $('.checked input[name="level"]').val()
            };
        $.apiCall(method,action,opts,function(err) {
            hostPrinterConfigBtn.prop('disabled', false);
            if (err) {
                return;
            }
        });
    });

    // ---------------------------------------------------------------
    // SNAPINS TAB
    var hostSnapinUpdateBtn = $('#host-snapin-send'),
        hostSnapinRemoveBtn = $('#host-snapin-remove'),
        hostSnapinDeleteConfirmBtn = $('#confirmsnapinDeleteModal');

    function disableSnapinButtons(disable) {
        hostSnapinUpdateBtn.prop('disabled', disable);
        hostSnapinRemoveBtn.prop('disabled', disable);
    }

    function onSnapinSelect(selected) {
        var disabled = selected.count() == 0;
        disableSnapinButtons(disabled);
    }

    hostSnapinUpdateBtn.on('click', function(e) {
        e.preventDefault();
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            rows = hostSnapinsTable.rows({selected: true}),
            toAdd = $.getSelectedIds(hostSnapinsTable),
            opts = {
                confirmadd: 1,
                additems: toAdd
            };
        $.apiCall(method,action,opts,function(err) {
            disableSnapinButtons(false);
            if (err) {
                return;
            }
            hostSnapinsTable.draw(false);
            hostSnapinsTable.rows({selected: true}).deselect();
        })
    });

    hostSnapinRemoveBtn.on('click', function(e) {
        e.preventDefault();
        $('#snapinDelModal').modal('show');
    });

    var hostSnapinsTable = $('#host-snapin-table').registerTable(onSnapinSelect, {
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
                        + '<input type="checkbox" class="associated" name="associate[]" id="hostSnapinAssoc_'
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

    hostSnapinDeleteConfirmBtn.on('click', function(e) {
        $.deleteAssociated(hostSnapinsTable, hostSnapinUpdateBtn.attr('action'), function(err) {
            $('#snapinDelModal').modal('hide');
            if (err) {
                return;
            }
            hostSnapinsTable.draw(false);
            hostSnapinsTable.rows({selected: true}).deselect();
        });
    });

    hostSnapinsTable.on('draw', function() {
        Common.iCheck('#host-snapin-table input');
        $('#host-snapin-table input.associated').on('ifChanged', onHostSnapinCheckboxSelect);
        onSnapinSelect(hostSnapinsTable.rows({selected: true}));
    });

    var onHostSnapinCheckboxSelect = function(e) {
        $.checkItemUpdate(hostSnapinsTable, this, e, hostSnapinUpdateBtn);
    };

    // FOG CLIENT AREA
    // ---------------------------------------------------------------
    // CLIENT SETTINGS TAB
    var hostModuleUpdateBtn = $('#host-module-send'),
        hostModuleRemoveBtn = $('#host-module-remove'),
        hostModuleDeleteConfirmBtn = $('#confirmmoduleDeleteModal');

    // Association area
    function disableModuleButtons(disable) {
        hostModuleUpdateBtn.prop('disabled', disable);
        hostModuleRemoveBtn.prop('disabled', disable);
    }

    function onModuleSelect(selected) {
        var disabled = selected.count() == 0;
        disableModuleButtons(disabled);
    }

    hostModuleUpdateBtn.on('click', function(e) {
        e.preventDefault();
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            rows = hostModulesTable.rows({selected: true}),
            toAdd = $.getSelectedIds(hostModulesTable),
            opts = {
                confirmadd: 1,
                additems: toAdd
            };
        $.apiCall(method,action,opts,function(err) {
            disableModuleButtons(false);
            if (err) {
                return;
            }
            hostModulesTable.draw(false);
            hostModulesTable.rows({selected: true}).deselect();
        });
    });

    hostModuleRemoveBtn.on('click', function(e) {
        e.preventDefault();
        $('#moduleDelModal').modal('show');
    });

    var hostModulesTable = $('#host-module-table').registerTable(onModuleSelect, {
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
                targets: 0
            },
            {
                render: function(data, type, row) {
                    var checkval = '';
                    if (row.association === 'associated') {
                        checkval = ' checked';
                    }
                    return '<div class="checkbox">'
                        + '<input type="checkbox" class="associated" name="associate[]" id="hostModuleAssoc_'
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

    hostModuleDeleteConfirmBtn.on('click', function(e) {
        $.deleteAssociated(hostModulesTable, hostModuleUpdateBtn.attr('action'), function(err) {
            $('#moduleDelModal').modal('hide');
            if (err) {
                return;
            }
            hostModulesTable.draw(false);
            hostModulesTable.rows({selected: true}).deselect();
        });
    });

    hostModulesTable.on('draw', function() {
        Common.iCheck('#host-module-table input');
        $('#host-module-table input.associated').on('ifChanged', onHostModuleCheckboxSelect);
        onModuleSelect(hostModulesTable.rows({selected: true}));
    });

    var onHostModuleCheckboxSelect = function(e) {
        $.checkItemUpdate(hostModulesTable, this, e, hostModuleUpdateBtn);
    };

    // Display manager area
    var hostModuleDisplaymanBtn = $('#host-displayman-send'),
        hostModuleDisplayForm = $('#host-displayman-form');

    function disableModuleDisplayButtons(disable) {
        hostModuleDisplaymanBtn.prop('disabled', disable);
    }

    hostModuleDisplayForm.on('submit', function(e) {
        e.preventDefault();
    });

    hostModuleDisplaymanBtn.on('click', function(e) {
        e.preventDefault();
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            opts = {
                confirmdisplaysend: 1,
                x: $('#x').val(),
                y: $('#y').val(),
                r: $('#r').val()
            };
        disableModuleDisplayButtons(true);
        $.apiCall(method,action,opts,function(err) {
            disableModuleDisplayButtons(false);
            if (err) {
                return;
            }
            var url = '../management/index.php?node='
                + Common.node
                + '&sub=getHostDisplayManVals'
                + '&id='
                + Common.id;
            Pace.ignore(function() {
                $.get(url, function(data) {
                    $('#x').val(data.x);
                    $('#y').val(data.y);
                    $('#r').val(data.r);
                }, 'json');
            });
        });
    });

    // Auto log out area
    var hostModuleAloBtn = $('#host-alo-send'),
        hostModuleAloForm = $('#host-alo-form');

    function disableModuleAloButtons(disable) {
        hostModuleAloBtn.prop('disabled', disable);
    }

    hostModuleAloForm.on('submit', function(e) {
        e.preventDefault();
    });

    hostModuleAloBtn.on('click', function(e) {
        e.preventDefault();
        disableModuleAloButtons(true);
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            opts = {
                confirmalosend: 1,
                tme: $('#tme').val()
            };
        $.apiCall(method,action,opts,function(err) {
            disableModuleAloButtons(false);
            if (err) {
                return;
            }
            var url = '../management/index.php?node='
                + Common.node
                + '&sub=getHostAloVals'
                + '&id='
                + Common.id;
            Pace.ignore(function() {
                $.get(url, function(data) {
                    $('#tme').val(data.tme);
                }, 'json');
            });
        });
    });

    // Host enforce ad join reboot and hostname changes area
    var hostModuleEnforceBtn = $('#host-enforce-send'),
        hostModuleEnforceForm = $('#host-enforce-form');

    function disableModuleEnforceButtons(disable) {
        hostModuleEnforceBtn.prop('disabled', disable);
    }

    hostModuleEnforceForm.on('submit', function(e) {
        e.preventDefault();
    })

    hostModuleEnforceBtn.on('click', function(e) {
        e.preventDefault();
        disableModuleEnforceButtons(true);
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            opts = {
                confirmenforcesend: 1,
                enforce: $('#enforce').iCheck('update')[0].checked
            };
      console.log(opts);
        $.apiCall(method,action,opts,function(err) {
            disableModuleEnforceButtons(false);
        });
    });

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
        instantForm = $('#host-powermanagement-instant-form'),
        scheduleModal = $('#scheduleModal'),
        scheduleBtn = $('#scheduleBtn'),
        scheduleModalCancelBtn = $('#scheduleCancelBtn'),
        scheduleModalCreateBtn = $('#scheduleCreateBtn'),
        scheduleForm = $('#host-powermanagement-cron-form'),
        pmdelete = $('#pm-delete');

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

    var powermanagementTable = $('#host-powermanagement-table').registerTable(onPMSelect, {
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

    instantBtn.on('click', function(e) {
        e.preventDefault();
        instantModal.modal('show');
    });
    scheduleBtn.on('click', function(e) {
        e.preventDefault();
        scheduleModal.modal('show');
    });
    instantModal.registerModal(
        function(e) {
            instantModalCreateBtn.on('click', function() {
                $(this).prop('disabled', true);
                instantForm.processForm(function(err) {
                    instantModalCreateBtn.prop('disabled', false);
                    if (err) {
                        return;
                    }
                    instantModal.modal('hide');
                    powermanagementTable.draw(false);
                });
            });
        },
        function(e) {
            $(this).modal('hide');
        }
    );
    scheduleModal.registerModal(
        function(e) {
            scheduleModalCreateBtn.on('click', function() {
                $(this).prop('disabled', true);
                scheduleForm.processForm(function(err) {
                    scheduleModalCreateBtn.prop('disabled', false);
                    if (err) {
                        return;
                    }
                    scheduleModal.modal('hide');
                    powermanagementTable.draw(false);
                });
            });
        },
        function(e) {
            $(this).modal('hide');
        }
    );

    pmdelete.on('click', function(e) {
        scheduleBtn.prop('disabled', true);
        instantBtn.prop('disabled', true);


        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            rows = powermanagementTable.rows({selected: true}),
            toDel = $.getSelectedIds(powermanagementTable),
            opts = {
                pmdelete: 1,
                rempowermanagements: toDel
            };
        $.apiCall(method,action,opts,function(err) {
            scheduleBtn.prop('disabled', false);
            instantBtn.prop('disabled', false);
            if (err) {
                return;
            }
            powermanagementTable.draw(false);
            powermanagementTable.rows({selected: true}).deselect();
        });
    });

    // ---------------------------------------------------------------
    // INVENTORY TAB
    var hostInventoryForm = $('#host-inventory-form'),
        hostInventoryUpdateBtn = $('#host-inventory-send');

    hostInventoryForm.on('submit', function(e) {
        e.preventDefault();
    });

    hostInventoryUpdateBtn.on('click', function(e) {
        e.preventDefault();
        $(this).prop('disabled', true);
        var method = hostInventoryForm.attr('method'),
            action = hostInventoryForm.attr('action'),
            opts = {
                confirminventoryadd: 1,
                pu: $('#pu').val(),
                other1: $('#other1').val(),
                other2: $('#other2').val()
            };
        $.apiCall(method,action,opts,function(err) {
            hostInventoryUpdateBtn.prop('disabled', false);
            if (err) {
                return;
            }
        })
    });

    // HISTORY TABS
    // ---------------------------------------------------------------
    // LOGIN HISTORY TAB
    var hostHistoryLoginTable = $('#host-login-history-table').registerTable(null, {
        columns: [
            {data: 'createdTime'},
            {data: 'action'},
            {data: 'username'},
            {data: 'description'}
        ],
        order: [
            [0, 'desc']
        ],
        rowId: 'id',
        processing: true,
        serverSide: true,
        select: false,
        ajax: {
            url: '../management/index.php?node='
                + Common.node
                + '&sub=getLoginHist&id='
                + Common.id,
            type: 'post'
        }
    });

    // ---------------------------------------------------------------
    // IMAGE HISTORY TAB
    var hostHistoryImageTable = $('#host-image-history-table').registerTable(null, {
        columns: [
            {data: 'createdBy'},
            {data: 'start'},
            {data: 'finish'},
            {data: 'diff'},
            {data: 'imageLink'},
            {data: 'type'}
        ],
        order: [
            [1, 'desc']
        ],
        rowId: 'id',
        processing: true,
        serverSide: true,
        select: false,
        ajax: {
            url: '../management/index.php?node='
                + Common.node
                + '&sub=getImageHist&id='
                + Common.id,
            type: 'post'
        }
    });

    // ---------------------------------------------------------------
    // SNAPIN HISTORY TAB
    var hostHistorySnapinTable = $('#host-snapin-history-table').registerTable(null, {
        columns: [
            {data: 'snapinLink'},
            {data: 'checkin'},
            {data: 'complete'},
            {data: 'diff'},
            {data: 'return'}
        ],
        columnDefs: [
            {
                responsivePriority: -1,
                render: function(data, type, row) {
                    console.log(row);
                    return data;
                },
                targets: 0
            },
        ],
        order: [
            [1, 'desc']
        ],
        rowId: 'id',
        processing: true,
        serverSide: true,
        select: false,
        ajax: {
            url: '../management/index.php?node='
                + Common.node
                + '&sub=getSnapinHist&id='
                + Common.id,
            type: 'post'
        }
    });

    // Enable searching
    if (Common.search && Common.search.length > 0) {
        macsTable.search(Common.search).draw();
        // Associations
        hostGroupsTable.search(Common.search).draw();
        hostPrintersTable.search(Common.search).draw();
        hostSnapinsTable.search(Common.search).draw();
        // FOG Client
        hostModulesTable.search(Common.search).draw();
        powermanagementTable.search(Common.search).draw();
        // History
        hostHistoryLoginTable.search(Common.search).draw();
        hostHistorymageTable.search(Common.search).draw();
        hostHistorySnapinTable.search(Common.search).draw();
    }
})(jQuery);
