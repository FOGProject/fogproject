/**
 * Renders a multicast session's client count as joined/expected.
 *
 * msClients is -1 or -2 until the first host checks in, which is a sentinel
 * meaning "nobody yet", not a count. Showing it raw made 0-of-30 and
 * 29-of-30 indistinguishable. msSessClients is 0 for sessions created
 * without an expected size, which have no total to count toward.
 */
function fogMulticastClients(joined, expected) {
  joined = parseInt(joined, 10);
  expected = parseInt(expected, 10);
  if (isNaN(joined) || joined < 0) {
    joined = 0;
  }
  if (isNaN(expected) || expected < 1) {
    return joined + ' / <span class="text-muted">&mdash;</span>';
  }
  return joined + ' / ' + expected;
}

var shouldReAuth,
  reAuthModal,
  deleteConfirmButton,
  deleteLang,
  // Per-column filtering, on every table that carries a button toolbar.
  //
  // DataTables' SearchBuilder: a popover of rules, each one column + one
  // condition + a value, combined with AND/OR and nestable into groups. The
  // conditions offered depend on what the column IS -- a datetime column gets
  // before/after/between/on with a calendar, a number gets the comparisons, a
  // string gets contains/starts/ends -- which is the whole reason for using it
  // over a row of text boxes. What the column is comes from the server, in the
  // payload's _searchtypes; see the xhr handler in registerTable().
  //
  // Reused by reference across every table. Buttons clones a button's config
  // per table before calling init(), which matters here because SearchBuilder
  // stores its instance ON that config -- without the clone, the last table
  // built would own everyone's filter panel.
  searchBuilderButton = {
    extend: 'searchBuilder',
    config: {
      i18n: {
        button: {
          0: '<i class="fas fa-filter"></i> Filter',
          _: '<i class="fas fa-filter"></i> Filter (%d)'
        }
      }
    }
  },
  exportButtons = [
    {
      extend: 'copy',
      text: '<i class="fas fa-copy"></i> Copy'
    },
    {
      text: '<i class="far fa-file-excel"></i> CSV (All)',
      // Full server-side export. Replays the table's current DataTables
      // request (active search + sort) but with no row limit, so the
      // exportAll endpoint streams EVERY matching record as CSV -- not just
      // the rows the browser currently holds. The header row it emits is the
      // friendly column keys, which import auto-detects.
      action: function(e, dt, node, config) {
        var params = dt.ajax.params();
        params.length = -1;
        params.start = 0;
        window.location = '../management/index.php?node='
          + Common.node
          + '&sub=exportAll&'
          + $.param(params);
      }
    },
    {
      extend: 'excel',
      text: '<i class="far fa-file-excel"></i> Excel'
    },
    {
      extend: 'print',
      text: '<i class="fas fa-print"></i> Print'
    },
    searchBuilderButton,
    {
      extend: 'colvis',
      text: '<i class="fas fa-table-columns"></i> Column Visibility'
    },
    {
      text: '<i class="fas fa-arrows-rotate"></i> Refresh',
      action: function(e, dt, node, config) {
        dt.clear().draw();
        dt.ajax.reload();
      }
    }
  ],
  // Toolbar for report tables. Same as exportButtons minus the "CSV (All)"
  // full-export action -- reports are a read-only view, not an import source,
  // so the standard client-side CSV button is all they need.
  reportButtons = [
    {
      extend: 'copy',
      text: '<i class="fas fa-copy"></i> Copy'
    },
    {
      extend: 'csv',
      text: '<i class="far fa-file-excel"></i> CSV'
    },
    {
      extend: 'excel',
      text: '<i class="far fa-file-excel"></i> Excel'
    },
    {
      extend: 'print',
      text: '<i class="fas fa-print"></i> Print'
    },
    searchBuilderButton,
    {
      extend: 'colvis',
      text: '<i class="fas fa-table-columns"></i> Column Visibility'
    },
    {
      text: '<i class="fas fa-arrows-rotate"></i> Refresh',
      action: function(e, dt, node, config) {
        dt.clear().draw();
        dt.ajax.reload();
      }
    }
  ],
  // Full export for a report table. Same role as the "CSV (All)" button on
  // the management export screen, and named identically because it solves the
  // identical problem: the DataTables export buttons beside it can only see
  // rows the browser is holding, which on a serverSide report is ONE PAGE.
  // Clicking CSV on a report with fifty thousand rows behind it produced a
  // file of twenty-five that looked exactly like a complete one.
  //
  // POSTED rather than navigated to, and that is the point. Route::listem()
  // reads its DataTables request -- search, sort, columns -- from php://input
  // and from nothing else, so a GET export carries an empty body and would
  // quietly ignore the search box. Posting the grid's own dt.ajax.params()
  // means the server answers the identical question it answers for the grid,
  // with length forced to -1 (bounded by MAX_ROWS server side, and the file
  // name says so when it bites).
  //
  // Submitted through the native form.submit(), which fires no submit event.
  // That is deliberate on both sides: disableFormDefaults() preventDefaults
  // every form on the page, and bootstrap-csrf.js hangs the _csrf field off
  // that same event -- so the token is appended here by hand rather than
  // relying on a listener that is deliberately not going to run.
  reportCsvAllButton = {
    text: '<i class="far fa-file-excel"></i> CSV (All)',
    titleAttr: 'Export every row this report returns, not just this page',
    action: function(e, dt, node, config) {
      // The window (start/end/sources[]) rides on the page URL and the
      // report reads it from there, so it stays on the action's query
      // string; only the three that address the endpoint are restated.
      var params = new URLSearchParams(window.location.search);
      params.set('node', 'report');
      params.set('sub', 'exportAll');
      params.set('f', Common.f);

      var body = new URLSearchParams($.param(dt.ajax.params() || {}));
      body.set('start', '0');
      body.set('length', '-1');
      // The columns as the user has them: colvis choices and order carry
      // into the file, with the on-screen heading as the CSV heading.
      dt.columns(':visible').every(function() {
        body.append('cols[]', this.dataSrc());
        body.append('heads[]', $(this.header()).text().trim());
      });
      var meta = document.querySelector('meta[name="csrf-token"]');
      body.append('_csrf', meta ? meta.getAttribute('content') || '' : '');

      var form = document.createElement('form');
      form.method = 'post';
      form.action = '../management/index.php?' + params.toString();
      body.forEach(function(value, key) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = value;
        form.appendChild(input);
      });
      document.body.appendChild(form);
      form.submit();
      document.body.removeChild(form);
    }
  },
  // The report toolbar with the full export folded in beside the plain CSV,
  // so the pair reads as "what I am looking at" and "all of it".
  //
  // Placed by FINDING the csv button rather than at a fixed index, so
  // reordering reportButtons cannot silently move this somewhere that reads
  // as unrelated to it.
  //
  // A SEPARATE ARRAY, not an addition to reportButtons, because that one is
  // also worn by the audit and activity grids -- which are their own nodes,
  // have no `f`, and are not reports. registerReportTable() (plugin reports)
  // keeps the plain toolbar too: a plugin report that has not implemented
  // reportRows() would answer this button with an empty file, and a button
  // that silently produces nothing is the bug being fixed, not a feature.
  reportFileButtons = (function() {
    var at = 0;
    reportButtons.some(function(button, i) {
      if (button.extend === 'csv') {
        at = i + 1;
        return true;
      }
      return false;
    });
    return reportButtons.slice(0, at)
      .concat([reportCsvAllButton], reportButtons.slice(at));
  })(),
  $_GET,
  Common;
/**
 * Non-selector required functions.
 */
$.apiCall = function(method, action, data, cb, processData) {
  if (undefined === processData) {
    processData = true;
  }
  Pace.track(function() {
    $.ajax('', {
      type: method,
      url: action,
      async: true,
      cache: false,
      data: data,
      contentType: !processData ? false : 'application/x-www-form-urlencoded',
      processData: !processData ? false : true,
      success: function(data, textStatus, jqXHR) {
        $.notifyFromAPI(data, jqXHR);
        if (cb && typeof cb === 'function') {
          cb(null, data);
        }
      },
      error: function(jqXHR, textStatus, errorThrown) {
        $('#progressFileUp').remove();
        $.notifyFromAPI(jqXHR.responseJSON, jqXHR);
        if (cb && typeof cb === 'function') {
          cb(jqXHR, jqXHR.responseJSON);
        }
      },
      xhr: function() {
        var myXHR = $.ajaxSettings.xhr();
        if (myXHR.upload) {
          $('.filedisp')
            .after('<div class="form-control progressFileUp" id="progressFileUp">'
              + '<div class="progress progress-md active">'
              + '<div class="progress-bar progress-bar-success progress-bar-striped" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0%">0.00%</div></div>'
            );
          myXHR.upload.addEventListener('progress', function(e) {
            if (e.lengthComputable) {
              var max = e.total,
                current = e.loaded,
                percentComplete = (current * 100) / max;
              $('#progressFileUp').html('<div class="progress progress-md active">'
                + '<div class="progress-bar progress-bar-success progress-bar-striped" role="progressbar" aria-valuenow="'
                + percentComplete.toFixed(2)
                + '" aria-valuemin="0" aria-valuemax="100" style="width:'
                + percentComplete.toFixed(2)
                + '%">'
                + percentComplete.toFixed(2)
                + '%'
                + '</div>'
                + '</div>');
              if (percentComplete === 100) {
                $('#progressFileUp').remove();
              }
            }
          }, false);
        }
        return myXHR;
      }
    });
  });
};
$.capitalizeFirstLetter = function(string) {
  return string.charAt(0).toUpperCase() + string.slice(1);
}
// HTML-escape a value for safe insertion into DataTables render strings.
// Regex version shared by the task and report pages. (host.edit.js keeps its
// own DOM-textNode variant, which has different quote/null semantics.)
$.escapeHtml = function(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}
// A DataTables column definition for a field that is PLAIN TEXT.
//
// Route's list formatters return text, not markup, and DataTables writes cell
// data as HTML unless a column supplies its own render -- so the escape lives
// here, at the renderer, for every grid alike. Escaping server side instead
// double-escapes wherever the reader also escapes, which is how the activity
// viewer came to show `Task &quot;host&quot; (ID 140) was saved`.
//
// The t === 'display' guard is load-bearing: the Buttons CSV/copy exports ask
// for other types, and escaping those would put &amp;/&lt; into the exported
// file. A column that intentionally emits markup (hostLink, mainlink) is not
// one of these -- it keeps `{data: field}` and escapes its own interpolations
// server side.
$.escapedColumn = function(field) {
  return {
    data: field,
    render: function(d, t) {
      return t === 'display' ? $.escapeHtml(d === null ? '' : String(d)) : d;
    }
  };
}
// Windows product-key display mask (mirror of FOGBase::productKeyMask).
// Empty -> ''. Already-masked (contains a bullet) -> returned unchanged so
// re-masking a redisplayed value is idempotent. A well-formed Base24 key
// (25 chars, tight charset) keeps its first and last group and bullets the
// middle three; anything else is fully bulleted.
$.productKeyMask = function(value) {
  var str = String(value == null ? '' : value);
  if (str.indexOf('•') !== -1) {
    return str;
  }
  var stripped = str.toUpperCase().replace(/[^A-Z0-9]/g, '');
  if (stripped === '') {
    return '';
  }
  var bullets = '•••••';
  if (/^[BCDFGHJKMPQRTVWXY2346789]{25}$/.test(stripped)) {
    return [
      stripped.slice(0, 5),
      bullets,
      bullets,
      bullets,
      stripped.slice(20, 25)
    ].join('-');
  }
  return [bullets, bullets, bullets, bullets, bullets].join('-');
}
// Wire a product-key input for consistent 5x5 entry.
//  - A plain/empty value (add form, or a legacy plaintext key) gets the
//    standard product-key inputmask immediately.
//  - A stored value shown masked (contains a bullet) defers the inputmask so
//    the masked display survives an untouched save (server keeps the stored
//    key when it sees the bullets). The masked display would fail an
//    exactlength check, so that attribute is stripped while masked and
//    restored the moment the user genuinely edits the field. Tab-through,
//    focus and modifier/navigation keys do NOT engage; a printable keystroke,
//    paste or cut clears the field and starts a clean masked entry.
$.initProductKeyField = function(selector) {
  var $field = $(selector);
  if (!$field.length) {
    return;
  }
  var masked = String($field.val() || '').indexOf('•') !== -1;
  if (!masked) {
    $field.inputmask({mask: Common.masks.productKey});
    return;
  }
  var savedExact = $field.attr('exactlength'),
    engaged = false;
  var engage = function() {
    if (engaged) {
      return;
    }
    engaged = true;
    if (savedExact !== undefined) {
      $field.attr('exactlength', savedExact);
    }
    $field.val('').inputmask({mask: Common.masks.productKey});
  };
  $field.removeAttr('exactlength');
  $field.on('keydown', function(e) {
    if (e.ctrlKey || e.altKey || e.metaKey) {
      return;
    }
    switch (e.key) {
      case 'Tab':
      case 'Shift':
      case 'Control':
      case 'Alt':
      case 'Meta':
      case 'Escape':
      case 'Enter':
      case 'ArrowLeft':
      case 'ArrowRight':
      case 'ArrowUp':
      case 'ArrowDown':
      case 'Home':
      case 'End':
      case 'PageUp':
      case 'PageDown':
        return;
    }
    engage();
  });
  $field.on('paste cut', function() {
    engage();
  });
}
// Fill only the keys of obj that are undefined from src (drop-in for the
// single lodash symbol FOG used, _.defaults; mutates and returns obj).
$.fogDefaults = function(obj, src) {
  obj = obj || {};
  for (var key in src) {
    if (obj[key] === undefined) {
      obj[key] = src[key];
    }
  }
  return obj;
}
$.checkItemUpdate = function(table, item, e, prop, opts, done) {
  var method = prop.attr('method'),
    action = prop.attr('action');
  if (item.checked) {
    opts = $.fogDefaults(opts, {
      confirmadd: 1,
      additems: [e.target.value]
    });
  } else {
    opts = $.fogDefaults(opts, {
      confirmdel: 1,
      remitems: [e.target.value]
    });
  }
  $.apiCall(method, action, opts, function(err) {
    if (err) {
      return;
    }
    table.draw(false);
    table.rows({selected: true}).deselect();
    if (typeof done === 'function') {
      done();
    }
  });
}
$.debugLog = function(obj) {
  if(Common.debug) {
    console.log(obj);
  }
}
$.deleteAssociated = function(table, url, cb, opts) {
  opts = opts || {};
  opts = $.fogDefaults(opts, {
    rows: table.rows({selected: true})
  });
  opts = $.fogDefaults(opts, {
    ids: opts.rows.ids().toArray()
  });

  var ajaxOpts = {
    confirmdel: 1,
    remitems: opts.ids
  };

  Pace.track(function(){
    $.ajax('', {
      type: 'post',
      url: url,
      async: true,
      data: ajaxOpts,
      success: function(res) {
        if (table !== undefined) {
          table.draw(false);
        }
        $.notifyFromAPI(res, false);
        if (cb && typeof(cb) === 'function') {
          cb(null, res);
        }
      },
      error: function(res) {
        $.notifyFromAPI(res.responseJSON, res);
        if (cb && typeof(cb) === 'function') {
          cb(res, res.responseJSON);
        }
      }
    });
  });
};
// opts.node       - entity node, used to build the default delete URL.
// opts.url        - the delete endpoint, when it is not <node>&sub=deletemulti.
// opts.modal      - confirm modal, when the page's own #deleteModal is not it.
// opts.confirmSel - that modal's confirm button.
// opts.noun       - what the confirm button should say is being deleted.
//                   The last three are passed straight through to $.reAuth;
//                   see the note there for why a page can need them.
$.deleteSelected = function(table, cb, opts) {
  opts = opts || {};
  opts = $.fogDefaults(opts, {
    node: Common.node,
    rows: table.rows({selected: true}),
    password: undefined
  });
  opts = $.fogDefaults(opts, {
    ids: opts.rows.ids().toArray(),
    url: '../management/index.php?node=' + opts.node + '&sub=deletemulti',
  });
  $('#andFile').on('change', function(e) {
    e.preventDefault();
    if (!this.checked) {
      delete opts.andFile;
    } else {
      opts.andFile = 1;
    }
  });
  $('#andFile').trigger('change');
  $('#andHosts').on('change', function(e) {
    e.preventDefault();
    if (!this.checked) {
      delete opts.andHosts;
    } else {
      opts.andHosts = 1;
    }
  });
  $('#andHosts').trigger('change');

  var ajaxOpts = {
    fogguipass: opts.password,
    confirmdel: 1,
    remitems: opts.ids,
    andHosts: 'andHosts' in opts ? 1 : 0,
    andFile: 'andFile' in opts ? 1 : 0
  };

  var numItems = ajaxOpts.remitems.length;

  // If we know in advance that the user should reauth,
  // prompt them with a modal to do so instead of wasting
  // an API call
  if (opts.password === undefined && shouldReAuth) {
    $.reAuth(numItems, function(err, password) {
      if (err) {
        if (cb && typeof(cb) === 'function') {
          cb(err);
        }
        return;
      }
      opts.password = password;
      $.deleteSelected(table, cb, opts);
    }, {
      modal: opts.modal,
      confirmSel: opts.confirmSel,
      noun: opts.noun
    });
    return;
  }

  Pace.track(function(){
    $.ajax('', {
      type: 'post',
      url: opts.url,
      async: true,
      data: ajaxOpts,
      success: function(res) {
        if (table !== undefined) {
          table.draw(false);
        }
        $.finishReAuth(opts.modal || reAuthModal);
        $.notifyFromAPI(res, false);
        if (cb && typeof(cb) === 'function') {
          cb(null,res);
        }
      },
      error: function(res) {
        if (res.status == 401) {
          $.notifyFromAPI(res.responseJSON, res);
          $.reAuth(numItems, function(err, password) {
            if (err) {
              if (cb && typeof(cb) === 'function') {
                cb(err,res.responseJSON);
              }
              return;
            }
            opts.password = password;
            $.deleteSelected(table, cb, opts);
          });
          return;
        } else {
          $.finishReAuth(opts.modal || reAuthModal);
          $.notifyFromAPI(res.responseJSON, res);
          if (cb && typeof(cb) === 'function') {
            cb(res,res.responseJSON);
          }
        }
      }
    });
  });
};
/**
 * Wire a standard management list page.
 *
 * Nearly every top-level list (usergroup, role, group, user, module, and every
 * plugin) is the same skeleton: a server-side #dataTable whose only per-page
 * variation is its column set, a "create new" modal, and a "delete selected"
 * button. This owns that skeleton so each *.list.js is a single call passing
 * just its columns.
 *
 * Behavior is the historical first-class-page shape: after a successful create
 * the table redraws and the modal hides (selection is left intact); after a
 * successful delete $.deleteSelected redraws the table itself and the delete
 * button is only re-enabled on error (on success nothing is selected, so it
 * stays correctly disabled).
 *
 * @param {Object} opts
 *   columns     {Array}  DataTables column defs (required)
 *   columnDefs  {Array}  optional per-column defs (omit to leave unset)
 *   order       {Array}  optional initial sort (DataTables default if omitted)
 *   rowId       {String} optional row-id source column (usually 'id')
 * @return {DataTable}
 */
