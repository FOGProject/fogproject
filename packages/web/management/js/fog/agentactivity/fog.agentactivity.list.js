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
 * expanded host's rows are server side, because that set is not bounded.
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

  function buildChild(hostID) {
    $('#agentactivity-child-' + hostID).DataTable({
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
      processing: true,
      serverSide: true,
      searching: false,
      lengthChange: false,
      pageLength: 10,
      ajax: {
        url: '../management/index.php?node=agentactivity'
          + '&sub=getHostActivity&id=' + encodeURIComponent(hostID),
        type: 'post'
      }
    });
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
