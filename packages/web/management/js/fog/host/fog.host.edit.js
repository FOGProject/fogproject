(function($) {
  var escapeHtml = function(str) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
  }
    // ---------------------------------------------------------------
    // GENERAL TAB
    var generalFormBtn = $('#general-send'),
        generalDeleteBtn = $('#general-delete'),
        resetEncryptionBtn = $('#reset-encryption-data'),
        resetEncryptionModal = $('#resetencryptionmodal'),
        resetEncryptionCancelBtn = $('#resetencryptionCancel'),
        resetEncryptionConfirmBtn = $('#resetencryptionConfirm');

    // Input masking and validation checks
    $('#host').inputmask({mask: Common.masks.hostname, repeat: 15});
    $('#mac').inputmask({mask: Common.masks.mac});
    $.initProductKeyField('#key');

    $.registerGeneralTab({
        nameInputSel: '#host',
        formSel: '#host-general-form'
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
            {data: 'description'},
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
                    if (type !== 'display') {
                        return data;
                    }
                    return (data || '') + macVendorIcon(row.mac_vendor);
                },
                targets: 0
            },
            {
                responsivePriority: -1,
                render: function(data, type, row) {
                    return data;
                },
                targets: 1
            },
            {
                render: function(data, type, row) {
                    var checkval = '';
                    if (data > 0) {
                        checkval = ' checked';
                    }
                    return '<div class="form-check">'
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
                targets: 2
            },
            {
                render: function(data, type, row) {
                    var checkval = '';
                    if (data > 0) {
                        checkval = ' checked';
                    }
                    return '<div class="form-check">'
                        + '<input type="checkbox" class="imageIgnore" name="imageIgnore[]" id="imageIgnore_'

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
                    return '<div class="form-check">'
                        + '<input type="checkbox" class="clientIgnore" name="clientIgnore[]" id="clientIgnore_'

                        + row.id
                        + '" value="'
                        + row.id
                        + '"'
                        + checkval
                        + '/>'
                        + '</div>';
                },
                targets: 4
            },
            {
                render: function(data, type, row) {
                    var checkval = '';
                    if (data > 0) {
                        checkval = ' checked';
                    }
                    return '<div class="form-check">'
                        + '<input type="checkbox" class="pending" name="pending[]" id="pending_'

                        + row.id
                        + '" value="'
                        + row.id
                        + '"'
                        + checkval
                        + '/>'
                        + '</div>';
                },
                targets: 5
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
        $('#host-macaddresses-table input.primary').on('change', onMacsRadioSelect);
        $('#host-macaddresses-table input.imageIgnore').on('change', onMacsCheckboxSelect);
        $('#host-macaddresses-table input.clientIgnore').on('change', onMacsCheckboxSelect);
        $('#host-macaddresses-table input.pending').on('change', onMacsCheckboxSelect);
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
    $('#host-macaddresses-table input.primary').on('change', onMacsRadioSelect);
    // Setup checkbox watchers.
    $('#host-macaddresses-table input.imageIgnore').on('change', onMacsCheckboxSelect);
    $('#host-macaddresses-table input.clientIgnore').on('change', onMacsCheckboxSelect);
    $('#host-macaddresses-table input.pending').on('change', onMacsCheckboxSelect);

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

                    $('#checkdebug').on('change', function(e) {
                        if (!this.checked) {
                            return;
                        }
                        $('.hideFromDebug,.delayedinput,.croninput').addClass('d-none');
                        $('.instant').prop('checked', true).trigger('change');
                    }).on('change', function(e) {
                        if (this.checked) {
                            return;
                        }
                        $('.hideFromDebug').removeClass('d-none');
                    });
                    $('input[name="scheduleType"]').on('change', function(e) {
                        switch (this.value) {
                            case 'instant':
                                $('.delayedinput,.croninput').addClass('d-none');
                                break;
                            case 'single':
                                $('.delayedinput').removeClass('d-none');
                                $('.croninput').addClass('d-none');
                                $('#delayedinput').datetimepicker('show');
                                break;
                            case 'cron':
                                $('.delayedinput').addClass('d-none');
                                $('.croninput').removeClass('d-none');
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
    var hostGroupsTable = $.registerAssociationTab({
        slug: 'host-group',
        item: 'group',
        sub: 'getGroupsList'
    });
    // Create-and-associate button/modal on the action row. Inert unless the
    // server rendered them, which it only does for users who can create groups.
    $.registerCreateAndAssociate('host-group', hostGroupsTable);

    // ---------------------------------------------------------------
    // PRINTER TAB
    //
    // Association area — the default-printer selector (below) lists only
    // associated printers, so refresh it on every redraw via onDraw. That fires
    // on the post-save redraw $.checkItemUpdate triggers, which lands after the
    // association commits (no race).
    var hostPrintersTable = $.registerAssociationTab({
        slug: 'host-printer',
        item: 'printer',
        sub: 'getPrintersList',
        onDraw: hostPrinterDefaultSelectorUpdate
    });
    // The printer create form is not inert markup -- it hides every type
    // section but the selected one -- and that JS lives on the printer pages,
    // which do not load here. onForm runs the same initialiser against the
    // fetched form; node:'printer' because the helper would otherwise ask
    // ?node=host for getPrinterInfo. validate matches what the printer pages
    // pass, so the hidden sections are not validated.
    $.registerCreateAndAssociate('host-printer', hostPrintersTable, {
        onForm: function(form) {
            form.initPrinterFormUI({node: 'printer'});
        },
        validate: ':input:visible'
    });

    // Default area
    var hostPrinterDefaultUpdateBtn = $('#host-printer-default-send'),
        hostPrinterDefaultSelector = $('#printerselector');
    function hostPrinterDefaultSelectorUpdate() {
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
    }

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
    // The snapin run-order panel (below) mirrors the associated set, so refresh
    // it after every add/remove/toggle commits via afterCommit.
    var hostSnapinsTable = $.registerAssociationTab({
        slug: 'host-snapin',
        item: 'snapin',
        sub: 'getSnapinsList',
        afterCommit: loadSnapinOrder
    });
    // The snapin create form drives its command builder from JS that normally
    // runs on the snapin pages, which do not load here. wirePackTypes matches
    // the snapin ADD page, since this modal renders the same _addFields() form.
    // The upload is multipart, which needs nothing extra: processForm() posts a
    // FormData built from the form, so the file rides along.
    $.registerCreateAndAssociate('host-snapin', hostSnapinsTable, {
        onForm: function(form) {
            form.initSnapinCommandUI({wirePackTypes: true});
        }
    });

    // ---------------------------------------------------------------
    // SNAPIN RUN ORDER
    var hostSnapinOrderList = $('#host-snapin-order-list'),
        hostSnapinOrderSaveBtn = $('#host-snapin-order-save');

    function updateSnapinOrderPositions() {
        hostSnapinOrderList.children('li').each(function(i) {
            $(this).find('.snapin-order-pos').text((i + 1) + '. ');
        });
    }

    function renderSnapinOrder(items) {
        hostSnapinOrderList.empty();
        if (!items || items.length === 0) {
            hostSnapinOrderList.append(
                $('<li>', {'class': 'list-group-item text-muted'})
                    .text('No snapins associated.')
            );
            hostSnapinOrderSaveBtn.prop('disabled', true);
            return;
        }
        hostSnapinOrderSaveBtn.prop('disabled', false);
        $.each(items, function(i, item) {
            var controls = $('<span>', {'class': 'float-end'})
                .append(
                    $('<button>', {
                        'type': 'button',
                        'class': 'btn btn-sm btn-secondary snapin-order-up',
                        'title': 'Move up'
                    }).append($('<i>', {'class': 'fas fa-arrow-up'})),
                    ' ',
                    $('<button>', {
                        'type': 'button',
                        'class': 'btn btn-sm btn-secondary snapin-order-down',
                        'title': 'Move down'
                    }).append($('<i>', {'class': 'fas fa-arrow-down'}))
                );
            hostSnapinOrderList.append(
                $('<li>', {
                    'class': 'list-group-item',
                    'data-id': item.id
                }).append(
                    $('<span>', {'class': 'snapin-order-pos'}),
                    $('<span>', {'class': 'snapin-order-name'}).text(item.name),
                    controls
                )
            );
        });
        updateSnapinOrderPositions();
    }

    function loadSnapinOrder() {
        $.ajax({
            url: '../management/index.php?node=' + Common.node
                + '&sub=getSnapinOrderList&id=' + Common.id,
            dataType: 'json',
            success: function(data) {
                renderSnapinOrder(data && data.data ? data.data : []);
            }
        });
    }

    hostSnapinOrderList.on('click', '.snapin-order-up', function(e) {
        e.preventDefault();
        var li = $(this).closest('li'),
            prev = li.prev('li');
        if (prev.length) {
            li.insertBefore(prev);
            updateSnapinOrderPositions();
        }
    });

    hostSnapinOrderList.on('click', '.snapin-order-down', function(e) {
        e.preventDefault();
        var li = $(this).closest('li'),
            next = li.next('li');
        if (next.length) {
            li.insertAfter(next);
            updateSnapinOrderPositions();
        }
    });

    hostSnapinOrderSaveBtn.on('click', function(e) {
        e.preventDefault();
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            order = [];
        hostSnapinOrderList.children('li').each(function() {
            var id = $(this).attr('data-id');
            if (id) {
                order.push(id);
            }
        });
        if (order.length === 0) {
            return;
        }
        $.apiCall(method, action, {snapinorder: order});
    });

    loadSnapinOrder();

    // FOG CLIENT AREA
    // ---------------------------------------------------------------
    // CLIENT SETTINGS TAB
    // Association area — col 0 is the module name (not a mainLink), given
    // responsivePriority so it never collapses.
    var hostModulesTable = $.registerAssociationTab({
        slug: 'host-module',
        item: 'module',
        sub: 'getModulesList',
        columns: [
            {data: 'name'},
            {data: 'association'}
        ],
        columnDefs: [
            {
                responsivePriority: -1,
                targets: 0
            }
        ]
    });

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

    // ---------------------------------------------------------------
    // ACTIVE DIRECTORY TAB
    var ADForm = $('#active-directory-form'),
        ADFormBtn = $('#ad-send'),
        ADClearBtn = $('#ad-clear'),
        ADJoinDomain = $('#adEnabled');

    ADJoinDomain.on('change', function(e) {
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
            restoreMap.push({checkbox: true, e: e, val: $(e)[0].checked});
            $(e).prop('checked', false).trigger('change');
            $(e).prop('disabled', true);
        });

        ADForm.find('input[type=text], input[type=password], textarea').val('');
        ADForm.find('input[type=checkbox]').prop('checked', false).trigger('change');

        ADForm.processForm(function(err) {
            ADClearBtn.prop('disabled', false);
            ADFormBtn.prop('disabled', false);
            for (var i = 0; i < restoreMap.length; i++) {
                field = restoreMap[i];
                if (field.checkbox) {
                    if (err) {
                        $(field.e).prop('checked', !!field.val).trigger('change');
                    }
                    $(field.e).prop('disabled', false);
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
            ondemand.prop('checked', false).trigger('change');
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
                    return escapeHtml(row.min)
                        + ' '
                        + escapeHtml(row.hour)
                        + ' '
                        + escapeHtml(row.dom)
                        + ' '
                        + escapeHtml(row.month)
                        + ' '
                        + escapeHtml(row.dow);
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
    // Absent for a user without usertracking.view -- the tab is not
    // rendered at all (ADR 0023), so there is nothing to register.
    var $hostLoginHist = $('#host-login-history-table');
    var hostHistoryLoginTable = !$hostLoginHist.length ? null :
      $hostLoginHist.registerTable(null, {
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
        // taskLog since imagingLog was retired (ADR 0022 decision 3).
        // statename is added by Route's tasklog column case; the rest are
        // the model's own keys.
        columns: [
            {data: 'createdBy'},
            {data: 'createdTime'},
            {data: 'statename'},
            {data: 'taskTypeName'},
            {data: 'imageName'}
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
        if (hostHistoryLoginTable) {
          hostHistoryLoginTable.search(Common.search).draw();
        }
        hostHistoryImageTable.search(Common.search).draw();
        hostHistorySnapinTable.search(Common.search).draw();
    }

    // ---------------------------------------------------------------
    // SITE TAB
    // Single dropdown, so registerSelectTab rather than the grid wiring.
    // node:'site' adds the create-and-select button when the user holds
    // site.create; without it the tab is just the select and Update.
    $.registerSelectTab({
        slug: 'host-site',
        send: 'site-send',
        node: 'site'
    });
})(jQuery);
