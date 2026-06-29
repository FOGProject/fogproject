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
$.checkItemUpdate = function(table, item, e, prop, opts, done) {
  $(item).iCheck('update');
  var method = prop.attr('method'),
    action = prop.attr('action');
  if (item.checked) {
    opts = _.defaults(opts, {
      confirmadd: 1,
      additems: [e.target.value]
    });
  } else {
    opts = _.defaults(opts, {
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
  opts = _.defaults(opts, {
    rows: table.rows({selected: true})
  });
  opts = _.defaults(opts, {
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
  opts = _.defaults(opts, {
    node: Common.node,
    rows: table.rows({selected: true}),
    password: undefined
  });
  opts = _.defaults(opts, {
    ids: opts.rows.ids().toArray(),
    url: '../management/index.php?node=' + opts.node + '&sub=deletemulti',
  });
  $('#andFile').on('ifChanged', function(e) {
    e.preventDefault();
    $(this).iCheck('update');
    if (!this.checked) {
      delete opts.andFile;
    } else {
      opts.andFile = 1;
    }
  });
  $('#andFile').trigger('change');
  $('#andHosts').on('ifChanged', function(e) {
    e.preventDefault();
    $(this).iCheck('update');
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
$.getSelectedIds = function(table) {
  var rows = table.rows({selected: true});
  return rows.ids().toArray();
};
$.notify = function(title, body, type) {
  new PNotify({
    title: title,
    text: body,
    type: type
  });
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
$.fn.registerModal = function(onOpen, onClose, opts) {
  var e = this;
  if (e._modalInit === undefined || !e._modalInit) {
    opts = opts || {};
    opts = _.defaults(opts, {
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
}
$.fn.dataTable.ext.order['dom-checkbox'] = function(settings, col) {
    return this.api().column(col, {order:'index'}).nodes().map(function(td, i) {
      return $('input', td).prop('checked') ? '1' : '0';
  });
};
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
          .find('[data-toggle="tooltip"]')
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

  opts = _.defaults(opts, defaults);

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
    + 'data-toggle="tooltip" data-placement="right" data-container="body" '
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
          holder.find('[data-toggle="tooltip"]').tooltip();
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
  var inputs = $(this).find('input:not([type="checkbox"]), select, button, .btn, textarea').toArray(),
    ichecks = $(this).find('.checkbox').toArray();
  $.each(inputs, function(index, value) {
    $(value).prop('disabled', disabled);
  });
  $.each(ichecks, function(index, value) {
    var check = disabled ? 'disable' : 'enable';
    $(value).iCheck(check);
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
      val = $(e).inputmask('unmaskedvalue');
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

    if (parent.hasClass('has-error')) {
      var possibleHelpblock = $(e).next('span');
      if (possibleHelpblock.hasClass('help-block')) {
        possibleHelpblock.remove();
      }
      if (isValid) {
        parent.removeClass('has-error');
      }
    } else if (!isValid) {
      parent.addClass('has-error');
    }

    if (isValid) {
      return;
    }

    if (!scrolling) {
      scrolling = true;
      $('html, body').animate({
        scrollTop: parent.offset().top
      }, 200);
    }

    var msgBlock = '<span class="help-block">' + invalidReason + '</span>'
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

  // Animate the plugin items.
  pluginOptionsAlt.on('click', function(event) {
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
    match = match || 'input'
    $(match).iCheck({
      checkboxClass: 'icheckbox_square-blue',
      radioClass: 'iradio_square-blue',
      increaseArea: '20%' // optional
    });
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
    form.find('.has-error').removeClass('has-error').find('span.help-block').remove();
    // Unbind the keypress event.
    $(':input:not(textarea)', this).off('keypress');
  };

  $.debugLog("=== DEBUG LOGGING ENABLED ===");
  setupIntegrations();
  $(":input").inputmask(); // Setup all input masks
  Common.iCheck(); // Setup all checkboxes
  patchSelect2SearchId(); // Must run before any .select2() init below.
  $('.fog-select2').select2({width: '100%'}); // Setup all select elements
  disableFormDefaults();
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

  // Extending input mask to add our types
  $.extend($.inputmask.defaults.definitions, {
    '#': {
      validator: "[A-Fa-f0-9]",
      cardinality: 1
    }
  });
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
    .before('<span class="input-group-addon"><i class="fa fa-eye-slash fogpasswordeye"></i></span>');
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
    $('[data-toggle="tooltip"]').tooltip({
      container: 'body'
    });
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

    if($(".sidebar-menu.tree .treeview.menu-open").find(targetElement).length === 0){
      $(".sidebar-menu.tree .treeview.menu-open .treeview-menu").slideUp();
      $(".sidebar-menu.tree .treeview.menu-open").removeClass('menu-open');
    }

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

      // Update the sidebar
      $(".sidebar-menu.tree li").not(targetElement.parent('.treeview')).removeClass('active');
      targetElement.parent().addClass('active');
      targetElement.parents('.treeview').addClass('active menu-open');
      targetElement.parents('.treeview-menu').slideDown();
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
