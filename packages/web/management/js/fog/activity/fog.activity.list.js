/**
 * The activity viewer's grid.
 *
 * One grid over every event log, with the source chosen by the select above
 * it -- see docs/adr/0023. There is deliberately NO timer here: activity is
 * something you go and read, not something that has to arrive while you watch
 * it, and a self-rescheduling poll on a page like this outlives the page it
 * was started on (ADR 0012). The Refresh button in the toolbar is the reload.
 *
 * NO rowGroup. Grouping by date read well, but registerTable() auto-pages any
 * table that uses it -- Scroller's virtual row-height math cannot reconcile
 * injected group-header rows -- and that silently opted these two out of the
 * infinite scroll every other list has. These are the tables that want the
 * scroller most: nothing paged them down and they grow for the life of the
 * install. The date is on every row already.
 */
(function($) {
  var $table = $('#activity-table'),
    $source = $('#activity-source');

  if (!$table.length) {
    return;
  }

  function listUrl() {
    return '../management/index.php?node=activity&sub=getList&source='
      + encodeURIComponent($source.val() || '');
  }

  // history.hText is plain text assembled by FOGBase::logHistory() -- it
  // interpolates object names, and a host name is writable from an
  // unauthenticated surface. DataTables writes cell data as HTML unless a
  // column supplies its own render, so every column escapes. Same reasoning,
  // and same shape, as registerExportTable().
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

  var table = $table.registerTable(null, {
    order: [
      [1, 'desc']
    ],
    buttons: reportButtons,
    columns: [
      escaped('createdBy'),
      escaped('createdTime'),
      escaped('info'),
      escaped('ip')
    ],
    rowId: 'id',
    processing: true,
    serverSide: true,
    select: false,
    ajax: {
      url: listUrl(),
      type: 'post'
    },
    createdRow: function(row) {
      // The whole row is the target, so say so: without a pointer there is
      // nothing to suggest the message has more behind it.
      $(row).css('cursor', 'pointer');
    }
  });

  $source.on('change', function() {
    table.ajax.url(listUrl()).load();
  });

  // Filled from the row the grid already holds -- no request. The message is
  // why this exists: it is the column that truncates on a narrow viewport.
  function showDetail(row) {
    var pairs = [
        ['Who', $.escapeHtml(row.createdBy || '')],
        ['When', $.escapeHtml(row.createdTime || '')],
        ['From', $.escapeHtml(row.ip || '')]
      ],
      html = '';
    $.each(pairs, function(i, pair) {
      html += '<dt class="col-sm-3">' + pair[0] + '</dt>'
        + '<dd class="col-sm-9">' + pair[1] + '</dd>';
    });
    html += '<dt class="col-sm-3">What</dt><dd class="col-sm-9">'
      + (row.info ?
        // NOT .text-wrap: Bootstrap defines that as
        // `white-space: normal !important`, which overrides the <pre>.
        '<pre class="mb-0" style="white-space:pre-wrap;overflow-wrap:anywhere;">'
        + $.escapeHtml(row.info) + '</pre>' :
        '<em>none</em>')
      + '</dd>';
    $('#activity-detail').html(html);
    $('#activity-modal').modal('show');
  }

  // Delegated, because the grid replaces its rows on every draw.
  $(document).on('click', '#activity-table tbody tr', function(e) {
    if ($(e.target).closest('a').length) {
      return;
    }
    var row = table.row(this).data();
    if (row) {
      showDetail(row);
    }
  });
})(jQuery);