$.registerListPage = function(opts) {
  opts = opts || {};
  var deleteSelected = $('#deleteSelected'),
    createnewBtn = $('#createnew'),
    createnewModal = $('#createnewModal'),
    createForm = $('#create-form'),
    createnewSendBtn = $('#send');

  function disableButtons(disable) {
    deleteSelected.prop('disabled', disable);
  }
  disableButtons(true);

  var tableOpts = {
    columns: opts.columns,
    processing: true,
    serverSide: true,
    ajax: {
      url: '../management/index.php?node=' + Common.node + '&sub=list',
      type: 'post'
    }
  };
  if (opts.order !== undefined) {
    tableOpts.order = opts.order;
  }
  if (opts.rowId !== undefined) {
    tableOpts.rowId = opts.rowId;
  }
  if (opts.columnDefs !== undefined) {
    tableOpts.columnDefs = opts.columnDefs;
  }

  var table = $('#dataTable').registerTable(function(selected) {
    disableButtons(selected.count() == 0);
  }, tableOpts);

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
  deleteSelected.on('click', function() {
    disableButtons(true);
    $.deleteSelected(table, function(err) {
      // if we couldn't delete the items, re-enable the buttons
      // as the rows still exist and are selected.
      if (err) {
        disableButtons(false);
      }
    });
  });

  return table;
};
// Standard "General" edit-tab wiring, duplicated across every *.edit.js:
//   - the form's submit event is suppressed (buttons drive it);
//   - the save button disables itself + the delete button, runs processForm,
//     re-enables both, and on success refreshes the page title (and #pageTitle
//     text) from the renamed entity;
//   - the delete button opens the confirm modal, whose confirm button issues
//     the delete apiCall and redirects back to the list.
//
// opts.formSel          - required; the general <form> selector.
// opts.nameInputSel     - the name field whose value drives the title refresh.
//                         Omit for pages with no renameable title (e.g. capone)
//                         to skip all rename/title handling.
// opts.sendBtn          - save button   (default '#general-send').
// opts.deleteBtn        - delete button (default '#general-delete').
// opts.deleteModal      - confirm modal (default '#deleteModal').
// opts.confirmSel       - modal confirm button (default '#confirmDeleteModal').
// opts.updateTitle      - refresh document.title on rename (default true;
//                         storagegroup passes false to keep its existing
//                         behavior of only updating #pageTitle text).
// opts.trimName         - trim the name value before using it (ipxe/user).
// opts.processTarget    - processForm's field selector (printer: ':input:visible').
// opts.deleteOpts       - function evaluated at confirm-time returning the opts
//                         object for the delete apiCall (group/image/snapin read
//                         their andHosts/andFile checkbox live here).
// opts.onRenameSuccess  - function(newName, oldName) called after a successful
//                         save, before originalName advances, for page-specific
//                         follow-up (user display name, printer #printercopy).
$.registerGeneralTab = function(opts) {
  opts = opts || {};
  var nameInput = opts.nameInputSel ? $(opts.nameInputSel) : null,
    form = $(opts.formSel),
    sendBtn = $(opts.sendBtn || '#general-send'),
    deleteBtn = $(opts.deleteBtn || '#general-delete'),
    deleteModal = $(opts.deleteModal || '#deleteModal'),
    deleteConfirm = $(opts.confirmSel || '#confirmDeleteModal'),
    updateTitle = opts.updateTitle !== false,
    trimName = opts.trimName === true,
    originalName = nameInput ? nameInput.val() : null;

  function readName() {
    var v = nameInput.val();
    return trimName ? v.trim() : v;
  }
  function refreshTitle(newName) {
    if (!updateTitle) {
      return;
    }
    var e = $('#pageTitle'),
      text = e.text().replace(': ' + originalName, ': ' + newName);
    document.title = text;
    e.text(text);
  }

  form.on('submit', function(e) {
    e.preventDefault();
  });
  sendBtn.on('click', function() {
    sendBtn.prop('disabled', true);
    deleteBtn.prop('disabled', true);
    form.processForm(function(err) {
      sendBtn.prop('disabled', false);
      deleteBtn.prop('disabled', false);
      if (err) {
        return;
      }
      if (nameInput) {
        var newName = readName();
        refreshTitle(newName);
        if (typeof opts.onRenameSuccess === 'function') {
          opts.onRenameSuccess(newName, originalName);
        }
        originalName = newName;
      }
    }, opts.processTarget);
  });
  deleteBtn.on('click', function() {
    deleteModal.modal('show');
  });
  deleteConfirm.on('click', function() {
    var action = '../management/index.php?node=' + Common.node
        + '&sub=delete&id=' + Common.id,
      delOpts = (typeof opts.deleteOpts === 'function') ? opts.deleteOpts() : null;
    $.apiCall('post', action, delOpts, function(err) {
      if (err) {
        return;
      }
      setTimeout(function() {
        window.location = '../management/index.php?node='
          + Common.node + '&sub=list';
      }, 2000);
    });
  });
};
// -----------------------------------------------------------------------
// $.registerAssociationTab(opts) - wire a standard "associated items" tab.
//
// Nearly every edit page carries one or more association tabs built from the
// same skeleton: an Add-selected button, a Remove-selected button that opens a
// confirm modal, a server-side DataTable with a per-row "associated" checkbox,
// and per-row checkbox toggles that POST immediately. Only the slug, the modal
// item name, the list endpoint, and (rarely) the sort/columns differ. This owns
// that skeleton so each tab is a single call.
//
// Element / endpoint conventions, all derived from opts.slug / opts.item:
//   table          #{slug}-table
//   add button     #{slug}-send      (carries method= and action= for the POST)
//   remove button  #{slug}-remove
//   delete modal   #{item}DelModal
//   confirm button #confirm{item}DeleteModal
//   list endpoint  ?node={Common.node}&sub={opts.sub}&id={Common.id}
//
// opts.slug        - required; the tab slug (e.g. 'site-host', 'user-role').
// opts.item        - required; the modal item type (e.g. 'host', 'user') keying
//                    #{item}DelModal / #confirm{item}DeleteModal. Not always the
//                    slug's suffix (usergroup-member's item is 'user').
// opts.sub         - required unless opts.url is given; the list endpoint sub
//                    (e.g. 'getHostsList').
// opts.url         - optional full list endpoint, replacing the derived one.
//                    Needed by a PLUGIN tab injected onto a core page: the
//                    derived URL points at the page's own node, and a plugin
//                    cannot add a sub method to a core page class, so its
//                    table has to be served from the plugin's own node. Core
//                    tabs should leave this unset and keep the convention.
// opts.order       - optional initial sort override (default is the
//                    association column ascending, then column 0 — associated
//                    rows first, then name).
// opts.columns     - optional DataTables columns (default the standard
//                    mainLink + association pair; ou passes a {data:'name'} col0
//                    it renders as a host link via opts.columnDefs).
// opts.columnDefs  - optional extra column defs, merged BEFORE the built-in
//                    associated-checkbox renderer on the association column.
// opts.checkboxRender - optional function(row) returning the FULL HTML for the
//                    association column cell, replacing the built-in plain
//                    checkbox. For tabs whose cell is not a simple on/off box
//                    (group's tri-state All/Some/None badge + host drill-down).
//                    The returned markup must still carry an
//                    input.associated[value=row.id] so the toggle/add/remove
//                    plumbing keeps working.
// opts.onDraw      - optional function(table) run at the end of every table
//                    redraw, after the checkbox styling/binding and button
//                    enable/disable. For tabs that mirror a side panel off the
//                    association state (host-printer's default-printer selector).
// opts.afterCommit - optional function() run after a successful add, remove, or
//                    per-row toggle commit. For tabs whose side panel must
//                    refresh once the association save lands (host-snapin's run
//                    order). Passed through as $.checkItemUpdate's done callback.
// Returns the DataTable API instance.
$.registerAssociationTab = function(opts) {
  opts = opts || {};
  var slug = opts.slug,
    item = opts.item,
    tableSel = '#' + slug + '-table',
    updateBtn = $('#' + slug + '-send'),
    removeBtn = $('#' + slug + '-remove'),
    deleteModal = $('#' + item + 'DelModal'),
    deleteConfirm = $('#confirm' + item + 'DeleteModal'),
    columns = opts.columns || [{data: 'mainLink'}, {data: 'association'}],
    checkboxRender = opts.checkboxRender || function(row) {
      var checkval = row.association === 'associated' ? ' checked' : '';
      return '<div class="form-check">'
        + '<input type="checkbox" class="associated" name="associate[]" id="'
        + slug + '-associate-' + row.id
        + '" value="' + row.id + '"'
        + checkval
        + '/>'
        + '</div>';
    },
    columnDefs = (opts.columnDefs || []).concat([{
      render: function(data, type, row) {
        return checkboxRender(row);
      },
      targets: columns.length - 1
    }]);

  function disableButtons(disable) {
    updateBtn.prop('disabled', disable);
    removeBtn.prop('disabled', disable);
  }
  function onSelect(selected) {
    disableButtons(selected.count() == 0);
  }
  function onCheckboxSelect(e) {
    $.checkItemUpdate(table, this, e, updateBtn, undefined, opts.afterCommit);
  }

  // The association column is not always index 1 -- the LDAP group tabs put a
  // directory-server column between the name and it -- so find it rather than
  // assume. Ascending puts 'associated' ahead of 'dissociated'.
  var assocIdx = columns.length - 1;
  for (var ci = 0; ci < columns.length; ci++) {
    if (columns[ci].data === 'association') {
      assocIdx = ci;
      break;
    }
  }

  var table = $(tableSel).registerTable(onSelect, {
    order: opts.order || [[assocIdx, 'asc'], [0, 'asc']],
    columns: columns,
    rowId: 'id',
    columnDefs: columnDefs,
    processing: true,
    serverSide: true,
    ajax: {
      url: opts.url
        || ('../management/index.php?node=' + Common.node
          + '&sub=' + opts.sub + '&id=' + Common.id),
      type: 'post'
    }
  });

  updateBtn.on('click', function(e) {
    e.preventDefault();
    var method = $(this).attr('method'),
      action = $(this).attr('action');
    $.apiCall(method, action, {
      confirmadd: 1,
      additems: $.getSelectedIds(table)
    }, function(err) {
      disableButtons(false);
      if (err) {
        return;
      }
      table.draw(false);
      table.rows({selected: true}).deselect();
      if (typeof opts.afterCommit === 'function') {
        opts.afterCommit();
      }
    });
  });

  removeBtn.on('click', function(e) {
    e.preventDefault();
    deleteModal.modal('show');
  });

  deleteConfirm.on('click', function() {
    $.deleteAssociated(table, updateBtn.attr('action'), function(err) {
      deleteModal.modal('hide');
      if (err) {
        return;
      }
      table.draw(false);
      table.rows({selected: true}).deselect();
      if (typeof opts.afterCommit === 'function') {
        opts.afterCommit();
      }
    });
  });

  table.on('draw', function() {
    Common.iCheck(tableSel + ' input');
    // .off() before .on() so repeat draw events (responsive recalc, column
    // adjust) don't stack duplicate change handlers on the same checkbox and
    // fire N commit toasts per toggle. Mirrors the pre-factory setupHostAssoc.
    $(tableSel + ' input.associated')
      .off('change', onCheckboxSelect)
      .on('change', onCheckboxSelect);
    onSelect(table.rows({selected: true}));
    if (typeof opts.onDraw === 'function') {
      opts.onDraw(table);
    }
  });

  return table;
};
// -----------------------------------------------------------------------
// wireCreateModal(slug, opts) - the machinery behind the "Create New X" button
// and modal that renderAssocCreate() adds to a tab, so the thing being
// associated can be created without leaving the page.
//
// Two-request flow, both against endpoints that already exist:
//   1. GET  ?node={createNode}&sub=addModal - the REAL create form, fetched
//      into the empty modal on first open. Fetched rather than duplicated here
//      so the fields (including any a plugin injects via {NODE}_ADD_FIELDS)
//      can never drift from the create page's own.
//   2. The form's own action (?node={createNode}&sub=add, which the page
//      manager routes to addPost() on POST) via processForm() -- the same call
//      the node's list page makes from its own create modal, so validation,
//      CSRF and error reporting all stay on one path. It answers with the
//      created entity under `object` (see FOGPagePost::attachCreatedObject).
//
// What happens with that created object is the ONE thing that differs between
// the two tab shapes, so it is the one thing this does not decide: it hands the
// object to opts.onCreated and lets the caller associate it. An association
// GRID adds it by POSTing additems[] ($.registerCreateAndAssociate); a single
// DROPDOWN tab adds it by selecting the new option and committing the tab's own
// form ($.registerCreateAndSelect). Everything either side of that -- the lazy
// fetch, the id namespacing, Enter-to-submit, validation state, the reset -- is
// identical, and lives here once.
//
// If the create succeeds but no `object` comes back, the association is
// skipped and the user is told: better a half-done step they can see than a
// silent one.
//
// slug - the tab slug (e.g. 'host-group'), matching the button and modal ids.
// opts.onCreated(obj, done) - required; associate obj, then call done() to
//      close the modal and reset the create form for the next one.
// opts.onSkipped - optional; run when the create succeeded but gave us no
//      object to associate (the grid still wants a redraw so the new row shows).
// opts.orphanMessage - required; what to tell the user in that case.
// opts.onForm   - optional callback(form) run once, right after the fetched form
//      is in the DOM. Some create forms are not inert markup: the printer form's
//      type sections and the snapin form's command builder are driven by JS that
//      normally runs on the node's own page, and that JS does not travel with a
//      fetched fragment. The tab passes its node's initializer here rather than
//      this helper carrying a node->initializer map, which would make a shared
//      helper grow a branch per node and put plugin nodes out of reach.
// opts.validate - optional processForm() validate filter, mirroring
//      wireCreateForm({selector}). The printer form needs ':input:visible'
//      because its hidden type sections must not be validated; forms with
//      nothing hidden leave it unset and validate everything.
function wireCreateModal(slug, opts) {
  opts = opts || {};
  var onForm = opts.onForm,
    btn = $('#' + slug + '-create'),
    modal = $('#' + slug + '-createModal'),
    holder = $('#' + slug + '-create-form'),
    sendBtn = $('#' + slug + '-create-send'),
    createNode = btn.data('create-node'),
    loaded = false;

  if (!btn.length || !modal.length) {
    return;
  }

  btn.on('click', function(e) {
    e.preventDefault();
    modal.modal('show');
  });

  // Lazily fetch the form the first time the modal is opened, so a tab nobody
  // creates from costs nothing.
  modal.on('show.bs.modal', function() {
    if (loaded) {
      return;
    }
    loaded = true;
    holder.setLoading(true);
    $.get(
      '../management/index.php?node=' + createNode + '&sub=addModal',
      function(html) {
        var parsed = $('<div/>').html(html),
          form = parsed.find('#create-form');
        holder.setLoading(false);
        if (!form.length) {
          loaded = false;
          $.notify('Error', 'Could not load the create form.', 'error');
          return;
        }
        // Namespace the ids. The create form is written for its own page,
        // where it is alone; a modal lives in the edit page's DOM for the
        // life of the page, so its ids can collide -- the group form's
        // kernel/init/dev are also host fields -- and a duplicate id silently
        // steals the host page's own selectors. Only id/for are rewritten;
        // name is what the POST reads and must not change.
        form.attr('id', slug + '-create-realform');
        form.find('[id]').each(function() {
          var el = $(this),
            oldId = el.attr('id'),
            newId = slug + '-create-' + oldId;
          form.find('label[for="' + oldId + '"]').attr('for', newId);
          el.attr('id', newId);
        });
        holder.html(form);
        // Before the keypress/focus wiring below, because an initializer can
        // change which fields are even visible -- the printer one hides every
        // type section but the selected one -- and focusing a hidden field or
        // binding Enter to it would be wrong.
        if (typeof onForm === 'function') {
          onForm(holder.find('form'));
        }
        // Submit on Enter, matching the list page's create modal.
        holder.find(':input:not(textarea)').on('keypress', function(ev) {
          if (ev.which == 13) {
            ev.preventDefault();
            sendBtn.trigger('click');
          }
        });
        // :visible so a form whose initializer hid sections still opens with the
        // caret in a field the user can actually see.
        holder.find(':input:visible:first').trigger('focus');
      }
    ).fail(function() {
      // Let them retry by closing and reopening.
      loaded = false;
      holder.setLoading(false);
      $.notify('Error', 'Could not load the create form.', 'error');
    });
  });

  // Clear validation state on close so a reopen does not show last time's
  // errors. The values are deliberately kept: a create that failed is usually
  // retried with a small edit, not retyped.
  modal.on('hidden.bs.modal', function() {
    holder.find('.is-invalid').removeClass('is-invalid');
    holder.find('span.invalid-feedback').remove();
  });

  sendBtn.on('click', function(e) {
    e.preventDefault();
    var form = holder.find('form');
    if (!form.length) {
      return;
    }
    sendBtn.prop('disabled', true);
    form.processForm(function(err, data) {
      sendBtn.prop('disabled', false);
      if (err) {
        return;
      }
      // The create already reported success to the user; all that is left is
      // associating what was made, which only the caller knows how to do.
      var obj = (data && data.object) ? data.object : null;
      if (!obj || !obj.id) {
        $.notify('Warning', opts.orphanMessage, 'notice');
        if (typeof opts.onSkipped === 'function') {
          opts.onSkipped();
        }
        modal.modal('hide');
        return;
      }
      opts.onCreated(obj, function() {
        modal.modal('hide');
        // Reset only after a clean run, so the next create starts empty.
        if (form[0]) {
          form[0].reset();
          // reset() restores field VALUES but fires no events, so any UI an
          // initializer drives off a select -- the printer type sections, the
          // snapin pack mode -- would be left displaying the previous choice
          // against a reset select. Re-fire change so those handlers re-sync;
          // they only read the current value, so running them again is safe.
          form.find('select').trigger('change');
        }
      });
    }, opts.validate);
  });
}
// $.registerCreateAndAssociate(slug, table, opts) - create-and-associate for an
// association GRID tab. The created id is POSTed to the tab's update URL, i.e.
// the same call "Add selected" makes, so this is not a second write path. The
// grid is redrawn whether or not the association half ran, so the new row shows
// up either way.
//
// slug  - the association tab slug (e.g. 'host-group'), matching the button.
// table - the tab's DataTable API instance, redrawn after a successful create.
// opts  - onForm/validate, passed through to wireCreateModal().
$.registerCreateAndAssociate = function(slug, table, opts) {
  opts = opts || {};
  // The endpoint rides on the button as a data attribute (renderAssocCreate
  // puts it there), so no URL is rebuilt here.
  var assocAction = $('#' + slug + '-create').data('assoc-action');
  wireCreateModal(slug, {
    onForm: opts.onForm,
    validate: opts.validate,
    orphanMessage: 'Created, but it could not be associated automatically. '
      + 'Add it from the list above.',
    onSkipped: function() {
      if (table) {
        table.draw(false);
      }
    },
    onCreated: function(obj, done) {
      $.apiCall('post', assocAction, {
        confirmadd: 1,
        additems: [obj.id]
      }, function() {
        if (table) {
          table.draw(false);
        }
        done();
      });
    }
  });
};
// $.registerCreateAndSelect(slug, opts) - create-and-associate for a tab whose
// association is a single DROPDOWN rather than a grid (the location/site/ou/
// windowskey plugin tabs). The new option is appended and selected, then the
// tab's own Update button is clicked so the association is written by exactly
// the path it would have taken had the admin picked the value by hand.
//
// Clicking the real button rather than POSTing here is deliberate: these tabs
// carry plugin-specific behavior on that button (site/location on a GROUP fans
// the choice out to every member host), and duplicating the submit would mean
// duplicating whatever the plugin does around it.
//
// opts.select - required; the tab's <select>, already resolved.
// opts.send   - required; the tab's Update button, already resolved.
// opts.onForm / opts.validate - passed through to wireCreateModal().
$.registerCreateAndSelect = function(slug, opts) {
  opts = opts || {};
  wireCreateModal(slug, {
    onForm: opts.onForm,
    validate: opts.validate,
    orphanMessage: 'Created, but it could not be selected automatically. '
      + 'Pick it from the list above.',
    onCreated: function(obj, done) {
      // trigger('change') both re-syncs any select2 wrapper and lets anything
      // else watching the field react, exactly as a manual pick would.
      opts.select
        .append($('<option/>', {value: obj.id, text: obj.name || obj.id}))
        .val(obj.id)
        .trigger('change');
      done();
      opts.send.trigger('click');
    }
  });
};
// $.registerSelectTab(opts) - wire a whole single-dropdown association tab.
//
// Nine plugin-injected tabs (location/site/ou on host+group, site on user+
// usergroup, windowskey on image) render the same card: one select, one Update
// button, in a form. Each shipped a near-identical JS file that did nothing but
// processForm() that form. That wiring lives here now, along with the optional
// create-and-select button, so a plugin tab is one call rather than a copy.
//
// opts.slug   - required; the tab slug (e.g. 'host-location'). The form is
//               #{slug}-form and the create button/modal are #{slug}-create*.
// opts.send   - required; the id of the tab's existing Update button
//               (e.g. 'location-send'). Not derived from the slug because these
//               ids predate the convention and are shared across a plugin's
//               tabs -- renaming them would be a bigger change than this.
// opts.select - optional; the select's NAME attribute, defaulting to opts.node.
// opts.node   - optional; the node owning the create form (e.g. 'location').
//               Omit and the tab is wired without a create button. Present but
//               the user lacking {node}.create simply means renderAssocCreate()
//               emitted no button, and the wiring no-ops.
$.registerSelectTab = function(opts) {
  opts = opts || {};
  var form = $('#' + opts.slug + '-form'),
    sendBtn = $('#' + opts.send);
  if (!form.length || !sendBtn.length) {
    return;
  }
  // No submit->preventDefault bind: disableFormDefaults() already blocks the
  // native submit of every form on the page.
  sendBtn.on('click', function() {
    sendBtn.prop('disabled', true);
    form.processForm(function() {
      sendBtn.prop('disabled', false);
    });
  });
  if (opts.node) {
    $.registerCreateAndSelect(opts.slug, {
      // By name, not id: the create modal namespaces every id it pulls in, but
      // never a name, so a name cannot be captured by the fetched form.
      select: form.find('[name="' + (opts.select || opts.node) + '"]'),
      send: sendBtn
    });
  }
};
// $.registerReloadToggle(btn, opts) - wire the single pause/resume auto-refresh
// button emitted by FOGPage::makeReloadToggle().
//
// This replaced a pause button and a resume button sitting side by side with one
// of them always disabled, so every pane rendered a permanently dead control.
// One button relabels itself instead: it always shows the action you can take.
//
// Both labels come off the button's own data attributes rather than being
// written here, so the strings stay inside gettext on the PHP side. The color
// class is deliberately not touched - state is carried by the label alone, so
// the button does not change color under the cursor and cannot end up as a
// second btn-primary next to a real one (the multicast pane's Create).
//
// btn   - the toggle button, a jQuery object or selector.
// opts  - onPause/onResume callbacks. Called after the label has been swapped.
$.registerReloadToggle = function(btn, opts) {
  opts = opts || {};
  var $btn = $(btn);
  if (!$btn.length) {
    return;
  }
  // Start live. Callers that render already-paused would set data-paused="1".
  var paused = $btn.data('paused') === 1 || $btn.data('paused') === '1';
  function paint() {
    $btn.text(paused ? $btn.data('resume-label') : $btn.data('pause-label'));
    $btn.attr('data-paused', paused ? '1' : '0');
  }
  paint();
  $btn.prop('disabled', false);
  $btn.on('click', function(e) {
    e.preventDefault();
    paused = !paused;
    paint();
    if (paused) {
      if (opts.onPause) {
        opts.onPause();
      }
    } else if (opts.onResume) {
      opts.onResume();
    }
  });
};
// Column resizing - let the user drag a table's column borders.
//
// No DataTables release has ever shipped this; the only third-party option
// (Daniel Hobi's colResize) is an unmaintained fork that does not work against
// DataTables 2.x, and the bundle we vendor carries ColReorder (moving columns)
// but nothing for resizing. So this does the small thing directly rather than
// take on a dead dependency.
//
// HOW DATATABLES ACTUALLY HOLDS COLUMN WIDTHS -- this is the whole trick, and
// getting it wrong is why the first attempt did nothing at all:
//
//   * Widths live on a <colgroup>, NOT on the <th> elements. Setting
//     th.style.width is simply overruled by the <col>, so the drag appeared
//     to do nothing.
//   * In scroll mode (Scroller / scrollY) there are TWO tables -- a cloned
//     header in .dt-scroll-head and the real table in .dt-scroll-body -- and
//     each carries its OWN colgroup. Both have to be written or the header
//     slides out of alignment with the body.
//   * The header you can see and click in scroll mode is the CLONE. The real
//     table's own thead is still in the DOM but hidden (its cells wrap their
//     content in .dt-scroll-sizing), so grab strips attached there land
//     somewhere no pointer can reach.
//
// So: attach the strips to whichever header is visible, and on drag rewrite
// the matching <col> on every colgroup involved. Width is moved from one
// column to its neighbor, so the table's total width never changes and
// nothing reflows sideways.
//
// The last column is skipped on purpose -- it is the one absorbing whatever
// the others leave, so there is no neighbor to take width from.

