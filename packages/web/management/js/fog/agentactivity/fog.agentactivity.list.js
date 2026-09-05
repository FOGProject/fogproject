/**
 * Agent activity: one grid, grouped by host, expanded in place.
 *
 * Read only by construction, not by omission: `auditlog` has no create,
 * update or delete route anywhere in FOG (ADR 0021 Decision 8), so there is
 * nothing here to wire a row action to. The page declares that once, in
 * AgentActivityManagement::$selectable, and the toolbar follows.
 *
 * WHY NOT A CHILD ROW. The first version of this file opened a nested
 * DataTable per host through `row.child()`. A DataTables row has ONE child
 * slot; registerTable() turns Responsive on for every grid, and Responsive
 * owns that slot. So the nested table was never built -- clicking expand
 * rendered Responsive's hidden-column list instead, on a live install, at a
 * width where nothing was hidden to begin with. Adding rows to the grid
 * itself has no such conflict, and it is what makes an expanded host look
 * like the rest of FOG instead of a grid inside a grid with its own
 * scrollbar and pager.
 *
 * WHY EVERY GROUP KEEPS ONE ROW. Collapse is a search filter over the event
 * rows, and RowGroup draws its headers from the rows that SURVIVE the
 * filter: a group left with none renders no header, and the host vanishes
 * from the page. So each host's newest event is seeded into the table and
 * is never filtered. It anchors the header, and it is the row worth reading
 * when everything is collapsed -- what each agent last did.
 *
 * WHY THE SEED IS SEPARATE FROM THE EXPANSION. An agent writes a row per
 * changed fact per host and FOG_AUDIT_RETENTION_DAYS defaults to 0 (keep
 * forever), so the flat event set is unbounded -- it cannot be loaded
 * client side, which is equally why rowGroup over a `serverSide` grid is
 * not an option (it would group within one page). The seed is bounded by
 * the fleet, each expansion by ROWS_PER_HOST. Nothing here loads a set
 * bounded by neither.
 */
