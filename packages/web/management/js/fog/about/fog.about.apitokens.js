(function($) {
  // The estate-wide API token pane. Auto-loaded by the js/fog/<node>/
  // fog.<node>.<sub>.js convention, so nothing registers this file.
  //
  // Driven by registerTable() -- the same initialiser every list page uses
  // -- rather than registerListPage(), which hardwires its ajax url to
  // '?node=' + Common.node + '&sub=list'. This grid lives at node=about,
  // sub=apitokens and its rows come from sub=apitokenlist, so it wires the
  // three buttons itself and reuses $.deleteSelected for the fourth.
  //
  // CLIENT-SIDE (no serverSide flag): the endpoint returns every visible
  // row once and DataTables does the search, sort and paging. See the
  // page class for why that is right here and wrong for the host list.
  var table,
    deleteBtn = $('#deleteSelected'),
    enableBtn = $('#apitokenEnable'),
    disableBtn = $('#apitokenDisable'),
    issueBtn = $('#issuetoken'),
    issueModal = $('#issueTokenModal'),
    freshModal = $('#freshTokenModal'),
    confirmIssue = $('#confirmIssueToken');

  function disableButtons(disable) {
    deleteBtn.prop('disabled', disable);
    enableBtn.prop('disabled', disable);
    disableBtn.prop('disabled', disable);
  }
  disableButtons(true);

  table = $('#dataTable').registerTable(function(selected) {
    disableButtons(selected.count() === 0);
  }, {
    order: [[0, 'asc']],
    rowId: 'id',
    ajax: {
      url: '../management/index.php?node=about&sub=apitokenlist',
      type: 'post'
    },
    columns: [
      {data: 'userName'},
      {data: 'name'},
      {data: 'createdTime'},
      {data: 'createdBy'},
      {data: 'lastUsed'},
      {data: 'enabled'}
    ],
    columnDefs: [
      {
        // The same check/cross badge pair the user list uses for API?, for
        // the same reason: one of these two values is a thing somebody may
        // want to change, so "good against bad" is the right pairing here
        // (unlike API Only?, where neither value is wrong).
        render: function(data, type, row) {
          if (type !== 'display') {
            // Sorting and filtering get the raw 0/1. Handing them the
            // badge markup would sort the column by '<' every time.
            return data;
          }
          if (data > 0) {
            return '<span class="badge bg-success">'
              + '<i class="fa fa-check-circle"></i></span>';
          }
          return '<span class="badge bg-danger">'
            + '<i class="fa fa-times-circle"></i></span>';
        },
        targets: 5
      }
    ]
  });

  function setEnabled(enabled) {
    var ids = table.rows({selected: true}).ids().toArray();
    if (ids.length < 1) {
      return;
    }
    disableButtons(true);
    $.apiCall(
      'post',
      '../management/index.php?node=about&sub=apitokenenable',
      {remitems: ids, enabled: enabled ? 1 : 0},
      function(err) {
        // Redrawn either way: on success the badges are stale, and on
        // failure the selection is still live, so the buttons come back
        // through the select handler rather than being force-enabled here.
        table.ajax.reload(null, false);
        if (err) {
          disableButtons(false);
        }
      }
    );
  }

  enableBtn.on('click', function() { setEnabled(true); });
  disableBtn.on('click', function() { setEnabled(false); });

  deleteBtn.on('click', function() {
    disableButtons(true);
    $.deleteSelected(table, function(err) {
      // $.deleteSelected redraws from the DOM table; this grid is fed by
      // ajax, so pull the rows again rather than redrawing a cache that
      // still holds the revoked ones.
      table.ajax.reload(null, false);
      if (err) {
        disableButtons(false);
      }
    }, {
      node: 'about',
      url: '../management/index.php?node=about&sub=apitokendelete',
      // Named modal and explicit noun rather than the page-wide #deleteModal
      // and Common.node -- which here is 'about', so the confirm button read
      // "Delete 1 abouts".
      modal: '#apitokenDeleteModal',
      confirmSel: '#confirmAPITokenDelete',
      noun: 'API token'
    });
  });

  issueBtn.on('click', function(e) {
    e.preventDefault();
    $('#fresh-token-value').val('');
    issueModal.modal('show');
  });

  confirmIssue.on('click', function(e) {
    e.preventDefault();
    var forUser = $('#issuefor').val(),
      name = $.trim($('#newtokenname').val() || '');

    // Checked here as well as on the server. The server refusal is the one
    // that counts -- this form is not the only way to reach that endpoint
    // -- but bouncing an empty name off the server just to render the same
    // sentence is a round trip for nothing.
    if ('' === name) {
      $.notifyFromAPI(
        {
          error: 'Give the token a name saying what it is for.',
          title: 'API Token Failed'
        },
        false
      );
      return;
    }

    confirmIssue.prop('disabled', true);
    $.apiCall(
      'post',
      '../management/index.php?node=about&sub=issueAPITokenFor',
      {issuefor: forUser, newtokenname: name},
      function(err, data) {
        confirmIssue.prop('disabled', false);
        if (err || !data || !data.token) {
          // The modal stays open on failure, holding what was typed, so a
          // rejected duplicate name can be edited rather than retyped.
          return;
        }
        issueModal.modal('hide');
        $('#newtokenname').val('');
        // Shown, never stored. Deliberately not written to localStorage, a
        // data attribute, or anywhere else a later render could recover it
        // from -- the server cannot show it again and neither should this.
        $('#fresh-token-value').val(data.token);
        freshModal.modal('show');
      }
    );
  });

  // The grid reloads when the token is DISMISSED, not when it is issued.
  // Reloading at issue time would redraw the table underneath a modal
  // holding a credential, and the row it adds is the one thing on screen
  // the administrator does not need to see yet.
  freshModal.on('hidden.bs.modal', function() {
    $('#fresh-token-value').val('');
    table.ajax.reload(null, false);
  });
})(jQuery);