// Resolve the pieces of a DataTable that resizing needs to talk to.
function fogTableParts(node) {
  var body = $(node),
    wrap = body.closest('.dt-container, .dataTables_wrapper'),
    head = wrap.find('.dt-scroll-head table, .dataTables_scrollHead table')
      .first(),
    visibleHead = head.length ? head : body,
    allTh = visibleHead.find('thead tr:first > th'),
    domIndex = [];

  // Responsive hides a column by putting display:none on its cells, it does
  // not remove them -- but DataTables' <colgroup> only ever carries the
  // columns that are actually showing. So the two lists routinely differ in
  // length, and they differ even at full width: the host list has always run
  // six header cells against five <col>s because its Description column is
  // hidden by default. Everything below addresses a column by its position in
  // the COLGROUP, so work out once which header cell each of those positions
  // belongs to instead of assuming the two line up 1:1.
  //
  // Comparing the counts without this is what left the host list with no
  // resize strips at any width -- the guard read a permanent six-versus-five
  // as "Responsive has collapsed this table" and bailed every time.
  //
  // Tested on the cell's OWN display rather than jQuery :visible on purpose:
  // :visible is false for every cell of a table sitting in a not-yet-shown
  // tab, which would throw away the mapping for a table that is merely
  // off-screen rather than collapsed.
  allTh.each(function (k) {
    if ($(this).css('display') !== 'none') {
      domIndex.push(k);
    }
  });

  return {
    // The header the user actually sees and grabs.
    visibleHead: visibleHead,
    // The table holding the actual rows.
    body: body,
    // Every table whose colgroup has to stay in step.
    tables: head.length ? head.add(body) : body,
    // The header cells that have a <col> behind them, in colgroup order.
    headers: allTh.filter(function () {
      return $(this).css('display') !== 'none';
    }),
    // colgroup position -> DOM position of the matching th/td.
    domIndex: domIndex
  };
}

// Column widths the user set by hand, remembered per table so that a
// Responsive rebuild does not throw them away.
//
// Stored as each column's SHARE of the table rather than its pixel width, and
// keyed on the column's original DataTables index rather than its position in
// the current colgroup. Both parts are load-bearing:
//
//  - Responsive rebuilds the colgroup with only the columns still showing, so
//    colgroup position 3 is a different column at 700px than at 1400px.
//    data-dt-column keeps its original number even while the column is
//    hidden, which makes it the one handle that survives the rebuild.
//  - A pixel width measured in a 1400px-wide table means nothing in a 700px
//    one. Shares restore the same PROPORTIONS at whatever width is available,
//    which is what "my layout came back" actually feels like.
//
// Deliberately in-memory and per page load. localStorage would also survive a
// reload, but that needs an answer for what happens when a table's columns
// change underneath a stored layout, and this has no such answer yet.
var fogColWidthStore = {};

// A table with no id has no identity worth storing against.
function fogTableKey(parts) {
  return parts.body.attr('id') || '';
}

// Identity of the column at colgroup position i.
function fogColKey(parts, i) {
  return parts.headers.eq(i).attr('data-dt-column');
}

// The widths currently on the colgroup. After a drag this is where the truth
// lives -- the header cells have not been re-measured yet.
function fogCurrentColWidths(parts) {
  return parts.visibleHead.find('colgroup > col').map(function() {
    return parseFloat(this.style.width) || 0;
  }).get();
}

// Record the layout a user gesture just produced.
function fogRememberColWidths(parts, widths) {
  var key = fogTableKey(parts),
    total = 0,
    store,
    ck,
    i;

  if (!key || !widths || !widths.length) {
    return;
  }
  for (i = 0; i < widths.length; i++) {
    if (widths[i] <= 0) {
      return;
    }
    total += widths[i];
  }
  store = fogColWidthStore[key] = fogColWidthStore[key] || {};
  for (i = 0; i < widths.length; i++) {
    ck = fogColKey(parts, i);
    if (ck !== undefined) {
      store[ck] = widths[i] / total;
    }
  }
}

// Rebuild a width row from what was remembered, or null when this table has
// nothing stored for any column currently showing.
//
// The shares are renormalized over the showing columns, and that renormalizing
// is the whole trick for carrying a layout across a breakpoint: hide two of
// five columns and the surviving three keep the same proportions to each other
// that the user gave them, spread over the full table width.
//
// A column with nothing stored is not a reason to throw the layout away. It
// happens routinely in normal use -- searching the host list down to a few
// rows narrows the content enough that Responsive brings a previously hidden
// column BACK, and refusing to restore then meant a search silently undid the
// user's sizing. Such a column is given its own freshly measured width as its
// share, so it slots in at its natural size while every remembered column
// keeps its proportions relative to the others.
function fogRestoredColWidths(parts, widths) {
  var store = fogColWidthStore[fogTableKey(parts)],
    shares = [],
    sumShare = 0,
    total = 0,
    known = 0,
    out = [],
    widest = 0,
    drift,
    ck,
    i;

  if (!store || !widths || !widths.length) {
    return null;
  }
  for (i = 0; i < widths.length; i++) {
    if (widths[i] <= 0) {
      return null;
    }
    total += widths[i];
  }
  for (i = 0; i < widths.length; i++) {
    ck = fogColKey(parts, i);
    if (ck !== undefined && store[ck] !== undefined) {
      shares[i] = store[ck];
      known++;
    } else {
      shares[i] = widths[i] / total;
    }
    sumShare += shares[i];
  }
  // Nothing remembered about any showing column: leave the seeded widths be.
  if (!known || sumShare <= 0 || total <= 0) {
    return null;
  }
  drift = total;
  for (i = 0; i < widths.length; i++) {
    out[i] = Math.max(40, Math.round(total * shares[i] / sumShare));
    drift -= out[i];
    if (out[i] > out[widest]) {
      widest = i;
    }
  }
  // Absorb the rounding into the widest column, where a pixel or two cannot
  // push anything under the 40px floor.
  out[widest] += drift;
  return out;
}

// Write a column and its neighbor in one go, on every colgroup involved.
function fogSetColPair(parts, i, widthA, widthB) {
  parts.tables.each(function() {
    var cols = $(this).find('colgroup > col');
    if (cols.length < i + 2) {
      return;
    }
    cols[i].style.width = widthA + 'px';
    cols[i + 1].style.width = widthB + 'px';
  });
}

// Write a whole row of column widths, on every colgroup involved.
function fogSetCols(parts, widths) {
  parts.tables.each(function() {
    var cols = $(this).find('colgroup > col');
    if (cols.length !== widths.length) {
      return;
    }
    cols.each(function(i) {
      this.style.width = widths[i] + 'px';
    });
  });
}

// Resize column i to `want`, paying for it out of ALL the other columns rather
// than only its right-hand neighbor.
//
// A drag takes width from the neighbor because the neighbor's border is the
// thing being dragged. A fit has no such anchor, and charging the whole cost
// to one column flattened it to the floor -- fitting the plugin Description
// took 238px straight out of Location and left it unreadable.
//
// So the cost is spread, and a donor is only asked for space it does not need
// for its OWN content: its floor is its own natural width, not a blind 40px.
// Without that the fit simply maxed out, because one very long description
// wants more width than the whole table has -- Description went to 1104px and
// every other column collapsed to 40. Now a column widens by whatever the
// others genuinely are not using, and stops there.
//
// The table's total width stays constant either way, so nothing reflows
// sideways and no horizontal scrollbar appears.
function fogFitColumn(parts, i, want, widths) {
  var out = widths.slice(),
    j,
    weights = [],
    sumWeight = 0,
    floor,
    delta = want - widths[i];

  for (j = 0; j < widths.length; j++) {
    if (j === i) {
      weights[j] = 0;
      continue;
    }
    if (delta > 0) {
      // Never below what this column needs for its own content -- and never
      // above its current width, so a column that is already too narrow is
      // simply not asked to contribute.
      floor = Math.min(widths[j], Math.max(40, fogNaturalColWidth(parts, j)));
      weights[j] = Math.max(0, widths[j] - floor);
    } else {
      // Shrinking hands space back in proportion to current width.
      weights[j] = widths[j];
    }
    sumWeight += weights[j];
  }
  if (!sumWeight) {
    return null;
  }
  if (delta > 0) {
    delta = Math.min(delta, sumWeight);
  } else {
    delta = Math.max(delta, 40 - widths[i]);
  }
  for (j = 0; j < out.length; j++) {
    if (j !== i) {
      out[j] = Math.round(widths[j] - (delta * weights[j] / sumWeight));
    }
  }
  // Absorb rounding drift into the fitted column so the widths still add up to
  // exactly what they did before.
  out[i] = widths.reduce(function(a, b) {
    return a + b;
  }, 0) - out.reduce(function(a, b, k) {
    return k === i ? a : a + b;
  }, 0);
  fogSetCols(parts, out);
  // Handed back so the caller can remember the layout this produced.
  return out;
}

