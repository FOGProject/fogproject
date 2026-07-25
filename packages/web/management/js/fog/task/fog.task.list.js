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
              + ' <i class="fa fa-' + row.tasktypeicon + '"></i> '
          },
          targets: 6
        },
        {
          render: function(data, type, row) {
            return row.taskstatename
              + ' <i class="fa fa-' + row.taskstateicon + '"></i> '
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
            return '<i class="fa fa-' + row.taskstateicon + '"></i>';
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
              + ' <i class="fa fa-'
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
              + ' <i class="fa fa-' + $.escapeHtml(row.tasktypeicon || '') + '"></i> ';
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
              + ' <i class="fa fa-' + $.escapeHtml(row.taskstateicon || '') + '"></i> ';
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
              + '<i class="fa fa-repeat"></i>'
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

  // ---------------------------------------------------------------
  // PER-PANE ACTION BUTTONS (cancel / pause / resume)
  //
  // Bound once, at the pane's first activation. Pause/resume only stop the
  // shared tick for this pane; other panes keep their own state.
  function bindPaneButtons(pane) {
    var $pane = $('#' + pane.id),
      cancelSelected = $pane.find('.cancel-selected'),
      pauseReload = $pane.find('.pause-refresh'),
      resumeReload = $pane.find('.resume-refresh');
    if (!cancelSelected.length) {
      return; // Recent has no action footer.
    }
    cancelSelected.prop('disabled', true);
    pauseReload.prop('disabled', false);
    resumeReload.prop('disabled', true);
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
    pauseReload.on('click', function() {
      pauseReload.prop('disabled', true);
      resumeReload.prop('disabled', false);
      pane.paused = true;
    });
    resumeReload.on('click', function() {
      resumeReload.prop('disabled', false);
      pauseReload.prop('disabled', false);
      pane.paused = false;
      pane.table.ajax.reload(null, false);
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
  // RECENT TAB FILTERS

  function reloadRecent() {
    if (panes.recent.table) {
      panes.recent.table.ajax.reload(null, true);
    }
  }
  $('#recent-type-filter').on('change', reloadRecent);
  $('input[name="recent-state-filter"]').on('change', reloadRecent);

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
