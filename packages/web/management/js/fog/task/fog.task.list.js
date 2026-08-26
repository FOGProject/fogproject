(function($) {
  // The tabbed task page marker; absent means we were loaded on some other
  // page (or the page was already swapped out), so do nothing.
  var initialTabEl = document.getElementById('task-initial-tab');
  if (!initialTabEl) {
    return;
  }

  // ---------------------------------------------------------------
  // PANE REGISTRY
  //
  // One entry per tab pane. Tables initialize lazily on first activation
  // (only the landing tab pays the init/first-load cost) and only the
  // visible pane's table is reloaded by the shared 5s tick. poll:false
  // (Recent) still refreshes on every tab activation, just not on the tick.
  var panes = {
    active: {
      id: 'active',
      poll: true,
      paused: false,
      table: null,
      build: buildActive
    },
    multicast: {
      id: 'multicast',
      poll: true,
      paused: false,
      table: null,
      build: buildMulticast
    },
    snapins: {
      id: 'snapins',
      poll: true,
      paused: false,
      table: null,
      build: buildSnapins
    },
    scheduled: {
      id: 'scheduled',
      poll: true,
      paused: false,
      table: null,
      build: buildScheduled
    },
    deletions: {
      id: 'deletions',
      poll: true,
      paused: false,
      table: null,
      build: buildDeletions
    },
    recent: {
      id: 'recent',
      poll: false,
      paused: false,
      table: null,
      build: buildRecent
    },
    logs: {
      id: 'logs',
      poll: false,
      paused: false,
      table: null,
      build: buildLogs
    }
  };

  function paneOnSelect(pane) {
    return function(selected) {
      $('#' + pane.id + ' .cancel-selected')
        .prop('disabled', selected.count() == 0);
    };
  }

  // ---------------------------------------------------------------
  // TABLE BUILDERS (ported unchanged from the former per-sub task files)

  function buildActive(pane) {
    return $('#active-tasks-table').registerTable(paneOnSelect(pane), {
      // Infinite-scroll (Scroller) for UI consistency with the other lists, but
      // with deferRender explicitly OFF for this table only. It polls every 5s and
      // frequently transitions to zero rows (task cancelled/completed); with
      // deferRender on, DataTables caches the last window's row node and Scroller
      // leaves it -- striped progress bar and all -- orphaned in the fixed-height
      // scroll body on the empty draw, beneath "No data available in table".
      // Disabling deferRender makes every draw rebuild the row nodes, so the empty
      // draw clears the body cleanly. The perf cost is nil for a short live-status
      // list (server-side only ever sends the current viewport window anyway).
      deferRender: false,
      order: [
        [0, 'asc'],
        [4, 'desc']
      ],
      columns: [
        {data: 'hostname'},
        {data: 'imagename'},
        {data: 'storagenodename'},
        {data: 'createdBy'},
        {data: 'scheduledStartTime'},
        {data: 'checkInTime'},
        {data: 'tasktypename'},
        {data: 'taskstatename'},
        {data: 'percent'}
      ],
      rowId: 'id',
      columnDefs: [
        {
          responsivePriority: -1,
          render: function(data, type, row) {
            return '<a href="../management/index.php?node=host&sub=edit&id=' + row.hostid + '">' + $.escapeHtml(data) + '</a>';
          },
          targets: 0
        },
        {
          responsivePriority: 0,
          render: function(data, type, row) {
            return '<a href="../management/index.php?node=image&sub=edit&id=' + row.imageid + '">' + $.escapeHtml(data) + '</a>';
          },
          targets: 1
        },
        {
          responsivePriority: 1,
          render: function(data, type, row) {
            return '<a href="../management/index.php?node=storagenode&sub=edit&id=' + row.storagenodeid + '">' + $.escapeHtml(data) + '</a>';
          },
          targets: 2
        },
        {
          responsivePriority: 2,
          render: function(data, type, row) {
            return '<a href="../management/index.php?node=user&sub=edit&id=' + row.userid + '">' + $.escapeHtml(data) + '</a>';
          },
          targets: 3
        },
        {
          responsivePriority: 3,
          render: function(data, type, row) {
            return data;
          },
          targets: 4
        },
        {
          responsivePriority: 4,
          render: function(data, type, row) {
            return data;
          },
          targets: 5
        },
        {
          render: function(data, type, row) {
            return row.tasktypename
              + ' <i class="fas fa-' + row.tasktypeicon + '"></i> '
          },
          targets: 6
        },
        {
          render: function(data, type, row) {
            return row.taskstatename
              + ' <i class="fas fa-' + row.taskstateicon + '"></i> '
          },
          targets: 7
        },
        {
          render: function(data, type, row) {
            if (data) {
              data = parseInt(data);
            } else {
              data = parseInt(row.pct);
            }
            return $.escapeHtml(row.timeElapsed)
              + ' / '
              + $.escapeHtml(row.timeRemaining)
              + ' '
              + $.escapeHtml(row.dataCopied)
              + ' of '
              + $.escapeHtml(row.dataTotal)
              + ' ('
              + $.escapeHtml(row.bpm)
              + '/min)'
              + '<div class="progress progress-md active">'
              + '<div class="progress-bar progress-bar-success progress-bar-striped" role="progressbar" aria-valuenow="'
              + data
              + '" aria-valuemin="0" aria-valuemax="100" style="width:'
              + data
              + '%">'
              + data
              + '%'
              + '</div>'
              + '</div>';
          },
          targets: 8
        }
      ],
      serverSide: true,
      ajax: {
        url: '../management/index.php?node=' + Common.node + '&sub=getActiveTasks',
        type: 'post'
      }
    });
  }

  function buildMulticast(pane) {
    return $('#active-multicast-table').registerTable(paneOnSelect(pane), {
      order: [
        [0, 'asc']
      ],
      columns: [
        {data: 'name'},
        {data: 'clients'},
        {data: 'starttime'},
        {data: 'taskstatename'}
      ],
      rowId: 'id',
      columnDefs: [
        {
          render: function(data, type, row) {
            if (type !== 'display') {
              return data;
            }
            return fogMulticastClients(data, row.sessclients);
          },
          targets: 1
        },
        {
          render: function(data, type, row) {
            return '<i class="fas fa-' + row.taskstateicon + '"></i>';
          },
          targets: 3
        }
      ],
      serverSide: true,
      ajax: {
        url: '../management/index.php?node=' + Common.node + '&sub=getActiveMulticastTasks',
        type: 'post'
      }
    });
  }

  function buildSnapins(pane) {
    return $('#active-snapintasks-table').registerTable(paneOnSelect(pane), {
      order: [
        [0, 'asc'],
        [2, 'desc']
      ],
      columns: [
        {data: 'snapinname'},
        {data: 'hostname'},
        {data: 'checkin'},
        {data: 'taskstatename'}
      ],
      rowId: 'id',
      columnDefs: [
        {
          render: function(data, type, row) {
            return '<a href="../management/index.php?node=snapin&sub=edit&id='
              + row.snapinid
              + '">'
              + '(' + row.snapinid + ') - ' + data
              + '</a>';
          },
          targets: 0
        },
        {
          render: function(data, type, row) {
            return '<a href="../management/index.php?node=host&sub=edit&id='
              + row.hostid
              + '">'
              + '(' + row.hostid + ') - ' + data
              + '</a>';
          },
          targets: 1
        },
        {
          render: function(data, type, row) {
            return data
              + ' <i class="fas fa-'
              + row.taskstateicon
              + '"></i>';
          },
          targets: 3
        }
      ],
      serverSide: true,
      ajax: {
        url: '../management/index.php?node=' + Common.node + '&sub=getActiveSnapinTasks',
        type: 'post'
      }
    });
  }

  function buildScheduled(pane) {
    return $('#scheduled-task-table').registerTable(paneOnSelect(pane), {
      columns: [
        {data: 'hostLink'},
        {data: 'taskTypeName'},
        {data: 'starttime'},
        {data: 'isActive'},
        {data: 'type'}
      ],
      rowId: 'id',
      serverSide: true,
      ajax: {
        url: '../management/index.php?node=' + Common.node + '&sub=getScheduledTasks',
        type: 'post'
      }
    });
  }

  function buildDeletions(pane) {
    return $('#scheduled-deletion-table').registerTable(paneOnSelect(pane), {
      columns: [
        {data: 'storagegroupLink'},
        {data: 'path'},
        {data: 'pathtype'},
        {data: 'createdTime'},
        {data: 'completedTime'},
        {data: 'createdBy'},
        {data: 'taskstatename'}
      ],
      rowId: 'id',
      serverSide: true,
      ajax: {
        url: '../management/index.php?node=' + Common.node + '&sub=getScheduledDeleteQueues',
        type: 'post'
      }
    });
  }

  function buildRecent(pane) {
    // Read-only history: rows link out to the host/image/user, plus a
    // re-deploy shortcut per row. No selection actions, no polling.
    return $('#recent-tasks-table').registerTable(null, {
      order: [
        [5, 'desc'],
        [0, 'asc']
      ],
      columns: [
        {data: 'hostname'},
        {data: 'imagename'},
        {data: 'tasktypename'},
        {data: 'createdBy'},
        {data: 'taskstatename'},
        {data: 'statechanged'},
        {data: 'id'}
      ],
      rowId: 'id',
      columnDefs: [
        {
          responsivePriority: -1,
          render: function(data, type, row) {
            if (!row.hostid) {
              return $.escapeHtml(data);
            }
            return '<a href="../management/index.php?node=host&sub=edit&id=' + row.hostid + '">' + $.escapeHtml(data) + '</a>';
          },
          targets: 0
        },
        {
          responsivePriority: 1,
          render: function(data, type, row) {
            if (!row.imageid) {
              return $.escapeHtml(data);
            }
            return '<a href="../management/index.php?node=image&sub=edit&id=' + row.imageid + '">' + $.escapeHtml(data) + '</a>';
          },
          targets: 1
        },
        {
          render: function(data, type, row) {
            return $.escapeHtml(data || '')
              + ' <i class="fas fa-' + $.escapeHtml(row.tasktypeicon || '') + '"></i> ';
          },
          targets: 2
        },
        {
          render: function(data, type, row) {
            if (!row.userid) {
              return $.escapeHtml(data);
            }
            return '<a href="../management/index.php?node=user&sub=edit&id=' + row.userid + '">' + $.escapeHtml(data) + '</a>';
          },
          targets: 3
        },
        {
          render: function(data, type, row) {
            return $.escapeHtml(data || '')
              + ' <i class="fas fa-' + $.escapeHtml(row.taskstateicon || '') + '"></i> ';
          },
          targets: 4
        },
        {
          responsivePriority: 0,
          targets: 5
        },
        {
          orderable: false,
          searchable: false,
          render: function(data, type, row) {
            if (!row.hostid || !row.typeID) {
              return '';
            }
            return '<a href="../management/index.php?node=host&sub=deploy&id='
              + row.hostid
              + '&type=' + row.typeID
              + '" class="taskitem" data-task-name="'
              + $.escapeHtml(row.tasktypename + ' - ' + row.hostname)
              + '" title="Run this task again">'
              + '<i class="fas fa-arrow-rotate-right"></i>'
              + '</a>';
          },
          targets: 6
        }
      ],
      serverSide: true,
      ajax: {
        url: '../management/index.php?node=' + Common.node + '&sub=getRecentTasks',
        type: 'post',
        data: function(d) {
          d.typegroup = $('#recent-type-filter').val();
          d.states = $('input[name="recent-state-filter"]:checked').val();
        }
      }
    });
  }

  function buildLogs(pane) {
    // Read-only history of taskLog, which nothing has ever displayed. Message
    // is the wide column, so it is the one that survives a narrow viewport;
    // the icons come from the same taskStates/taskTypes rows the other panes
    // draw from, so a Failed row is marked the same way everywhere.
    return $('#task-logs-table').registerTable(null, {
      order: [
        [0, 'desc']
      ],
      columns: [
        {data: 'logtime'},
        {data: 'hostname'},
        {data: 'tasktypename'},
        {data: 'taskstatename'},
        {data: 'logtype'},
        {data: 'logtext'},
        {data: 'createdBy'}
      ],
      rowId: 'id',
      columnDefs: [
        {
          responsivePriority: 0,
          targets: 0
        },
        {
          responsivePriority: 1,
          render: function(data, type, row) {
            // A row can genuinely have no name to show: state rows written
            // before schema 341 carry no copy of one, and once their task is
            // deleted the join has nothing to fall back on either. Schema 373
            // fills in every such row whose task still exists; the rest are
            // unrecoverable. Say so with a dash rather than leaving the cell
            // empty, which reads as a rendering fault. Dash rather than words
            // because this file has no translation mechanism.
            if (!data) {
              return '<span class="text-body-secondary">&mdash;</span>';
            }
            if (!row.hostid) {
              return $.escapeHtml(data);
            }
            return '<a href="../management/index.php?node=host&sub=edit&id=' + row.hostid + '">' + $.escapeHtml(data) + '</a>';
          },
          targets: 1
        },
        {
          render: function(data, type, row) {
            // Same as the host column, and the icon goes with it: an icon
            // beside no name is worse than no icon.
            if (!data) {
              return '<span class="text-body-secondary">&mdash;</span>';
            }
            return $.escapeHtml(data)
              + ' <i class="fas fa-' + $.escapeHtml(row.tasktypeicon || '') + '"></i> ';
          },
          targets: 2
        },
        {
          render: function(data, type, row) {
            return $.escapeHtml(data || '')
              + ' <i class="fas fa-' + $.escapeHtml(row.taskstateicon || '') + '"></i> ';
          },
          targets: 3
        },
        {
          // Ranked above task type and state, because it is the column that
          // says whether the machine stopped or carried on -- the first thing
          // anyone opening this tab is looking for, and the one that must not
          // be the one Responsive collapses away on a laptop.
          responsivePriority: 2,
          render: function(data) {
            // Badged rather than left as bare text, for the same reason.
            var cls = {error: 'bg-danger', warning: 'bg-warning text-dark'};
            return '<span class="badge ' + (cls[data] || 'bg-secondary') + '">'
              + $.escapeHtml(data || '')
              + '</span>';
          },
          targets: 4
        },
        {
          responsivePriority: -1,
          // Escaped, like every other column here. DataTables writes cell
          // content with innerHTML, so a column with no render is an HTML
          // sink -- and this is the one column fed by taskerror.class.php,
          // an endpoint FOS reaches without authenticating. It was the only
          // one left bare.
          //
          // Flattened as well: the stored report now keeps its line breaks,
          // which the modal renders in a <pre>. In a one-line grid cell they
          // are only whitespace, and a preview that starts with a blank line
          // reads as an empty column.
          render: function(data) {
            return $.escapeHtml(
              String(data || '').replace(/\s+/g, ' ').trim()
            );
          },
          targets: 5
        }
      ],
      serverSide: true,
      ajax: {
        url: '../management/index.php?node=' + Common.node + '&sub=getTaskLogs',
        type: 'post',
        data: function(d) {
          d.logtypes = $('input[name="log-type-filter"]:checked').val();
        }
      },
      createdRow: function(row) {
        // The whole row is the target, so say so: without a pointer there is
        // nothing to suggest the message has more behind it.
        $(row).css('cursor', 'pointer');
      }
    });
  }

  // ---------------------------------------------------------------
  // LOG ENTRY DETAIL
  //
  // Filled from the row the grid already holds -- no request. The message is
  // the reason this exists: it is the column that truncates on a narrow
  // viewport, and a FOS report carries the script it came from and the
  // arguments it was passed, which is exactly what someone reading it needs.
  function showLogDetail(row) {
    var badge = {error: 'bg-danger', warning: 'bg-warning text-dark'},
      $dl = $('#task-log-detail'),
      pairs = [
        ['Time', $.escapeHtml(row.logtime || '')],
        ['Host', row.hostid ?
          '<a href="../management/index.php?node=host&sub=edit&id=' + row.hostid + '">' + $.escapeHtml(row.hostname || '') + '</a>' :
          $.escapeHtml(row.hostname || '')],
        ['Task', $.escapeHtml(String(row.taskid || '')) + ' &mdash; ' + $.escapeHtml(row.tasktypename || '')],
        ['State at the time', $.escapeHtml(row.taskstatename || '')
          + ' <i class="fas fa-' + $.escapeHtml(row.taskstateicon || '') + '"></i>'],
        ['Type', '<span class="badge ' + (badge[row.logtype] || 'bg-secondary') + '">'
          + $.escapeHtml(row.logtype || '') + '</span>'],
        ['Recorded by', $.escapeHtml(row.createdBy || '')]
      ],
      html = '';
    $.each(pairs, function(i, pair) {
      html += '<dt class="col-sm-3">' + pair[0] + '</dt>'
        + '<dd class="col-sm-9">' + pair[1] + '</dd>';
    });
    // A state change has no message at all, which is a fact worth showing
    // rather than an empty box.
    html += '<dt class="col-sm-3">Message</dt><dd class="col-sm-9">'
      + (row.logtext ?
        // NOT .text-wrap: Bootstrap defines that as
        // `white-space: normal !important`, which overrides the <pre> and
        // collapses exactly the line breaks the report is now stored with.
        // pre-wrap keeps them and still wraps long lines inside the modal.
        '<pre class="mb-0" style="white-space:pre-wrap;overflow-wrap:anywhere;">' + $.escapeHtml(row.logtext) + '</pre>' :
        '<em>none</em>')
      + '</dd>';
    $dl.html(html);
    $('#task-log-modal').modal('show');
  }

  // Delegated, because the grid replaces its rows on every draw.
  $(document).on('click', '#task-logs-table tbody tr', function(e) {
    // A row carries links out to the host; let those win.
    if ($(e.target).closest('a').length) {
      return;
    }
    var row = panes.logs.table && panes.logs.table.row(this).data();
    if (row) {
      showLogDetail(row);
    }
  });

  // ---------------------------------------------------------------
  // PER-PANE ACTION BUTTONS (cancel / reload toggle)
  //
  // Bound once, at the pane's first activation. The reload toggle only stops
  // the shared tick for this pane; other panes keep their own state.
  function bindPaneButtons(pane) {
    var $pane = $('#' + pane.id),
      cancelSelected = $pane.find('.cancel-selected');
    if (!cancelSelected.length) {
      return; // Recent has no action footer.
    }
    cancelSelected.prop('disabled', true);
    $.registerReloadToggle($pane.find('.reload-toggle'), {
      onPause: function() {
        pane.paused = true;
      },
      onResume: function() {
        pane.paused = false;
        pane.table.ajax.reload(null, false);
      }
    });
    cancelSelected.on('click', function() {
      cancelSelected.prop('disabled', true);
      var toRemove = $.getSelectedIds(pane.table),
        opts = {
          'cancelconfirm': '1',
          'tasks': toRemove
        };
      $.apiCall(cancelSelected.attr('method'), cancelSelected.attr('action'), opts, function(err) {
        if (!err) {
          pane.table.draw(false);
          pane.table.rows({selected: true}).deselect();
        } else {
          cancelSelected.prop('disabled', false);
        }
      });
    });
  }

  // ---------------------------------------------------------------
  // TAB ACTIVATION

  function activatePane(id) {
    var pane = panes[id];
    if (!pane) {
      return; // Plugin-injected tab; not ours to drive.
    }
    if (!pane.table) {
      pane.table = pane.build(pane);
      if (Common.search && Common.search.length > 0) {
        pane.table.search(Common.search).draw();
      }
      bindPaneButtons(pane);
    } else if (!pane.paused) {
      pane.table.ajax.reload(null, false);
    }
  }

  function visiblePaneId() {
    for (var id in panes) {
      var el = document.getElementById(id);
      if (el && el.classList.contains('active')) {
        return id;
      }
    }
    return null;
  }

  // The card wrapping both the nav-tabs and the panes. Scoping the listener
  // (and the nav queries) here keeps them clear of any other tab markup and
  // lets the handler die with the element on an AJAX page swap.
  var card = document.getElementById('active');
  card = card && card.closest ? card.closest('.card') : null;
  var tabScope = card || document;

  function navLinkFor(id) {
    return tabScope.querySelector('.nav-tabs a.nav-link[href="#' + id + '"]');
  }

  // The tab shim only bridges modal events to jQuery, so listen natively.
  // Bootstrap fires shown.bs.tab on the nav link; it bubbles to the card.
  tabScope.addEventListener('shown.bs.tab', function(e) {
    if (!document.body.contains(initialTabEl)) {
      return;
    }
    var href = e.target.getAttribute('href') || '';
    if (href.charAt(0) === '#') {
      activatePane(href.slice(1));
    }
  });

  // ---------------------------------------------------------------
  // SHARED 5s TICK: refresh the visible pane's table + the tab count badges.
  //
  // setTimeout chains aren't cleared by the AJAX-nav teardown
  // (clearAllIntervals only kills intervals), so each tick re-checks that
  // this page is still in the DOM and stops rescheduling once it's gone.
  var POLL_MS = 5000;

  function updateBadges() {
    $.ajax({
      type: 'get',
      url: '../management/index.php?node=' + Common.node + '&sub=getTaskCounts',
      dataType: 'json',
      success: function(counts) {
        if (!counts) {
          return;
        }
        $('.task-count-badge').each(function() {
          var key = $(this).data('count');
          if (!(key in counts)) {
            return;
          }
          var n = parseInt(counts[key]) || 0;
          $(this)
            .text(n)
            .toggleClass('bg-primary', n > 0)
            .toggleClass('bg-secondary opacity-50', n === 0);
        });
      }
    });
  }

  function tick() {
    if (!document.body.contains(initialTabEl)) {
      return;
    }
    updateBadges();
    var pane = panes[visiblePaneId()];
    if (pane && pane.table && pane.poll && !pane.paused) {
      pane.table.ajax.reload(null, false);
    }
    setTimeout(tick, POLL_MS);
  }

  // ---------------------------------------------------------------
  // RECENT AND LOG TAB FILTERS

  function reloadRecent() {
    if (panes.recent.table) {
      panes.recent.table.ajax.reload(null, true);
    }
  }

  function reloadLogs() {
    if (panes.logs.table) {
      panes.logs.table.ajax.reload(null, true);
    }
  }
  $('#recent-type-filter').on('change', reloadRecent);
  $('input[name="recent-state-filter"]').on('change', reloadRecent);
  $('input[name="log-type-filter"]').on('change', reloadLogs);

  // ---------------------------------------------------------------
  // RECENT TAB RE-DEPLOY (ported from the host edit tasking tab; delegated
  // because the rows are redrawn by DataTables)
  var taskModal = $('#task-modal');
  $('#recent-tasks-table').on('click', 'a.taskitem', function(e) {
    e.preventDefault();
    var taskName = $(this).data('task-name') || $(this).text();
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
      if (req != null) {
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

          var hostDeployForm = '#host-deploy-form',
            minutes = $('#cronMin', $(hostDeployForm)),
            hours = $('#cronHour', $(hostDeployForm)),
            dom = $('#cronDom', $(hostDeployForm)),
            month = $('#cronMonth', $(hostDeployForm)),
            dow = $('#cronDow', $(hostDeployForm));
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
              var vals = $(this).cron('value').split(' ');
              minutes.val(vals[0]);
              hours.val(vals[1]);
              dom.val(vals[2]);
              month.val(vals[3]);
              dow.val(vals[4]);
            }
          });
        },
        error: function(jqXHR, textStatus, errorThrown) {
          if (textStatus == 'abort') return; // Do not show error message on abort.
          taskModal.modal('hide');
          $.notifyFromAPI(jqXHR.responseJSON, jqXHR);
        }
      });
    });
  });

  // ---------------------------------------------------------------
  // STARTUP: resolve which tab to land on, then start the tick.
  //
  // Priority: a valid #hash deep link, then the server-provided initial tab
  // (legacy ?sub= links), then whatever the server marked active. fog.js may
  // have already shown the hash tab before this script ran (script order on
  // a full page load), so an already-active target is activated directly --
  // .tab('show') on the active link fires no shown event.
  var target = null,
    hashMatch = location.hash.match(/^#([A-Za-z0-9_-]+)$/);
  if (hashMatch) {
    target = navLinkFor(hashMatch[1]);
  }
  if (!target) {
    target = navLinkFor(initialTabEl.value);
  }
  if (!target) {
    target = tabScope.querySelector('.nav-tabs a.nav-link.active');
  }
  if (target) {
    if (target.classList.contains('active')) {
      var targetHref = target.getAttribute('href') || '';
      if (targetHref.charAt(0) === '#') {
        activatePane(targetHref.slice(1));
      }
    } else {
      $(target).tab('show');
    }
  }

  updateBadges();
  setTimeout(tick, POLL_MS);
})(jQuery);