// Widest content in a column, for double-click-to-fit.
//
// Measured in an off-screen ruler that borrows the cell's font and padding,
// rather than read off the cells themselves. A clipped cell reports its
// clipped width, so once a column is too narrow there is no way to ask it how
// wide it would like to be -- and reading scrollWidth would only ever let a
// column grow, never shrink back to fit content that is shorter than it.
//
// The ruler takes innerHTML, not text, so a cell holding a badge or a button
// measures as what it renders rather than as an empty string.
//
// Only RENDERED rows can be measured. With the scroller on that is the chunk
// currently drawn, not the whole result set -- so this fits what you can see.
// Measuring every row would mean rendering every row, which is precisely the
// cost the scroller exists to avoid.
function fogNaturalColWidth(parts, i) {
  // i is a colgroup position; the row cells are in DOM order and still
  // include any column Responsive has hidden, so translate before indexing.
  var domI = parts.domIndex[i],
    cells = parts.body.find('tbody tr').map(function() {
      return this.cells[domI];
    }).get(),
    title = parts.headers.eq(i).find('.dt-column-title'),
    probe = cells.length ? $(cells[0]) : title,
    ruler = $('#fog-col-ruler');

  if (!probe.length) {
    return 0;
  }
  if (!ruler.length) {
    ruler = $('<div id="fog-col-ruler"></div>').appendTo('body');
  }
  var cs = window.getComputedStyle(probe[0]),
    max = 0;
  ruler.css({
    position: 'absolute',
    top: '-9999px',
    left: '-9999px',
    visibility: 'hidden',
    whiteSpace: 'nowrap',
    fontFamily: cs.fontFamily,
    fontSize: cs.fontSize,
    fontWeight: cs.fontWeight,
    paddingLeft: cs.paddingLeft,
    paddingRight: cs.paddingRight
  });
  function measure(html, bold) {
    ruler.css('font-weight', bold ? 'bold' : cs.fontWeight).html(html);
    max = Math.max(max, Math.ceil(ruler[0].getBoundingClientRect().width));
  }
  // The heading counts too -- fitting a column so tightly that its own title
  // is cut off is not a fit.
  if (title.length) {
    measure(title.html(), true);
  }
  $.each(cells, function() {
    measure(this.innerHTML, false);
  });
  ruler.empty();
  // Slack for the sort arrow and cell border, so a fresh fit does not land
  // one pixel short and immediately re-clip what it just made room for.
  return max + 24;
}

// Make sure each colgroup carries explicit px widths, seeded from whatever the
// columns currently measure. Without this a non-scrolling table has a colgroup
// of empty <col>s (DataTables only fills them in when it sizes the table
// itself), and there is no width to move around.
function fogSeedColWidths(parts) {
  var widths = parts.headers.map(function() {
    return $(this).outerWidth();
  }).get();
  // A table inside a not-yet-shown tab measures zero. Seeding "0px" then would
  // stick, because seeding only fills in a col that has no width yet -- so
  // leave it alone and let the column-sizing pass that fires when the tab is
  // shown do the seeding against real numbers.
  for (var w = 0; w < widths.length; w++) {
    if (!widths[w]) {
      return widths;
    }
  }
  parts.tables.each(function() {
    var cols = $(this).find('colgroup > col');
    if (cols.length !== widths.length) {
      return;
    }
    cols.each(function(i) {
      if (!this.style.width) {
        this.style.width = widths[i] + 'px';
      }
    });
  });
  return widths;
}

$.fn.makeColumnsResizable = function() {
  return this.each(function() {
    var parts = fogTableParts(this),
      headers = parts.headers,
      colCount = parts.visibleHead.find('colgroup > col').length;

    // Clear every existing strip and build fresh ones, rather than skipping
    // headers that look like they already have one.
    //
    // "Already has a strip" is not a safe test here. DataTables builds the
    // visible scroll header by CLONING the real one, and a clone copies the
    // strip's markup but not its event handlers -- so the first pass (which
    // may run before the clone exists, and therefore wires the real header)
    // leaves behind dead look-alike strips in the clone. Skipping on sight of
    // one meant the visible header kept its corpses and never got a working
    // handler bound. Rebuilding is the only check a clone cannot fool.
    parts.tables.find('thead .fog-col-resizer').remove();

    // The showing header cells and the <col>s have to line up 1:1, because
    // everything below addresses a column by its colgroup position. They
    // normally do -- fogTableParts() drops the cells Responsive has hidden
    // precisely so they can. If they still disagree the mapping is not
    // trustworthy and a drag would move a different column than the one
    // grabbed, so leave the table alone rather than offer strips that quietly
    // do the wrong thing.
    if (!colCount || colCount !== headers.length) {
      // Hand the table back to the browser on the way out. A previous pass at
      // a wider window left `fog-table-fixed` on it, and a fixed layout over a
      // colgroup that no longer matches the header sizes the surviving columns
      // as equal shares of the table -- so collapsing to mobile widths made the
      // columns visibly wrong, not merely un-resizable. Dropping the class
      // (and the widths that pass wrote) restores content-based sizing; the
      // column-sizing pass on the way back out re-seeds and re-applies both.
      parts.tables.removeClass('fog-table-fixed')
        .find('colgroup > col').css('width', '');
      return;
    }

    // Seed BEFORE switching to a fixed layout, never after. A fixed layout
    // with an empty colgroup makes every column an equal share of the table,
    // so measuring at that point records five identical widths and throws away
    // the content-based sizing the table actually had. Measure what the
    // browser worked out, write it down, and only then make it authoritative.
    var seeded = fogSeedColWidths(parts),
      restored = fogRestoredColWidths(parts, seeded);
    parts.tables.addClass('fog-table-fixed');
    // Put a hand-set layout back on top of the freshly measured one. This runs
    // on every column-sizing pass, so it covers the Responsive rebuild that
    // prompted it and, for free, any redraw that re-measures the table.
    if (restored) {
      fogSetCols(parts, restored);
    }

    headers.each(function(i) {
      var th = $(this);
      if (i >= headers.length - 1) {
        return;
      }
      var handle = $('<span class="fog-col-resizer"></span>').appendTo(th);
      handle.on('mousedown', function(ev) {
        // Both stops matter: the header is a sort control, so without them a
        // drag would also re-sort the table, and the browser would try to
        // text-select the heading while dragging.
        ev.preventDefault();
        ev.stopPropagation();
        // Re-seed on grab, not at wire-up time: the table may have been
        // resized, re-drawn or had a column hidden since.
        var widths = fogSeedColWidths(parts),
          startX = ev.pageX,
          startW = widths[i],
          startNextW = widths[i + 1];
        function move(e) {
          var dx = e.pageX - startX;
          // 40px floor so a column cannot be dragged away to nothing.
          if (startW + dx < 40 || startNextW - dx < 40) {
            return;
          }
          fogSetColPair(parts, i, startW + dx, startNextW - dx);
        }
        function up() {
          $(document).off('mousemove.fogcol mouseup.fogcol');
          $('body').removeClass('fog-col-resizing');
          // Remember where the drag settled, read off the colgroup rather
          // than the header cells -- the cells have not been re-measured.
          fogRememberColWidths(parts, fogCurrentColWidths(parts));
        }
        $('body').addClass('fog-col-resizing');
        $(document).on('mousemove.fogcol', move).on('mouseup.fogcol', up);
      });
      // Double-click the strip to size the column to its widest content, the
      // same gesture a spreadsheet uses.
      //
      // The cost is spread across the other columns rather than charged to the
      // neighbor (see fogFitColumn), so the table's total width still never
      // changes but no single column gets flattened to pay for the fit.
      handle.on('dblclick', function(ev) {
        ev.preventDefault();
        ev.stopPropagation();
        var widths = fogSeedColWidths(parts),
          want = fogNaturalColWidth(parts, i);
        if (want) {
          fogRememberColWidths(parts, fogFitColumn(parts, i, want, widths));
        }
      });
      // A plain click on the strip would still bubble to the sort handler.
      handle.on('click', function(ev) {
        ev.stopPropagation();
      });
    });
  });
};
// A clipped cell ends in an ellipsis, which makes the tail unreadable rather
// than merely out of the way. Give it back on hover.
//
// Done on mouseenter, and delegated at the document, rather than stamping a
// title on every cell as it is created: the check costs a layout read, and on
// a 500-row table that is thousands of reads per draw for text nobody is
// looking at. This measures exactly the one cell under the pointer.
//
// Cells holding only markup (the status badges, action buttons) have no text
// and are skipped, so they do not sprout empty tooltips.
$(document).on('mouseenter', '.fog-table-clip tbody td', function() {
  if (this.title || this.scrollWidth <= this.clientWidth) {
    return;
  }
  var txt = $(this).text().trim();
  if (txt) {
    this.title = txt;
  }
});
$.getSelectedIds = function(table) {
  var rows = table.rows({selected: true});
  return rows.ids().toArray();
};
// Toasts are Bootstrap 5's own Toast component. FOG used to vendor PNotify
// 3.2.0 for this; bootstrap5.bundle.min.js already carries Toast, so the
// library was dropped rather than updated. PNotify's last release was 2020,
// it has no Bootstrap 5 styling and its icon presets stop at Font Awesome 5 --
// which is exactly why the toast icon names had to be hand-overridden at this
// call site after the FA7 migration. Owning the markup ends that class of
// problem: the icon names below are ordinary FOG source, checked by
// tests/fontawesome7-icon-names.test.php like every other icon we emit.
//
// Auto-hide delay is PNotify's default, kept so toasts live exactly as long
// as they used to.
var TOAST_DELAY = 8000;

// type -> [Bootstrap contextual suffix, icon, header theme].
//
// `warning` is genuinely new: PNotify 3 had no such type, so every warning
// $.notifyFromAPI() produced has been rendering with plain notice styling.
//
// The third field is the fix for a contrast bug worth explaining, because
// neither half of it is visible from reading the markup. `text-bg-*` picks its
// own foreground for contrast, and for warning and info Bootstrap picks DARK
// text -- so a blanket `btn-close-white` puts a white x on yellow and on cyan,
// near enough invisible. But swapping to a plain `.btn-close` only moves the
// problem: under `[data-bs-theme=dark]` Bootstrap filters `.btn-close` white
// again, so those two go invisible for anyone using FOG's dark theme instead.
//
// These backgrounds are fixed colors in BOTH themes, so the header is pinned
// to the theme its own background belongs to. That is enough for the text and
// the icon, which read their color from the scoped variables.
//
// It is NOT enough for the close button, and the reason is worth writing down
// because the markup looks correct either way: Bootstrap dims it with
// `[data-bs-theme=dark] .btn-close`, a DESCENDANT selector, so it matches any
// close button anywhere under <html data-bs-theme="dark"> and a nearer `light`
// scope does not call it off. Verified by reading the computed filter -- all
// five were identical while the title colors had scoped correctly. So a light
// header clears the filter itself, below.
var TOAST_TYPES = {
  success: ['success', 'fas fa-circle-check', 'dark'],
  error: ['danger', 'fas fa-triangle-exclamation', 'dark'],
  warning: ['warning', 'fas fa-triangle-exclamation', 'light'],
  info: ['info', 'fas fa-circle-info', 'light'],
  notice: ['secondary', 'fas fa-circle-exclamation', 'dark']
};

// One container, made on demand rather than baked into the page templates --
// toasts are raised from the login page and the management shell both, and
// neither should have to carry markup for a thing it may never show.
// Top-right is where PNotify's default stack sat. The z-index is Bootstrap's
// own (--bs-toast-zindex: 1090), which is above modals at 1055, so a toast
// raised by an action inside a modal is still visible.
function fogToastContainer() {
  var el = document.getElementById('fog-toast-container');
  if (!el) {
    el = document.createElement('div');
    el.id = 'fog-toast-container';
    el.className = 'toast-container position-fixed top-0 end-0 p-3';
    document.body.appendChild(el);
  }
  return el;
}

$.notify = function(title, body, type) {
  type = TOAST_TYPES[type] ? type : 'success';
  // De-dupe identical, still-visible notices. Repeated identical actions
  // (clicking a button several times, or several genuine updates in a row)
  // should collapse into the existing toast -- refreshing its auto-hide timer
  // and showing a running count -- instead of piling separate toasts on the
  // stack. Distinct messages still stack normally.
  var active = ($.notify._active = $.notify._active || {});
  var key = type + '\u0000' + (title || '') + '\u0000' + (body || '');
  var existing = active[key];
  // isConnected as well as isShown: a toast removed from the document by
  // anything other than its own hidden.bs.toast handler keeps both its `show`
  // class and its map entry, so isShown() alone stays true for an element that
  // is no longer on the page -- and every later notification with the same
  // text would then be "collapsed" onto a node nobody can see, silently. The
  // container is a direct child of body and nothing in FOG removes it today;
  // this is one condition so that stays a fact about the code rather than an
  // assumption it depends on.
  if (existing && existing.el.isConnected && existing.toast.isShown()) {
    existing.count += 1;
    existing.titleEl.textContent = (title || '') + ' (\u00d7' + existing.count + ')';
    // show() clears the pending hide timeout before rescheduling it, so this
    // restarts the countdown exactly as PNotify's queueRemove() did.
    existing.toast.show();
    return existing.toast;
  }

  var variant = TOAST_TYPES[type][0],
    icon = TOAST_TYPES[type][1],
    // An error or a warning interrupts; anything else is a status update the
    // screen reader should announce at the next opportunity instead of cutting
    // across what is being read.
    urgent = (type === 'error' || type === 'warning');

  var el = document.createElement('div');
  el.className = 'toast fog-toast';
  el.setAttribute('role', urgent ? 'alert' : 'status');
  el.setAttribute('aria-live', urgent ? 'assertive' : 'polite');
  el.setAttribute('aria-atomic', 'true');

  var header = document.createElement('div');
  header.className = 'toast-header text-bg-' + variant;
  header.setAttribute('data-bs-theme', TOAST_TYPES[type][2]);

  var iconEl = document.createElement('i');
  iconEl.className = icon + ' me-2';
  iconEl.setAttribute('aria-hidden', 'true');

  var titleEl = document.createElement('strong');
  titleEl.className = 'me-auto';
  // textContent, not innerHTML. PNotify defaulted title_escape/text_escape to
  // false, so every toast title and body has been parsed as HTML -- including
  // $.notifyFromAPI()'s res.error, which can carry a value the user typed.
  // Nothing passes markup, so making this text-only costs nothing and closes
  // the hole rather than relying on every future caller to escape.
  titleEl.textContent = title || '';

  var closer = document.createElement('button');
  closer.type = 'button';
  closer.className = 'btn-close';
  if ('light' === TOAST_TYPES[type][2]) {
    // See the TOAST_TYPES comment: the theme scope cannot reach this one.
    // Inline rather than a stylesheet rule because fog-default-ui.min.css is
    // a committed build artifact whose compiler no longer reproduces it byte
    // for byte, so editing its source would mix an unrelated 50-byte
    // regeneration into this change. CSP allows it: style-src carries
    // 'unsafe-inline' and sets no style-src-attr.
    closer.style.filter = 'none';
  }
  closer.setAttribute('data-bs-dismiss', 'toast');
  closer.setAttribute('aria-label', 'Close');

  header.appendChild(iconEl);
  header.appendChild(titleEl);
  header.appendChild(closer);

  var bodyEl = document.createElement('div');
  bodyEl.className = 'toast-body';
  bodyEl.textContent = body || '';

  el.appendChild(header);
  el.appendChild(bodyEl);
  fogToastContainer().appendChild(el);

  // Disposed and removed once it has gone, so neither the DOM nor the de-dupe
  // map grows without bound on a long-lived AJAX-navigated page. This replaces
  // PNotify's leftover-reference sweep: the element tells us when it is done
  // rather than being polled for it.
  el.addEventListener('hidden.bs.toast', function() {
    delete active[key];
    bootstrap.Toast.getInstance(el).dispose();
    el.remove();
  });

  var toast = new bootstrap.Toast(el, {delay: TOAST_DELAY});
  active[key] = {toast: toast, el: el, titleEl: titleEl, count: 1};
  toast.show();
  return toast;
};
$.notifyFromAPI = function(res, isError) {
  // A body that is not an object cannot carry a message, and the guard used
  // to be `res === undefined` -- which a STRING body walks straight past. A
  // string is exactly what arrives when an endpoint answers with HTML, the
  // shape management/index.php returned for a signed-out XHR: the sign-in
  // page at 200. Every lookup below was then undefined, `type` kept its old
  // 'success' default, and the user got a GREEN toast reading 'Bad Response'
  // while the write was silently discarded.
  //
  // statusText is not used for the reason: it is empty over HTTP/2, which has
  // no reason phrase, so it produced an empty message on exactly the servers
  // most likely to hit this.
  if (!res || typeof res !== 'object') {
    res = {
      title: 'Bad Response',
      error: (isError && isError.status)
        ? 'The server answered ' + isError.status
          + ' with no readable message.'
        : 'The server returned no readable message.'
    };
  }
  var title = res.title,
    // NOT 'success'. A response carrying none of error/info/warning/msg is
    // one that nothing could be read out of, and that is a failure whatever
    // the status line says -- the fallback at the bottom of this function
    // exists precisely for it. Each branch below overrides this, so a
    // response that does carry a message is unaffected.
    type = 'error',
    // Declared. It never was: every branch below ASSIGNED it as an implicit
    // global, and `if (!msg)` READS it -- so any response carrying none of
    // error/info/warning/msg threw ReferenceError out of the success
    // handler and took the caller's callback with it. The fallback two
    // lines down existed precisely for that case and could never run.
    //
    // The usual way in was a body jQuery did not parse as JSON (an endpoint
    // missing its Content-type header, or answering with HTML): res was then
    // a string and every lookup below undefined. The guard at the top of this
    // function now turns any non-object into a real error object, so a string
    // no longer reaches here -- but `msg` can still end up unset if an object
    // carries none of the four keys, which is what the fallback below is for.
    msg;
  if (res.error) {
    type = 'error';
    msg = res.error;
  }
  if (res.info) {
    type = 'info';
    msg = res.info;
  }
  if (res.warning) {
    type = 'warning';
    msg = res.warning;
  }
  if (res.msg) {
    type = 'success';
    msg = res.msg;
  }
  if (!msg) {
    msg = 'Bad Response';
  }

  $.notify(
    title || 'Bad Response',
    msg,
    type
  );
  $.debugLog(res);
};
// opts.modal      - the confirm modal (default '#deleteModal', resolved once
//                   at page load into reAuthModal).
// opts.confirmSel - its confirm button (default '#confirmDeleteModal').
// opts.noun       - what is being deleted, for the button text. Defaults to
//                   Common.node, which is the page's entity -- wrong for any
//                   grid that is not the page's own list.
//
// Parameterized because a page can carry more than one deletable grid. The
// Bearer API token card sits on the USER edit page, whose own #deleteModal
// deletes the account: sharing it meant the confirm read "Delete 1 users",
// the password field the token delete needs was not in that modal at all,
// and -- worst -- deleteConfirmButton.off('click') below tore the General
// tab's delete-user handler off, leaving that button dead until a reload.
// $.registerGeneralTab already parameterizes exactly these two selectors;
// this follows it.
$.reAuth = function(count, cb, opts) {
  opts = opts || {};
  var modal = opts.modal ? $(opts.modal) : reAuthModal,
    confirmBtn = opts.confirmSel ? $(opts.confirmSel) : deleteConfirmButton,
    // deleteLang is captured once at load from the default button. A custom
    // one carries its own template, so read it the first time and stash it
    // -- by the second call the text has been substituted already.
    lang = opts.confirmSel
      ? (confirmBtn.data('reauthLang')
        || confirmBtn.data('reauthLang', confirmBtn.text()).data('reauthLang'))
      : deleteLang,
    noun = opts.noun || Common.node,
    // Scoped to the modal: two of these on one page means two #deletePassword
    // inputs, and a document-wide lookup reads whichever came first.
    pw = modal.find('input[type="password"]').first();

  confirmBtn.text(lang.replace('{0}', count).replace('{node}', noun + (count != 1 ? 's' : '')));
  // enable all buttons / focus on the input box incase
  //   the modal is already being shown
  modal.setContainerDisable(false);
  pw.trigger('focus');
  modal.registerModal(
    // On show
    function(e) {
      pw.val('');
      pw.trigger('focus');
      modal.setContainerDisable(false);
    },
    // On close
    function(e) {
      pw.val('');
      cb('authClose');
    }
  );
  // The auth modal is not a form, so
  //   the enter key must be manually bound
  //   to submit the password
  pw.off('keypress');
  pw.keypress(function (e) {
    if (e.which == 13) {
      modal.setContainerDisable(true);
      cb(null, pw.val());
      return false;
    }
  });

  confirmBtn.off('click');
  confirmBtn.on('click', function(e) {
    modal.setContainerDisable(true);
    cb(null, pw.val());
  });
  modal.modal('show');
};
/**
 * Allows calling as $.funcname(element, ...args);
 */