(function($) {
  var $table = $('#agentactivity-table');

  if (!$table.length) {
    return;
  }

  // How many rows one expansion pulls. A host that has been enrolled for a
  // year has thousands, and putting all of them into the browser to answer
  // "what has this machine been doing" is the cost the summary exists to
  // avoid. Past this the header says so and offers the host's own page.
  var ROWS_PER_HOST = 500;

  // hostName -> true while that host's events are showing. Keyed by name
  // because that is what RowGroup groups on and what startRender is handed;
  // the id travels on the row data for the fetch.
  var expanded = {};
  // hostName -> true once its rows are in the table, so a collapse and a
  // second expand do not re-fetch what is already loaded.
  var loaded = {};
  // hostName -> true while a fetch is in flight, so a double click on a
  // header cannot start two.
  var loading = {};
  // hostName -> total the server reported, when it is more than we asked
  // for. Read by the header to say what is not being shown.
  var truncated = {};

  var outcomeClass = {
    allowed: 'text-bg-success',
    denied: 'text-bg-danger',
    failed: 'text-bg-warning',
    partial: 'text-bg-warning',
    unknown: 'text-bg-secondary'
  };

  // Every column escapes. An audit row carries subject labels and detail
  // text that came from a machine on the network, so its contents are
  // hostile by definition -- and DataTables writes cell data as HTML unless
  // a column supplies its own render.
  function escaped(field) {
    return {
      data: field,
      render: function(d, t) {
        // display only: the Buttons CSV/copy exports ask for other types and
        // escaping those would put &amp; into the exported file.
        return t === 'display' ? $.escapeHtml(d === null ? '' : String(d)) : d;
      }
    };
  }

  function outcomeColumn() {
    return {
      data: 'outcome',
      render: function(d, t) {
        var v = d === null ? '' : String(d);
        if (t !== 'display') {
          return v;
        }
        if ('' === v) {
          return '';
        }
        return '<span class="badge ' + (outcomeClass[v] || outcomeClass.unknown)
          + '">' + $.escapeHtml(v) + '</span>';
      }
    };
  }

  // The seed rows and the fetched rows are the same shape by the time they
  // reach the table, so one column set serves both and an expanded group is
  // continuous with its anchor rather than a differently-shaped insert.
  function seedRow(host) {
    return {
      hostID: host.hostID,
      hostName: host.hostName,
      events: host.events,
      createdTime: host.lastTime,
      type: host.lastType,
      text: host.lastText,
      outcome: host.lastOutcome,
      // What the hidden host column SORTS by -- see groupSortColumn().
      groupSort: String(host.lastTime) + '|' + String(host.hostID),
      // Marks the row that must never be filtered out. Without it a
      // collapsed group loses every row, and with them its header.
      anchor: true
    };
  }

  // `seed` is a row this file already built, NOT the raw summary object --
  // so the sort key is COPIED from it rather than recomputed. Recomputing it
  // from seed.lastTime read undefined (a seed row carries createdTime), every
  // event of a host sorted under "undefined|<id>" instead of beside its
  // anchor, and the group split in two with its header drawn twice.
  function eventRow(seed, row) {
    return {
      hostID: seed.hostID,
      hostName: seed.hostName,
      events: seed.events,
      createdTime: row.createdTime,
      type: row.type,
      text: row.text,
      outcome: row.outcome,
      // Identical for every row of one host, which is what keeps the group
      // contiguous once the table is ordered by it.
      groupSort: seed.groupSort,
      anchor: false
    };
  }

  // The hidden Host column: displayed as the host name if it were ever
  // shown, but SORTED by the group's own recency.
  //
  // RowGroup starts a new group -- and draws another header -- every time
  // its dataSrc changes down the ordered rows, so a group is only whole if
  // the table is ordered by it. Ordering by time alone is not enough: one
  // host's older events fall past the next host's newest one and its header
  // is drawn twice. That was measured, not predicted.
  //
  // Sorting alphabetically by name would fix contiguity and lose the order
  // that matters, which is "which agents have done something lately". So
  // every row of a host sorts on that host's LAST activity plus its id --
  // one value per group, so groups stay whole, ordered by recency, with the
  // id breaking a tie between two hosts last seen in the same second.
  function groupSortColumn() {
    return {
      data: 'hostName',
      visible: false,
      className: 'noVis',
      render: function(d, t, row) {
        if (t === 'sort' || t === 'type') {
          return row.groupSort;
        }
        return t === 'display'
          ? $.escapeHtml(d === null ? '' : String(d))
          : d;
      }
    };
  }

  // Collapse hides a group's event rows and leaves its anchor. Registered
  // once and scoped to this table by node identity -- ext.search is global,
  // so an unscoped filter would silently apply to every grid on the page.
  var tableNode = $table.get(0);

  $.fn.dataTable.ext.search.push(function(settings, data, dataIndex, row) {
    if (settings.nTable !== tableNode) {
      return true;
    }
    return row.anchor === true || expanded[row.hostName] === true;
  });

  // The group header. It carries what used to be four columns of a summary
  // grid -- the host, its event count, and now the expand control -- which
  // is what frees the columns below to be the events themselves.
  function groupHeader(rows, name) {
    var d = rows.data()[0] || {},
      open = expanded[name] === true,
      count = parseInt(d.events, 10) || 0,
      note = '';

    if (truncated[name]) {
      // Said on the header rather than in a row: it is a fact about the
      // group, and a row saying it would sort and filter like an event.
      note = ' <span class="text-body-secondary small">'
        + $.escapeHtml('showing the newest ' + ROWS_PER_HOST) + '</span>';
    }

    return $('<tr/>')
      .addClass('agentactivity-group')
      .attr('data-host', name)
      .append(
        $('<td/>')
          .attr('colspan', 5)
          .html(
            '<button type="button" class="btn btn-sm btn-link p-0 me-2'
            + ' agentactivity-toggle" aria-expanded="' + (open ? 'true' : 'false')
            + '"><i class="fas '
            // Whole names, not 'fa-chevron-' plus a half: the icon audit
            // reads the emitted string and a concatenated name resolves to
            // nothing it can check.
            + (open ? 'fa-chevron-down' : 'fa-chevron-right')
            + '"></i></button>'
            + '<span class="fw-semibold">' + $.escapeHtml(name) + '</span>'
            + ' <span class="badge text-bg-secondary">'
            + $.escapeHtml(String(count)) + '</span>'
            + note
          )
      );
  }

  var table = $table.registerTable(null, {
    // Newest activity first. Ordering by the time column also keeps each
    // host's rows in the order its agent wrote them, since RowGroup orders
    // groups by the first ordering column it is grouped on and rows within
    // a group by whatever follows.
    // Groups first, then time within a group. Both descending: the most
    // recently active host heads the page, and its newest event heads it.
    order: [
      [4, 'desc'],
      [0, 'desc']
    ],
    columns: [
      escaped('createdTime'),
      escaped('type'),
      escaped('text'),
      outcomeColumn(),
      groupSortColumn()
    ],
    rowGroup: {
      dataSrc: 'hostName',
      startRender: groupHeader
    },
    processing: true,
    // NO PAGING, and that is the whole reason this page reads correctly.
    //
    // Paging counts ROWS; this page's unit is HOSTS. Expanding one host with
    // 83 events at 25 rows a page filled pages one to three with that host
    // and pushed every other host onto page four -- and RowGroup redraws a
    // group's header on each page the group spans, so the same host appeared
    // four times, expanded, which is what it looked like to the person
    // reading it. Neither is a bug in RowGroup: both are what paging by row
    // means when the thing being grouped is bigger than a page.
    //
    // There is no page length that fixes it, because the number of rows an
    // expansion adds is a property of the host, not of the setting. Turning
    // paging off removes the unit mismatch instead of tuning it: collapsed,
    // the grid is one row per host; expanded, it gets longer and you scroll.
    // The seed is bounded by the fleet (MAX_HOSTS) and each expansion by
    // ROWS_PER_HOST, so "no paging" is not "no limit".
    //
    // Scroller is not the alternative -- registerTable() excludes any
    // rowGroup table from it, because its virtual row-height math cannot
    // reconcile injected header rows.
    paging: false,
    // ...and with it the "entries per page" control, which paging is the
    // only thing that gives a value. Left in, it renders as an empty box
    // beside its own label in both themes -- not a contrast problem, a
    // control with nothing to say. dom keeps `l` for every other grid, so
    // this is the switch that removes it here.
    lengthChange: false,
    // Client side: the seed is one row per host, which is bounded by the
    // fleet, and rowGroup cannot group a server-side grid beyond one page.
    serverSide: false,
    // Nothing here is selectable -- auditlog has no write route at all (ADR
    // 0021 Decision 8) -- and registerTable() drops Select All / Deselect
    // All when it sees this.
    select: false,
    ajax: {
      url: '../management/index.php?node=agentactivity&sub=getList',
      type: 'post',
      dataSrc: function(json) {
        var out = [];
        $.each(json.data || [], function(i, host) {
          out.push(seedRow(host));
        });
        return out;
      }
    }
  });

  // Groups start collapsed, which is the whole point: the page opens as a
  // list of hosts and what each last did, and you go looking from there.
  // Nothing to do to arrange it -- `expanded` starts empty and the filter
  // above keeps every non-anchor row out until a header is clicked.

  // Every reload starts over, because a reload throws the rows away.
  //
  // The toolbar's Refresh is `dt.clear().draw(); dt.ajax.reload();` -- it
  // empties the table and re-fetches the seed. Without this the maps below
  // survived that: a host still marked `loaded` was never re-fetched, so its
  // rows were gone and clicking its header did nothing at all. It read as
  // the expander breaking permanently after one press of Refresh.
  //
  // xhr.dt fires when the new seed lands, including the first load, where
  // resetting empty maps costs nothing.
  $table.on('xhr.dt', function() {
    expanded = {};
    loaded = {};
    loading = {};
    truncated = {};
  });

  function loadHost(name, hostID, done) {
    if (loading[name]) {
      return;
    }
    loading[name] = true;

    $.ajax({
      url: '../management/index.php?node=agentactivity&sub=getHostActivity&id='
        + encodeURIComponent(hostID),
      type: 'post',
      dataType: 'json',
      // Route::listem() reads DataTables' own paging parameters, so the cap
      // is expressed the way that endpoint already understands rather than
      // by teaching it a second one.
      data: {start: 0, length: ROWS_PER_HOST},
      success: function(json) {
        var rows = (json && json.data) || [],
          // recordsFiltered, NOT recordsTotal. listem()'s recordsTotal is
          // every row in auditLog -- 1435 on the lab install -- so testing
          // against it declared a 134-event host truncated at 500.
          total = (json && json.recordsFiltered) || rows.length,
          seed = table.rows().data().toArray().filter(function(r) {
            return r.hostName === name && r.anchor;
          })[0],
          add = [];

        if (total > rows.length) {
          truncated[name] = true;
        }

        $.each(rows, function(i, row) {
          // The newest row is already on screen as the anchor. Adding it
          // again would show one event twice under its own header.
          if (seed && String(row.createdTime) === String(seed.createdTime)
            && String(row.type) === String(seed.type)) {
            return;
          }
          // A host with no anchor cannot be reached from the UI (there is
          // no header to click), but the key still has to be per-host so a
          // programmatic load cannot merge two hosts into one group.
          add.push(eventRow(
            seed || {
              hostID: hostID,
              hostName: name,
              events: rows.length,
              groupSort: String(row.createdTime) + '|' + String(hostID)
            },
            row
          ));
        });

        if (add.length) {
          table.rows.add(add);
        }
        loaded[name] = true;
      },
      complete: function() {
        loading[name] = false;
        done();
      }
    });
  }

  // Delegated to the table: RowGroup redraws its headers on every draw, so
  // a handler bound to the header elements themselves would be lost the
  // first time anything sorted, searched or added a row.
  $table.on('click', '.agentactivity-group', function(e) {
    var name = $(this).attr('data-host'),
      row = table.rows().data().toArray().filter(function(r) {
        return r.hostName === name && r.anchor;
      })[0];

    e.preventDefault();

    if (expanded[name]) {
      expanded[name] = false;
      table.draw(false);
      return;
    }

    expanded[name] = true;

    if (loaded[name] || !row) {
      table.draw(false);
      return;
    }

    // Drawn before the fetch as well as after: the chevron turns over
    // immediately, so a slow endpoint reads as loading rather than as a
    // click that did nothing.
    table.draw(false);
    loadHost(name, row.hostID, function() {
      table.draw(false);
    });
  });
})(jQuery);
