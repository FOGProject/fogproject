/**
 * The audit log's grid.
 *
 * Read only by construction, not by omission: `auditlog` and `auditchange`
 * have no create, update or delete route anywhere in FOG (ADR 0021 Decision
 * 8), so there is nothing here to wire a row action to.
 *
 * No timer, for the same reason as the activity viewer -- an audit trail is
 * something you go and read, and a self-rescheduling poll outlives the page
 * it was started on (ADR 0012). The toolbar's Refresh is the reload.
 */
(function($) {
  var $table = $('#audit-table');

  if (!$table.length) {
    return;
  }

  // Every column escapes. An audit row records attempted usernames and
  // subject labels that came from a machine on the network, so its contents
  // are hostile by definition -- and DataTables writes cell data as HTML
  // unless a column supplies its own render.
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

  // allowed / denied / failed / partial, coloured because scanning a page of
  // these for the denials is the whole reason somebody opens this grid.
  var outcomeClass = {
    allowed: 'text-bg-success',
    denied: 'text-bg-danger',
    failed: 'text-bg-warning',
    partial: 'text-bg-warning',
    unknown: 'text-bg-secondary'
  };

  function outcomeColumn() {
    return {
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
    };
  }

  // The label is what stays readable after the object is deleted, which is
  // the reason it is stored rather than joined. Fall back to type#id so a row
  // written before a label existed still says what it was about.
  function subjectText(row) {
    if (row.subjectLabel) {
      return row.subjectLabel;
    }
    if (row.subjectType && row.subjectID) {
      return row.subjectType + '#' + row.subjectID;
    }
    return row.subjectType || '';
  }

  var table = $table.registerTable(null, {
    order: [
      [0, 'desc']
    ],
    rowGroup: {
      dataSrc: function(row) {
        return moment(row.createdTime, moment.ISO_8601).format('MMM DD YYYY');
      }
    },
    buttons: reportButtons,
    columns: [
      escaped('createdTime'),
      escaped('createdBy'),
      escaped('type'),
      outcomeColumn(),
      {
        data: 'subjectLabel',
        render: function(d, t, row) {
          var v = subjectText(row);
          return t === 'display' ? $.escapeHtml(v) : v;
        }
      },
      escaped('ip')
    ],
    rowId: 'id',
    processing: true,
    serverSide: true,
    select: false,
    ajax: {
      url: '../management/index.php?node=audit&sub=getList',
      type: 'post'
    },
    createdRow: function(row) {
      $(row).css('cursor', 'pointer');
    }
  });

  function pair(term, value) {
    return '<dt class="col-sm-3">' + term + '</dt>'
      + '<dd class="col-sm-9">' + value + '</dd>';
  }

  // An empty permission is not a missing value -- it is the record of a write
  // that reached no authorization gate at all (the FOS and registration
  // endpoints, ADR 0021 Decision 4). Say so, or it reads as a bug.
  function permissionText(row) {
    return row.permission ?
      $.escapeHtml(row.permission) :
      '<em>' + $.escapeHtml('none - this path has no authorization gate')
        + '</em>';
  }

  function renderChanges(payload) {
    var rows = (payload && payload.changes) || [],
      html;
    if (!rows.length) {
      $('#audit-changes').html('');
      return;
    }
    html = '<h5>' + $.escapeHtml('Changes') + '</h5>'
      + '<div class="table-responsive"><table class="table table-sm">'
      + '<thead><tr><th>Field</th><th>From</th><th>To</th></tr></thead>'
      + '<tbody>';
    $.each(rows, function(i, c) {
      var from, to;
      if (c.redacted) {
        // Nothing is masked here. What is withheld was withheld at write
        // time -- see Redaction::values() -- and the flag, not the value,
        // is the record of it.
        from = to = '<em>redacted</em>';
      } else {
        // null and '' alike: the ORM writes '' for an empty text column
        // (GH-1245), so both spellings reach the browser and both mean the
        // field held nothing.
        from = (c.oldValue === null || c.oldValue === '') ? '<em>empty</em>'
          : $.escapeHtml(String(c.oldValue));
        to = (c.newValue === null || c.newValue === '') ? '<em>empty</em>'
          : $.escapeHtml(String(c.newValue));
      }
      html += '<tr><td>' + $.escapeHtml(c.field || '') + '</td>'
        + '<td>' + from + '</td><td>' + to + '</td></tr>';
    });
    html += '</tbody></table></div>';
    if (payload && payload.truncated) {
      html += '<p class="text-muted mb-0">'
        + $.escapeHtml('Showing the first ' + rows.length + ' changes.')
        + '</p>';
    }
    $('#audit-changes').html(html);
  }

  function showDetail(row) {
    var html = pair('When', $.escapeHtml(row.createdTime || ''))
      + pair('Who', $.escapeHtml(row.createdBy || ''))
      + pair('From', $.escapeHtml(row.ip || ''))
      + pair('Authenticated by', $.escapeHtml(row.authSource || ''))
      + pair('Action', $.escapeHtml(row.type || ''))
      + pair('Outcome', $.escapeHtml(row.outcome || ''))
      + pair('Subject', $.escapeHtml(subjectText(row)))
      + pair('Permission', permissionText(row))
      + pair('Affected', $.escapeHtml(String(row.affectedCount || 0)))
      + pair('Operation', $.escapeHtml(row.correlationID || ''));
    if (row.text) {
      html += pair(
        'Detail',
        '<pre class="mb-0" style="white-space:pre-wrap;'
        + 'overflow-wrap:anywhere;">' + $.escapeHtml(row.text) + '</pre>'
      );
    }
    $('#audit-detail').html(html);
    $('#audit-changes').html('');
    $('#audit-modal').modal('show');
    // Fetched rather than carried on every row: one header can have hundreds
    // of change rows and the grid draws twenty-five headers at a time.
    $.getJSON(
      '../management/index.php?node=audit&sub=getChanges&id='
        + encodeURIComponent(row.id),
      renderChanges
    );
  }

  // Delegated, because the grid replaces its rows on every draw.
  $(document).on('click', '#audit-table tbody tr', function(e) {
    if ($(e.target).closest('a').length) {
      return;
    }
    var row = table.row(this).data();
    if (row) {
      showDetail(row);
    }
  });
})(jQuery);