$.cachedScript = function(url, options) {
  // Allow user to set any option except for dataType, cache, and url
  options = $.extend(options || {}, {
    dataType: 'script',
    cache: true,
    url: url
  });

  // Use $.ajax() since it is more flexible than $.getScript
  // Return the jqXHR object so we can chain callbacks
  return $.ajax(options);
};
$.finishReAuth = function(modal) {
  $(modal).modal('hide');
};
$.mirror = function(start, selector, regex, replace) {
  $(start).mirror(selector, regex, replace);
};
$.processForm = function(form, cb, input) {
  if (undefined === input) {
    input = ':input';
  }
  $(form).processForm(cb, input);
};
$.registerModal = function(modal, onOpen, onClose, opts) {
  $(modal).registerModal(onOpen, onClose, opts);
};
$.registerTable = function(e, onSelect, opts) {
  $(e).registerTable(onSelect, opts);
};
$.setContainerDisable = function(container, disable) {
  $(container).setContainerDisable(disable);
};
$.setLoading = function(container, loading) {
  $(container).setLoading(loading);
};
$.validateForm = function(form, input) {
  if (undefined === input) {
    input = ':input';
  }
  $(form).validateForm(input);
};
// Snapin command-builder UI, shared by the snapin add / edit / list-create
// forms and the create-and-associate modal on association tabs. All of them
// wire the same pack / argTypes / .snapin-action / .cmdletN handlers and rebuild
// the hidden .snapincmd field identically.
//
// Root-scoped: call it on the form. This used to look everything up
// document-wide, which held while "each page has a single snapin form context"
// was true. The create-and-associate modal breaks that -- it injects a fetched
// form into a page that has fields of its own -- so every lookup has to stay
// inside the form it belongs to. That includes the [type=file] probe in
// updateCmdStore, which would otherwise find a file input anywhere on the page.
//
// The two SUBMITTED selects are matched by [name], which survives the modal's id
// namespacing because name is what the POST reads and is deliberately never
// rewritten. Two traps here, both found the hard way:
//   - snapinpack's name is 'packtype', which does NOT match its id.
//   - packTypes has no name at all -- it is a UI-only control driving rw/rwa and
//     is never submitted -- so it is matched on an id suffix, which resolves
//     whether or not the id has been namespaced.
//
// opts.packHide      - also toggle .packhide with the template class (edit form
//                      only; add / list-create have no .packhide elements).
// opts.wirePackTypes - wire the packTypes -> rw/rwa handler. Note the snapin
//                      LIST page's create modal does not pass this even though
//                      _addFields() does render packTypes there, so its "Snapin
//                      Pack Template" select goes unwired. Left as-is rather
//                      than changed on the way past.
$.fn.initSnapinCommandUI = function(opts) {
  opts = opts || {};
  var root = this,
    ACTION_VAL = -1,
    snapinpack = root.find('[name="packtype"]'),
    argTypes = root.find('[name="argTypes"]'),
    packTypes = root.find('[id$="packTypes"]');

  function packchanger(packval) {
    switch (packval) {
      case '0':
        root.find('.packnotemplate').removeClass('d-none');
        root.find('.packtemplate').addClass('d-none');
        if (opts.packHide) {
          root.find('.packhide').addClass('d-none');
        }
        break;
      case '1':
        root.find('.packnotemplate').addClass('d-none');
        root.find('.packtemplate').removeClass('d-none');
        if (opts.packHide) {
          root.find('.packhide').removeClass('d-none');
        }
        break;
    }
  }
  function updateCmdStore() {
    if (typeof root.find('.cmdlet3').val() === 'undefined') {
      return;
    }
    var cmd1 = root.find('.cmdlet1').val(),
      cmd2 = root.find('.cmdlet2').val(),
      cmd3 = root.find('.cmdlet3').val(),
      cmd4 = root.find('.cmdlet4').val(),
      test = root.find('[type="file"]');
    if (test.length < 1) {
      cmd3 = root.find('select.cmdlet3').val();
    } else {
      test = test[0].files.length;
      if (test < 1) {
        cmd3 = root.find('select.cmdlet3').val();
      } else {
        cmd3 = root.find('[type="file"]')[0].files[0].name;
      }
    }
    var snapCMD = [cmd1, cmd2, cmd3, cmd4];
    root.find('.snapincmd').val(snapCMD.join(' '));
  }
  // Allow radio to change properly but also be unset as maybe the user doesn't
  // want an action to occur after the snapin completes.
  var onRadioSelect = function() {
    var action = $(this).val();
    if (ACTION_VAL === -1) {
      ACTION_VAL = action;
    }
    if (action === ACTION_VAL) {
      $(this).prop('checked', false).trigger('change');
      ACTION_VAL = 0;
    } else {
      ACTION_VAL = action;
    }
  };
  // Make sure selectors are select2 friendly
  packchanger(snapinpack.val());
  // Make the change when the snapin pack selector changes.
  snapinpack.on('change', function() {
    packchanger($(this).val());
  });
  argTypes.on('change', function() {
    var option = $('option:selected', this),
      value = option.attr('value'),
      rwarg = option.attr('rwargs'),
      args = option.attr('args'),
      rwinp = root.find('input[name=rw]'),
      rwainp = root.find('input[name=rwa]'),
      argsinp = root.find('input[name=args]');
    if (value) {
      rwinp.val(value);
    }
    rwainp.val(rwarg);
    argsinp.val(args);
    updateCmdStore();
  });
  if (opts.wirePackTypes) {
    packTypes.on('change', function() {
      var option = $('option:selected', this),
        file = option.attr('file'),
        args = option.attr('args'),
        rwinp = root.find('input[name=rw]'),
        rwainp = root.find('input[name=rwa]');
      rwinp.val(file);
      rwainp.val(args);
    });
  }
  // Setup action radio selector
  root.find('.snapin-action').on('click', onRadioSelect);
  updateCmdStore();
  root.find('.cmdlet1,.cmdlet2,.cmdlet3,.cmdlet4').on('change keyup', function(e) {
    e.preventDefault();
    updateCmdStore();
  });
  root.find('.cmdlet3').on('change blur', function() {
    updateCmdStore();
  });
  return this;
};
/**
 * Printer create-form UI, shared by the printer add page, the printer list
 * page's create modal, and the create-and-associate modal on association tabs.
 *
 * Root-scoped on purpose. The three previous copies of this (fog.printer.add.js,
 * fog.printer.list.js and fog.printer.edit.js, identical but for comment
 * wording) looked their fields up document-wide via #printertype /
 * #printercopy. That holds only while a page shows exactly
 * one printer form. The create-and-associate modal breaks the assumption twice
 * over: it injects a fetched form into a page that has fields of its own, and
 * it namespaces the fragment's ids precisely so they cannot collide -- so an id
 * lookup would find nothing. Matching on [name] instead survives that rename,
 * because `name` is what the POST reads and is deliberately never rewritten.
 *
 * Everything else here was already class-based (.printer-type-section,
 * .printerport-input and friends), and classes are untouched by namespacing;
 * scoping them to the root just stops one form reaching into another.
 *
 * @param {Object} opts optional: {node} the node to ask for printer info,
 *                      default 'printer'. The old copies used Common.node,
 *                      which is right on the printer pages and wrong anywhere
 *                      else -- from a host page it asked ?node=host for
 *                      getPrinterInfo and quietly got nothing, so "Copy from
 *                      existing" would have looked broken there.
 * @return {jQuery} this
 */
$.fn.initPrinterFormUI = function(opts) {
  opts = opts || {};
  var root = this,
    node = opts.node || 'printer',
    printertype = root.find('[name="printertype"]'),
    printercopy = root.find('[name="printercopy"]');

  // Nothing to wire if this root holds no printer form.
  if (!printertype.length) {
    return this;
  }

  // Show only the selected type's section. Hidden sections are disabled so
  // their inputs stay out of the submitted FormData and out of validation.
  function showType(type) {
    root.find('.printer-type-section').each(function() {
      var section = $(this),
        match = section.hasClass(type);
      section.toggleClass('d-none', !match);
      section.find(':input').prop('disabled', !match);
    });
  }
  // Copy an existing printer's settings in. Each value is written to every type
  // section's matching input by class; only the visible one is submitted. Name
  // and description are left for the admin to fill in.
  function copyFromExisting(id) {
    if (!id) {
      return;
    }
    $.getJSON(
      '../management/index.php?node=' + node + '&sub=getPrinterInfo&id=' + id,
      function(data) {
        if (!data) {
          return;
        }
        root.find('.printerport-input').val(data.port);
        root.find('.printerinf-input').val(data.file);
        root.find('.printerip-input').val(data.ip);
        root.find('.printermodel-input').val(data.model);
        root.find('.printerconfigfile-input').val(data.configFile);
        var wanted = (data.config || '').toLowerCase(),
          matched = null;
        printertype.find('option').each(function() {
          if ($(this).val().toLowerCase() === wanted) {
            matched = $(this).val();
          }
        });
        if (matched !== null) {
          printertype.val(matched).trigger('change');
        } else {
          showType(wanted);
        }
      }
    );
  }

  // || '' because a select with no selection returns null, and the previous
  // copies called .toLowerCase() on it unguarded.
  showType((printertype.val() || '').toLowerCase());
  printertype.on('change', function(e) {
    e.preventDefault();
    showType((printertype.val() || '').toLowerCase());
  });
  printercopy.on('change', function() {
    copyFromExisting($(this).val());
  });
  return this;
};
/**
 * Selector required elements.
 */
$.fn.finishReAuth = function() {
  $(this).modal('hide');
};
$.fn.mirror = function(selector, regex, replace) {
  return this.each(function() {
    var start = $(this),
      mirror = $(selector);
    start.on('keyup', function() {
      if (regex) {
        if (typeof replace === 'undefined') {
          replace = '';
        }
        mirror.val(start.val().replace(regex, replace));
      } else {
        mirror.val(start.val());
      }
    });
  });
};
$.fn.processForm = function(cb, input) {
  if (undefined === input) {
    input = ':input';
  }
  var form = $(this),
    opts = new FormData(form[0]),
    method = form.attr('method'),
    action = form.attr('action');
  form.setContainerDisable(true);
  if (!form.validateForm(input)) {
    form.setContainerDisable(false);
    if (cb && typeof cb === 'function') {
      cb('invalid', null);
    }
    return;
  }
  $.apiCall(method, action, opts, function(err, data) {
    form.setContainerDisable(false);
    if (cb && typeof cb === 'function') {
      cb(err, data);
    }
  }, false);
};

/**
 * Wire an entity create form's #send button.
 *
 * Every add page renders a create form plus a single #send button and wired it
 * one of two ways, which this preserves via opts.mode:
 *   - 'disable' (default): disable #send, processForm(), re-enable on completion.
 *     Used by group/host/image/module/printer/snapin/storagegroup/storagenode.
 *   - 'clear': processForm(), and on success wipe every input so the form is
 *     ready for the next entry. Used by the pages meant for adding many rows in
 *     a row -- user/usergroup/role/ipxe.
 * disableFormDefaults() already prevents the form's native submit, so the
 * per-file submit->preventDefault bind is dropped as redundant. Each add page's
 * DOM (and thus #send) is torn down and rebuilt on every visit, so the click
 * bind here does not stack across navs and needs no namespace.
 *
 * @param {Object} opts optional: {mode:'disable'|'clear', selector} where
 *                       selector is the processForm validate filter (printer
 *                       passes ':input:visible' so only the shown type-section
 *                       is validated).
 * @return {jQuery} this
 */
$.fn.wireCreateForm = function(opts) {
  opts = opts || {};
  var createForm = this,
    createFormBtn = $('#send'),
    clear = (opts.mode === 'clear'),
    selector = opts.selector;
  createFormBtn.on('click', function() {
    if (clear) {
      createForm.processForm(function(err) {
        if (err) {
          return;
        }
        $(':input').val('');
      }, selector);
      return;
    }
    createFormBtn.prop('disabled', true);
    createForm.processForm(function(err) {
      createFormBtn.prop('disabled', false);
    }, selector);
  });
  return this;
};

$.fn.registerModal = function(onOpen, onClose, opts) {
  var e = this;
  if (e._modalInit === undefined || !e._modalInit) {
    opts = opts || {};
    opts = $.fogDefaults(opts, {
      backdrop: true,
      keyboard: true,
      focus: true,
      show: false
    });

    e.modal(opts);
    e._modalInit = true;
  }
  e.off('show.bs.modal');
  e.off('shown.bs.modal');
  e.off('hidden.bs.modal');

  if (onOpen && typeof(onOpen) === 'function')
    e.on('shown.bs.modal', onOpen);
  if (onClose && typeof(onClose) === 'function')
    e.on('hidden.bs.modal', onClose);
};
/**
 * General modal-opener re-enable safety net.
 *
 * A common pattern in FOG: a button disables itself (and sibling buttons) right
 * before opening a modal, and only re-enables them in the modal's explicit
 * Cancel/confirm handlers. Dismissing the modal another way -- clicking the
 * backdrop or pressing ESC -- fires neither, so those openers stay stuck
 * disabled until the page is reloaded.
 *
 * This catches every dismiss path without each page having to opt in: snapshot
 * what was already disabled before a click's handlers run, note anything the
 * open newly disabled outside the modal, and re-enable exactly those when the
 * modal is hidden. Delegated on document so it also covers raw modals that
 * never went through registerModal().
 */
(function() {
  var CANDIDATES = 'button, input, select, textarea, a, .btn';
  var clickSnapshot = null;

  function isDisabled(el) {
    return el.disabled === true || $(el).hasClass('disabled');
  }

  // Capture phase: record what was ALREADY disabled before this click's
  // (bubble-phase) handlers run and possibly disable the openers.
  document.addEventListener('click', function() {
    var snap = [];
    $(CANDIDATES).each(function() {
      if (isDisabled(this)) {
        snap.push(this);
      }
    });
    clickSnapshot = snap;
    // Drop the snapshot once this click has fully resolved.
    setTimeout(function() { clickSnapshot = null; }, 0);
  }, true);

  $(document).on('show.bs.modal', '.modal', function() {
    var modal = this,
      snap = clickSnapshot || [],
      newlyDisabled = [];
    $(CANDIDATES).each(function() {
      if (!isDisabled(this)) {
        return;
      }
      if (snap.indexOf(this) !== -1) {
        return; // already disabled before this open
      }
      if (this === modal || $.contains(modal, this)) {
        return; // inside the modal itself
      }
      newlyDisabled.push(this);
    });
    $.data(modal, 'fogReenableOnHide', newlyDisabled);
  });

  $(document).on('hidden.bs.modal', '.modal', function() {
    var list = $.data(this, 'fogReenableOnHide') || [];
    $(list).prop('disabled', false).removeClass('disabled');
    $.removeData(this, 'fogReenableOnHide');
  });
}());
/**
 * Password show/hide toggle. Any .fog-password-toggle button flips the password
 * input in its input-group between hidden and visible, so the user can confirm
 * what they typed (e.g. the login form).
 */
$(document).on('click', '.fog-password-toggle', function(e) {
  e.preventDefault();
  var input = $(this).closest('.input-group').find('input').first(),
    icon = $(this).find('i, span').first(),
    reveal = input.attr('type') === 'password';
  input.attr('type', reveal ? 'text' : 'password');
  icon.toggleClass('fa-eye', !reveal).toggleClass('fa-eye-slash', reveal);
  $(this).attr('aria-pressed', reveal ? 'true' : 'false');
});
// DataTables is not part of the slim (unauthenticated) asset set.
if ($.fn.dataTable) {
  $.fn.dataTable.ext.order['dom-checkbox'] = function(settings, col) {
      return this.api().column(col, {order:'index'}).nodes().map(function(td, i) {
        return $('input', td).prop('checked') ? '1' : '0';
    });
  };
}
/**
 * Adaptive height for infinite-scroll (Scroller) tables (#853).
 *
 * Instead of a hard-coded scrollY, size the scroll body to the space actually
 * available between the top of the rows and the bottom of the viewport, minus
 * whatever the DataTables wrapper renders below the body (the info line) and a
 * small gap. scrollCollapse keeps small tables short; this just raises the
 * ceiling for large ones so they fill the screen rather than wasting (or
 * crowding) vertical space. Recomputed on window resize and tab show.
 */
