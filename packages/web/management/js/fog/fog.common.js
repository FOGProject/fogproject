/**
 * Renders a multicast session's client count as joined/expected.
 *
 * msClients is -1 or -2 until the first host checks in, which is a sentinel
 * meaning "nobody yet", not a count. Showing it raw made 0-of-30 and
 * 29-of-30 indistinguishable. msSessClients is 0 for sessions created
 * without an expected size, which have no total to count towards.
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
  exportButtons = [
    {
      extend: 'copy',
      text: '<i class="fa fa-copy"></i> Copy'
    },
    {
      text: '<i class="fa fa-file-excel-o"></i> CSV (All)',
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
      text: '<i class="fa fa-file-excel-o"></i> Excel'
    },
    {
      extend: 'print',
      text: '<i class="fa fa-print"></i> Print'
    },
    {
      extend: 'colvis',
      text: '<i class="fa fa-columns"></i> Column Visibility'
    },
    {
      text: '<i class="fa fa-refresh"></i> Refresh',
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
      text: '<i class="fa fa-copy"></i> Copy'
    },
    {
      extend: 'csv',
      text: '<i class="fa fa-file-excel-o"></i> CSV'
    },
    {
      extend: 'excel',
      text: '<i class="fa fa-file-excel-o"></i> Excel'
    },
    {
      extend: 'print',
      text: '<i class="fa fa-print"></i> Print'
    },
    {
      extend: 'colvis',
      text: '<i class="fa fa-columns"></i> Column Visibility'
    },
    {
      text: '<i class="fa fa-refresh"></i> Refresh',
      action: function(e, dt, node, config) {
        dt.clear().draw();
        dt.ajax.reload();
      }
    }
  ],
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
        reAuthModal.finishReAuth();
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
          reAuthModal.finishReAuth();
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
// opts.order       - optional initial sort override (default
//                    [[1,'asc'],[0,'asc']] — associated rows first, then name).
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

  var table = $(tableSel).registerTable(onSelect, {
    order: opts.order || [[1, 'asc'], [0, 'asc']],
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
// $.registerCreateAndAssociate(slug, table) - wire the "Create New X" button
// and modal that renderAssocCreate() adds to an association tab, so the thing
// being associated can be created without leaving the page.
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
//      created entity under `object` (see FOGPagePost::attachCreatedObject),
//      and that id is POSTed to the association tab's update URL, i.e. the
//      same call "Add selected" makes. So neither half is a second code path.
//
// If the create succeeds but no `object` comes back, the association is
// skipped and the user is told: better a half-done step they can see than a
// silent one. The grid is redrawn either way so the new row shows up.
//
// slug  - the association tab slug (e.g. 'host-group'), matching the button.
// table - the tab's DataTable API instance, redrawn after a successful create.
$.registerCreateAndAssociate = function(slug, table) {
  var btn = $('#' + slug + '-create'),
    modal = $('#' + slug + '-createModal'),
    holder = $('#' + slug + '-create-form'),
    sendBtn = $('#' + slug + '-create-send'),
    createNode = btn.data('create-node'),
    assocAction = btn.data('assoc-action'),
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
        // Submit on Enter, matching the list page's create modal.
        holder.find(':input:not(textarea)').on('keypress', function(ev) {
          if (ev.which == 13) {
            ev.preventDefault();
            sendBtn.trigger('click');
          }
        });
        holder.find(':input:first').trigger('focus');
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
      // The create already reported success to the user; only redraw and,
      // when we know what was made, associate it.
      var id = data && data.object ? data.object.id : null;
      if (!id) {
        $.notify(
          'Warning',
          'Created, but it could not be associated automatically. '
            + 'Add it from the list above.',
          'notice'
        );
        if (table) {
          table.draw(false);
        }
        modal.modal('hide');
        return;
      }
      $.apiCall('post', assocAction, {
        confirmadd: 1,
        additems: [id]
      }, function() {
        if (table) {
          table.draw(false);
        }
        modal.modal('hide');
        // Reset only after a clean run, so the next create starts empty.
        if (form[0]) {
          form[0].reset();
        }
      });
    });
  });
};
$.getSelectedIds = function(table) {
  var rows = table.rows({selected: true});
  return rows.ids().toArray();
};
$.notify = function(title, body, type) {
  // De-dupe identical, still-visible notices. Repeated identical actions
  // (clicking a button several times, or several genuine updates in a row)
  // should collapse into the existing toast -- refreshing its auto-hide timer
  // and showing a running count -- instead of piling separate toasts on the
  // stack. Distinct messages still stack normally.
  type = type || 'success';
  var active = ($.notify._active = $.notify._active || {});
  var key = type + ' ' + (title || '') + ' ' + (body || '');
  var existing = active[key];
  if (existing && existing.state !== 'closed' && existing.state !== 'closing') {
    existing._fogCount = (existing._fogCount || 1) + 1;
    existing.update({title: (title || '') + ' (×' + existing._fogCount + ')'});
    existing.queueRemove(); // restart the auto-hide countdown
    return existing;
  }
  // Prune references to notices that have since closed so the map can't grow
  // unbounded across a long-lived (AJAX-navigated) page.
  for (var k in active) {
    if (active[k].state === 'closed') {
      delete active[k];
    }
  }
  var notice = new PNotify({
    title: title,
    text: body,
    type: type
  });
  notice._fogCount = 1;
  active[key] = notice;
  return notice;
};
$.notifyFromAPI = function(res, isError) {
  if (res === undefined) {
    typemsg = "msg";
    res = {
      title: 'Generic ' + (isError ? 'Error' : 'Message'),
    };
    if (isError) {
      res.error = isError ? isError.statusText : 'Unknown issue';
    } else {
      res.msg = 'No message';
    }
  }
  var title = res.title,
    type = 'success';
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
$.reAuth = function(count, cb) {
  deleteConfirmButton.text(deleteLang.replace('{0}', count).replace('{node}', Common.node + (count != 1 ? 's' : '')));
  // enable all buttons / focus on the input box incase
  //   the modal is already being shown
  reAuthModal.setContainerDisable(false);
  $("#deletePassword").trigger('focus');
  reAuthModal.registerModal(
    // On show
    function(e) {
      $("#deletePassword").val('');
      $("#deletePassword").trigger('focus');
      reAuthModal.setContainerDisable(false);
    },
    // On close
    function(e) {
      $("#deletePassword").val('');
      cb('authClose');
    }
  );
  // The auth modal is not a form, so
  //   the enter key must be manually bound
  //   to submit the password
  $("#deletePassword").off('keypress');
  $('#deletePassword').keypress(function (e) {
    if (e.which == 13) {
      reAuthModal.setContainerDisable(true);
      cb(null, $("#deletePassword").val());
      return false;
    }
  });

  deleteConfirmButton.off('click');
  deleteConfirmButton.on('click', function(e) {
    reAuthModal.setContainerDisable(true);
    cb(null, $("#deletePassword").val());
  });
  reAuthModal.modal('show');
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
// forms. All three wired the same #snapinpack / #argTypes / .snapin-action /
// .cmdletN handlers and rebuilt the hidden .snapincmd field identically.
// Selectors stay document-scoped exactly as the original three inline copies
// were (each page has a single snapin form context).
//
// opts.packHide      - also toggle .packhide with the template class (edit form
//                      only; add / list-create have no .packhide elements).
// opts.wirePackTypes - wire the #packTypes -> rw/rwa handler (add + edit; the
//                      list create-modal has no #packTypes).
$.initSnapinCommandUI = function(opts) {
  opts = opts || {};
  var ACTION_VAL = -1;
  function packchanger(packval) {
    switch (packval) {
      case '0':
        $('.packnotemplate').removeClass('d-none');
        $('.packtemplate').addClass('d-none');
        if (opts.packHide) {
          $('.packhide').addClass('d-none');
        }
        break;
      case '1':
        $('.packnotemplate').addClass('d-none');
        $('.packtemplate').removeClass('d-none');
        if (opts.packHide) {
          $('.packhide').removeClass('d-none');
        }
        break;
    }
  }
  function updateCmdStore() {
    if (typeof $('.cmdlet3').val() === 'undefined') {
      return;
    }
    var cmd1 = $('.cmdlet1').val(),
      cmd2 = $('.cmdlet2').val(),
      cmd3 = $('.cmdlet3').val(),
      cmd4 = $('.cmdlet4').val(),
      test = $('[type="file"]');
    if (test.length < 1) {
      cmd3 = $('select.cmdlet3').val();
    } else {
      test = test[0].files.length;
      if (test < 1) {
        cmd3 = $('select.cmdlet3').val();
      } else {
        cmd3 = $('[type="file"]')[0].files[0].name;
      }
    }
    var snapCMD = [cmd1, cmd2, cmd3, cmd4];
    $('.snapincmd').val(snapCMD.join(' '));
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
  packchanger($('#snapinpack').val());
  // Make the change when the snapin pack selector changes.
  $('#snapinpack').on('change', function() {
    packchanger($(this).val());
  });
  $('#argTypes').on('change', function() {
    var option = $('option:selected', this),
      value = option.attr('value'),
      rwarg = option.attr('rwargs'),
      args = option.attr('args'),
      rwinp = $('input[name=rw]'),
      rwainp = $('input[name=rwa]'),
      argsinp = $('input[name=args]');
    if (value) {
      rwinp.val(value);
    }
    rwainp.val(rwarg);
    argsinp.val(args);
    updateCmdStore();
  });
  if (opts.wirePackTypes) {
    $('#packTypes').on('change', function() {
      var option = $('option:selected', this),
        file = option.attr('file'),
        args = option.attr('args'),
        rwinp = $('input[name=rw]'),
        rwainp = $('input[name=rwa]');
      rwinp.val(file);
      rwainp.val(args);
    });
  }
  // Setup action radio selector
  $('.snapin-action').on('click', onRadioSelect);
  updateCmdStore();
  $('.cmdlet1,.cmdlet2,.cmdlet3,.cmdlet4').on('change keyup', function(e) {
    e.preventDefault();
    updateCmdStore();
  });
  $('.cmdlet3').on('change blur', function() {
    updateCmdStore();
  });
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
  var settings = dt.settings()[0];
  if (settings && !settings._fogPostShowAdjusted) {
    settings._fogPostShowAdjusted = true;
    dt.one('draw.dt.fogScroller', function() {
      dt.columns.adjust();
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
function fogSizeAllScrollers() {
  if (!$.fn.dataTable || !$.fn.dataTable.isDataTable) {
    return;
  }
  // Iterate initialized tables via isDataTable() rather than the 1.10-era
  // $.fn.dataTable.tables({api:true}).every() idiom, which throws in the
  // bundled 2.x/3.x build ("tables(...).every is not a function") and silently
  // aborted the entire post-show resize path on every shown.bs.tab.
  $('table.dataTable').each(function() {
    if ($.fn.dataTable.isDataTable(this)) {
      fogSizeScroller($(this).DataTable());
    }
  });
}
function fogBindScrollerAutosize() {
  if ($.fn.dataTable.__fogScrollerBound) {
    return; // window/tab handlers only need binding once per page
  }
  $.fn.dataTable.__fogScrollerBound = true;
  var debounce;
  $(window).on('resize.fogScroller', function() {
    clearTimeout(debounce);
    debounce = setTimeout(fogSizeAllScrollers, 150);
  });
  // In-tab tables (edit pages) measure as zero-height while hidden; size them
  // once their tab is shown. Defer a tick: inside shown.bs.tab the revealed
  // tab's layout isn't final, so a synchronous columns.adjust() sizes against
  // a stale (~zero) width and leaves the header/body split misaligned until
  // the next redraw. One macrotask later the layout is settled.
  $(document).on('shown.bs.tab.fogScroller', function () {
    setTimeout(fogSizeAllScrollers, 0);
  });
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
  // in fogBindScrollerAutosize() re-measures (scroller.measure) and re-syncs the
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
        text: '<i class="fa fa-check-square-o"></i> Select All'
      },
      {
        extend: 'selectNone',
        text: '<i class="fa fa-square-o"></i> Deselect All'
      },
      {
        text: '<i class="fa fa-refresh"></i> Refresh',
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

  opts = $.fogDefaults(opts, defaults);

  var table = $(this).DataTable(opts);

  if (infiniteScroll) {
    // Size the scroll body to fill the available height now (deferred so the
    // table is laid out in the DOM first) and keep it sized on resize/tab show.
    fogBindScrollerAutosize();
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
 * and column contract, but the toolbar is reportButtons (no full-export CSV)
 * and the data comes from the report's own getList() via
 * node=report&sub=getList&f=<report>, keyed off Common.f. Every plugin report
 * JS calls this so the tables stay identical across plugins.
 *
 * @param {Array}  columns DataTables column defs ({data:'name'}, ...).
 * @param {Object} opts    Optional overrides (order).
 * @return {Object} the DataTables API for the registered table.
 */
