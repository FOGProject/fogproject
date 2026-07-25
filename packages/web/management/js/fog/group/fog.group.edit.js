(function($) {
    // ---------------------------------------------------------------
    // GENERAL TAB
    var generalFormBtn = $('#general-send'),
        generalDeleteBtn = $('#general-delete'),
        resetEncryptionBtn = $('#reset-encryption-data'),
        resetEncryptionModal = $('#resetencryptionmodal'),
        resetEncryptionCancelBtn = $('#resetencryptionCancel'),
        resetEncryptionConfirmBtn = $('#resetencryptionConfirm'),
        opts = {};

    // Mask for product key.
    $.initProductKeyField('#key');

    $('#andHosts').on('change', function(e) {
        if (!this.checked) {
            opts = {};
            return;
        }
        opts = {andHosts: 1};
    });

    $.registerGeneralTab({
        nameInputSel: '#group',
        formSel: '#group-general-form',
        deleteOpts: function() {
            $('#andHosts').trigger('change');
            return opts;
        }
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
                groupid: Common.id
            };
        $.apiCall(method, action, opts, function(err) {
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
    // IMAGE TAB
    var groupImageUpdateBtn = $('#group-image-send');

    function disableImageButtons(disable) {
        groupImageUpdateBtn.prop('disabled', disable);
    }

    groupImageUpdateBtn.on('click', function(e) {
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

    // ASSOCIATIONS
    // ---------------------------------------------------------------
    // HOST ASSOCIATION TAB
    var groupHostsTable = $.registerAssociationTab({
        slug: 'group-host',
        item: 'host',
        sub: 'getHostsList'
    });

    // ---------------------------------------------------------------
    // GROUP ASSOCIATION TRI-STATE (shared by the printer, snapin and
    // module tabs). Each item shows All (checked) / Some (indeterminate) /
    // None (unchecked) coverage across member hosts, an "n / total" badge,
    // and an on-demand Has/Missing host drill-down (a DataTables child row).
    //
    // These plug into the generic $.registerAssociationTab via its
    // checkboxRender/onDraw hooks; the tri-state badge markup and the
    // drill-down AJAX stay group-local because they are not part of the
    // generic (plain on/off) association-tab skeleton.
    // ---------------------------------------------------------------

    // Returns the checkboxRender function for a tri-state tab: the full
    // association-cell markup (checkbox carrying data-state + the badge that
    // opens the drill-down). The input keeps class="associated"/value=row.id
    // so the generic add/remove/toggle plumbing keeps working.
    function groupAssocRender(entityType, idPrefix) {
        return function(row) {
            var state = row.association,
                cnt = (row.assocCount === undefined ? 0 : row.assocCount),
                total = (row.assocTotal === undefined ? 0 : row.assocTotal),
                checked = (state === 'all') ? ' checked' : '',
                label = (state === 'all')
                    ? 'bg-success'
                    : (state === 'some' ? 'bg-warning' : 'bg-secondary');
            return '<div class="form-check" '
                + 'style="display:inline-block;vertical-align:middle;margin:0 6px 0 0;">'
                + '<input type="checkbox" class="associated" data-state="' + state + '" '
                + 'name="associate[]" id="' + idPrefix + row.id + '" value="' + row.id + '"'
                + checked + '/></div>'
                + '<a href="#" class="assoc-drill badge ' + label + '" '
                + 'data-id="' + row.id + '" data-type="' + entityType + '" '
                + 'title="Show which hosts have this">' + cnt + ' / ' + total + '</a>';
        };
    }

    // Wire a tri-state association tab: the standard $.registerAssociationTab
    // skeleton plus the tri-state checkbox render, the post-draw indeterminate
    // styling for "some" rows, and the Has/Missing host drill-down. cfg extends
    // the generic opts with entityType/idPrefix (for the render + drill-down)
    // and an optional onDraw (run after the indeterminate styling). Returns the
    // DataTables instance.
    function wireGroupAssocTab(cfg) {
        var tableSel = '#' + cfg.slug + '-table';
        var table = $.registerAssociationTab({
            slug: cfg.slug,
            item: cfg.item,
            sub: cfg.sub,
            columns: cfg.columns,
            order: cfg.order,
            checkboxRender: groupAssocRender(cfg.entityType, cfg.idPrefix),
            afterCommit: cfg.afterCommit,
            onDraw: function(t) {
                $(tableSel + ' input.associated').each(function() {
                    if ($(this).data('state') === 'some') {
                        $(this).prop('indeterminate', true);
                    }
                });
                if (typeof cfg.onDraw === 'function') {
                    cfg.onDraw(t);
                }
            }
        });
        // On-demand Has/Missing host drill-down (a DataTables child row).
        $(tableSel).on('click', '.assoc-drill', function(e) {
            e.preventDefault();
            var tr = $(this).closest('tr'),
                row = table.row(tr),
                id = $(this).data('id'),
                type = $(this).data('type');
            if (row.child.isShown()) {
                row.child.hide();
                return;
            }
            row.child('<div class="assoc-drill-detail" style="padding:6px 12px;">'
                + 'Loading…</div>').show();
            $.ajax({
                url: '../management/index.php?node=' + Common.node
                    + '&sub=getAssocHostsList&id=' + Common.id
                    + '&assoctype=' + type + '&itemid=' + id,
                dataType: 'json',
                success: function(d) {
                    var has = (d && d.has) ? d.has : [],
                        miss = (d && d.missing) ? d.missing : [];
                    function names(arr) {
                        if (!arr.length) {
                            return '<em>none</em>';
                        }
                        return arr.map(function(h) {
                            return $('<span>').text(h.name).html();
                        }).join(', ');
                    }
                    row.child(
                        $('<div class="assoc-drill-detail" style="padding:6px 12px;">')
                            .append($('<div>').html('<strong>Hosts with this ('
                                + has.length + '):</strong> ' + names(has)))
                            .append($('<div>').html('<strong>Hosts without it ('
                                + miss.length + '):</strong> ' + names(miss)))
                    ).show();
                }
            });
        });
        return table;
    }

    // ---------------------------------------------------------------
    // PRINTER TAB
    // Association area
    var groupPrintersTable = wireGroupAssocTab({
        slug: 'group-printer',
        item: 'printer',
        sub: 'getPrintersList',
        entityType: 'printer',
        idPrefix: 'groupPrinterAssoc_',
        onDraw: function() {
            groupPrinterDefaultSelectorUpdate();
        }
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
    // Association area
    var groupSnapinsTable = wireGroupAssocTab({
        slug: 'group-snapin',
        item: 'snapin',
        sub: 'getSnapinsList',
        entityType: 'snapin',
        idPrefix: 'groupSnapinAssoc_',
        afterCommit: loadGroupSnapinOrder
    });

    // ---------------------------------------------------------------
    // GROUP SNAPIN RUN ORDER (snapins shared by all hosts)
    var groupSnapinOrderList = $('#group-snapin-order-list'),
        groupSnapinOrderSaveBtn = $('#group-snapin-order-save');

    function updateGroupSnapinOrderPositions() {
        groupSnapinOrderList.children('li').each(function(i) {
            $(this).find('.snapin-order-pos').text((i + 1) + '. ');
        });
    }

    function renderGroupSnapinOrder(items) {
        groupSnapinOrderList.empty();
        if (!items || items.length === 0) {
            groupSnapinOrderList.append(
                $('<li>', {'class': 'list-group-item text-muted'})
                    .text('No snapins are shared by every host in this group.')
            );
            groupSnapinOrderSaveBtn.prop('disabled', true);
            return;
        }
        groupSnapinOrderSaveBtn.prop('disabled', false);
        $.each(items, function(i, item) {
            var controls = $('<span>', {'class': 'float-end'})
                .append(
                    $('<button>', {
                        'type': 'button',
                        'class': 'btn btn-sm btn-secondary snapin-order-up',
                        'title': 'Move up'
                    }).append($('<i>', {'class': 'fa fa-arrow-up'})),
                    ' ',
                    $('<button>', {
                        'type': 'button',
                        'class': 'btn btn-sm btn-secondary snapin-order-down',
                        'title': 'Move down'
                    }).append($('<i>', {'class': 'fa fa-arrow-down'}))
                );
            groupSnapinOrderList.append(
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
        updateGroupSnapinOrderPositions();
    }

    function loadGroupSnapinOrder() {
        $.ajax({
            url: '../management/index.php?node=' + Common.node
                + '&sub=getSnapinOrderList&id=' + Common.id,
            dataType: 'json',
            success: function(data) {
                renderGroupSnapinOrder(data && data.data ? data.data : []);
            }
        });
    }

    groupSnapinOrderList.on('click', '.snapin-order-up', function(e) {
        e.preventDefault();
        var li = $(this).closest('li'),
            prev = li.prev('li');
        if (prev.length) {
            li.insertBefore(prev);
            updateGroupSnapinOrderPositions();
        }
    });

    groupSnapinOrderList.on('click', '.snapin-order-down', function(e) {
        e.preventDefault();
        var li = $(this).closest('li'),
            next = li.next('li');
        if (next.length) {
            li.insertAfter(next);
            updateGroupSnapinOrderPositions();
        }
    });

    groupSnapinOrderSaveBtn.on('click', function(e) {
        e.preventDefault();
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            order = [];
        groupSnapinOrderList.children('li').each(function() {
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

    loadGroupSnapinOrder();

    // FOG CLIENT AREA
    // ---------------------------------------------------------------
    // CLIENT SETTINGS TAB
    // Association area
    var groupModulesTable = wireGroupAssocTab({
        slug: 'group-module',
        item: 'module',
        sub: 'getModulesList',
        entityType: 'module',
        idPrefix: 'groupModuleAssoc_',
        columns: [
            // Aisle 097: this tab overrides the default association column set,
            // so it reads the raw 'name' field rather than the server-escaped
            // mainLink. Latent today (module names are not user-writable through
            // the UI) but it is the same sink shape as the snapin tab.
            {data: 'name', render: $.fn.dataTable.render.text()},
            {data: 'association'}
        ]
    });

    // Display manager area
    var groupModuleDisplaymanBtn = $('#group-displayman-send'),
        groupModuleDisplayForm = $('#group-displayman-form');

    function disableModuleDisplayButtons(disable) {
        groupModuleDisplaymanBtn.prop('disabled', disable);
    }

    groupModuleDisplayForm.on('submit', function(e) {
        e.preventDefault();
    });

    groupModuleDisplaymanBtn.on('click', function(e) {
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
        });
    });

    // Auto log out area
    var groupModuleAloBtn = $('#group-alo-send'),
        groupModuleAloForm = $('#group-alo-form');

    function disableModuleAloButtons(disable) {
        groupModuleAloBtn.prop('disabled', disable);
    }

    groupModuleAloBtn.on('click', function(e) {
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
        });
    });

    // Host enforce ad join reboot and hostname changes area
    var groupModuleEnforceBtn = $('#group-enforce-send'),
        groupModuleEnforceForm = $('#group-enforce-form');

    function disableModuleEnforceButtons(disable) {
        groupModuleEnforceBtn.prop('disabled', disable);
    }

    groupModuleEnforceForm.on('submit', function(e) {
        e.preventDefault();
    });

    groupModuleEnforceBtn.on('click', function(e) {
        e.preventDefault();
        disableModuleEnforceButtons(true);
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            opts = {
                confirmenforcesend: 1,
                enforce: $('#enforce')[0].checked ? 1 : 0
            };
        $.apiCall(method,action,opts,function(err) {
            disableModuleEnforceButtons(false);
        });
    });

    // ACTIVE DIRECTORY TAB
    var ADForm = $('#active-directory-form'),
        ADFormBtn = $('#ad-send'),
        ADClearBtn = $('#ad-clear'),
        ADJoinDomain = $('#adEnabled');

    // #adEnabled is a tri-state <select> in group mode (No change/Enable/
    // Disable). Populate the blank fields from the AD defaults only when the
    // admin actively selects Enable -- never just from existing state.
    ADJoinDomain.on('change', function(e) {
        if ($(this).val() !== '1') {
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
        // Reset the tri-state Domain Joining select back to "No change".
        $('#adEnabled').val('');

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
    ondemand.on('change', function(e) {
        if (!this.checked) {
            return;
        }
        $(this).parents('.card-body').find('.form-group:eq(0)').find(':input').prop('disabled', true);
    });
    ondemand.on('change', function(e) {
        if (this.checked) {
            return;
        }
        $(this).parents('.card-body').find('.form-group:eq(0)').find(':input').prop('disabled', false);
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

    // ---------------------------------------------------------------
    // INVENTORY TAB

    // HISTORY TABS
    // ---------------------------------------------------------------
    // LOGIN HISTORY TAB
    var groupHistoryLoginTable = $('#group-login-history-table').registerTable(null, {
        columns: [
            {data: 'hostLink'},
            {data: 'createdTime'},
            {data: 'action'},
            {data: 'username'},
            {data: 'description'}
        ],
        rowId: 'id',
        rowGroup: {
            dataSrc: 'hostLink'
        },
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
    var groupHistoryImageTable = $('#group-image-history-table').registerTable(null, {
        columns: [
            {data: 'hostLink'},
            {data: 'createdBy'},
            {data: 'start'},
            {data: 'finish'},
            {data: 'diff'},
            {data: 'imageLink'},
            {data: 'type'}
        ],
        rowId: 'id',
        rowGroup: {
            dataSrc: 'hostLink'
        },
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
    var groupHistorySnapinTable = $('#group-snapin-history-table').registerTable(null, {
        columns: [
            {data: 'hostLink'},
            {data: 'snapinLink'},
            {data: 'checkin'},
            {data: 'complete'},
            {data: 'diff'},
            {data: 'return'}
        ],
        rowId: 'id',
        rowGroup: {
            dataSrc: 'hostLink'
        },
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
        // Associations
        groupHostsTable.search(Common.search).draw();
        groupPrintersTable.search(Common.search).draw();
        groupSnapinsTable.search(Common.search).draw();
        // FOG Client
        groupModulesTable.search(Common.search).draw();
        // History
        groupHistoryLoginTable.search(Common.search).draw();
        groupHistoryImageTable.search(Common.search).draw();
        groupHistorySnapinTable.search(Common.search).draw();
    }
})(jQuery)