function fogSizeScroller(dt) {
  // dt.init() can be null for nodes the table.dataTable selector also matches
  // but that aren't fully-initialized Scroller tables (e.g. the scrollY split
  // table's cloned header). Guard it so we skip them instead of throwing on
  // null.scroller, which would abort the caller's loop before the real table.
  var init = (dt && typeof dt.init === 'function') ? dt.init() : null;
  if (!init || !init.scroller) {
    return; // only Scroller-enabled tables
  }
  var container = dt.table().container(),
    body = $('div.dt-scroll-body', container);
  if (!body.length || !body.is(':visible')) {
    return; // not rendered, or in a hidden tab
  }
  var bodyRect = body[0].getBoundingClientRect(),
    belowBody = container.getBoundingClientRect().bottom - bodyRect.bottom,
    gap = 20, // breathing room above the window bottom
    avail = window.innerHeight - bodyRect.top - belowBody - gap;
  if (avail < 150) {
    avail = 150; // sane floor
  }
  body.css('max-height', avail + 'px');
  // A table first laid out in a hidden tab renders its rows at zero width;
  // measure() below schedules a redraw at the real (now-visible) width, but that
  // redraw can land after this call returns, so the synchronous columns.adjust()
  // sizes the header against still-stale rows and the split stays misaligned
  // until a manual resize. Re-adjust once on the first draw after the table
  // becomes visible, when the real row widths exist. One-shot per table (flagged
  // on the settings object); the resize path runs on already-aligned rows so it
  // never needs this. Bound before measure() so it catches measure()'s redraw.
  //
  // Deferred a macrotask rather than run inside the draw, for the same reason
  // the shown.bs.tab handler is: during the draw the layout is not final yet.
  // Scroller sizes its viewport for a whole page of rows before the ajax has
  // said how many there really are, so the scroll body is still overflowing at
  // that moment. DataTables reserves the scrollbar's width on the header when it
  // sees that -- a padding-right on .dt-scroll-headInner -- and it never takes
  // the reservation back on its own. One tick later the row count is settled,
  // the body no longer overflows, and the same call computes a padding of zero.
  //
  // Measured on a one-row snapin list: padding-right stuck at 15px with the body
  // not overflowing, leaving the header 15px narrower than its rows (1540 against
  // 1555) and the column boundaries walking out -5, -8, -12, -15 across four
  // columns. Invisible wherever scrollbars are the overlay kind that occupy no
  // width, which is why it shows on a desktop browser and not in headless.
  var settings = dt.settings()[0];
  if (settings && !settings._fogPostShowAdjusted) {
    settings._fogPostShowAdjusted = true;
    dt.one('draw.dt.fogScroller', function() {
      setTimeout(function() {
        // The whole sizing pass, not just columns.adjust(): the height this
        // function sets is itself an input to whether the body overflows, so
        // re-deciding the height and the columns together is what makes the
        // reservation and the actual scrollbar agree. Re-entry is safe --
        // _fogPostShowAdjusted is already set, so this does not rebind.
        fogSizeScroller(dt);
      }, 0);
    });
  }
  // Recompute Scroller's virtual viewport for the new height (measure() also
  // redraws). Guarded in case Scroller isn't attached for some reason.
  if (dt.scroller && typeof dt.scroller.measure === 'function') {
    dt.scroller.measure();
  }
  // Re-sync the scrollY header/body column widths. measure() only recomputes
  // the virtual viewport height, so a table first laid out in a hidden tab
  // keeps its zero-width header/body split (header narrow, body full-width)
  // until the columns are adjusted once it becomes visible.
  dt.columns.adjust();
}
/**
 * Re-sync every initialized table to the width (and, for Scroller tables, the
 * height) it now has.
 *
 * A scrolling table is TWO tables: DataTables puts the header in its own
 * .dt-scroll-head table and pins that table's width in a style attribute, while
 * the body table is width:100%. Nothing keeps the two in step by itself, so any
 * change to the container's width leaves the header at its old pixel width and
 * the body at the new one -- and because makeColumnsResizable() has switched
 * both to table-layout:fixed over a shared set of colgroup widths, the body's
 * surplus is shared out across its columns while the header's is not. The
 * result is a header whose column boundaries no longer line up with the rows
 * beneath them. columns.adjust() re-measures and rewrites both.
 */
/**
 * Hand a table's columns back to the browser, so the next columns.adjust() is a
 * real re-measure rather than a re-run of the numbers it already had.
 *
 * makeColumnsResizable() writes explicit px widths into every colgroup and puts
 * table-layout:fixed over the top. Those widths are a floor: a table whose cols
 * add up to 1796px cannot be measured at 1546px, so adjusting into a NARROWER
 * container leaves the table its old width and it simply overflows. (Widening
 * appears to work only because the body table is width:100% and grows anyway --
 * which is the very mismatch this whole path exists to fix.)
 *
 * Nothing is lost by clearing them. The hand-set layout is remembered as
 * per-column SHARES, and the column-sizing event that columns.adjust() fires
 * runs makeColumnsResizable() again, which re-seeds against the new width and
 * re-applies those shares -- so a dragged column keeps its proportion of the
 * table across the resize.
 */
function fogReleaseColWidths(node) {
  fogTableParts(node).tables
    .removeClass('fog-table-fixed')
    .find('colgroup > col').css('width', '');
}
function fogAdjustAllTables() {
  if (!$.fn.dataTable || !$.fn.dataTable.isDataTable) {
    return;
  }
  // Iterate initialized tables via isDataTable() rather than the 1.10-era
  // $.fn.dataTable.tables({api:true}).every() idiom, which throws in the
  // bundled 2.x/3.x build ("tables(...).every is not a function") and silently
  // aborted the entire post-show resize path on every shown.bs.tab.
  $('table.dataTable').each(function() {
    if (!$.fn.dataTable.isDataTable(this)) {
      return;
    }
    var dt = $(this).DataTable(),
      init = (dt && typeof dt.init === 'function') ? dt.init() : null;
    // Null init: a node the table.dataTable selector matches but that isn't a
    // table of its own (the scrollY cloned header). Nothing to size.
    if (!init) {
      return;
    }
    if (init.scroller) {
      // Height as well as width, and it does its own visibility check -- so
      // only release the widths once we know it will act on them.
      if ($(this).is(':visible')) {
        fogReleaseColWidths(this);
      }
      fogSizeScroller(dt);
      return;
    }
    // A paged table has no scroll body to measure, but it still needs its
    // columns re-adjusted -- and one sitting in a hidden tab measures zero, so
    // adjusting it there would write the zero widths in as fact.
    if (!$(this).is(':visible')) {
      return;
    }
    fogReleaseColWidths(this);
    dt.columns.adjust();
  });
}
function fogBindTableAutosize() {
  if ($.fn.dataTable.__fogScrollerBound) {
    return; // window/tab/observer handlers only need binding once per page
  }
  $.fn.dataTable.__fogScrollerBound = true;
  var debounce;
  function adjustSoon() {
    clearTimeout(debounce);
    debounce = setTimeout(fogAdjustAllTables, 150);
  }
  $(window).on('resize.fogScroller', adjustSoon);
  // The sidebar is the other thing that changes a table's width, and it does it
  // without a window resize: AdminLTE's push-menu toggle only adds/removes
  // body.sidebar-collapse, and the content area follows via a CSS transition.
  // So watch the content box itself rather than the window -- that covers the
  // toggle, AL4's own responsive collapse at the sidebar breakpoint, and any
  // other layout change that moves the edge, without this code having to know
  // about any of them. Observing the container (whose width comes from the
  // layout, not from what we write inside it) plus the debounce above means an
  // adjust cannot feed itself: the transition's intermediate widths coalesce
  // into one pass at the settled width.
  var main = document.querySelector('.app-main');
  if (main && typeof ResizeObserver === 'function') {
    new ResizeObserver(adjustSoon).observe(main);
  }
  // In-tab tables (edit pages) measure as zero-height while hidden; size them
  // once their tab is shown. Defer a tick: inside shown.bs.tab the revealed
  // tab's layout isn't final, so a synchronous columns.adjust() sizes against
  // a stale (~zero) width and leaves the header/body split misaligned until
  // the next redraw. One macrotask later the layout is settled.
  $(document).on('shown.bs.tab.fogScroller', function () {
    setTimeout(fogAdjustAllTables, 0);
  });
}
// DataTables' default errMode alerts "DataTables warning: table id=X - Ajax
// error" and then throws away the only thing that would explain it. That text
// names neither the status nor the reason, so a report of it arrives with
// nothing to act on -- and the server's own answer (a 404 from
// FOGPage::objectNotFound, a 406 carrying a SQLSTATE, a 403, a proxy timeout)
// is discarded by the browser before anybody reads it. Two separate bug
// reports have now been narrowed by hand purely because this alert says
// nothing.
//
// The replacement shows what the server actually said and logs the whole
// untruncated response to the console, so the next report carries its own
// diagnosis. Only the Ajax half is changed: a DataTables error with no request
// behind it (a column-count mismatch, say -- see registerExportTable) still
// reports its original message.
if ($.fn.dataTable) {
  $.fn.dataTable.ext.errMode = function(settings, helpPage, message) {
    var xhr = settings ? settings.jqXHR : null,
      tableId = (settings && settings.sTableId) ? settings.sTableId : 'table',
      detail = '';

    if (xhr) {
      if (xhr.responseJSON && xhr.responseJSON.error) {
        detail = xhr.responseJSON.error;
      } else if (xhr.responseText) {
        // Truncated for the toast only; the console below keeps all of it. An
        // HTML error page is worth showing the first line of -- it is usually
        // the one that names the failure.
        detail = $.trim(xhr.responseText).substring(0, 300);
      }
      detail = 'HTTP ' + xhr.status
        + (detail ? ' - ' + detail : ' (empty response body)');
    }

    if (window.console && console.error) {
      console.error('FOG: table "' + tableId + '" failed to load', {
        dataTablesMessage: message,
        status: xhr ? xhr.status : null,
        response: xhr ? (xhr.responseJSON || xhr.responseText) : null
      });
    }

    $.notify(tableId, detail || message, 'error');
  };
}
$.fn.registerTable = function(onSelect, opts) {
  opts = opts || {};

  // Default row count comes from FOG_VIEW_DEFAULT_SCREEN (hidden #pageLength).
  var pageLength = parseInt($('#pageLength').val());

  // Paging style is admin-selectable via FOG_TABLE_SCROLL_MODE (hidden
  // #scrollMode). Default is infinite (virtual-scroll) when unset.
  //
  // Two things force classic paging regardless of that setting:
  //  - rowGroup: grouped tables inject category header rows that Scroller's
  //    virtual row-height math can't reconcile, so any table using rowGroup is
  //    auto-paged (no per-table flag needed).
  //  - scroller:false: an explicit per-table opt-out for any other reason.
  //
  // In-tab edit tables (MACs, snapins, printers, history, ...) are hidden at
  // init, where Scroller's scrollY measures a display:none table as zero width
  // and the split header/body columns start misaligned. They still use infinite
  // scroll for UI consistency with the top-level lists: the shown.bs.tab handler
  // in fogBindTableAutosize() re-measures (scroller.measure) and re-syncs the
  // columns (columns.adjust) once the tab is visible, which is the first moment
  // the real widths exist. Selection/association is unaffected by deferRender —
  // checkbox toggles POST per-row immediately ($.checkItemUpdate) and bulk
  // actions read the DataTables API (rows({selected:true})), never the DOM.
  var infiniteScroll =
    (opts.scroller !== false) &&
    !opts.rowGroup &&
    (($('#scrollMode').val() || 'infinite').toLowerCase() !== 'paged');

  var defaults = {
    paging: true,
    lengthChange: true,
    searching: true,
    ordering: true,
    info: true,
    stateSave: false,
    autoWidth: false,
    responsive: true,
    lengthMenu: [
      [10, 25, 50, 100, 250, 500, -1],
      [10, 25, 50, 100, 250, 500, 'All']
    ],
    pageLength: pageLength,
    buttons: [
      {
        extend: 'selectAll',
        text: '<i class="far fa-square-check"></i> Select All'
      },
      {
        extend: 'selectNone',
        text: '<i class="far fa-square"></i> Deselect All'
      },
      searchBuilderButton,
      {
        text: '<i class="fas fa-arrows-rotate"></i> Refresh',
        action: function(e, dt, node, config) {
          dt.clear().draw();
          dt.ajax.reload();
        }
      }
    ],
    pagingType: 'simple_numbers',
    select: {
      style: 'multi+shift'
    },
    dom: "<'row'<'col-sm-6'l><'col-sm-6'f>>B<'row'<'col-sm-12'tr>><'row'<'col-sm-5'i><'col-sm-7'p>>",
    retrieve: true,
    // Bootstrap tooltips bind to elements present at init time; rows drawn by
    // DataTables (incl. Scroller redraws) arrive later, so re-init any tooltip
    // markup (e.g. the MAC vendor icon) within the table on every draw.
    drawCallback: function () {
      try {
        $(this.api().table().node())
          .find('[data-bs-toggle="tooltip"]')
          .tooltip();
      } catch (e) {}
    }
  };

  if (infiniteScroll) {
    // Virtual-scroll: rows load in chunks as you scroll, replacing the
    // page-number bar and length menu. scrollCollapse keeps small tables
    // (e.g. association lists in edit views) from showing an empty viewport.
    // Scroller needs a finite chunk size, so fall back when pageLength is
    // "All" (-1) or unset.
    if (!pageLength || pageLength < 1) {
      defaults.pageLength = 50;
    }
    defaults.scroller = true;
    defaults.deferRender = true;
    defaults.scrollY = '55vh';
    defaults.scrollCollapse = true;
    defaults.lengthChange = false;
    defaults.dom = "<'row'<'col-sm-6'><'col-sm-6'f>>B<'row'<'col-sm-12'tr>><'row'<'col-sm-12'i>>";
  }

  // Column resizing is on for every table. Pulled off opts before they reach
  // DataTables, which has no such option and would only carry it around.
  var columnResize = opts.columnResize !== false;
  delete opts.columnResize;

  if (infiniteScroll) {
    // Clip overlong cells to an ellipsis rather than letting them run on.
    //
    // Scoped to scrolling tables on purpose. Scroller sizes its virtual
    // viewport from a UNIFORM row height, so rows there have to stay one line
    // -- and DataTables' own scroll CSS already forces white-space:nowrap for
    // that reason, which today means long text simply overflows its column.
    // Clipping is what turns that into something readable. A paged or grouped
    // table has no such constraint and keeps wrapping, which is the better
    // behavior when rows are allowed to be tall.
    $(this).addClass('fog-table-clip');
  }

  opts = $.fogDefaults(opts, defaults);

  // Teach the Filter button what each column IS, and hide from it the columns
  // it could never filter.
  //
  // Both facts have to come from the server. A server-side grid hands the
  // browser ONE PAGE, so DataTables' own type sniffing only ever sees the rows
  // that page happens to hold -- a datetime column that is empty on page one
  // sniffs as text and loses its calendar and its before/after conditions, and
  // which columns those are changes with the sort. And some displayed columns
  // are computed by the query rather than selected from a table (a group's
  // member count, a site's four counts: 'removeFromQuery'), so a rule against
  // one is dropped server-side -- better to leave it out of the picker than to
  // offer a filter that silently does nothing. The server answers both in
  // _searchtypes, keyed by column name, with false for "not searchable".
  //
  // Bound to xhr rather than applied at init because the payload is the first
  // moment either fact exists. SearchBuilder reads them when the user adds a
  // rule, which cannot happen before the table has drawn. A client-side table
  // sends no _searchtypes and is left to DataTables' sniffing, which is
  // reliable there because it has every row.
  //
  // Bound BEFORE the table is constructed, on the node rather than through
  // the API, because a table whose ajax answers synchronously fires its first
  // xhr inside the DataTable() call -- so a handler added afterward misses
  // the only response that ever carries these facts and silently does
  // nothing.
  // Namespaced 'xhr.dt.fogsb', not 'xhr.fogsb': DataTables fires its events
  // as 'xhr.dt', and jQuery only runs a handler whose namespaces cover the
  // ones triggered -- so a plain '.fogsb' handler is never called at all, and
  // silently so. The extra name is what lets the off() below remove this one
  // handler when registerTable() is re-run over a retrieved table.
  $(this).off('xhr.dt.fogsb').on('xhr.dt.fogsb', function(e, settings, json) {
    if (!json || !json._searchtypes) {
      return;
    }
    var types = json._searchtypes,
      columns = settings.aoColumns,
      searchable = [],
      i,
      key;
    for (i = 0; i < columns.length; i++) {
      key = columns[i].data;
      // Absent from the map means the column is not one of the server's at
      // all -- a checkbox or an action column - and false means it is one
      // the server refuses to match on.
      if (!(key in types) || types[key] === false) {
        continue;
      }
      columns[i].searchBuilderType = types[key];
      searchable.push(i);
    }
    if (settings._searchBuilder) {
      // searchBuilder.columns is a normal option; it is only being written
      // late because what belongs in it arrives with the first response.
      // SearchBuilder re-reads it every time it draws a rule's column list.
      settings._searchBuilder.c.columns = searchable;
    }
  });

  var table = $(this).DataTable(opts);

  if (columnResize) {
    var tableNode = $(this);
    // Bound to column-sizing rather than draw: DataTables rebuilds the header
    // (and, in scroll mode, re-clones it) whenever it recalculates widths --
    // an ajax load, a tab becoming visible, a Responsive collapse -- and the
    // strips go with it. draw fires on every Scroller redraw, which would mean
    // re-measuring the header on every scroll tick for nothing.
    table.on('column-sizing.dt', function() {
      tableNode.makeColumnsResizable();
    });
    // First pass deferred: at this point an ajax table has not drawn yet and
    // the scroll header clone may not exist.
    setTimeout(function() {
      tableNode.makeColumnsResizable();
    }, 0);
  }

  // Keep every table sized to its container on resize, sidebar toggle and tab
  // show. Bound for paged tables too, not just Scroller ones: the header/body
  // split that goes out of alignment is created by scrollX/scrollY, but a paged
  // table still carries the fixed colgroup widths makeColumnsResizable() wrote
  // at the old container width and needs the same re-adjust.
  fogBindTableAutosize();
  if (infiniteScroll) {
    // Size the scroll body to fill the available height now, deferred so the
    // table is laid out in the DOM first.
    setTimeout(function() { fogSizeScroller(table); }, 0);
  }

  if (onSelect !== undefined && typeof(onSelect) === 'function') {
    table.on('select deselect', function( e, dt, type, indexes) {
      onSelect(dt.rows({selected: true}));
    });
  }

  return table;
};
/**
 * Build an export-list DataTable.
 *
 * Every *.export.js page built the same server-side, non-selectable table off
 * the shared exportButtons and the getExportList endpoint -- only the table's
 * column list and default sort ever differed. This owns that shared envelope so
 * each page is a single call: pass the columns (mark hidden ones with
 * visible:false, as DataTables columns support directly) and, optionally, a
 * non-default sort. The Common.search deep-link is wired the same way here that
 * every page wired it by hand.
 *
 * @param {Array}  columns DataTables column defs ({data:'x'[, visible:false]})
 * @param {Object} opts    optional overrides: {order}
 * @return {DataTable}
 */
