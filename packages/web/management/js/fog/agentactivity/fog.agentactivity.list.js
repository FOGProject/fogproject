/**
 * Agent activity, one row per host, expanded on demand.
 *
 * Read only by construction, not by omission: `auditlog` has no create,
 * update or delete route anywhere in FOG (ADR 0021 Decision 8), so there is
 * nothing here to wire a row action to.
 *
 * NO rowGroup, and not for the reason fog.audit.list.js gives. Grouping by
 * host is the whole point of this page, but rowGroup groups only within the
 * current PAGE, and the audit grid is serverSide -- so one hostname would
 * head a dozen separate pages. The summary this table renders is grouped in
 * SQL instead, which is exact across the whole table, and it is bounded by
 * the size of the fleet where a flat list of agent rows is not. The scroller
 * survives as a side effect: registerTable() auto-pages any table using
 * rowGroup, and this one does not use it.
 *
 * The summary is CLIENT side -- one row per host is a bounded set, and
 * sorting a fleet by "last seen" has to sort the fleet, not the page. Each
 * expanded host's rows are server side, because that set is not bounded --
 * and they go through registerTable() like every other grid, so they carry
 * the same infinite scroll. Collapsed grouping and infinite scroll were
 * never actually in tension: the tension is between rowGroup and Scroller,
 * and grouping in SQL means there is no rowGroup to have it with.
 */
(function($) {
  var $table = $('#agentactivity-table');

  if (!$table.length) {
    return;
  }

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

  var outcomeClass = {
    allowed: 'text-bg-success',
    denied: 'text-bg-danger',
    failed: 'text-bg-warning',
    partial: 'text-bg-warning',
    unknown: 'text-bg-secondary'
  };

  // The expand control. A button and not a styled cell: it is operated by
  // keyboard as well as by mouse, and a div with a click handler is not.
  function toggleColumn() {
    return {
      data: null,
      orderable: false,
      searchable: false,
      className: 'agentactivity-toggle',
      render: function() {
        return '<button type="button" class="btn btn-sm btn-link p-0'
          + ' agentactivity-expand" aria-expanded="false"'
          + ' title="' + $.escapeHtml('Show this host\'s agent activity')
          + '"><i class="fas fa-chevron-right"></i></button>';
      }
    };
  }

  var table = $table.registerTable(null, {
    // Newest activity first: the question this page answers is "what have
    // the agents been doing", and a host silent for a month is not it.
    order: [
      [3, 'desc']
    ],
    columns: [
      toggleColumn(),
      escaped('hostName'),
      escaped('events'),
      escaped('lastTime'),
      escaped('lastType')
    ],
    rowId: 'hostID',
    processing: true,
    // Client side on purpose -- see the file docblock.
    serverSide: false,
    select: false,
    ajax: {
      url: '../management/index.php?node=agentactivity&sub=getList',
      type: 'post'
    }
  });

  // One child table per expanded host, each its own DataTable against the
  // scoped endpoint. Destroyed on collapse rather than hidden: leaving them
  // alive means every host a user has ever opened keeps redrawing behind a
  // closed row.
  function childTable(hostID) {
    var id = 'agentactivity-child-' + hostID;

    return '<div class="p-2"><table id="' + id
      + '" class="table table-sm w-100"><thead><tr>'
      + '<th>' + $.escapeHtml('When') + '</th>'
      + '<th>' + $.escapeHtml('Event') + '</th>'
      + '<th>' + $.escapeHtml('Detail') + '</th>'
      + '<th>' + $.escapeHtml('Outcome') + '</th>'
      + '</tr></thead></table></div>';
  }

  // registerTable(), not a bare .DataTable(). The first version of this file
  // hand-rolled the child and so opted out of every convention the helper
  // applies -- including its `dom`, which is where the pager lives. The
  // result showed the first ten of a host's events with no way to reach the
  // rest, on a host with seventy-nine of them.
  //
  // Going through the helper also means the child gets the SAME infinite
  // scroll as every other grid: registerTable() turns Scroller on unless the
  // table uses rowGroup or opts out. So the collapsed-by-host view and
  // continuous scrolling are not in tension after all -- the tension was
  // between rowGroup and Scroller, and this design has no rowGroup.
  function buildChild(hostID) {
    var dt = $('#agentactivity-child-' + hostID).registerTable(null, {
      order: [
        [0, 'desc']
      ],
      columns: [
        escaped('createdTime'),
        escaped('type'),
        escaped('text'),
        {
          data: 'outcome',
          render: function(d, t) {
            var v = d === null ? '' : String(d);
            if (t !== 'display') {
              return v;
            }
            return '<span class="badge '
              + (outcomeClass[v] || outcomeClass.unknown) + '">'
              + $.escapeHtml(v) + '</span>';
          }
        }
      ],
      rowId: 'id',
      processing: true,
      serverSide: true,
      // Nothing here is selectable: auditlog has no write route at all
      // (ADR 0021 Decision 8), so a selection could not act on anything.
      select: false,
      // The helper's default viewport is 55vh, which is most of the screen --
      // right for a page that IS the table, wrong for one nested inside a row
      // of another. This is tall enough to scroll and short enough that the
      // host rows below stay reachable.
      scrollY: '18rem',
      ajax: {
        url: '../management/index.php?node=agentactivity'
          + '&sub=getHostActivity&id=' + encodeURIComponent(hostID),
        type: 'post'
      }
    });

    // A child row is inserted AFTER the page has laid out, which is the same
    // situation as a table built inside a hidden tab: Scroller measures a
    // table that has no height and no width yet, and the header/body split
    // stays misaligned until something re-measures. fogBindTableAutosize()
    // does this on shown.bs.tab; there is no such event here, so the sizing
    // pass runs once the row is actually in the document.
    setTimeout(function() {
      try {
        fogSizeScroller(dt);
        dt.columns.adjust();
      } catch (e) {}
    }, 0);

    return dt;
  }

  $table.on('click', '.agentactivity-expand', function() {
    var $btn = $(this),
      row = table.row($btn.closest('tr')),
      hostID = row.id();

    if (row.child.isShown()) {
      $('#agentactivity-child-' + hostID).DataTable().destroy();
      row.child.hide();
      $btn.attr('aria-expanded', 'false')
        .find('i').removeClass('fa-chevron-down').addClass('fa-chevron-right');
      return;
    }

    row.child(childTable(hostID)).show();
    buildChild(hostID);
    $btn.attr('aria-expanded', 'true')
      .find('i').removeClass('fa-chevron-right').addClass('fa-chevron-down');
  });
})(jQuery);
