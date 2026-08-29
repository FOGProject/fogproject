(function($) {
    var addToGroup = $('#addSelectedToGroup'),
        deleteSelected = $('#deleteSelected'),
        groupModal = $('#addToGroupModal'),
        groupModalSelect = $('#groupSelect'),
        createnewBtn = $('#createnew'),
        createnewModal = $('#createnewModal'),
        createForm = $('#create-form'),
        createnewSendBtn = $('#send');
    var groupList = [];

    function disableButtons(disable) {
        addToGroup.prop('disabled', disable);
        deleteSelected.prop('disabled', disable);
    }
    disableButtons(true);

    function onSelect(selected) {
        var disabled = selected.count() == 0;
        disableButtons(disabled);
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
                // Colour carries the same meaning the enrolment task acts on,
                // so the grid and the refusal cannot tell different stories:
                // green is ready to enrol unattended, blue is enrollable with
                // someone at the machine, red cannot run the task at all, and
                // grey is "we have never heard from this host" -- which is
                // deliberately not the same grey-as-harmless as the others,
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
                // Blank, not "Never": an empty enrolment cell next to a
                // populated Secure Boot cell already reads as "not enrolled",
                // and the word would crowd a column most fleets never look at.
                if (!data) {
                    return '';
                }
                // A staged MOK request is NOT an enrolment and must not read
                // as one -- the machine will not boot with Secure Boot on
                // until someone confirms it at the MokManager screen. The
                // date alone would say the opposite.
                if (String(row.sbenrollvia || '') === 'mok-pending') {
                    return data +
                        ' <span class="badge bg-warning">pending</span>';
                }
                return data;
            },
            targets: colIndex.sbenrolled
        });
    }

    var table = $('#dataTable').registerTable(onSelect, {
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
})(jQuery);