/**
 * Register an export table.
 *
 * The columns array is POSITIONAL against the <th> row, which FOGPage::export()
 * builds from the class's $databaseFields (plus 'primac' for hosts and a
 * trailing 'associations' where getAssociationConfig() supplies one). So every
 * field must appear here, in that same order -- DataTables walks each <th>,
 * looks up aoColumns[i], and raises error 18 "Incorrect column count" when one
 * is missing. Under the default errMode that is an alert the user dismisses
 * before the page continues, so a field added to $databaseFields without a
 * matching entry here shows up as a popup on the export page, not a silent
 * omission. Add the column (visible: false is fine) whenever a field is added.
 */
$.fn.registerExportTable = function(columns, opts) {
  opts = opts || {};
  // Aisle 029: export tables render raw DB columns, several of which are
  // attacker-writable through unauthenticated surfaces (productKey via the iPXE
  // keyset path, the inventory fields, etc), and DataTables writes cell data as
  // HTML by default. Escaping here covers productKey plus the ~30 other raw
  // columns and every *.export.js page at once, instead of hand-patching one.
  // The t === 'display' guard is load-bearing: the Buttons CSV/copy exports ask
  // for other types, and escaping those would put &amp;/&lt; into exported files
  // and break import round-tripping. A column that intentionally emits markup
  // opts out simply by supplying its own render.
  columns = (columns || []).map(function(col) {
    if (!col || col.render !== undefined) {
      return col;
    }
    return $.extend({}, col, {
      render: function(d, t) {
        return t === 'display' ? $.escapeHtml(d) : d;
      }
    });
  });
  var table = this.registerTable(null, {
    buttons: exportButtons,
    order: opts.order || [[0, 'asc']],
    columns: columns,
    rowId: 'id',
    processing: true,
    serverSide: true,
    select: false,
    ajax: {
      url: '../management/index.php?node=' + Common.node + '&sub=getExportList',
      type: 'post'
    }
  });
  if (Common.search && Common.search.length > 0) {
    table.search(Common.search).draw();
  }
  return table;
};
/**
 * Register a plugin report table.
 *
 * Mirror of registerExportTable for the Reports node: same serverSide plumbing
 * and column contract, and the data comes from the report's own getList() via
 * node=report&sub=getList&f=<report>, keyed off Common.f. Every plugin report
 * JS calls this so the tables stay identical across plugins.
 *
 * THE FULL EXPORT IS OPT-IN, via opts.fullExport. "CSV (All)" posts to
 * sub=exportAll, which serves ReportManagement::reportRows() -- so a report
 * that still overrides getList() the old way would answer the button with an
 * empty file rather than an error. Defaulting it on would hand that to every
 * third-party plugin report at once; a report that has been converted asks
 * for it, and gets a CSV of the whole table instead of the page the browser
 * happens to be holding.
 *
 * @param {Array}  columns DataTables column defs ({data:'name'}, ...).
 * @param {Object} opts    Optional overrides (order, fullExport).
 * @return {Object} the DataTables API for the registered table.
 */
$.fn.registerReportTable = function(columns, opts) {
  opts = opts || {};
  var table = this.registerTable(null, {
    buttons: opts.fullExport ? reportFileButtons : reportButtons,
    order: opts.order || [[0, 'asc']],
    columns: columns,
    rowId: 'id',
    processing: true,
    serverSide: true,
    select: false,
    ajax: {
      url: '../management/index.php?node=report&sub=getList&f=' + Common.f,
      type: 'post'
    }
  });
  if (Common.search && Common.search.length > 0) {
    table.search(Common.search).draw();
  }
  return table;
};
/**
 * Build the compact hardware-vendor (OUI) icon shown next to a MAC address.
 *
 * Kept global so the per-table DataTables renders and the live input binder
 * all emit identical markup. Returns '' when the vendor is unknown, so
 * unresolved MACs stay uncluttered; the vendor name rides in the tooltip.
 *
 * @param {string} vendor resolved vendor name (server-side from the oui table)
 * @return {string} icon HTML, or '' when no vendor
 */
