(function($) {
    var addToGroup = $('#addSelectedToGroup'),
        deleteSelected = $('#deleteSelected'),
        queueTask = $('#queueTask'),
        queueTaskModal = $('#queueTaskModal'),
        groupModal = $('#addToGroupModal'),
        groupModalSelect = $('#groupSelect'),
        createnewBtn = $('#createnew'),
        createnewModal = $('#createnewModal'),
        createForm = $('#create-form'),
        createnewSendBtn = $('#send');
    var groupList = [];
    // How many rows were ticked when the Queue Task modal was opened. Read
    // again at submit time rather than trusted: the grid stays interactive
    // behind the modal.
    var queueCount = 0;

    function disableButtons(disable) {
        addToGroup.prop('disabled', disable);
        deleteSelected.prop('disabled', disable);
        queueTask.prop('disabled', disable);
    }
    disableButtons(true);

    function onSelect(selected) {
        var count = selected.count();
        disableButtons(count == 0);
        applyQuickAvailability(count);
    }

    function loadGroupSelect(){
        var hostGroupUpdateBtn = $('#confirmGroupAdd');
        groupModalSelect.select2({
            tags: true,
            tokenSeparators: [',', ' '],
            ajax: {
                url: function(params) {
                    return '../group/names/name='
                        + encodeURIComponent(
                            '%'
                            + params.term
                            + '%'
                        );
                },
                dataType: 'json',
                processResults: function(data, params) {
                    // /names now answers with the rows under `data`, the
                    // same envelope every list route uses. Tolerate both
                    // shapes so this page keeps working against a server
                    // that has not been updated yet.
                    var rows = (data && data.data) ? data.data : data;
                    return {
                        results: $.map(rows, function(item) {
                            return {
                                id: item.id || item.name,
                                name: item.name,
                                text: item.name
                            };
                        }),
                        totals: rows.length
                    };
                }
            },
            width: '100%',
            placeholder: 'Select or create group',
            createTag: function (params) {
                var term = $.trim(params.term);
                if (term === '') {
                    return;
                }
                return {
                    id: term,
                    text: term,
                    newOption: true
                }
            },
            templateResult: function (data) {
                if (!data.text.length) {
                    return;
                }
                var $result = $("<span></span>");

                $result.text(data.text);
                if (data.newOption) {
                    $result.append(" <em><b>(new)</b></em>");
                }
                return $result;
            }
        });

        hostGroupUpdateBtn.on('click', function(e) {
            e.preventDefault();
            var items = groupModalSelect.find('option').map(function() {return $(this).val()}).get(),
                hosts = $.getSelectedIds(table),
                groups = [],
                groups_new = [];
            $.map(items, function(item) {
                item = $.trim(item);
                if (item === '') {
                    return;
                }
                if ($.isNumeric(item)) {
                    groups.push(item);
                } else {
                    groups_new.push(item);
                }
            });
            var action = '../management/index.php?node='
                + Common.node
                + '&sub=saveGroup',
                method = 'post',
                opts = {
                    hosts: hosts,
                    groups: groups,
                    groups_new: groups_new
                };
            $.apiCall(method,action,opts,function(err) {
                if (err) {
                    return;
                }
                groupModalSelect.val(null);
                groupModal.modal('hide');
            });
        });
    }

    // Build the column list from the header row instead of hardcoding it.
    //
    // The Ping Status header is conditional on FOG_HOST_LOOKUP server-side
    // (HostManagement::index()), so a fixed list here had one more column
    // than the table had <th> whenever that setting was off. DataTables
    // compares the two and raises "Incorrect column count", which kills the
    // whole grid rather than one cell -- and nothing on the page says why.
    // Each <th> carries its data key in data-col, so the two now cannot
    // drift, and any column added or gated server-side needs no change here.
    //
    // colIndex maps a key to its position, because columnDefs addresses
    // columns by index and those indexes move whenever a column is added or
    // gated. That was the other half of the same bug.
    var columns = [],
        colIndex = {};
    $('#dataTable thead th').each(function() {
        var key = $(this).attr('data-col');
        if (!key) {
            // Keep the counts equal no matter what: an untagged header costs
            // one blank cell, where skipping it would resurrect the
            // column-count mismatch this whole block exists to prevent.
            if (window.console && console.warn) {
                console.warn('FOG: host list header with no data-col', this);
            }
            columns.push({data: null, defaultContent: ''});
            return;
        }
        colIndex[key] = columns.length;
        columns.push({data: key});
    });

    var columnDefs = [];
    if ('mainlink' in colIndex) {
        columnDefs.push({
            responsivePriority: -1,
            targets: colIndex.mainlink
        });
    }
    if ('primac' in colIndex) {
        columnDefs.push({
            responsivePriority: 0,
            render: function (data, type, row) {
                if (type !== 'display') {
                    return data;
                }
                return (data || '') + macVendorIcon(row.primac_vendor);
            },
            targets: colIndex.primac
        });
    }
    if ('deployed' in colIndex) {
        columnDefs.push({
            render: function (data, type, row) {
                // GH-1245: "never deployed" is NULL from schema step 344 on,
                // and was the zero date before it. Both spellings reach here
                // on an upgraded server until the rows are rewritten, so both
                // have to blank the cell.
                if (!data || String(data).indexOf('0000-00-00') === 0) {
                    return '';
                }
                return data;
            },
            targets: colIndex.deployed
        });
    }

    if ('arch' in colIndex) {
        columnDefs.push({
            render: function (data, type, row) {
                if (type !== 'display') {
                    return data;
                }
                // Spelled out rather than blanked, unlike 'deployed' above.
                // A never-deployed host and a blank cell mean the same thing,
                // but a blank architecture reads as x86 to anyone scanning a
                // list -- which is the assumption schema step 369 exists to
                // stop. NULL here means the host has not PXE booted since the
                // upgrade, not that it is x86.
                if (!data) {
                    return '<span class="text-muted">Not yet seen</span>';
                }
                return '<code>' + data + '</code>';
            },
            targets: colIndex.arch
        });
    }

    if ('sbstate' in colIndex) {
        columnDefs.push({
            render: function (data, type, row) {
                // Display only -- sort, search and the CSV export all get the
                // server's plain text back untouched. A badge baked into the
                // value is the GH-1446 failure: registerExportTable() escapes
                // each cell, so the markup lands in the CSV as literal text.
                if (type !== 'display') {
                    return data;
                }
                // Color carries the same meaning the enrollment task acts on,
                // so the grid and the refusal cannot tell different stories:
                // green is ready to enroll unattended, blue is enrollable with
                // someone at the machine, red cannot run the task at all, and
                // gray is "we have never heard from this host" -- which is
                // deliberately not the same gray-as-harmless as the others,
                // because unknown is allowed through with a warning.
                var raw = String(row.sbstatecode || '');
                var tone = 'secondary';
                if (raw === 'setup') {
                    tone = 'success';
                } else if (raw === 'disabled') {
                    tone = 'info';
                } else if (raw === 'enforcing') {
                    tone = 'danger';
                } else if (raw === 'nonefi' || raw === 'noefivars') {
                    tone = 'warning';
                }
                if (!raw) {
                    return '<span class="text-muted">' + (data || '') +
                        '</span>';
                }
                return '<span class="badge bg-' + tone + '">' +
                    (data || '') + '</span>';
            },
            targets: colIndex.sbstate
        });
    }
    if ('sbenrolled' in colIndex) {
        columnDefs.push({
            render: function (data, type, row) {
                if (type !== 'display') {
                    return data;
                }
                // Blank, not "Never": an empty enrollment cell next to a
                // populated Secure Boot cell already reads as "not enrolled",
                // and the word would crowd a column most fleets never look at.
                if (!data) {
                    return '';
                }
                // A staged MOK request is NOT an enrollment and must not read
                // as one -- the machine will not boot with Secure Boot on
                // until someone confirms it at the MokManager screen. The
                // date alone would say the opposite.
                if (String(row.sbenrollvia || '') === 'mok-pending') {
                    return data +
                        ' <span class="badge bg-warning">pending</span>';
                }
                // The comparison the fingerprint column exists for, shown
                // where somebody scanning a fleet will see it. 'stale' means
                // this host trusts a certificate this server no longer
                // serves: it boots fine today and stops booting under Secure
                // Boot the day the old kernels are retired, with nothing on
                // screen to connect the two events.
                //
                // Only 'stale' is badged. A green "current" on every
                // correctly-enrolled host is a badge on the majority, which
                // is decoration -- the exception is what needs finding.
                if (String(row.sbenrollfresh || '') === 'stale') {
                    return data +
                        ' <span class="badge bg-danger">old cert</span>';
                }
                return data;
            },
            targets: colIndex.sbenrolled
        });
    }

    // ---------------------------------------------------------------
    // QUICK TASKS
    //
    // One click, one task. The Queue Task button in the toolbar stays and is
    // still the way to reach anything that needs options -- a snapin choice,
    // an account to reset, a debug session. These three need none, so making
    // somebody open a modal, pick the type and then confirm a form with
    // nothing on it is three clicks spent on a decision already made.
    //
    // They sit in the grid's own button bar, under the search box, rather
    // than in the toolbar with Delete/Queue Task/Add. That bar is where the
    // controls that act on the SELECTION already live (Select All, Deselect
    // All), and it is the strip a person's eye is on while they are ticking
    // rows.
    //
    // Which ones are usable is decided by how many rows are ticked, using
    // the ttIsAccess value the SERVER put on each entry rather than a rule
    // written again here: 'host' (Capture) means exactly one row, 'group'
    // (Multi-Cast) means two or more, 'both' (Deploy) means any. The Queue
    // Task modal filters on the same value and deployMultiPost() refuses on
    // it, so all three agree by construction and a button that somehow
    // slipped through enabled still cannot create a task the server would
    // not have made anyway.
    var quickTasks = $('#quick-task-data .quicktaskitem').map(function() {
        var el = $(this);
        return {
            type: el.attr('data-type'),
            access: el.attr('data-access'),
            icon: el.attr('data-icon'),
            name: el.attr('data-name'),
            // The handle enable()/disable() addresses the button by. Class
            // rather than index because the shared button set sits in front
            // of these and an index would move the day one is added there.
            cls: 'quicktask-' + el.attr('data-type')
        };
    }).get();

    function quickTaskUsable(access, count) {
        if (count < 1) {
            return false;
        }
        if (access === 'group') {
            return count > 1;
        }
        if (access === 'host') {
            return count === 1;
        }
        return true;
    }

    // Gray the ones this many rows cannot run, rather than hiding them. The
    // Queue Task modal hides its rows because it is a list of everything the
    // server offers and an unusable entry there is noise; here there are
    // three fixed buttons in a fixed order, and a button that disappears and
    // comes back as you tick rows is harder to aim at than one that grays.
    function applyQuickAvailability(count) {
        if (!quickTasks.length) {
            return;
        }
        $.each(quickTasks, function(i, task) {
            table.buttons('.' + task.cls)
                .enable(quickTaskUsable(task.access, count));
        });
    }

    // Guards the window between the click and the server's answer. The
    // button stays enabled underneath -- disabling it would fight
    // applyQuickAvailability() on the deselect that follows -- so without
    // this an impatient double-click is two identical taskings.
    var quickTaskRunning = false;

    function runQuickTask(task, dt) {
        var hosts = $.getSelectedIds(dt);
        if (quickTaskRunning || !quickTaskUsable(task.access, hosts.length)) {
            return;
        }
        quickTaskRunning = true;
        // Straight to the create. deployMulti (the options form) is skipped
        // deliberately: there is nothing on it these three types need, and
        // fetching it only to post it back unchanged is the click this
        // feature exists to remove. Everything the form's POST is checked
        // for -- site scope, pending hosts, an assigned and enabled image,
        // one image across a multicast -- is checked in deployMultiPost(),
        // not in the form, so nothing is skipped but the rendering.
        $.apiCall(
            'post',
            '../management/index.php?node=host&sub=deployMulti&type='
                + encodeURIComponent(task.type),
            {hosts: hosts},
            function(err) {
                quickTaskRunning = false;
                if (err) {
                    // Keep the selection. The refusal is nearly always
                    // something about these hosts that the person is about
                    // to go and fix, and losing the ticks means finding them
                    // again.
                    return;
                }
                dt.rows({selected: true}).deselect();
            }
        );
    }

    var quickButtons = $.map(quickTasks, function(task) {
        return {
            // Escaped: both values are shown as markup here, and a task type
            // name is admin-editable text from the taskTypes table.
            text: '<i class="fas fa-' + $.escapeHtml(task.icon) + '"></i> '
                + $.escapeHtml(task.name),
            className: task.cls,
            // Nothing is ticked on first draw, so every one of them starts
            // grayed rather than enabled-then-corrected on the first select.
            enabled: false,
            action: function(e, dt) {
                runQuickTask(task, dt);
            }
        };
    });

    var table = $('#dataTable').registerTable(onSelect, {
        extraButtons: quickButtons,
        // Sort on the host name. Named rather than numbered for the same
        // reason columnDefs is: the position moves when a column is added
        // or gated, and a stale index silently sorts the wrong column.
        order: [
            [('mainlink' in colIndex ? colIndex.mainlink : 0), 'asc']
        ],
        // lastping/lastcheckin need no render: both arrive already formatted
        // by the server, or empty when the host has never been seen.
        columns: columns,
        rowId: 'id',
        columnDefs: columnDefs,
        processing: true,
        serverSide: true,
        ajax: {
            url: '../management/index.php?node=host&sub=list',
            type: 'POST'
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
        createForm.processForm(function(err) {
            if (err) {
                return;
            }
            table.draw(false);
            createnewModal.modal('hide');
        });
    });
    $('#mac').inputmask({mask: Common.masks.mac});
    $.initProductKeyField('#key');
    // ---------------------------------------------------------------
    // ACTIVE DIRECTORY TAB
    var ADJoinDomain = $('#adEnabled');

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

    // Delete hosts.
    deleteSelected.on('click', function() {
        disableButtons(true);
        $.deleteSelected(table, function(err) {
            // if we couldn't delete the items, enable the buttons
            //   as the rows still exist and are selected
            if (err) {
                disableButtons(false);
            }
        });
    });

    // Add host(s) to group.
    groupModal.registerModal(
        // On show
        null,
        // On close
        function(e) {
            // Clear the group selector and data
            groupModalSelect.select2('destroy');
        }
    );

    groupModal.on('show.bs.modal', function(e) {
        Pace.track(function(){
            loadGroupSelect();
        });
    });

    addToGroup.on('click', function() {
        groupModal.modal('show');
    });

    // ---------------------------------------------------------------
    // QUEUE TASK
    //
    // Two panes in one modal: the Basic/Advanced accordion, then the chosen
    // task's options form fetched from the server. The Create button belongs
    // to the second pane only, so it stays hidden until there is a form for
    // it to submit.
    var queuePicker = $('#queue-task-picker'),
        queueHolder = $('#queue-task-form-holder'),
        queueSend = $('#queueTaskSend'),
        queueName = $('.queue-task-name');

    // Which task types a selection of this size can actually run.
    //
    // The two exceptions are the whole reason the count matters. Multi-Cast
    // ships as ttIsAccess='group' because one session serves many hosts, so
    // it means nothing for a single host; Capture ships as ttIsAccess='host'
    // because an image comes off exactly one machine. Everything else is
    // 'both'. The server refuses the same two cases in
    // assertSelectionTaskable(), so this is the courtesy, not the guard.
    function applyTaskAvailability(count) {
        $('.queuetaskitem').each(function() {
            var access = $(this).attr('data-access'),
                usable = true;
            if (access === 'group' && count < 2) {
                usable = false;
            }
            if (access === 'host' && count > 1) {
                usable = false;
            }
            // The description cell beside it goes too, or the table shows a
            // row of prose with nothing to click.
            $(this).closest('tr').toggleClass('d-none', !usable);
        });
    }

    function showQueuePicker() {
        queueHolder.empty().addClass('d-none');
        queuePicker.removeClass('d-none');
        queueSend.addClass('d-none').off('click');
        queueName.text('');
    }

    queueTask.on('click', function() {
        queueCount = $.getSelectedIds(table).length;
        if (queueCount < 1) {
            return;
        }
        applyTaskAvailability(queueCount);
        showQueuePicker();
        queueTaskModal.modal('show');
    });

    queueTaskModal.on('hidden.bs.modal', function() {
        showQueuePicker();
    });

    $('.queuetaskitem').on('click', function(e) {
        e.preventDefault();
        var type = $(this).attr('data-type'),
            taskName = $(this).text(),
            hosts = $.getSelectedIds(table);

        if (hosts.length < 1) {
            return;
        }
        queueCount = hosts.length;

        queuePicker.addClass('d-none');
        queueHolder.removeClass('d-none').html('Loading, please wait...');
        queueName.text(' - ' + taskName);
        $('#queueTaskModal .modal-dialog').setLoading(true);

        Pace.track(function() {
            $.ajax({
                type: 'get',
                url: '../management/index.php?node=host&sub=deployMulti'
                    + '&type=' + encodeURIComponent(type)
                    + '&count=' + encodeURIComponent(hosts.length),
                dataType: 'json',
                success: function(data) {
                    $('#queueTaskModal .modal-dialog').setLoading(false);
                    queueHolder.html($.parseHTML(data.msg));

                    var form = $('#host-deploy-multi-form');
                    Common.iCheck('#queue-task-form-holder input');

                    // Debug hides the schedule fields on the single-host
                    // form; there are none here, but the same checkbox still
                    // gates the shutdown row.
                    $('#checkdebug', form).on('change', function() {
                        $('.hideFromDebug', form).toggleClass(
                            'd-none',
                            this.checked
                        );
                    });

                    queueSend.removeClass('d-none').off('click').on(
                        'click',
                        function(e) {
                            e.stopImmediatePropagation();
                            // Read the selection again HERE. The grid is
                            // still live behind the modal, so what was ticked
                            // when the form was fetched is not necessarily
                            // what is ticked now, and the ids are what the
                            // server tasks.
                            var current = $.getSelectedIds(table);
                            $('input.queued-host', form).remove();
                            $.each(current, function(i, id) {
                                $('<input>').attr({
                                    type: 'hidden',
                                    name: 'hosts[]',
                                    'class': 'queued-host'
                                }).val(id).appendTo(form);
                            });
                            form.processForm(function(err) {
                                if (err) {
                                    return;
                                }
                                queueTaskModal.modal('hide');
                                table.rows({selected: true}).deselect();
                            });
                        }
                    );
                },
                error: function(jqXHR, textStatus) {
                    if (textStatus == 'abort') {
                        return;
                    }
                    $('#queueTaskModal .modal-dialog').setLoading(false);
                    queueTaskModal.modal('hide');
                    $.notifyFromAPI(jqXHR.responseJSON, jqXHR);
                }
            });
        });
    });
})(jQuery);