$.fn.registerReportTable = function(columns, opts) {
  opts = opts || {};
  var table = this.registerTable(null, {
    buttons: reportButtons,
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
  return ' <i class="fa fa-info-circle text-muted mac-vendor-icon" '
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
      '<div class="overlay" id="' + loadingId  + '"><i class="fa fa-refresh fa-spin"></i></div>'
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
      maxLength = parseInt(maxLength) / 2;
      exactLength = parseInt(exactLength);

      if (beEqualTo) beEqualTo = "#" + beEqualTo;

      if (beRegexTo) beRegexTo = '#' + beRegexTo;

      if (val.length < minLength) {
        isValid = false;
        if (maxLength == minLength) {
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
};

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
  PNotify.prototype.options.styling = "bootstrap3";

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
    .before('<span class="input-group-text"><i class="fa fa-eye-slash fogpasswordeye"></i></span>');
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

    scripts.forEach(function(value, index){
      if(scripts[index] == null) { delete scripts[index]; return; }
      scripts[index] = scripts[index] + (scripts[index].indexOf("?v") === -1 ? "?ver=" + assetVersion : "");
    });

    commonScripts.forEach(function(value, index){
      if(commonScripts[index] == null) { delete commonScripts[index]; return; }
      commonScripts[index] = commonScripts[index] + (commonScripts[index].indexOf("?v") === -1 ? "?ver=" + assetVersion : "");
    });

    // Determine the currently loaded scripts.
    var loadedScripts = [];
    $("#scripts").find("script").each(function(index, element){
      loadedScripts.push($(element).attr('src'));
    });

    // Calculate the script delta:
    var scriptDelta = {};
    // -> If a script is loaded and it isn't a script common to every page, remove it.
    for(var scriptIndex in loadedScripts){
      var script = loadedScripts[scriptIndex];
      if (commonScripts.indexOf(script) === -1) scriptDelta[script] = -1;
    }
    // -> Reload all scripts this page needs.
    for(var scriptIndex in scripts){
      var script = scripts[scriptIndex];
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