function macVendorIcon(vendor) {
  if (!vendor) {
    return '';
  }
  var esc = $('<div>').text(vendor).html().replace(/"/g, '&quot;');
  // fa-info-circle: a circular "info" glyph, chosen for visual consistency
  // with fog-node. It is a low-codepoint (FA 1.x) icon, so it renders from any
  // FontAwesome 4 webfont, including stale browser caches; newer glyphs like
  // fa-microchip (4.7-only) show as tofu when an older font is cached.
  // container=body keeps the tooltip from being clipped by the DataTables
  // scroll body (infinite-scroll) and from rendering under the sticky header;
  // placement=right clears the header above the first row.
  return ' <i class="fas fa-circle-info text-muted mac-vendor-icon" '
    + 'data-bs-toggle="tooltip" data-bs-placement="right" data-container="body" '
    + 'title="' + esc + '"></i>';
}
/**
 * Live vendor lookup for MAC inputs on the host create/edit forms.
 *
 * Delegated on document so it covers the create modal (rendered after page
 * load) as well as the edit form. Debounced; only fires once at least a full
 * OUI prefix (6 hex chars) has been typed. The icon is dropped into a sibling
 * span so it never interferes with the input's own value.
 */
(function () {
  var macVendorTimer;
  $(document).on('input change', '.hostmac-input', function () {
    var input = $(this);
    var holder = input.nextAll('.mac-vendor-live').first();
    if (!holder.length) {
      holder = $('<span class="mac-vendor-live"></span>');
      input.after(holder);
    }
    clearTimeout(macVendorTimer);
    if ((input.val() || '').replace(/[^0-9a-fA-F]/g, '').length < 6) {
      holder.empty();
      return;
    }
    macVendorTimer = setTimeout(function () {
      $.get(
        '../management/index.php?node=host&sub=getmacman',
        {prefix: input.val()},
        function (res) {
          holder.html(macVendorIcon(res && res.vendor ? res.vendor : ''));
          holder.find('[data-bs-toggle="tooltip"]').tooltip();
        },
        'json'
      );
    }, 400);
  });
})();
$.fn.setContainerDisable = function(disabled) {
  if(disabled !== false) {
    disabled = true;
  }
  var inputs = $(this).find('input, select, button, .btn, textarea').toArray();
  $.each(inputs, function(index, value) {
    $(value).prop('disabled', disabled);
  });
};
$.fn.setLoading = function(loading) {
  if(loading !== false) {
    loading = true;
  }

  var loadingId = 'loadingOverlay';

  if (loading) {
    $(this).append(
      '<div class="overlay" id="' + loadingId  + '"><i class="fas fa-arrows-rotate fa-spin"></i></div>'
    );
  } else {
    $(this).children('#'+loadingId).remove();;
  }
}
$.fn.validateForm = function(input) {
  if (undefined === input) {
    input = ':input';
  }
  var scrolling = false,
    isError = false,
    form = $(this);
  form.find(input).each(function(i, e) {
    var isValid = true,
      invalidReason = undefined,
      // Grab the parent form-group, as we will need it to visually mark
      //   invalid fields
      parent = $(e).closest('div[class^="form-group"]'),
      required = $(e).prop('required'),
      // inputmask is not part of the slim (unauthenticated) asset set
      val = $.fn.inputmask
        ? $(e).inputmask('unmaskedvalue')
        : String($(e).val() || '');
    if(required) {
      if (val.length == 0) {
        isValid = false;
        invalidReason = 'Field is required';
      }
    }

    if (required || val.length > 0) {
      var minLength = $(e).attr("minlength") || "-1",
        maxLength = $(e).attr("maxlength") || "-1",
        exactLength = $(e).attr("exactlength") || "-1";

      minLength = parseInt(minLength);
      // NOT halved. This used to read `parseInt(maxLength) / 2`, which was
      // wrong in both directions and only ever reached the user as a
      // message, because no maximum is actually enforced here:
      //
      //   no maxlength at all -> makeInput() omits the attribute, the
      //     default "-1" halves to -0.5, and the password field on Create
      //     New User told you it "must be between 4 and -0.5 characters"
      //   a real maxlength    -> it was halved, so the username field
      //     claimed a limit of 25 when makeInput() had been given 50
      maxLength = parseInt(maxLength);
      exactLength = parseInt(exactLength);

      if (beEqualTo) beEqualTo = "#" + beEqualTo;

      if (beRegexTo) beRegexTo = '#' + beRegexTo;

      if (val.length < minLength) {
        isValid = false;
        if (maxLength < 0) {
          // No upper bound was declared, so do not invent one. The old
          // message named a range whose top half was meaningless.
          invalidReason = 'Field must be at least ' + minLength + ' characters';
        } else if (maxLength == minLength) {
          invalidReason = 'Field must be ' + minLength + ' characters';
        } else {
          invalidReason = 'Field must be between ' + minLength + ' and ' + maxLength +' characters';
        }
      } else if (exactLength > 0) {
        if (val.length !== exactLength) {
          isValid = false;
          invalidReason = 'Field is incomplete';
        }
      }
    }

    equalCheck: if (isValid) {
      var beEqualTo = $(e).attr("beEqualTo");
      if (!beEqualTo) break equalCheck;

      if (! $("#" + beEqualTo).length) {
        $.debugLog("Missing target " + beEqualTo + " for " + e);
        break equalCheck;
      }
      var target = $("#" + beEqualTo);
      if ($(e).val() !== target.val()) {
        isValid = false;
        invalidReason = 'Field does not match';
      }
    }

    regexCheck: if (isValid) {
      var beRegexTo = $(e).attr('beRegexTo'),
        regexID = $(e).attr('id'),
        helpMsg = $(e).attr('requirements'),
        localstr = $(e).val(),
        regex = new RegExp(beRegexTo);
      if (!regexID) break regexCheck;
      if (!$('#'+regexID).length) {
        $.debugLog('Missing target ' + regexID + ' for ' + e);
        break regexCheck;
      }
      if (!regex.test(localstr)) {
        isValid = false;
        invalidReason = 'Does not meet the requirements.';
        if (helpMsg) {
          invalidReason += ' ' + helpMsg;
        }
      }
    }

    if ($(e).hasClass('is-invalid')) {
      var possibleHelpblock = $(e).next('span');
      if (possibleHelpblock.hasClass('invalid-feedback')) {
        possibleHelpblock.remove();
      }
      if (isValid) {
        $(e).removeClass('is-invalid');
      }
    } else if (!isValid) {
      $(e).addClass('is-invalid');
    }

    if (isValid) {
      return;
    }

    if (!scrolling) {
      // formFields() wraps rows in ".row mb-3", not a "form-group" div, so
      // parent can be empty. Fall back to the field itself, and skip the
      // scroll entirely if neither has an offset (e.g. inside a modal) so a
      // missing offset can't crash validation and wedge the form.
      var scrollTarget = parent.length ? parent : $(e),
        scrollOffset = scrollTarget.offset();
      if (scrollOffset) {
        scrolling = true;
        $('html, body').animate({
          scrollTop: scrollOffset.top
        }, 200);
      }
    }

    var msgBlock = '<span class="invalid-feedback">' + invalidReason + '</span>'
    $(msgBlock).insertAfter(e)
    isError = true;
  });

  return !isError;
};
// URL Variables. AKA GET variables.

function reinitialize() {
  if (typeof NodeList.prototype.forEach !== 'function') {
    NodeList.prototype.forEach = Array.prototype.forEach;
  }
  $_GET = getQueryParams();
  shouldReAuth = ($('#reAuthDelete').val() == '1') ? true : false;
  reAuthModal = $('#deleteModal');
  deleteConfirmButton = $('#confirmDeleteModal');
  deleteLang = deleteConfirmButton.text();
  Common = {
    node: $_GET['node'],
    sub: $_GET['sub'],
    id: $_GET['id'],
    tab: $_GET['tab'],
    type: $_GET['type'],
    f: $_GET['f'],
    debug: $_GET['debug'],
    search: $_GET['search'],
    masks: {
      mac: "##:##:##:##:##:##",
      productKey: "*****-*****-*****-*****-*****",
      hostname: ""
    }
  };
  var pluginOptionsOpen = true,
    pluginOptionsAlt = $('.plugin-options-alternate');

  // Animate the plugin items. reinitialize() runs on every AJAX nav and
  // .plugin-options-alternate lives in the persistent chrome (never torn
  // down), so clear any prior handler first to avoid stacking one per nav.
  pluginOptionsAlt.off('click').on('click', function(event) {
    event.preventDefault();
    var whenDone = function() {
      $(window).resize();
    };
    if (pluginOptionsOpen) {
      $('.plugin-options').slideUp('fast', whenDone);
      $('.plugin-options-alternate .fa')
        .removeClass('fa-minus')
        .addClass('fa-plus');
    }
    if (!pluginOptionsOpen) {
      $('.plugin-options').slideDown('fast', whenDone);
      $('.plugin-options-alternate .fa')
        .removeClass('fa-plus')
        .addClass('fa-minus');
    }
    pluginOptionsOpen = !pluginOptionsOpen;
  });
  Common.iCheck = function(match) {
    match = match || 'input';
    // iCheck retired: apply native Bootstrap 5 form-check styling to
    // checkboxes/radios. Re-run after table redraws to re-style new rows.
    $(match).filter(':checkbox, :radio').addClass('form-check-input');
  };

  Common.createModalShow = function() {
    var form = $(this).find('#create-form'),
      btn = $('#send');
    form[0].reset();
    $(':input:first', this).trigger('focus');
    $(':input:not(textarea)', this).on('keypress', function(e) {
      if (e.which == 13) {
        btn.trigger('click');
      }
    });
  };

  Common.createModalHide = function() {
    // Find the form
    var form = $(this).find('#create-form');
    // Remove the errors if any.
    form.find('.is-invalid').removeClass('is-invalid');
    form.find('span.invalid-feedback').remove();
    // Unbind the keypress event.
    $(':input:not(textarea)', this).off('keypress');
  };

  $.debugLog("=== DEBUG LOGGING ENABLED ===");
  setupIntegrations();
  if ($.fn.inputmask) {
    $(":input").inputmask(); // Setup all input masks
  }
  Common.iCheck(); // Setup all checkboxes
  patchSelect2SearchId(); // Must run before any .select2() init below.
  // Setup all select elements. Anchor the dropdown to its closest modal when
  // inside one: Bootstrap 5 modals add a focus-trap and their own stacking
  // context, so a Select2 dropdown appended to <body> (the default) renders
  // detached behind/below the modal and its options can't be clicked. Pinning
  // dropdownParent to the .modal keeps the dropdown inside that context.
  $('.fog-select2').each(function() {
    var $sel = $(this),
      $modal = $sel.closest('.modal');
    $sel.select2({
      width: '100%',
      dropdownParent: $modal.length ? $modal : $(document.body)
    });
  });
  disableFormDefaults();
  wireImportForm();
  setupPasswordReveal();
  setupUniversalSearch();
  setupInfoCard();
};

/**
 * Keep the edit page's info card in step with the form under it.
 *
 * The card summarizes the record you are editing, and it used to be rendered
 * once and left alone -- so changing Max Clients on the General tab left the
 * card still showing the old number until a full page reload. It is sticky, so
 * that stale number follows you down the page.
 *
 * The mapping is declared server side (FOGPage::$noteSources) and arrives as
 * data-note-src on the note's value div, so nothing here is page-specific and
 * any edit page gets this by declaring the mapping. A note with no data-note-src
 * is left exactly as the server drew it, which is what pages want for values no
 * control on the page can change.
 *
 * Deliberately no initial repaint: until you touch a control, the server's
 * value is the truthful one. Painting on load would let a client-side reading
 * of the control quietly replace a value the server had normalized (the image
 * path, for one, comes back with its trailing slash trimmed).
 */
function setupInfoCard() {
  var card = $('#edit-info-card');
  if (!card.length) {
    return;
  }
  card.find('[data-note-src]').each(function() {
    var note = $(this),
      src = $(note.data('note-src'));
    if (!src.length) {
      return;
    }
    function read() {
      if (src.is(':checkbox')) {
        return src.prop('checked') ?
          note.data('note-on') :
          note.data('note-off');
      }
      if (src.is('select')) {
        // The pickers append " - (id)" to an option's visible text so two
        // same-named items can be told apart; data-label is the bare name,
        // which is what the server rendered into the card. Fall back to the
        // text for a hand-built select that carries no data-label.
        var opt = src.find('option:selected');
        return opt.data('label') !== undefined ? opt.data('label') : opt.text();
      }
      return src.val();
    }
    // The card is torn down and rebuilt with the page on every AJAX nav, but
    // the CONTROL may not be (a modal-injected form reuses ids), so namespace
    // the binding and clear it first rather than stacking one per visit.
    src.off('.fogInfoCard').on('input.fogInfoCard change.fogInfoCard',
      function() {
        var value = read();
        value = (value === undefined || value === null) ? '' : String(value);
        value = value.trim();
        if (value === '') {
          // Same em dash the server draws for an empty note, so clearing a
          // field looks like an empty value rather than a broken card.
          note.html('<span class="text-muted">&mdash;</span>');
          return;
        }
        note.text(value);
      });
  });
}

// Select2 builds its search inputs with neither id/name nor a label, tripping
// the browser's "form field should have an id or name" autofill advisory and the
// "no label associated with a form field" accessibility advisory. There are two
// such inputs: the dropdown search box (single selects) and the always-present
// inline search box of `multiple` selects -- rendered by two different adapters
// (select2/dropdown/search and select2/selection/search). The audits fire when
// the element is inserted into the DOM (inside Select2's render routines), so
// stamping attributes afterward on select2:open is too late, and the inline
// field never fires open at all. Instead, decorate each adapter's render() so an
// id and aria-label are present at creation time. Deliberately an id, NOT a name
// -- the field sits inside FOG forms and a name would POST a stray value.
// Idempotent: patches the shared adapter prototypes once, so it must run before
// any .select2().
function patchSelect2SearchId() {
  if (!$.fn.select2 || !$.fn.select2.amd) {
    return;
  }
  var seq = 0;
  var decorate = function(Adapter, kind) {
    if (!Adapter || Adapter.__fogIdPatched) {
      return;
    }
    var origRender = Adapter.prototype.render;
    Adapter.prototype.render = function() {
      var $rendered = origRender.apply(this, arguments);
      if (this.$search) {
        if (!this.$search.attr('id')) {
          // data-select2-id is unique per instance; fall back to a counter.
          var base = (this.$element && this.$element.attr('data-select2-id')) || (++seq);
          this.$search.attr('id', 'select2-search--' + kind + '-' + base);
        }
        if (!this.$search.attr('aria-label')) {
          var label = (this.$element
              && (this.$element.attr('aria-label') || this.$element.attr('title')))
            || this.$search.attr('placeholder')
            || 'Search';
          this.$search.attr('aria-label', label);
        }
      }
      return $rendered;
    };
    Adapter.__fogIdPatched = true;
  };
  // Use the single-string require form, which resolves synchronously and returns
  // the module. The array+callback form defers via setTimeout, so the decoration
  // would land AFTER the .select2() calls below have already captured the
  // original render() -- making the patch a no-op.
  try {
    decorate($.fn.select2.amd.require('select2/dropdown/search'), 'dropdown');
    decorate($.fn.select2.amd.require('select2/selection/search'), 'inline');
  } catch (e) {}
}
function setupIntegrations() {
  Pace.options = {
    ajax: {
      trackMethods: ['GET', 'POST', 'DELETE', 'PUT', 'PATCH']
    },
    restartOnRequestAfter: false
  };
  // Extending input mask to add our types (absent on the slim asset set)
  if ($.inputmask) {
    $.extend($.inputmask.defaults.definitions, {
      '#': {
        validator: "[A-Fa-f0-9]",
        cardinality: 1
      }
    });
  }
}

function setupUniversalSearch() {
  var uniSearchForm = $('#universal-search-form');
  if (!uniSearchForm.length)
    return;

  var resultLimit = 5;

  var uniSearchField = $('#universal-search-select');
  var baseURL = uniSearchForm.attr('action');
  var method = uniSearchForm.attr('method');

  uniSearchField.on('select2:selecting', function(e) {
    e.preventDefault();
    var url = e.params.args.data.url;
    uniSearchField.prop('disabled', true);
    window.location.href = url;
  });

  uniSearchField.select2({
    width: '100%',
    dropdownAutoWidth: true,
    minimumInputLength: 1,
    multiple: true,
    maximumSelectionSize: 1,
    ajax: {
      delay: 250,
      url: function(params)  {
        return baseURL + '/' + params.term + '/' + resultLimit;
      },
      type: method,
      dataType: 'json',
      cache: false,
      processResults: function (data) {
        var results = [];

        var lang = data._lang;
        var id = 0;
        for (var key in data) {
          if (!data.hasOwnProperty(key)) continue;
          if (key.startsWith("_")) continue;

          var obj = data[key];
          if (obj.length == 0) continue;
          var objData = [];

          for (var i = 0; i < obj.length; i++) {
            var item = obj[i];
            objData.push({
              id: id,
              text: item.name,
              url: '../management/index.php?node='
              + (
                key != 'setting' ?
                key + '&sub=edit&id=' + item.id :
                'about&sub=settings&search=' + item.name
              )
            });
          }
          objData.push({
            id: id,
            text: "--> " + lang.AllResults,
            url: '../management/index.php?node='
            + (
              key != 'setting' ?
              key + '&sub=list&search=' :
              'about&sub=settings&search='
            )
            + data._query
          });

          results.push({
            text: $.capitalizeFirstLetter(lang[key]),
            children: objData
          });
        }
        return {
          results: results
        };
      }
    }
  });
}

function setupPasswordReveal() {
  $(':password')
    .not('.fakes, [name="upass"]')
    .before('<span class="input-group-text"><i class="far fa-eye-slash fogpasswordeye"></i></span>');
  // These are delegated on `document`, which survives AJAX page swaps, while
  // reinitialize() (and thus this function) runs again on every AJAX page
  // load. Namespace and remove them first so they don't accumulate -- two
  // stacked click handlers would toggle the field password->text->password and
  // appear to do nothing.
  $(document)
    .off('click.fogReveal change.fogReveal mouseover.fogReveal')
    .on('click.fogReveal', '.fogpasswordeye', function(e) {
    e.preventDefault();
    if (0 == $('.showpass').val()) {
      return;
    }
    if (!$(this).hasClass('clicked')) {
      $(this)
        .addClass('clicked')
        .removeClass('fa-eye-slash')
        .addClass('fa-eye')
        .closest('.input-group')
        .find('input[type="password"]')
        .prop('type', 'text');
    } else {
      $(this)
        .removeClass('clicked')
        .addClass('fa-eye-slash')
        .removeClass('fa-eye')
        .closest('.input-group')
        .find('input[type="text"]')
        .prop('type', 'password');
    }
  }).on('change.fogReveal', ':file', function() {
    var input = $(this),
      numFiles = input.get(0).files ? input.get(0).files.length : 1,
      label = input
      .val()
      .replace(/\\/g, '/')
      .replace(/.*\//, '');
    input.trigger('fileselect', [numFiles, label]);
    /**
     * If only one file display the value in the text field.
     * Otherwise show the number of files selected.
     */
    if (numFiles == 1) {
      $('.filedisp').val(label);
    } else {
      $('.filedisp').val(numFiles + ' files selected');
    }
  }).on('mouseover.fogReveal', function() {
    if ($.fn.tooltip) {
      $('[data-bs-toggle="tooltip"]').tooltip({
        container: 'body'
      });
    }
  });
}

function disableFormDefaults() {
  var forms = document.querySelectorAll('form');
  forms.forEach(function(form) {
    $(form).on('submit',function(e) {
      e.preventDefault();
    });
  });
}

/**
 * Wire the CSV import form.
 *
 * Every node's import page renders the same #import-form / #import-send pair and
 * nine byte-identical *.import.js files wired it the same way: disable the send
 * button, processForm(), re-enable on completion. That wiring lives here now and
 * self-activates whenever an import form is present. disableFormDefaults() (run
 * just above in reinitialize) already prevents the form's native submit, so the
 * per-file submit->preventDefault bind is dropped as redundant. Namespaced and
 * rebound each reinitialize() so it never stacks across AJAX navs.
 */
function wireImportForm() {
  var importForm = $('#import-form'),
    importFormBtn = $('#import-send');
  if (!importForm.length || !importFormBtn.length) {
    return;
  }
  importFormBtn.off('click.fogImport').on('click.fogImport', function() {
    importFormBtn.prop('disabled', true);
    importForm.processForm(function(err) {
      importFormBtn.prop('disabled', false);
    });
  });
}

/**
 * Gets the GET params from the URL.
 */
function getQueryParams(qs) {
  var a = document.createElement('a'),
    params = {},
    tokens,
    re = /[?&]?([^=]+)=([^&]*)/g;
  a.href = (qs || document.location.href);
  qs = a.search
  qs = qs.replace(/\+/g, ' ');
  while (tokens = re.exec(qs)) {
    params[decodeURIComponent(tokens[1])] = decodeURIComponent(tokens[2]);
  }
  return params;
}

/***** AJAX PAGE LOADING *****/
var AJAX_PAGE_LOADING_ENABLED = true;

/**
 * Override jQuery XHR to abort requests before page change.
 */
$.xhrPool = { pool: [] };

$.xhrPool.abortAll = function() {
  $(this.pool).each(function(i, jqXHR) {   //  cycle through list of recorded connection
    jqXHR.abort();  //  aborts connection
    $.xhrPool.pool.splice(i, 1); //  removes from list by index
  });
};

$.ajaxSetup({
  beforeSend: function(jqXHR) { $.xhrPool.pool.push(jqXHR); }, //  annd connection to list
  complete: function(jqXHR) {
    if($.xhrPool == null) return;
    var i = $.xhrPool.pool.indexOf(jqXHR);   //  get index for current connection completed
    if (i > -1) $.xhrPool.pool.splice(i, 1); //  removes from list by index
  }
});

/**
 * Override setInterval (to make sure all intervals can be cleared on page switch.)
 */
var intervals = [];
var realSetInterval = window.setInterval;
window.setInterval = function() {
  var params = Array.prototype.slice.call(arguments),
    handler = params.shift() || null,
    timeout = params.shift() || null;

  var interval = realSetInterval(handler, timeout, params);
  intervals.push(interval);
  return interval;
};

function clearAllIntervals(){
  while(intervals.length > 0){
    clearInterval(intervals.pop());
  }
}

/**
 *  Handle 'ajax-ified' links.
 *  (.ajax-page-link)
 */
(function($){
  if(!AJAX_PAGE_LOADING_ENABLED) return;

  var ajaxPageLoading = false;

  reinitialize();

  window.onpopstate = function(event){
    // Ignore history entries we did not create. Tab navigation (fog.js) pushes
    // a null state, search pushes { path: ... }, and the initial page-load
    // entry has a null state. Only our AJAX page links push { target: ... }.
    if(!event.state || !event.state.target) return;
    var target = event.state.target;
    var targetElement = $(".ajax-page-link[href='" + target + "']");
    doPageLoad(target, targetElement, false);
  };

  // Delegated so links injected after page load (e.g. plugin items swapped
  // into the sidebar on install/activate) still navigate via AJAX.
  $(document).on('click', '.ajax-page-link', function(event){
    event.preventDefault();
    var targetElement = $(this);
    var target = targetElement.attr('href');
    doPageLoad(target, targetElement);
  });

  function doPageLoad(targetPage, targetElement, shouldPushState){
    if (undefined === shouldPushState) {
      shouldPushState = true;
    }

    // Setup the loading page state...
    ajaxPageLoading = true;
    $("#ajaxPageWrapper").setLoading(true);
    $("body").addClass("scroll-lock");
    $("html, body").animate({ scrollTop: 0 }, 300);

    // Prepare to display new page
    clearAllIntervals();
    $.xhrPool.abortAll();

    // AL4 treeview visibility is gated purely by the .menu-open class on the
    // parent .nav-item (CSS shows .menu-open > .nav-treeview). Collapse any open
    // branch that does not contain the target link. AL4's own expand animation
    // leaves an inline "display:block" on the .nav-treeview; removing the class
    // alone would leave that inline style winning over the CSS, so strip it too.
    $(".sidebar-menu .nav-item.menu-open").each(function(){
      if($(this).find(targetElement).length === 0){
        $(this).removeClass('menu-open')
          .children('.nav-treeview').removeAttr('style');
      }
    });

    // Load the page asynchronously.
    $.ajax(targetPage, {
      method: 'GET',
      headers: {
        // Stop FOG backend trying to helpful.
        // (We want HTML, not JSON.)
        'X-Requested-With': 'AjaxPageLink'
      },
      data: { 'contentOnly': true }
    }).done(function(data, status, req){
      var ajaxPageWrapper = $("#ajaxPageWrapper");
      ajaxPageWrapper.empty().html(data);

      // Set new page information
      document.title = req.getResponseHeader('X-FOG-PageTitle');
      if(shouldPushState) history.pushState({ target: targetPage }, document.title, targetPage);

      // Reinitialize, render and display the new page.
      reinitialize();
      renderPage(req);

      // Remove the page loading state.
      ajaxPageWrapper.setLoading(false);
      $("body").removeClass("scroll-lock");

      ajaxPageLoading = false;

      // Update the sidebar. AL4: active highlights the .nav-link; parent
      // branches get .menu-open (which the CSS expands) and their own .nav-link
      // marked active so the open ancestor is visibly highlighted too.
      $(".sidebar-menu .nav-link").removeClass('active');
      targetElement.addClass('active');
      var $branch = targetElement.parents('.nav-item').addClass('menu-open');
      $branch.children('.nav-link').addClass('active');
      // Clear any inline style AL4 left from a prior collapse (display:none)
      // so the CSS .menu-open rule can expand this branch.
      $branch.children('.nav-treeview').removeAttr('style');
    });
  }

  function renderPage(req){
    // Get asset version
    var assetVersion = req.getResponseHeader('X-FOG-BCacheVer');

    /** UPDATE STYLESHEETS **/
    var styles = JSON.parse(req.getResponseHeader('X-FOG-Stylesheets'));
    styles.forEach(function(value, index){
      if(styles[index] == null) { delete styles[index]; return; }
      styles[index] = styles[index] + (styles[index].indexOf("?v") === -1 ? "?ver=" + assetVersion : "");
    });

    // Determine currently loaded stylesheets
    var loadedStyles = [];
    $("link[rel='stylesheet']").each(function(index, element){
      loadedStyles.push($(element).attr('href'));
    });

    // Calculate the style delta:
    var styleDelta = {};
    // -> If a style is loaded that the current page does not need, remove it.
    for(var styleIndex in loadedStyles){
      var style = loadedStyles[styleIndex];
      if(styles.indexOf(style) === -1) styleDelta[style] = -1;
    }
    // -> If a style is not loaded and the current page needs it, add it.
    for(var styleIndex in styles){
      var style = styles[styleIndex] + "?ver=" + assetVersion;
      if(loadedStyles.indexOf(style) === -1) styleDelta[style] = 1;
    }

    // Now act according to the style delta
    Object.keys(styleDelta).forEach(function(key){
      var value = styleDelta[key];
      switch(value){
          // Add script
        case 1:
          $("head").append("<link rel='stylesheet' type='text/css' href='" + key + "' />");
          break;
          // Remove script
        case -1:
          $("link[rel='stylesheet'][href='" + key + "']").remove();
          break;
      }
    });


    /** UPDATE SCRIPTS **/
    var scripts = JSON.parse(req.getResponseHeader('X-FOG-JavaScripts'));
    var commonScripts = JSON.parse(req.getResponseHeader('X-FOG-Common-JavaScripts'));
    // Libraries that must execute at most once per session. Defaults to empty
    // so a response from an older server (or any handler that does not send
    // the header) keeps exactly the previous behavior.
    var onceHeader = req.getResponseHeader('X-FOG-Once-JavaScripts');
    var onceScripts = onceHeader ? JSON.parse(onceHeader) : [];

    scripts.forEach(function(value, index){
      if(scripts[index] == null) { delete scripts[index]; return; }
      scripts[index] = scripts[index] + (scripts[index].indexOf("?v") === -1 ? "?ver=" + assetVersion : "");
    });

    commonScripts.forEach(function(value, index){
      if(commonScripts[index] == null) { delete commonScripts[index]; return; }
      commonScripts[index] = commonScripts[index] + (commonScripts[index].indexOf("?v") === -1 ? "?ver=" + assetVersion : "");
    });

    // Same version suffix as the other two lists, so the comparisons below are
    // against the src actually sitting in the DOM.
    onceScripts.forEach(function(value, index){
      if(onceScripts[index] == null) { delete onceScripts[index]; return; }
      onceScripts[index] = onceScripts[index] + (onceScripts[index].indexOf("?v") === -1 ? "?ver=" + assetVersion : "");
    });

    // Determine the currently loaded scripts.
    var loadedScripts = [];
    $("#scripts").find("script").each(function(index, element){
      loadedScripts.push($(element).attr('src'));
    });

    // Calculate the script delta:
    var scriptDelta = {};
    // -> If a script is loaded and it is neither common to every page nor a
    //    load-once library, remove it.
    for(var scriptIndex in loadedScripts){
      var script = loadedScripts[scriptIndex];
      if (commonScripts.indexOf(script) === -1 && onceScripts.indexOf(script) === -1) scriptDelta[script] = -1;
    }
    // -> Reload all scripts this page needs. Re-executing them is the point:
    //    FOG's page scripts are IIFEs that wire up the DOM when they run, and
    //    the DOM they wired has just been replaced, so skipping this would
    //    leave a revisited page with controls that do nothing.
    //
    //    A load-once library is the exception. It has no side effects at
    //    execution time, so a second copy buys nothing and costs a retained
    //    module graph -- ~3.5MB per re-execution for swagger-ui-bundle.js,
    //    measured with forced GC. Add it only if it is not already there.
    for(var scriptIndex in scripts){
      var script = scripts[scriptIndex];
      if (onceScripts.indexOf(script) !== -1 && loadedScripts.indexOf(script) !== -1) continue;
      scriptDelta[script] = 1;
    }

    // Now act according to the script delta:
    Object.keys(scriptDelta).forEach(function(key){
      var value = scriptDelta[key];
      switch(value){
          // Add script
        case 1:
          // Use a native script element rather than jQuery's .append(), which
          // strips <script> tags and re-runs them through jQuery.globalEval()
          // (an inline eval). That is blocked by Content-Security-Policy
          // script-src 'self'. Appending the element directly loads it as a
          // normal external script. async=false preserves execution order.
          var scriptEl = document.createElement("script");
          scriptEl.src = key;
          scriptEl.type = "text/javascript";
          scriptEl.async = false;
          document.getElementById("scripts").appendChild(scriptEl);
          break;
          // Remove script
        case -1:
          $("script[src='" + key + "']").remove();
          break;
      }
    });
  }
})(jQuery);
