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

    // Modal canceled
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
    // PRINTER TAB
    // Association area
    // ADR 0038: the group owns its printers, so this is the plain on/off
    // association tab. It replaced a tri-state one that showed All/Some/None
    // coverage across member hosts with an n-of-total drill-down -- a whole
    // vocabulary that existed only because the group owned nothing itself.
    var groupPrintersTable = $.registerAssociationTab({
        slug: 'group-printer',
        item: 'printer',
        sub: 'getPrintersList',
        onDraw: function() {
            groupPrinterDefaultSelectorUpdate();
        }
    });
    // The printer create form hides all but the selected type section, and that
    // JS lives on the printer pages, which do not load here. node:'printer'
    // because Common.node is 'group' here and would aim getPrinterInfo wrong.
    // validate matches what the printer pages pass, so hidden sections are not
    // validated. Association goes through Group::addPrinter(), which writes
    // one grant row on the group.
    $.registerCreateAndAssociate('group-printer', groupPrintersTable, {
        onForm: function(form) {
            form.initPrinterFormUI({node: 'printer'});
        },
        validate: ':input:visible'
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

    // ---------------------------------------------------------------
    // SNAPINS TAB
    // Association area
    var groupSnapinsTable = $.registerAssociationTab({
        slug: 'group-snapin',
        item: 'snapin',
        sub: 'getSnapinsList',
        afterCommit: loadGroupSnapinOrder
    });
    // wirePackTypes matches the snapin ADD page, since this modal renders the
    // same _addFields() form. Association goes through Group::addSnapin(),
    // which writes one grant row on the group.
    $.registerCreateAndAssociate('group-snapin', groupSnapinsTable, {
        onForm: function(form) {
            form.initSnapinCommandUI({wirePackTypes: true});
        }
    });

    // ---------------------------------------------------------------
    // GROUP SNAPIN RUN ORDER (the snapins this group grants)
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
                    }).append($('<i>', {'class': 'fas fa-arrow-up'})),
                    ' ',
                    $('<button>', {
                        'type': 'button',
                        'class': 'btn btn-sm btn-secondary snapin-order-down',
                        'title': 'Move down'
                    }).append($('<i>', {'class': 'fas fa-arrow-down'}))
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

    // ---------------------------------------------------------------
    // SOFTWARE TAB
    // Association goes through Group::addSoftware(), which writes one grant
    // row on the group, mirroring the snapin tab above.
    var groupSoftwareTable = $.registerAssociationTab({
        slug: 'group-software',
        item: 'software',
        sub: 'getSoftwareList',
        afterCommit: loadGroupSoftwareOrder
    });
    $.registerCreateAndAssociate('group-software', groupSoftwareTable);

    // ---------------------------------------------------------------
    // GROUP SOFTWARE ORDER (the software this group grants)
    var groupSoftwareOrderList = $('#group-software-order-list'),
        groupSoftwareOrderSaveBtn = $('#group-software-order-save');

    function updateGroupSoftwareOrderPositions() {
        groupSoftwareOrderList.children('li').each(function(i) {
            $(this).find('.software-order-pos').text((i + 1) + '. ');
        });
    }

    function renderGroupSoftwareOrder(items) {
        groupSoftwareOrderList.empty();
        if (!items || items.length === 0) {
            groupSoftwareOrderList.append(
                $('<li>', {'class': 'list-group-item text-muted'})
                    .text('No software is granted by this group.')
            );
            groupSoftwareOrderSaveBtn.prop('disabled', true);
            return;
        }
        groupSoftwareOrderSaveBtn.prop('disabled', false);
        $.each(items, function(i, item) {
            var controls = $('<span>', {'class': 'float-end'})
                .append(
                    $('<button>', {
                        'type': 'button',
                        'class': 'btn btn-sm btn-secondary software-order-up',
                        'title': 'Move up'
                    }).append($('<i>', {'class': 'fas fa-arrow-up'})),
                    ' ',
                    $('<button>', {
                        'type': 'button',
                        'class': 'btn btn-sm btn-secondary software-order-down',
                        'title': 'Move down'
                    }).append($('<i>', {'class': 'fas fa-arrow-down'}))
                );
            groupSoftwareOrderList.append(
                $('<li>', {
                    'class': 'list-group-item',
                    'data-id': item.id
                }).append(
                    $('<span>', {'class': 'software-order-pos'}),
                    $('<span>', {'class': 'software-order-name'}).text(item.name),
                    controls
                )
            );
        });
        updateGroupSoftwareOrderPositions();
    }

    function loadGroupSoftwareOrder() {
        $.ajax({
            url: '../management/index.php?node=' + Common.node
                + '&sub=getSoftwareOrderList&id=' + Common.id,
            dataType: 'json',
            success: function(data) {
                renderGroupSoftwareOrder(data && data.data ? data.data : []);
            }
        });
    }

    groupSoftwareOrderList.on('click', '.software-order-up', function(e) {
        e.preventDefault();
        var li = $(this).closest('li'),
            prev = li.prev('li');
        if (prev.length) {
            li.insertBefore(prev);
            updateGroupSoftwareOrderPositions();
        }
    });

    groupSoftwareOrderList.on('click', '.software-order-down', function(e) {
        e.preventDefault();
        var li = $(this).closest('li'),
            next = li.next('li');
        if (next.length) {
            li.insertAfter(next);
            updateGroupSoftwareOrderPositions();
        }
    });

    groupSoftwareOrderSaveBtn.on('click', function(e) {
        e.preventDefault();
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            order = [];
        groupSoftwareOrderList.children('li').each(function() {
            var id = $(this).attr('data-id');
            if (id) {
                order.push(id);
            }
        });
        if (order.length === 0) {
            return;
        }
        $.apiCall(method, action, {softwareorder: order});
    });

    loadGroupSoftwareOrder();

    // FOG CLIENT AREA
    // ---------------------------------------------------------------
    // CLIENT SETTINGS TAB
    // Association area
    var groupModulesTable = $.registerAssociationTab({
        slug: 'group-module',
        item: 'module',
        sub: 'getModulesList',
        columns: [
            // Aisle 097: this tab overrides the default association column set,
            // so it reads the raw 'name' field rather than the server-escaped
            // mainLink. Latent today (module names are not user-writable through
            // the UI) but it is the same sink shape as the snapin tab.
            {data: 'name', render: $.fn.dataTable.render.text()},
            {data: 'association'}
        ]
    });

    // ---------------------------------------------------------------
    // POWER MANAGEMENT TAB
    //
    // ADR 0038: the grid lists the GROUP'S OWN grants -- one row per schedule
    // the group grants, not a summary of what its member hosts hold. It reads
    // ?sub=getGrouppowermanagementList, a page endpoint rather than a REST
    // route, for the reason GroupManagement::getGrouppowermanagementList()
    // gives.
    var powermanagementDeleteBtn = $('#powermanagement-delete'),
        powermanagementDeleteModal = $('#deletepowermanagementmodal'),
        powermanagementDeleteConfirmBtn = $('#deletepowermanagementConfirm'),
        pmGrantDelete = $('#pm-delete'),
        ondemandModalBtn = $('#ondemandBtn'),
        ondemandModal = $('#ondemandModal'),
        ondemandModalConfirmBtn = $('#ondemandCreateBtn'),
        scheduleModalBtn = $('#scheduleBtn'),
        scheduleModal = $('#scheduleModal'),
        scheduleModalConfirmBtn = $('#scheduleCreateBtn'),
        instantForm = $('#group-powermanagement-instant-form'),
        scheduleForm = $('#group-powermanagement-cron-form'),
        minutes = $('.cronmin', scheduleForm),
        hours = $('.cronhour', scheduleForm),
        dom = $('.crondom', scheduleForm),
        month = $('.cronmonth', scheduleForm),
        dow = $('.crondow', scheduleForm);

    $('.fogcron').cron({
        initial: '* * * * *',
        onChange: function() {
            var vals = $(this).cron('value').split(' ');
            minutes.val(vals[0]);
            hours.val(vals[1]);
            dom.val(vals[2]);
            month.val(vals[3]);
            dow.val(vals[4]);
        }
    });

    // No select callback: the delete button posts the ticked ids and an empty
    // post is a no-op, which is how the host page's identical grid behaves.
    // A disable-on-empty handler here would have to agree with registerTable's
    // notion of "selected" on first draw, and getting that wrong leaves the
    // button dead rather than merely unhelpful.
    var powermanagementTable = $('#group-powermanagement-table').registerTable(null, {
        columns: [
            {data: 'id'},
            {data: 'action'}
        ],
        columnDefs: [
            {
                targets: 0,
                // The five cron fields joined for display. escapeHtml on each
                // rather than $.escapedColumn on the column, because the cell
                // is built from five values and there is no single one to
                // escape.
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
                + '&sub=getGrouppowermanagementList&id='
                + Common.id,
            type: 'post'
        }
    });

    scheduleForm.on('submit', function(e) {
        e.preventDefault();
    });
    instantForm.on('submit', function(e) {
        e.preventDefault();
    });

    // New scheduled grant.
    scheduleModalBtn.on('click', function(e) {
        e.preventDefault();
        scheduleModal.modal('show');
    });
    scheduleModal.registerModal(
        function(e) {
            scheduleModalConfirmBtn.on('click', function() {
                $(this).prop('disabled', true);
                scheduleForm.processForm(function(err) {
                    scheduleModalConfirmBtn.prop('disabled', false);
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

    // New immediate task. Still a fan-out, so it changes nothing in the grid
    // above -- no redraw, because no grant was created.
    ondemandModalBtn.on('click', function(e) {
        e.preventDefault();
        ondemandModal.modal('show');
    });
    ondemandModal.registerModal(
        function(e) {
            ondemandModalConfirmBtn.on('click', function() {
                $(this).prop('disabled', true);
                instantForm.processForm(function(err) {
                    ondemandModalConfirmBtn.prop('disabled', false);
                    if (err) {
                        return;
                    }
                    ondemandModal.modal('hide');
                });
            });
        },
        function(e) {
            $(this).modal('hide');
        }
    );

    // Revoke the ticked grants.
    pmGrantDelete.on('click', function(e) {
        e.preventDefault();
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            toDel = $.getSelectedIds(powermanagementTable),
            opts = {
                pmremove: 1,
                remgrants: toDel
            };
        $.apiCall(method, action, opts, function(err) {
            if (err) {
                return;
            }
            powermanagementTable.draw(false);
            powermanagementTable.rows({selected: true}).deselect();
        });
    });

    // The legacy sweep: member hosts' OWN rows, not this group's grants.
    powermanagementDeleteBtn.on('click', function(e) {
        e.preventDefault();
        powermanagementDeleteModal.modal('show');
    });
    powermanagementDeleteConfirmBtn.on('click', function(e) {
        e.preventDefault();
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            opts = {
                pmdelete: 1
            };
        $.apiCall(method, action, opts, function(err) {
            if (err) {
                return;
            }
            powermanagementDeleteModal.modal('hide');
        });
    });

    // ---------------------------------------------------------------
    // INVENTORY TAB

    // HISTORY TABS
    // ---------------------------------------------------------------
    // LOGIN HISTORY TAB
    // Absent for a user without usertracking.view -- see the host page.
    var $groupLoginHist = $('#group-login-history-table');
    var groupHistoryLoginTable = !$groupLoginHist.length ? null :
      $groupLoginHist.registerTable(null, {
        // hostLink stays raw -- it is a server-built <a>, and escaping it
        // would print the markup literally. Everything beside it is plain
        // text from Route and escapes here; `username` and `description`
        // are written by the client check-in. Same split as the inventory
        // report and the host page's copy of this tab.
        columns: [
            {data: 'hostLink'},
            $.escapedColumn('createdTime'),
            $.escapedColumn('action'),
            $.escapedColumn('username'),
            $.escapedColumn('description')
        ],
        // Host first, because RowGroup only groups correctly when the
        // grouped column is the primary sort -- otherwise a host's rows
        // scatter and its group header repeats. Then createdTime
        // descending, so newest is at the top WITHIN each host, which is
        // what the three host-page tabs already do.
        //
        // Column 0 is hostLink. Sorting it ascending puts the hosts in
        // alphabetical order, not id order: the model joins `hosts` into
        // its list query and Route::_hostNameOrder() sorts the column on
        // the joined name. All of that is server-side -- these grids are
        // serverSide:true, so DataTables sorts nothing itself.
        order: [
            [0, 'asc'],
            [1, 'desc']
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
            {data: 'createdTime'},
            {data: 'statename'},
            {data: 'taskTypeName'},
            {data: 'imageName'}
        ],
        // Host first, because RowGroup only groups correctly when the
        // grouped column is the primary sort -- otherwise a host's rows
        // scatter and its group header repeats. Then createdTime
        // descending, so newest is at the top WITHIN each host, which is
        // what the three host-page tabs already do.
        //
        // Column 0 is hostLink. Sorting it ascending puts the hosts in
        // alphabetical order, not id order: the model joins `hosts` into
        // its list query and Route::_hostNameOrder() sorts the column on
        // the joined name. All of that is server-side -- these grids are
        // serverSide:true, so DataTables sorts nothing itself.
        order: [
            [0, 'asc'],
            [2, 'desc']
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
            {data: 'return'},
            {data: 'status'}
        ],
        // Host first, because RowGroup only groups correctly when the
        // grouped column is the primary sort -- otherwise a host's rows
        // scatter and its group header repeats. Then checkin
        // descending, so newest is at the top WITHIN each host, which is
        // what the three host-page tabs already do.
        //
        // Column 0 is hostLink. Sorting it ascending puts the hosts in
        // alphabetical order, not id order: the model joins `hosts` into
        // its list query and Route::_hostNameOrder() sorts the column on
        // the joined name. All of that is server-side -- these grids are
        // serverSide:true, so DataTables sorts nothing itself.
        order: [
            [0, 'asc'],
            [2, 'desc']
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
        groupSoftwareTable.search(Common.search).draw();
        // FOG Client
        groupModulesTable.search(Common.search).draw();
        // History
        if (groupHistoryLoginTable) {
          groupHistoryLoginTable.search(Common.search).draw();
        }
        groupHistoryImageTable.search(Common.search).draw();
        groupHistorySnapinTable.search(Common.search).draw();
    }

    // ---------------------------------------------------------------
    // SITE TAB
    // Single dropdown, so registerSelectTab rather than the grid wiring.
    // node:'site' adds the create-and-select button when the user holds
    // site.create; without it the tab is just the select and Update.
    $.registerSelectTab({
        slug: 'group-site',
        send: 'site-send',
        node: 'site'
    });
})(jQuery)
