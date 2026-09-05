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

/**
 * Builds one column's search control: a condition picker and a value box.
 *
 * The picker is a native <select> rather than a Bootstrap dropdown on
 * purpose. In scrolling mode DataTables moves the header into
 * div.dt-scroll-head, which clips its overflow -- a dropdown-menu opened in
 * there is cut off at the header's own height. A native select's popup is
 * drawn by the browser outside the document, so it cannot be clipped by any
 * ancestor.
 *
 * A date column gets type="date" so the browser supplies its own calendar;
 * that also means the value arrives as YYYY-MM-DD, which is exactly the whole
 * day the server's date conditions are written around.
 *
 * @param {number} index the column's DataTables index
 * @param {string} type  the server-derived type: string, num or date
 *
 * @return {jQuery} the control
 */
function fogColumnSearchControl(index, type) {
  var conditions = columnSearchConditions[type] || columnSearchConditions.string,
    // A plain flex row rather than Bootstrap's .input-group: the group's
    // joined-borders rules assume its children never wrap, and these have to
    // -- FOG's grids run from a 60px ID column to a wide name, and a control
    // that cannot wrap leaves the narrow ones with an unusably thin box.
    group = $('<div class="fog-colsearch"/>'),
    mode = $('<select class="form-select form-select-sm fog-colsearch-mode"/>'),
    box = $('<input class="form-control form-control-sm fog-colsearch-input"/>'),
    i;
  box.attr('type', type === 'date' ? 'date' : 'text');
  for (i = 0; i < conditions.length; i++) {
    mode.append(
      $('<option/>')
        .attr('value', conditions[i].v)
        .attr('title', conditions[i].t)
        .text(conditions[i].s)
    );
  }
  return group.attr('data-column', index).append(mode).append(box);
}

/**
 * Reads one control and pushes it into that column's DataTables search.
 *
 * The wire value is the condition, the separator, then what was typed. An
 * empty box clears the column instead of searching for nothing -- except for
 * the two conditions that HAVE no value ("is empty" / "is not empty"), where
 * the empty box is the whole point, so the box is disabled and the condition
 * alone is sent.
 *
 * @param {object} api   the DataTables API for the table
 * @param {jQuery} group one .fog-colsearch control
 *
 * @return {void}
 */
function fogColumnSearchApply(api, group) {
  var index = parseInt(group.attr('data-column'), 10),
    condition = group.find('select.fog-colsearch-mode').val(),
    box = group.find('input.fog-colsearch-input'),
    valueless = condition === 'null' || condition === '!null',
    wire;
  box.prop('disabled', valueless);
  if (valueless) {
    wire = condition + columnSearchSeparator;
  } else if (box.val() === '') {
    wire = '';
  } else {
    wire = condition + columnSearchSeparator + box.val();
  }
  if (api.column(index).search() !== wire) {
    api.column(index).search(wire).draw();
  }
}

/**
 * Clears every column search when the row is hidden.
 *
 * A filter you cannot see is indistinguishable from missing data, so hiding
 * the row has to mean unfiltering rather than merely concealing why the grid
 * is short. Redraws only if something was actually cleared.
 *
 * @param {object} api the DataTables API for the table
 *
 * @return {void}
 */
function fogColumnSearchSync(api) {
  var container = $(api.table().container()),
    cleared = false;
  if (container.hasClass('fog-colsearch-on')) {
    return;
  }
  container.find('div.fog-colsearch').each(function() {
    var group = $(this),
      index = parseInt(group.attr('data-column'), 10);
    group.find('input.fog-colsearch-input').val('').prop('disabled', false);
    group.find('select.fog-colsearch-mode').prop('selectedIndex', 0);
    if (api.column(index).search() !== '') {
      api.column(index).search('');
      cleared = true;
    }
  });
  if (cleared) {
    api.draw();
  }
}

/**
 * Repopulates the boxes from the searches the table is actually running.
 *
 * The row is rebuilt on every draw, so the DOM is not where a column search
 * lives -- DataTables' own column state is. Without this the boxes would go
 * blank the moment the first filtered response came back, while the grid
 * stayed filtered.
 *
 * @param {object} api the DataTables API for the table
 *
 * @return {void}
 */
function fogColumnSearchRestore(api) {
  $(api.table().container()).find('div.fog-colsearch').each(function() {
    var group = $(this),
      index = parseInt(group.attr('data-column'), 10),
      search = api.column(index).search(),
      parts;
    if (search === '') {
      return;
    }
    // Never write over the box being typed in: this runs when the response to
    // that very keystroke arrives.
    if (group.find(':focus').length) {
      return;
    }
    parts = search.split(columnSearchSeparator);
    if (parts.length < 2) {
      // Set by something other than this row -- a plain contains-anywhere
      // search, which is what column().search() has always meant.
      group.find('input.fog-colsearch-input').val(search);
      return;
    }
    group.find('select.fog-colsearch-mode').val(parts[0]);
    group
      .find('input.fog-colsearch-input')
      .val(parts[1])
      .prop('disabled', parts[0] === 'null' || parts[0] === '!null');
  });
}

/**
 * Builds, or rebuilds, the search row beneath a table's header.
 *
 * Cloned from the title row rather than generated from the column list: the
 * header only holds cells for VISIBLE columns, so cloning is what keeps the
 * boxes lined up with the columns they belong to without tracking visibility
 * separately. Rebuilt whenever that changes.
 *
 * Columns the server will not match on -- a checkbox, an action link, a
 * computed count, a value the emitter strips -- get an empty cell rather than
 * a box, for the same reason they are left out of the Filter panel: a control
 * that silently does nothing reads as the feature being broken.
 *
 * @param {object} api   the DataTables API for the table
 * @param {object} types the server's _searchtypes map
 *
 * @return {void}
 */
function fogColumnSearchRow(api, types) {
  var header = $(api.table().header()),
    title = header.find('tr').not('.fog-colsearch-row').first(),
    columns = api.settings()[0].aoColumns,
    existing = header.find('tr.fog-colsearch-row'),
    visible = [],
    signature,
    position,
    row;
  // Map each header cell to the column it is showing. With ColReorder on, a
  // column's index no longer says where its heading sits, so walking
  // columns(':visible') in index order pairs every box with the wrong column
  // the moment anything is dragged -- and the box then filters a column the
  // user is not looking at, silently.
  //
  // ':visIdx' resolves a DISPLAY position back to a column, accounting for
  // both hidden and reordered columns, so it is the right selector -- but it
  // is only right at the moment it is read. A saved layout's column order is
  // applied AFTER the first response, so a row built at xhr time and left
  // alone describes the pre-restore layout and every box filters a column its
  // heading does not belong to. That is why the rebuild below is bound to
  // init and draw as well as to the reorder event itself; the signature check
  // makes the extra passes free.
  for (position = 0; position < title.children().length; position++) {
    visible.push(api.column(position + ':visIdx').index());
  }
  signature = visible.join(',');
  // In scrolling mode DataTables keeps a second, sizing-only copy of the
  // header in the scroll body and clones the whole thead into it -- our row
  // included. That copy is collapsed to zero height, so it is invisible
  // rather than a doubled row, but it is still a live set of controls with
  // the same data-column values, reachable by keyboard and going stale the
  // moment anything is typed in the real one. Only the header the API points
  // at is ours; drop any other copy.
  $(api.table().container())
    .find('tr.fog-colsearch-row')
    .not(existing)
    .remove();
  // Rebuild only when the row is actually gone or now describes the wrong
  // columns. This runs on every response and on every width recalculation,
  // and replacing the row unconditionally would take the focus out of the box
  // the user is typing in the moment their own search came back.
  if (existing.length && existing.attr('data-columns') === signature) {
    return;
  }
  existing.remove();
  row = $('<tr class="fog-colsearch-row"/>').attr('data-columns', signature);
  title.children().each(function(position) {
    var cell = $('<th class="fog-colsearch-cell"/>'),
      index = visible[position],
      key = index === undefined ? null : columns[index].data,
      type = key !== null && key in types ? types[key] : false;
    if (type) {
      cell.append(fogColumnSearchControl(index, type));
    }
    row.append(cell);
  });
  header.append(row);
}

/**
 * Where the REST API lives, from the hidden input the page shell emits.
 *
 * Derived server-side from FOG_WEB_ROOT rather than assumed to be '/fog/',
 * which the installer's -W/--webroot can change.
 *
 * @return {string} the API base path, with a trailing slash
 */
function fogApiBase() {
  return $('#apiBase').val() || '/fog/';
}

/**
 * The preference key a table's saved state is stored under.
 *
 * Namespaced so that preferences added later cannot collide with a grid's,
 * and keyed on the table's DOM id -- which is what distinguishes the host
 * list from the image list, since every FOG grid is built by the same helper.
 *
 * @param {object} settings the DataTables settings object
 *
 * @return {string} the key, or '' when the table has no id to key on
 */
function fogStateKey(settings) {
  var id = settings.sTableId || '';
  // Read from the URL directly rather than from $_GET, which is populated
  // during page init and so may not be ready when a table is built. Getting
  // that wrong is not a crash -- it is every page silently sharing one key,
  // which is exactly the collision this function exists to prevent.
  var params = new URLSearchParams(window.location.search);
  var node = params.get('node') || '';
  var sub = params.get('sub') || '';
  if (!id) {
    return '';
  }
  // The id alone is not enough: FOG builds most grids as `#dataTable`, so
  // the host list and the image list would share one saved layout and each
  // would load the other's column widths. The page the table is on is what
  // actually identifies it.
  return 'dt.' + node + '.' + sub + '.' + id;
}

/**
 * Strips the parts of a DataTables state that must not be restored.
 *
 * Column ORDER, VISIBILITY, page length and sort are preferences: they say
 * how you like to look at the list. A SEARCH is a question you asked once,
 * and restoring one silently is the same failure the Column search toggle
 * avoids by clearing when it closes -- you open Hosts, see 3 of 86, and
 * nothing on screen says why. So the searches are dropped on the way out
 * rather than on the way in, which also keeps them out of the stored value
 * entirely: a filter someone typed is not something to persist server-side
 * on their behalf.
 *
 * A SELECTION is the same thing with teeth. DataTables Select registers its
 * own stateSaveParams handler that writes `select.rows` -- the row ids -- into
 * every save, and a matching stateLoadParams that re-selects them and calls
 * state.save() again. Nothing here asked for that and nothing here reads it.
 * Left in, three hosts ticked on Friday come back ticked on Monday, from
 * whichever machine you next sign in on, because this store is server-side and
 * lasts a year. Every bulk action on the page reads rows({selected: true}), so
 * what is restored is not a view of the list -- it is a loaded gun pointed at
 * rows the person in front of it did not choose, sitting somewhere down an
 * 86-row virtual scroll where they cannot see it.
 *
 * Verified on the lab server before this was written: selecting three hosts
 * POSTed `select: {rows: ["#211","#48","#49"], ...}` to
 * system/userpref/dt.host.list.dataTable, and the next load came up with three
 * rows selected and their checkboxes ticked.
 *
 * Select's restore is guarded on `void 0 !== l.select`, so dropping the key
 * makes it a clean no-op rather than an error -- and skips the extra
 * state.save() it fires on every page load.
 *
 * @param {object} state the state DataTables wants saved
 *
 * @return {object} a copy safe to store
 */
function fogOrderKeys(settings, order) {
  var columns = settings.aoColumns,
    keys = [],
    i;
  for (i = 0; i < (order || []).length; i++) {
    if (columns[order[i][0]] && columns[order[i][0]].data) {
      keys.push([columns[order[i][0]].data, order[i][1]]);
    }
  }
  return keys;
}
/**
 * Puts the sort back on the column it was saved against.
 *
 * A no-op unless the restored sort actually names a different column than
 * the one saved, which is only the case when the columns have been reordered
 * -- so the common visit costs nothing, and the redraw (a request, on a
 * server-side table) happens only where the alternative is a table sorted by
 * the wrong column.
 */
function fogApplyOrder(api) {
  var loaded = api.state.loaded(),
    columns = api.settings()[0].aoColumns,
    wanted = [],
    lookup = {},
    i;
  if (!loaded || !loaded.fogOrder || !loaded.fogOrder.length) {
    return;
  }
  for (i = 0; i < columns.length; i++) {
    lookup[columns[i].data] = i;
  }
  for (i = 0; i < loaded.fogOrder.length; i++) {
    if (loaded.fogOrder[i][0] in lookup) {
      wanted.push([lookup[loaded.fogOrder[i][0]], loaded.fogOrder[i][1]]);
    }
  }
  if (!wanted.length || JSON.stringify(wanted) === JSON.stringify(api.order())) {
    return;
  }
  api.order(wanted).draw(false);
}
function fogStripVolatileState(state) {
  var copy = $.extend(true, {}, state), i;
  delete copy.search;
  delete copy.searchBuilder;
  delete copy.select;
  for (i = 0; i < (copy.columns || []).length; i++) {
    delete copy.columns[i].search;
  }
  // `time` is deliberately KEPT. DataTables will not apply a state that has
  // no timestamp -- _fnImplementState opens with `if (state && state.time)`
  // and silently drops it otherwise -- and it is also what stateDuration
  // measures against, which is the escape hatch that lets a bad saved layout
  // age out instead of following someone forever. Stripping it made every
  // saved layout unrestorable, with nothing logged and no error: the table
  // simply came up in its default arrangement every time.
  return copy;
}

/**
 * Turns a grid's Description column into a row tooltip.
 *
 * A description is prose ABOUT the whole record, not a value to line up
 * against the other columns. It is routinely longer than everything else on
 * the row put together, so as a column it either dictates the table's widths
 * or is clipped to an ellipsis that says nothing -- and it does that on every
 * grid that carries one, which is why the rule lives here rather than being
 * decided page by page. A page writes `{data: 'description'}` and gets the
 * same treatment whichever list it is.
 *
 * HIDDEN, NOT DROPPED, and the difference matters. The column still rides the
 * request, so the global search box and the Filter button still match on a
 * description -- FOGManagerController::filter() resolves each searchable
 * column out of `request.columns`, which carries invisible columns too, so
 * removing it would have quietly taken description search with it. It is kept
 * out of the Column Visibility picker (`noVis`) because "sometimes a column,
 * sometimes a tooltip" is a worse answer than either one.
 *
 * @param {Array} columns the DataTables column list, modified in place
 *
 * @return {Array} indexes of the description columns, for the state fix-up
 */
function fogDescriptionColumns(columns) {
  var found = [],
    col,
    i;
  for (i = 0; i < (columns || []).length; i++) {
    col = columns[i];
    if (!col || col.data !== 'description') {
      continue;
    }
    col.visible = false;
    col.className = (col.className ? col.className + ' ' : '') + 'noVis';
    found.push(i);
  }
  return found;
}

/**
 * Forces Description hidden in a state saved before it became a tooltip.
 *
 * Column visibility is part of the saved state and the state wins over the
 * column definition, so without this every user who has ever loaded one of
 * these grids would keep the column they already have and see no change at
 * all -- the feature would look like it had not shipped. Only the visible
 * flag is touched; the rest of their layout is theirs.
 *
 * @param {Array}  indexes the description columns, from fogDescriptionColumns
 * @param {object} state   the state about to be handed to DataTables
 *
 * @return {object} the same state
 */
function fogHideDescriptionState(indexes, state) {
  var i;
  if (!state || !state.columns) {
    return state;
  }
  for (i = 0; i < indexes.length; i++) {
    if (state.columns[indexes[i]]) {
      state.columns[indexes[i]].visible = false;
    }
  }
  return state;
}

/**
 * Stores one preference for the signed-in user.
 *
 * Deliberately not $.apiCall: that raises a toast on every response, and
 * these fire on every column drag and every sort. A failure is not worth
 * interrupting anybody over either -- the state is also written to
 * localStorage, so the worst case is that the layout stops following the
 * user between machines, which is where it already was.
 *
 * @param {string}   key   the preference key
 * @param {string}   value the value to store
 * @param {function} cb    optional callback, receives an error or null
 *
 * @return {void}
 */
var fogPrefInFlight = {},
  fogPrefPending = {};

function fogPrefStore(key, value, cb) {
  // AT MOST ONE WRITE PER KEY IN FLIGHT, and the newest value wins.
  //
  // One user gesture routinely produces SEVERAL saves of the same key: a grid
  // writes its whole state on every column-sizing pass and every redraw, and
  // DataTables fires those itself while a column is being resized. Sent
  // concurrently they are answered in whatever order the server finishes
  // them, and the row keeps whichever ANSWERS last rather than whichever was
  // SENT last.
  //
  // Measured on the lab, one double-click-to-fit on the host list:
  //
  //     send 1012   send 1012   send 1522
  //       ok 1012     ok 1522     ok 1012
  //
  // -- the stale write answered last, so the page showed the fitted layout
  // while the server kept the old one, and the next page load undid the fit.
  // It reads exactly like the layout being ignored, which is why it is worth
  // the queue: the write succeeded, twice, with the wrong value.
  //
  // So while a key's write is in flight, later values for it are held rather
  // than sent, and only the newest is sent when that one completes. Anything
  // superseded in between is dropped -- it was never going to be the final
  // answer. Per KEY, not globally: two different preferences have no ordering
  // relationship and must not wait on each other.
  if (fogPrefInFlight[key]) {
    // Callbacks accumulate rather than replace. A caller that passed one is
    // waiting to hear what happened, and dropping its callback with its value
    // would leave it waiting forever; they are all told the outcome of the
    // write that actually lands, which is the one whose value stuck.
    fogPrefPending[key] = {
      value: value,
      cbs: (fogPrefPending[key] ? fogPrefPending[key].cbs : []).concat(cb ? [cb] : [])
    };
    return;
  }
  fogPrefInFlight[key] = true;
  $.ajax({
    url: fogApiBase() + 'system/userpref/' + encodeURIComponent(key),
    type: 'POST',
    contentType: 'application/json',
    data: JSON.stringify({ value: value }),
    global: false,
    success: function() { if (cb) { cb(null); } },
    error: function(xhr) { if (cb) { cb(xhr); } },
    // complete, not success: a failed write must release the key too, or one
    // error would wedge that preference for the rest of the page's life.
    complete: function() {
      delete fogPrefInFlight[key];
      var next = fogPrefPending[key];
      if (!next) {
        return;
      }
      delete fogPrefPending[key];
      fogPrefStore(key, next.value, function(err) {
        for (var i = 0; i < next.cbs.length; i++) {
          next.cbs[i](err);
        }
      });
    }
  });
}

/**
 * Reads one preference for the signed-in user.
 *
 * @param {string}   key the preference key
 * @param {function} cb  receives (error, value)
 *
 * @return {void}
 */
function fogPrefFetch(key, cb) {
  $.ajax({
    url: fogApiBase() + 'system/userpref/' + encodeURIComponent(key),
    type: 'GET',
    global: false,
    success: function(data) { cb(null, (data && data.value) || ''); },
    error: function(xhr) { cb(xhr, ''); }
  });
}

/**
 * The preference key for one of a grid's affordances.
 *
 * Namespaced under the grid's own layout key so two tables cannot collide,
 * and suffixed so an affordance cannot collide with the layout itself.
 *
 * @param {object} dt   the DataTables API for the table
 * @param {string} name the affordance
 *
 * @return {string} the key, or '' when the table has no id to key on
 */
function fogAffordanceKey(dt, name) {
  var base = fogStateKey(dt.settings()[0]);
  return base ? base + '.' + name : '';
}

/**
 * Remembers whether one of a grid's affordances is showing.
 *
 * Deliberately a BOOLEAN and never a filter term. Restoring "the search row
 * is open" shows an empty row; restoring what was typed in it would show a
 * short grid with no visible reason, which is the failure the layout state
 * avoids by stripping searches entirely.
 *
 * @param {object}  dt   the DataTables API for the table
 * @param {string}  name the affordance
 * @param {boolean} on   whether it is showing
 *
 * @return {void}
 */
function fogAffordanceStore(dt, name, on) {
  var key = fogAffordanceKey(dt, name);
  if (!key) {
    return;
  }
  // An empty value clears the preference, so "off" leaves no row behind --
  // the same convention the rest of the store uses for "no opinion".
  fogPrefStore(key, on ? '1' : '');
}

/**
 * Reads one affordance back and applies it.
 *
 * @param {object}   dt    the DataTables API for the table
 * @param {string}   name  the affordance
 * @param {function} apply receives true when it was left showing
 *
 * @return {void}
 */
function fogAffordanceRestore(dt, name, apply) {
  var key = fogAffordanceKey(dt, name);
  if (!key) {
    return;
  }
  fogPrefFetch(key, function(err, value) {
    if (!err && value === '1') {
      apply();
    }
  });
}

/**
 * Display-timezone picker in the navbar.
 *
 * Reads and writes the same preference store the grid layouts use, so a chosen
 * zone follows the user to any browser. Bound once, on the shell's modal --
 * the shell is not re-rendered by AJAX navigation, so there is nothing to
 * rebind on a page change.
 *
 * Saving reloads. Dates are rendered SERVER-side (grids included, see
 * FOGManagerController::displayDates), so nothing already on the page can be
 * relabeled in place -- and a picker that appeared to do nothing until the
 * next navigation would read as broken.
 */
function fogBindTimezonePicker() {
  // #prefsModal, not #tzModal: the timezone picker now shares the preferences
  // dialog with the theme choices. Missing this rename is a silent failure --
  // the dialog still opens and the select still renders, it just never loads
  // the stored value and Save does nothing.
  var modal = document.getElementById('prefsModal');
  if (!modal) {
    return;
  }
  $(modal).on('show.bs.modal', function() {
    // Read on open rather than at page load: this is one request per use of a
    // control almost nobody opens, against one on every single page view.
    fogPrefFetch('display.timezone', function(err, value) {
      $('#tzSelect').val(err ? '' : (value || ''));
    });
  });
  $('#tzSave').on('click', function() {
    var chosen = $('#tzSelect').val() || '';
    var button = $(this);
    button.prop('disabled', true);
    // An empty value clears the preference, which is exactly what "server
    // default" means -- the store deletes the row rather than holding an
    // empty string, so nothing has to know the default's name.
    fogPrefStore('display.timezone', chosen, function(storeErr) {
      if (storeErr) {
        button.prop('disabled', false);
        // notifyFromAPI, not notify: $.notify takes (title, body, type),
        // and the object-and-options form this used to pass is a different
        // library's signature -- it rendered "[object Object]" as the title.
        // notifyFromAPI is also the better answer here because it surfaces
        // the reason the server actually gave.
        $.notifyFromAPI(storeErr.responseJSON, storeErr);
        return;
      }
      window.location.reload();
    });
  });
}

// Bound the way theme.js binds its own shell control: the navbar is emitted
// once by the page shell and AJAX navigation replaces only the content area,
// so this runs once and stays wired.
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', fogBindTimezonePicker);
} else {
  fogBindTimezonePicker();
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
  // Per-column boxes in a second header row, as an alternative front end to
  // the same filtering. Both controls end up in the same server code path --
  // the box sends its condition inline, and FOGManagerController::filter()
  // hands it to the same criterion builder a Filter-panel rule goes through
  // -- so there is one set of guards, one escaping rule and one piece of
  // whole-day date arithmetic, not two. They stack: a box and a panel rule
  // are ANDed, which is why the row does not try to stay in sync with the
  // panel's state. There is nothing to sync.
  //
  // The condition each column offers comes from what the column IS, exactly
  // as the panel's does, and the vocabularies below mirror
  // FOGManagerController::$_sbConditions. The server re-validates every one
  // against the column's real SQL type, so this list is a convenience, never
  // a trust boundary.
  //
  // Labels are terse because they sit in a select inside a header cell; the
  // readable form is the option's title, which the browser shows on hover.
  columnSearchConditions = {
    string: [
      { v: 'contains', s: 'has', t: 'contains' },
      { v: '=', s: 'is', t: 'is exactly' },
      { v: 'starts', s: 'a…', t: 'starts with' },
      { v: 'ends', s: '…z', t: 'ends with' },
      { v: '!contains', s: '!has', t: 'does not contain' },
      { v: '!=', s: 'is not', t: 'is not' },
      { v: 'null', s: 'empty', t: 'is empty' },
      { v: '!null', s: 'set', t: 'is not empty' }
    ],
    num: [
      { v: '=', s: '=', t: 'equals' },
      { v: '!=', s: '\u2260', t: 'does not equal' },
      { v: '>', s: '>', t: 'greater than' },
      { v: '>=', s: '\u2265', t: 'at least' },
      { v: '<', s: '<', t: 'less than' },
      { v: '<=', s: '\u2264', t: 'at most' },
      { v: 'null', s: 'empty', t: 'is empty' },
      { v: '!null', s: 'set', t: 'is not empty' }
    ],
    date: [
      { v: '=', s: 'on', t: 'on that day' },
      { v: '>', s: 'after', t: 'after that day' },
      { v: '<', s: 'before', t: 'before that day' },
      { v: '!=', s: 'not on', t: 'not on that day' },
      { v: 'null', s: 'never', t: 'never set' },
      { v: '!null', s: 'set', t: 'is set' }
    ]
  },
  // Separates the condition from its value on the wire. Matches
  // FOGManagerController::COLUMN_SEARCH_SEPARATOR. The ASCII unit separator
  // cannot be typed into a box, so a value a user really entered can never be
  // mistaken for this form; a box with no separator in it keeps DataTables'
  // historical contains-anywhere meaning, which is what every other caller of
  // column().search() in FOG relies on.
  columnSearchSeparator = '\u001f',
  // Starts disabled and is enabled once the first response proves the server
  // can type the columns -- a client-side table sends no _searchtypes, and a
  // row of boxes that filtered on nothing but "contains" would be a different
  // feature wearing the same clothes.
  columnSearchButton = {
    name: 'colsearch',
    enabled: false,
    text: '<i class="fas fa-magnifying-glass-chart"></i> Column search',
    action: function(e, dt) {
      var on = $(dt.table().container())
        .toggleClass('fog-colsearch-on')
        .hasClass('fog-colsearch-on');
      fogColumnSearchSync(dt);
      // WHETHER THE ROW IS SHOWN, never what is typed in it. The row is an
      // affordance -- "I like filtering from the headers" -- and remembering
      // that is not the same as remembering a question somebody asked once.
      // The terms are still cleared when the row closes, and still stripped
      // from the saved layout; see fogStripVolatileState().
      fogAffordanceStore(dt, 'searchrow', on);
    }
  },
  // Named, saved filters. A separate control from the Filter panel next to
  // it: that one BUILDS a filter, this one remembers the ones worth keeping
  // and hands them out. Everything about it lives in fog.filters.js -- the
  // toolbar only needs to know how to open it.
  savedFiltersButton = {
    name: 'savedfilters',
    text: '<i class="far fa-bookmark"></i> Saved filters',
    action: function(e, dt) {
      if (window.fogSavedFilters) {
        window.fogSavedFilters.open(dt);
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
    savedFiltersButton,
    columnSearchButton,
    {
      extend: 'colvis',
      // Skips columns the grid hides on purpose rather than as a default --
      // today that is Description, which is a row tooltip everywhere (see
      // fogDescriptionColumns) and is not a column anyone should be able to
      // turn back on from here.
      columns: ':not(.noVis)',
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
    savedFiltersButton,
    columnSearchButton,
    {
      extend: 'colvis',
      // Skips columns the grid hides on purpose rather than as a default --
      // today that is Description, which is a row tooltip everywhere (see
      // fogDescriptionColumns) and is not a column anyone should be able to
      // turn back on from here.
      columns: ':not(.noVis)',
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
//                    plumbing keeps working -- UNLESS the tab commits the
//                    cell itself, as host-module does: its cell is a
//                    three-state select (ADR 0038) and there is no checkbox
//                    to toggle, so it binds its own change handler in
//                    onDraw. The Add/Remove buttons are unaffected either
//                    way; they read DataTables' row selection, not this
//                    cell.
// opts.onDraw      - optional function(table) run at the end of every table
//                    redraw, after the checkbox styling/binding and button
//                    enable/disable. For tabs that mirror a side panel off the
//                    association state (host-printer's default-printer selector).
// opts.afterCommit - optional function() run after a successful add, remove, or
//                    per-row toggle commit. For tabs whose side panel must
//                    refresh once the association save lands (host-snapin's run
//                    order). Passed through as $.checkItemUpdate's done callback.
// opts.allowRemove - optional; default true. Pass false for a tab whose
//                    membership is a single-valued property of the row rather
//                    than a link row, so it can be repointed but not broken --
//                    a storage node's group. There are TWO ways to remove on a
//                    normal tab and hiding the button only closes one: an
//                    already-associated checkbox, when unticked, posts the same
//                    confirmdel/remitems on its own. So this also renders such
//                    a checkbox disabled. The matching renderAssocTab()
//                    argument suppresses the button and its confirm modal, and
//                    the two must agree -- a tab that hides the button while
//                    leaving the checkbox live still offers the operation.
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
    allowRemove = opts.allowRemove !== false,
    columns = opts.columns || [{data: 'mainLink'}, {data: 'association'}],
    checkboxRender = opts.checkboxRender || function(row) {
      var associated = row.association === 'associated',
        checkval = associated ? ' checked' : '',
        // Ticking still works -- that is the repoint. Unticking is what has
        // no meaning, and it is a live commit path, not just a display state.
        lockval = (associated && !allowRemove) ? ' disabled' : '';
      return '<div class="form-check">'
        + '<input type="checkbox" class="associated" name="associate[]" id="'
        + slug + '-associate-' + row.id
        + '" value="' + row.id + '"'
        + checkval
        + lockval
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
function fogTableParts(node, api) {
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
    // The table's column definitions, when the caller has an API to hand.
    // Only used to name a column (see fogColKey); resizing works without it.
    columns: api ? api.settings()[0].aoColumns : null,
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
// Persisted per user, not just per page load. The shares ride the table's
// DataTables state -- the same object that already carries column order,
// visibility, page length and sort -- so they are written to localStorage AND
// to the preference store by stateSaveCallback below, and come back through
// stateLoadCallback before the first sizing pass runs. Nothing new is stored
// and no new key is invented; a width is simply another thing about "how I
// like to look at this list".
//
// What happens when a table's columns change underneath a stored layout was
// the open question that kept this in memory. The answer is the same one the
// saved SORT already uses: store against the column's NAME rather than its
// position. A name the table no longer has is ignored, a column the store has
// never seen takes its own freshly measured width (fogRestoredColWidths does
// this already, for the Responsive case), and a layout that goes bad ages out
// with the rest of the state after stateDuration.
var fogColWidthStore = {};

// Push the remembered widths out to where they survive a reload.
//
// Only ever called off a user GESTURE -- the end of a drag, a double-click to
// fit -- and never off a sizing pass, which fires on every ajax load, tab
// show and window resize and would turn a layout nobody touched into a stream
// of preference writes.
function fogSaveColWidths(api) {
  if (api) {
    api.state.save();
  }
}

// A table with no id has no identity worth storing against.
function fogTableKey(parts) {
  return parts.body.attr('id') || '';
}

// Identity of the column at colgroup position i.
//
// The column's NAME (its DataTables `data` key) where there is one, because
// that is what survives being stored and read back on a later visit -- an
// index does not, since adding or removing a column shifts every index after
// it and the stored widths would land on the wrong columns. Same reason the
// saved sort stores a name; see fogOrderKeys().
//
// data-dt-column is still how we get there: it is the column's ORIGINAL index
// and it stays put while ColReorder moves the cell around, so it is the one
// handle that maps a colgroup position back to a column definition.
//
// Columns with no name -- the select checkbox, an actions column -- fall back
// to that index, which is stable for as long as the column set is.
function fogColKey(parts, i) {
  var idx = parts.headers.eq(i).attr('data-dt-column'),
    col;
  if (idx === undefined) {
    return undefined;
  }
  col = parts.columns ? parts.columns[idx] : null;
  if (col && typeof col.data === 'string' && col.data) {
    return col.data;
  }
  return String(idx);
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

// `api` is the table's DataTables API, and it is optional only so that this
// stays a plain jQuery plugin any caller can use. With it, a column is
// remembered by name and the layout is saved through the table's state (so it
// follows the user); without it, resizing still works but only for this page
// load, keyed by column position.
$.fn.makeColumnsResizable = function(api) {
  return this.each(function() {
    var parts = fogTableParts(this, api),
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
          fogSaveColWidths(api);
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
          fogSaveColWidths(api);
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
/**
 * Draw only if the table is actually short of rows.
 *
 * Called when a re-measure has raised Scroller's page length: the taller
 * viewport wants more rows than the last request asked for. That is not the
 * same as needing them -- a grid that already holds every row the server has
 * cannot be short, however tall it gets, and on a server-side table a draw it
 * does not need is a round trip plus a re-render of every loaded row.
 *
 * The first pass runs before the initial response has landed, when the row
 * count is not knowable yet. Rather than guess, wait for that draw and ask
 * again: by then the answer is a fact. Deferred at most once -- if the reply
 * still leaves the table short, fetch, and stop.
 *
 * @param {object} dt      the DataTables API for the table
 * @param {bool}   waited  internal: this is the re-check after the deferral
 *
 * @return {void}
 */
function fogFetchIfShort(dt, waited) {
  var settings = dt.settings()[0],
    xhr = settings ? settings.jqXHR : null,
    info;
  if (!waited && xhr && xhr.readyState !== 4) {
    dt.one('draw.dt', function() {
      fogFetchIfShort(dt, true);
    });
    return;
  }
  info = dt.page.info();
  // recordsDisplay is what the server says matches the current filter. Holding
  // that many means there is nothing left to ask for.
  if (info && dt.rows().count() >= info.recordsDisplay) {
    return;
  }
  dt.draw(false);
}
/**
 * Measure a table's natural column widths the cheap way.
 *
 * This is a like-for-like replacement for DataTables Responsive's own
 * _resizeAuto(). The measurement is identical -- build a throwaway copy of the
 * table at width:auto inside a 1px hidden box and read offsetWidth off a row of
 * empty cells -- but it assembles that copy with native cloneNode instead of
 * walking the DataTables API cell by cell.
 *
 * That walk is the cost. On the 86-row host list the shipped version spends
 * ~105ms a call, of which only ~25ms is the layout the browser is asked for and
 * ~15ms the cloning; the remaining ~65ms is rows().every() and cells().every()
 * doing 946 individual jQuery clones. Cloning each row once, natively, gets the
 * same answer in ~30ms. Measured 4.4x on hosts, 2.8x on images and users, with
 * byte-identical minWidth vectors at 1600/1280/1024/860/700px.
 *
 * (The vector does not change with viewport width, which is not a bug in the
 * test: the copy is measured at width:auto in a 1px box, so only content and
 * CSS are inputs. That is also what makes fogMemoizeResponsiveMeasure() safe.)
 *
 * Two cases hand back to the original rather than guess:
 *
 *  - childNodeStore holding anything. When a row's details are expanded
 *    Responsive MOVES the hidden columns' nodes into the child row, so a plain
 *    cloneNode of the parent would measure cells whose content has gone
 *    elsewhere and report those columns too narrow.
 *  - auto measurement switched off, per-table or per-column, where the original
 *    deliberately leaves minWidth alone.
 *
 * @param {function} original  Responsive's own _resizeAuto, for those cases
 *
 * @return {function} a replacement to install on the prototype
 */
function fogFastResizeAuto(original) {
  return function() {
    var dt = this.s.dt,
      columns = this.s.columns,
      i;
    // Same guard as the original: with auto off it writes no minWidth at all,
    // and anything else here would invent numbers it never had.
    if (!this.c.auto || $.inArray(true, $.map(columns, function(c) {
      return c.auto;
    })) === -1) {
      return;
    }
    if (!$.isEmptyObject(this.s.childNodeStore)) {
      return original.apply(this, arguments);
    }
    var visible = dt.columns().indexes().filter(function(index) {
        return dt.column(index).visible();
      }),
      node = dt.table().node(),
      clone = node.cloneNode(false),
      probe = document.createElement('tr'),
      body = document.createElement('tbody'),
      source = node.querySelectorAll('tbody > tr'),
      cells,
      row;
    clone.style.width = 'auto';
    clone.style.position = 'relative';
    clone.appendChild(dt.table().header().cloneNode(true));
    if (dt.table().footer()) {
      clone.appendChild(dt.table().footer().cloneNode(true));
    }
    clone.appendChild(body);
    // The empty row whose cells are what actually gets measured. One per
    // visible column, and it goes in FIRST so its cells are the ones the table
    // layout reports against.
    for (i = 0; i < visible.count(); i++) {
      probe.appendChild(document.createElement('td'));
    }
    body.appendChild(probe);
    for (i = 0; i < source.length; i++) {
      // Responsive's own child rows are not data and would skew the widths.
      if (source[i].className.indexOf('child') === -1) {
        body.appendChild(source[i].cloneNode(true));
      }
    }
    // Undo every display/width the live table is carrying so the copy is sized
    // by its content alone -- which is the whole point of the measurement.
    cells = clone.querySelectorAll('th, td');
    for (i = 0; i < cells.length; i++) {
      cells[i].style.display = '';
      cells[i].style.width = 'auto';
      cells[i].style.minWidth = '0';
      cells[i].removeAttribute('name');
    }
    if (this.c.details && this.c.details.type === 'inline') {
      // The control column is wider with the expand affordance on it, and the
      // original adds these to its copy unconditionally too.
      clone.className += ' dtr-inline collapsed';
    }
    row = document.createElement('div');
    row.style.cssText = 'width:1px;height:1px;overflow:hidden;clear:both';
    row.appendChild(clone);
    node.parentNode.insertBefore(row, node);
    for (i = 0; i < probe.children.length; i++) {
      // s.columns is indexed by COLUMN, the probe by visible position, and the
      // two only coincide while nothing is hidden.
      columns[dt.column.index('fromVisible', i)].minWidth =
        probe.children[i].offsetWidth || 0;
    }
    row.parentNode.removeChild(row);
  };
}
/**
 * Stop DataTables Responsive re-measuring every loaded row on every adjust.
 *
 * Responsive answers `column-sizing` -- which every columns.adjust() fires --
 * by running _resizeAuto(), and _resizeAuto() deep-clones the header, the
 * footer and EVERY row of the current page into a hidden table so the browser
 * will compute each column's natural width. With Scroller the "current page"
 * is every row fetched so far, so the cost is linear in the size of the list.
 * Measured on a 86-host list: 8 calls per load at ~140ms each, of which only
 * 15ms is building the clone -- the other ~125ms is the layout the browser is
 * forced into to answer offsetWidth.
 *
 * Most of those calls cannot produce a new answer. The widths _resizeAuto()
 * reads come only from the markup it clones; it measures with the table at
 * width:auto inside a 1px hidden box, so the viewport is not an input. Five of
 * the eight calls returned a vector byte-identical to the call before them.
 *
 * So sign exactly what gets cloned -- the table node's class list, the visible
 * column set, and the header/body/footer HTML -- and when the signature is
 * unchanged, restore the previous answer instead of re-measuring. Signing the
 * markup rather than a draw counter is what keeps this correct: Responsive
 * converges over two passes, adding `dtr-control` to the first column between
 * them, and that genuinely does change the answer. A coarser key would cache
 * the pre-convergence widths and leave the column permanently 17px narrow.
 *
 * The class list is sorted before signing because addClass()/removeClass()
 * reorder the tokens without changing what renders, and that alone defeated
 * the cache twice per load.
 *
 * The signature costs ~1.5ms against the ~140ms it skips. Nothing else caches
 * it, so a stale entry cannot outlive the table.
 *
 * Dropped on window resize: the markup does not change when the browser zooms
 * or the font size does, but the metrics do.
 *
 * @return {void}
 */
function fogMemoizeResponsiveMeasure() {
  var Responsive = $.fn.dataTable ? $.fn.dataTable.Responsive : null;
  if (!Responsive || !Responsive.prototype ||
    typeof Responsive.prototype._resizeAuto !== 'function' ||
    Responsive.prototype._fogMemoized
  ) {
    return;
  }
  Responsive.prototype._fogMemoized = true;
  // Swap in the cheap measurement first, so what the memo wraps -- and what
  // runs on a cache MISS -- is the fast one. Order matters: the memo must stay
  // the outer layer or a hit would still pay for a measurement.
  var original = fogFastResizeAuto(Responsive.prototype._resizeAuto);
  Responsive.prototype._resizeAuto = function() {
    var dt = this.s ? this.s.dt : null,
      node = dt ? dt.table().node() : null,
      footer,
      signature,
      i;
    // No table to sign: fall through untouched rather than guess.
    if (!node) {
      return original.apply(this, arguments);
    }
    footer = dt.table().footer();
    signature = String(node.className).split(/\s+/).sort().join(' ') + '\u0000' +
      dt.columns().indexes().filter(function(index) {
        return dt.column(index).visible();
      }).toArray().join(',') + '\u0000' +
      dt.table().header().innerHTML + '\u0000' +
      dt.table().body().innerHTML + '\u0000' +
      (footer ? footer.innerHTML : '');
    if (this._fogSignature === signature && this._fogMinWidths) {
      for (i = 0; i < this._fogMinWidths.length; i++) {
        // s.columns is the array _resizeAuto() writes minWidth into, and is
        // what _resize() reads a moment later to pick the columns to drop.
        if (this.s.columns[i]) {
          this.s.columns[i].minWidth = this._fogMinWidths[i];
        }
      }
      return;
    }
    var result = original.apply(this, arguments);
    this._fogSignature = signature;
    this._fogMinWidths = $.map(this.s.columns, function(column) {
      return column.minWidth;
    });
    return result;
  };
  $(window).on('resize.fogresponsivememo', function() {
    $('table.dataTable').each(function() {
      if (!$.fn.dataTable.isDataTable(this)) {
        return;
      }
      var settings = $(this).DataTable().settings()[0];
      if (settings && settings._responsive) {
        settings._responsive._fogSignature = null;
      }
    });
  });
}
/**
 * Is a scrolling table already sized correctly for the box it is in?
 *
 * columns.adjust() is the expensive half of every sizing pass -- it fires
 * column-sizing, which Responsive answers by re-measuring the whole loaded
 * page of rows -- and on a normal page load DataTables has already run it
 * several times of its own accord before FOG's .app-main ResizeObserver ever
 * fires: once when Responsive is constructed, once when the first response
 * makes the body overflow, once when Responsive settles which columns fit, and
 * once at init complete. That observer also fires for HEIGHT changes, and the
 * rows rendering is a height change, so the pass it wakes was re-doing work
 * the library had already done: ~50ms on an 86-row host list, for a set of
 * widths that came out byte-identical.
 *
 * What could not be answered by asking "have the inputs changed since last
 * time" is that the library's own adjust is sometimes not the last word.
 * Below the sidebar breakpoint the vertical scrollbar appears as a CONSEQUENCE
 * of the adjust that sized the table, so DataTables leaves the body table 15px
 * wider than the viewport it sits in and the pass here is what converges it.
 * An input-based gate skipped that and left the grid with a horizontal
 * scrollbar at 860px and 700px (771px of table in a 756px body).
 *
 * So ask the question directly instead. Two things have to hold, and if they
 * do, another adjust cannot produce a different answer:
 *
 *  - the body table exactly fills the scroll body's client area, which is what
 *    goes wrong when a scrollbar appears or disappears under it;
 *  - every header cell is the same width as the body cell beneath it, which is
 *    the misalignment the whole sizing path exists to prevent.
 *
 * Both are read after the max-height write, since that is an input to whether
 * the scrollbar is there at all.
 *
 * A table with no split (a paged one) has nothing to prove either way, so it
 * says no and is adjusted as before.
 *
 * @param {object} dt the DataTables API for the table
 *
 * @return {boolean} true when an adjust would be a no-op
 */
function fogColumnsAligned(dt) {
  var container = dt.table().container(),
    scrollBody = $('div.dt-scroll-body', container)[0],
    head = $('div.dt-scroll-head table', container)[0],
    body = $('div.dt-scroll-body table', container)[0],
    headCells,
    bodyCells,
    i;
  if (!scrollBody || !head || !body) {
    return false;
  }
  // clientWidth is an integer and the table's box is not, so a pixel of slack:
  // the mismatch this is looking for is a whole scrollbar wide.
  if (Math.abs(body.getBoundingClientRect().width - scrollBody.clientWidth) > 1) {
    return false;
  }
  // No rows, or the "no matching records" placeholder (one cell spanning the
  // lot): no column boundaries to be out of line.
  if (body.querySelector('tbody td.dt-empty')) {
    return true;
  }
  headCells = head.querySelectorAll('thead tr:first-child > th, thead tr:first-child > td');
  bodyCells = body.querySelectorAll('tbody tr:first-child > th, tbody tr:first-child > td');
  if (!bodyCells.length) {
    return true;
  }
  if (headCells.length !== bodyCells.length) {
    return false;
  }
  for (i = 0; i < headCells.length; i++) {
    if (Math.abs(headCells[i].getBoundingClientRect().width -
      bodyCells[i].getBoundingClientRect().width) > 1
    ) {
      return false;
    }
  }
  return true;
}
function fogSizeScroller(dt, release) {
  // dt.init() can be null for nodes the table.dataTable selector also matches
  // but that aren't fully-initialized Scroller tables (e.g. the scrollY split
  // table's cloned header). Guard it so we skip them instead of throwing on
  // null.scroller, which would abort the caller's loop before the real table.
  var init = (dt && typeof dt.init === 'function') ? dt.init() : null;
  if (!init || !init.scroller) {
    return; // only Scroller-enabled tables
  }
  // container() is null for a table whose wrapper is not in the document
  // yet. registerTable() calls this on a setTimeout(0) right after init, and
  // a grid built into a node that is still being attached -- a DataTables
  // child row is the case that found this -- gets here before its wrapper
  // lands. Guarded for the same reason init is above: `outer` below falls
  // back to `container`, so a null one reached getBoundingClientRect() and
  // threw an uncaught TypeError out of the timeout. Skipping is right rather
  // than merely safe -- there is nothing to measure against yet, and
  // whatever attaches the node re-runs the sizing pass once it has.
  var container = dt.table().container();
  if (!container) {
    return;
  }
  var body = $('div.dt-scroll-body', container);
  if (!body.length || !body.is(':visible')) {
    return; // not rendered, or in a hidden tab
  }
  // Measure to the bottom of the CARD, not of the DataTables container.
  //
  // The container ends at the info line ("Showing 1 to 21 of 86 entries").
  // The card carries a FOOTER below that holding the toolbar -- Delete
  // selected, Mass edit, Queue Task, Add to group, Add -- and sizing the
  // scroll body against the container therefore pushed exactly that footer's
  // height off the bottom of the window. The one card on the page that is
  // supposed to fit was the one whose buttons you could not reach without
  // scrolling, and the taller the window the more rows Scroller asked for, so
  // it never came back into view on its own.
  //
  // belowBody is a DISTANCE between two edges of the same box, so it is
  // correct even while the card is currently overflowing -- which it is, at
  // the moment this runs, on every load. Falls back to the container when
  // there is no card ancestor (a bare grid in a modal or a report).
  var outer = $(container).closest('.card')[0] || container;
  var bodyRect = body[0].getBoundingClientRect(),
    belowBody = outer.getBoundingClientRect().bottom - bodyRect.bottom,
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
  // Recompute Scroller's virtual viewport for the new height.
  //
  // measure() redraws unless told not to, and on a server-side table a redraw
  // is a full round trip plus a re-render of every loaded row. This function
  // runs at least three times on a normal page load -- the deferred first pass
  // below registerTable(), the one-shot post-draw re-adjust above, and the
  // .app-main ResizeObserver firing once the rows have changed the layout --
  // so honoring the default cost the host list four identical fetches of the
  // same 86 rows and ~2.4s before the grid settled, against 22ms of server
  // time. Measured 2026-08-29 on 10.255.20.1.
  //
  // The only thing measure() changes that a redraw is needed for is the page
  // length: viewportRows * displayBuffer, derived from the height just set. So
  // measure without redrawing, and redraw only when the new height genuinely
  // asks for MORE rows than are already loaded. A shorter viewport needs none
  // -- the rows for it are already there.
  if (dt.scroller && typeof dt.scroller.measure === 'function') {
    var lenBefore = dt.page.len();
    dt.scroller.measure(false);
    if (dt.page.len() > lenBefore) {
      fogFetchIfShort(dt);
    }
  }
  // Re-sync the scrollY header/body column widths. measure() only recomputes
  // the virtual viewport height, so a table first laid out in a hidden tab
  // keeps its zero-width header/body split (header narrow, body full-width)
  // until the columns are adjusted once it becomes visible.
  //
  // Gated on fogColumnsAligned(), because this is the expensive half and most
  // calls have nothing to do -- see that function for what "nothing to do"
  // means and how it is decided.
  //
  // The colgroup widths have to be released before the adjust to let the table
  // narrow (see fogReleaseColWidths), so that is done here rather than by the
  // caller -- releasing them and then skipping the adjust would leave the
  // table on auto widths.
  if (fogColumnsAligned(dt)) {
    return;
  }
  if (release) {
    fogReleaseColWidths(dt.table().node());
  }
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
/**
 * Hold a scrolling table still while its container is resizing.
 *
 * DataTables pins the .dt-scroll-head table's width in a style attribute; the
 * body table is width:100%. So the instant the container's width changes the
 * body tracks it and the header does not, and makeColumnsResizable() has put
 * table-layout:fixed over a shared colgroup, so the body shares its surplus out
 * across its columns while the header does not. Nothing puts the two back in
 * step until the debounced columns.adjust() in fogBindTableAutosize() runs.
 *
 * That is a long time to be wrong. Collapsing the sidebar animates .app-main
 * over ~290ms, the 150ms debounce restarts on every frame of it, and the adjust
 * is not free -- so the host list sat with a 1246px header against a 1496px
 * body, columns up to 37px out of line, until t=735ms. It is the misalignment
 * you can watch happen: the headers stay put and the rows walk right.
 *
 * Freezing the body at the header's width for the duration is what fixes it.
 * The two candidates were measured against each other: releasing the header's
 * pinned width so it grows with the body equalises the two TOTALS (250px of
 * mismatch down to 1.5px) but leaves the columns 36.75px out, because the
 * surplus is still shared out differently in a fixed-layout header than in the
 * body -- and additionally releasing the colgroups is far worse (86px), since
 * two tables left to size themselves from their own content never agree, which
 * is the whole reason this sizing path exists. Freezing measured 0.1px.
 *
 * So nothing moves until the adjust lands, and then it moves once, correctly.
 * Cheap on purpose: this runs per animation frame, so it must not call
 * columns.adjust(), which fires column-sizing and makes Responsive re-measure
 * every loaded row.
 *
 * @param {object} dt  the DataTables API for the table
 *
 * @return {void}
 */
function fogHoldBodyWidth(dt) {
  var container = dt.table().container(),
    head = $('div.dt-scroll-head table', container),
    body = $('div.dt-scroll-body table', container);
  if (!head.length || !body.length) {
    return; // not a scrolling table: one table, no split to keep in step
  }
  body.css('width', head[0].getBoundingClientRect().width + 'px');
}
/**
 * Undo fogHoldBodyWidth(), so the adjust that follows measures the real layout
 * rather than the width we pinned to stop it flickering.
 *
 * @param {object} dt  the DataTables API for the table
 *
 * @return {void}
 */
function fogReleaseBodyWidth(dt) {
  $('div.dt-scroll-body table', dt.table().container()).css('width', '');
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
    // Whatever fogHoldBodyWidth() pinned was there to stop the body drifting
    // away from the header mid-resize. It must go before we measure, or the
    // adjust below re-derives the layout from our own placeholder.
    fogReleaseBodyWidth($(this).DataTable());
    var dt = $(this).DataTable(),
      init = (dt && typeof dt.init === 'function') ? dt.init() : null;
    // Null init: a node the table.dataTable selector matches but that isn't a
    // table of its own (the scrollY cloned header). Nothing to size.
    if (!init) {
      return;
    }
    if (init.scroller) {
      // Height as well as width, and it does its own visibility check. The
      // width release goes with it rather than happening here: it is only
      // correct paired with the adjust that follows, and fogSizeScroller() is
      // what decides whether that adjust is needed at all.
      fogSizeScroller(dt, true);
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
  var debounce,
    lastWidth = null;
  function adjustSoon() {
    // Hold the body to the header's width first, synchronously, so the pair
    // cannot drift apart while the container moves. The debounce below is what
    // keeps the expensive re-measure off the per-frame path, but it also means
    // the mismatch would otherwise be on screen for the whole of the change
    // plus 150ms plus the adjust.
    //
    // Only on a WIDTH change. This observer also fires for height changes --
    // notably when the rows first render, which is not a resize at all -- and
    // pinning there would fight the initial layout. Width is the only input to
    // the header/body mismatch.
    var main = document.querySelector('.app-main'),
      width = main ? Math.round(main.getBoundingClientRect().width) : null;
    if (width !== null && width !== lastWidth) {
      lastWidth = width;
      $('table.dataTable').each(function() {
        if ($.fn.dataTable.isDataTable(this)) {
          fogHoldBodyWidth($(this).DataTable());
        }
      });
    }
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
/**
 * Suffix FOGManagerController::UNADJUSTED_SUFFIX appends to a date column's
 * key when that row's value predates this server's move to UTC.
 */
var FOG_UNADJUSTED_SUFFIX = '__unadjusted';

/**
 * Puts the "not converted" marker on the cells the server flagged.
 *
 * The flag arrives as a SIBLING key on the row -- `hostLastDeploy` plus
 * `hostLastDeploy__unadjusted` -- and the glyph is added to the cell's DOM
 * here rather than to the value on the server. That split is the whole point.
 * A DataTables cell's data is escaped into the cell, is what sorting and
 * filtering compare, and is what the CSV/Excel buttons export; markup put
 * there prints as tags and rides into the download (GH-1245, GH-1446). Adding
 * it to the DOM after the fact leaves all three untouched -- and sorting on
 * the raw value is the honest behavior anyway, because within the
 * pre-boundary era the values are mutually consistent with each other.
 *
 * rowCallback rather than a columnDefs render for the same reason: a global
 * render would also have to be the render for every column, and ten list
 * pages define their own.
 *
 * @param {object} api  the DataTables API for the table
 * @param {Node}   tr   the row's <tr>
 * @param {object} data the row's data object
 * @param {string} note the sentence to show on hover, from the payload
 *
 * @return {void}
 */
function fogMarkUnadjusted(api, tr, data, note) {
  if (!note || !data || typeof data !== 'object') {
    return;
  }
  var columns = api.settings()[0].aoColumns,
    rowIndex = api.row(tr).index(),
    suffixLen = FOG_UNADJUSTED_SUFFIX.length,
    key,
    base,
    i,
    cell;
  for (key in data) {
    if (!data[key] || key.slice(-suffixLen) !== FOG_UNADJUSTED_SUFFIX) {
      continue;
    }
    base = key.slice(0, -suffixLen);
    for (i = 0; i < columns.length; i++) {
      if (columns[i].data !== base) {
        continue;
      }
      cell = api.cell(rowIndex, i).node();
      // A hidden column -- Responsive collapsed it, or column visibility
      // turned it off -- has no node to decorate. Responsive redraws the
      // child row from the cell data, which is exactly the value without
      // the glyph, so the marker is a visible-column affordance and the
      // tooltip is where the explanation lives.
      if (!cell || $(cell).find('.fog-unadjusted').length) {
        continue;
      }
      $(cell).append(
        $('<span class="fog-unadjusted text-muted ms-1"'
          + ' data-bs-toggle="tooltip" data-container="body">*</span>')
          .attr('title', note)
      );
    }
  }
}

$.fn.registerTable = function(onSelect, opts) {
  opts = opts || {};

  // Idempotent; every grid calls this and only the first does any work.
  fogMemoizeResponsiveMeasure();

  // Resolved on the first rowCallback; see the note there.
  var unadjustedTable = null;

  // Which columns are Description, filled in once opts and the defaults have
  // been merged. Declared here so stateLoadCallback below closes over it --
  // that callback is defined before the answer is known and runs long after.
  var descriptionColumns = [];

  // Default row count comes from FOG_VIEW_DEFAULT_SCREEN (hidden #pageLength).
  var pageLength = parseInt($('#pageLength').val());

  // That setting arrives as a STRING, and until schema step 397 a fresh
  // install seeded it as the page name 'SEARCH' -- step 17 pre-dates the
  // setting being repurposed into a row count. parseInt() answers NaN, and
  // `pageLength: NaN` is not something DataTables catches: it is only the
  // infinite-scroll branch below that ever had a fallback, so classic paging
  // took the NaN straight through. Normalize here so neither mode depends on
  // the setting being sane.
  //
  // -1 is left alone -- it is the legitimate "All" from the length menu.
  // Scroller cannot use it, but that is the branch below's problem and it
  // already handles it.
  if (isNaN(pageLength) || pageLength === 0 || pageLength < -1) {
    pageLength = 25;
  }

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
    // Marks any date the server flagged as predating this install's move to
    // UTC. The note itself rides the payload (see the xhr handler below), so
    // this is a no-op on an install that has not crossed the boundary and on
    // every client-side table, neither of which sends one.
    rowCallback: function(tr, data) {
      // The description is the row's tooltip rather than a column of its own
      // -- see fogDescriptionColumns(). A native `title` rather than a
      // Bootstrap tooltip on purpose: this runs for every row of every draw,
      // and a 500-row grid has no business building 500 tooltip instances for
      // text nobody may hover. The clipped-cell handler further down sets a
      // title on the CELL, which correctly wins over this one where a cell's
      // own content is what has been cut off.
      if (data && typeof data.description === 'string' && data.description) {
        tr.title = data.description;
      }
      // Reached through the callback's own `this`, which DataTables sets to
      // the table's jQuery instance, and NOT through the row: rowCallback runs
      // while the row is still DETACHED -- DataTables builds every <tr>, fires
      // this for each, and attaches the tbody afterward -- so
      // $(tr).closest('table') matches nothing and .DataTable() hands back an
      // API with no settings behind it. Reading settings()[0] off that threw
      // "Cannot read properties of undefined" on the first ajax draw of every
      // grid, and the throw aborted the draw: one row rendered, the
      // header/body split never sized, and nothing but the console said so.
      //
      // Cached because it is otherwise a settings-array scan per row.
      if (!unadjustedTable) {
        unadjustedTable = this.api();
      }
      var note = unadjustedTable.settings()[0].fogUnadjustedNote;
      // No note means nothing to mark: an install that has not crossed the
      // boundary does not send one, and neither does a client-side table.
      if (!note) {
        return;
      }
      fogMarkUnadjusted(unadjustedTable, tr, data, note);
    },
    searching: true,
    ordering: true,
    info: true,
    // Drag a heading to move a column. Off until now, which meant the saved
    // state below had no positions to save -- "customise the layout" and
    // "remember the layout" are one feature, not two.
    colReorder: true,
    // Column order, which columns are showing, page length and sort now
    // persist per user, through the preference store rather than
    // localStorage -- see stateSaveCallback below. Searches and the row
    // SELECTION are deliberately NOT part of it; fogStripVolatileState() says
    // why. The selection in particular is put there by the Select extension
    // itself, not by anything here, so it has to be taken back out.
    stateSave: true,
    // Long enough that a layout survives a holiday. DataTables discards a
    // state older than this, which is the escape hatch if a saved layout ever
    // goes bad: it ages out rather than following someone forever.
    stateDuration: 60 * 60 * 24 * 365,
    stateSaveCallback: function(settings, data) {
      var key = fogStateKey(settings);
      var value;
      // Record WHICH COLUMN is sorted, not just its index. DataTables and
      // ColReorder disagree about which frame a saved order index is in, and
      // the sort lands on a different column after a reload once anything has
      // been dragged -- reproduced with stock DataTables 2.0.8, ColReorder
      // 2.0.3 and the library's own localStorage callbacks, so it is not
      // ours to fix here. The key survives any reordering, and fogApplyOrder()
      // puts the sort back where it belongs once the table is up.
      data.fogOrder = fogOrderKeys(settings, data.order);
      // Hand-set column widths, as each column's SHARE of the table keyed by
      // column name -- see fogColWidthStore. Absent on a table nobody has
      // resized, and JSON.stringify drops the key entirely in that case, so
      // this costs a saved state nothing until someone drags a border.
      data.fogColWidths = fogColWidthStore[settings.sTableId];
      value = JSON.stringify(fogStripVolatileState(data));
      if (!key) {
        return;
      }
      // Written to both. localStorage is what makes the layout survive the
      // reload you are about to do even if the server call is still in
      // flight or the session has expired; the preference is what makes it
      // follow you to another machine. The server wins on load.
      try {
        window.localStorage.setItem(key, value);
      } catch (e) {}
      fogPrefStore(key, value);
    },
    stateLoadCallback: function(settings, callback) {
      var key = fogStateKey(settings);
      var local = null;
      // RETURN UNDEFINED, ALWAYS. DataTables waits for the callback only when
      // this function returns undefined -- `void 0 !== ret && useIt(ret)`. Any
      // other return value, null included, is taken as the state itself, so
      // the table loads "no saved layout" and the answer this function is
      // waiting for arrives too late to be used. Nothing errors; the feature
      // just silently does not work.
      if (!key) {
        callback(null);
        return;
      }
      try {
        local = window.localStorage.getItem(key);
      } catch (e) {}
      fogPrefFetch(key, function(err, value) {
        var raw = (!err && value) ? value : local;
        if (!raw) {
          callback(null);
          return;
        }
        try {
          // Strip on the way IN as well. A value stored by an older release
          // -- or by localStorage before this shipped -- can carry a saved
          // search, and restoring one invisibly is the failure this whole
          // arrangement is written to avoid.
          var state = fogStripVolatileState(JSON.parse(raw));
          // Put the column widths back before the table exists, which is what
          // makes them available to the very first sizing pass: DataTables
          // waits for this callback, and makeColumnsResizable() reads the
          // store rather than the state.
          if (state.fogColWidths) {
            fogColWidthStore[settings.sTableId] = state.fogColWidths;
          }
          callback(fogHideDescriptionState(descriptionColumns, state));
        } catch (e) {
          // A corrupt or truncated value means "no saved layout", not a
          // broken table. JSON.parse throwing here would otherwise take out
          // the whole grid.
          callback(null);
        }
      });
      // Asynchronous: see the note above -- undefined is what makes
      // DataTables wait for the callback rather than acting on the return.
      return;
    },
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
      savedFiltersButton,
      columnSearchButton,
      // Which columns are showing has been SAVED per user since the layout
      // work -- it rides in the same state as column order, page length and
      // sort -- but nothing on a management grid ever let anyone change it.
      // The control existed only on the export and report toolbars, so the
      // preference could be restored and never set. Same definition as those
      // two, deliberately: three copies of one button that behaved
      // differently would be worse than the gap.
      {
        extend: 'colvis',
        // See the note on the export toolbar's copy of this button.
        columns: ':not(.noVis)',
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

  // Page-specific buttons, APPENDED to the shared set rather than replacing
  // it. Passing `buttons` in opts cannot do this: fogDefaults() is a shallow
  // merge, so a page that supplied its own array would silently lose Select
  // All, the search builder, saved filters, column search, column visibility
  // and Refresh. Pulled off opts for the same reason columnResize is --
  // DataTables has no such option and would only carry it around.
  if (opts.extraButtons && opts.extraButtons.length) {
    defaults.buttons = defaults.buttons.concat(opts.extraButtons);
  }
  delete opts.extraButtons;

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

  // Description becomes the row tooltip on every grid, not a column on any of
  // them. Applied after the merge so it covers the columns the caller passed.
  descriptionColumns = fogDescriptionColumns(opts.columns);

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
    // Stashed on SETTINGS, which is the one object that is the table. A
    // DataTables Api is constructed fresh per call, so a property set on one
    // is gone by the next -- and rowCallback cannot close over the note
    // anyway, because it is defined before the first response exists and runs
    // after every one. Read before the _searchtypes guard below: a grid can
    // carry unadjusted dates whether or not the server typed its columns.
    if (json && typeof json._unadjustednote === 'string') {
      settings.fogUnadjustedNote = json._unadjustednote;
    }
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

    // The other front end onto the same filtering: a row of boxes under the
    // header. Built here for the same reason the types are applied here --
    // this response is the first moment the server has said what each column
    // is, and a box whose condition list did not match its column would be
    // offering filters the server then drops.
    //
    // Rebuilt on every response rather than once: DataTables re-renders the
    // header whenever it recalculates widths, and in scrolling mode the
    // header lives in a clone, so the row cannot be assumed to have survived.
    // The values are carried across from the DataTables column state, which
    // is the thing that actually holds the search.
    var api = new $.fn.dataTable.Api(settings);
    // Stashed so the column-visibility rebuild has them; that fires with no
    // response of its own.
    settings._fogSearchTypes = types;
    fogColumnSearchRow(api, types);
    fogColumnSearchRestore(api);
    api.buttons('colsearch:name').enable();
    // Only once. This handler runs on every response, and re-applying would
    // fight a user who closed the row and then changed a page.
    if (!settings._fogAffordancesDone) {
      settings._fogAffordancesDone = true;
      fogAffordanceRestore(api, 'searchrow', function() {
        $(api.table().container()).addClass('fog-colsearch-on');
      });
    }
  });

  var table = $(this).DataTable(opts);

  // Delegated off the table's container so the handlers survive the row being
  // rebuilt -- which it is on every draw, and again whenever a column is
  // shown or hidden. 'input' rather than 'change' so typing filters as you go;
  // debounced because each keystroke is a server round trip.
  //
  // Deferred, and that is the whole point of the function: with server-side
  // data DataTables does not build the wrapper until the first response has
  // arrived, so at the moment DataTable() returns, table().container() is
  // still null. Binding there binds to an EMPTY jQuery set -- silently, since
  // .on() on nothing is not an error -- and the result is a search row that
  // renders, accepts typing, and filters nothing.
  //
  // Called twice on purpose. Straight away covers registerTable() being
  // re-run over an already-initialized table (retrieve:true), where the
  // container exists and no further init event will ever fire; on init.dt
  // covers the normal first run. The .off() makes the overlap harmless.
  var columnSearchTimer = null;
  function fogBindColumnSearch() {
    var container = table.table().container();
    if (!container) {
      return;
    }
    $(container)
      .off('.fogcolsearch')
      .on('change.fogcolsearch', 'select.fog-colsearch-mode', function() {
        fogColumnSearchApply(table, $(this).closest('div.fog-colsearch'));
      })
      .on('change.fogcolsearch', 'input.fog-colsearch-input[type="date"]', function() {
        fogColumnSearchApply(table, $(this).closest('div.fog-colsearch'));
      })
      .on('input.fogcolsearch', 'input.fog-colsearch-input[type="text"]', function() {
        var group = $(this).closest('div.fog-colsearch');
        clearTimeout(columnSearchTimer);
        columnSearchTimer = setTimeout(function() {
          fogColumnSearchApply(table, group);
        }, 400);
      })
      .on('keydown.fogcolsearch', 'input.fog-colsearch-input', function(e) {
        if (e.which !== 13) {
          return;
        }
        // Enter means "now", not "also submit the form this table sits in".
        e.preventDefault();
        clearTimeout(columnSearchTimer);
        fogColumnSearchApply(table, $(this).closest('div.fog-colsearch'));
      });
  }
  fogBindColumnSearch();
  $(this)
    .off('init.dt.fogcolsearch')
    .on('init.dt.fogcolsearch', fogBindColumnSearch);

  // A hidden column's box has to go with it, and the remaining boxes have to
  // re-line-up: the header only carries cells for visible columns, so every
  // box after the hidden one would otherwise be pointed one column off.
  // column-visibility for the obvious reason; column-sizing because
  // DataTables re-renders the header (and in scrolling mode re-clones it)
  // whenever it recalculates widths, which drops anything appended to it;
  // column-reorder because dragging a heading changes which column each
  // cell position is showing, and a box left where it was would then be
  // filtering a different column than the one above it.
  // Both are cheap when nothing has changed -- the builder returns early if
  // the row it finds already describes the right columns.
  // init and draw are here for the saved layout: ColReorder applies a restored
  // column order after the first response, so the row built by the xhr handler
  // above describes the layout as it was BEFORE the restore. Without these two
  // the boxes stay pinned to that stale layout and quietly filter the wrong
  // columns for the rest of the visit.
  table.on('init.dt', function() {
    fogApplyOrder(table);
  });
  table.on(
    'column-visibility.dt column-sizing.dt column-reorder.dt init.dt draw.dt',
    function() {
        var types = table.settings()[0]._fogSearchTypes;
        if (!types) {
            return;
        }
        fogColumnSearchRow(table, types);
        fogColumnSearchRestore(table);
    }
  );

  if (columnResize) {
    var tableNode = $(this);
    // Bound to column-sizing rather than draw: DataTables rebuilds the header
    // (and, in scroll mode, re-clones it) whenever it recalculates widths --
    // an ajax load, a tab becoming visible, a Responsive collapse -- and the
    // strips go with it. draw fires on every Scroller redraw, which would mean
    // re-measuring the header on every scroll tick for nothing.
    table.on('column-sizing.dt', function() {
      tableNode.makeColumnsResizable(table);
    });
    // First pass deferred: at this point an ajax table has not drawn yet and
    // the scroll header clone may not exist.
    setTimeout(function() {
      tableNode.makeColumnsResizable(table);
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
 * Keeps Bootstrap's dropdown keyboard handler out of DataTables collections.
 *
 * Bootstrap 5.3 registers one keydown handler on `document`, delegated to any
 * `.dropdown-menu`. The DataTables Bootstrap 5 integration builds a button
 * collection -- Column Visibility, Export -- as `<ul class="dropdown-menu">`
 * inside `div.dt-button-collection`, with no `[data-bs-toggle="dropdown"]`
 * anywhere near it, because DataTables opens and closes the collection itself.
 *
 * On Escape, ArrowUp or ArrowDown that handler calls preventDefault() and then
 * goes looking for the toggle it assumes exists: the menu itself, a previous
 * sibling, a next sibling, and finally
 * findOne(toggle, event.delegateTarget.parentNode). The delegate target is
 * `document`, whose parentNode is null, so that last lookup calls querySelector
 * on null and throws "Illegal invocation" -- after the preventDefault has
 * already landed. Inside an open collection that is a console error on each of
 * those three keys, plus arrow keys that no longer scroll the list.
 *
 * On `window`, in the capture phase, and both halves matter. Bootstrap's
 * EventHandler passes its `isDelegated` flag straight through as
 * addEventListener's capture argument, so every delegated Bootstrap handler is
 * a CAPTURE listener on `document` -- registered when the bundle loaded, which
 * is before this file. Same node, same phase, so registration order wins and
 * `document` capture is already too late: it runs second, stops the event, and
 * Bootstrap has thrown regardless. `window` is the one position ahead of it,
 * because the propagation path starts there.
 *
 * stopPropagation() at the top of the path halts the event outright, including
 * at `body`, which is where DataTables binds the collection's own handlers.
 * That is why the key list is exactly these three: DataTables traps Tab on
 * keydown and dismisses on Escape KEYUP, so both still reach it untouched, and
 * nothing else listens for arrows in here. The default action is untouched, so
 * the arrows go back to scrolling the panel.
 *
 * A collection open inside a modal therefore takes Escape for itself: the
 * collection closes on the keyup and the modal stays, which is what you want
 * from one dismissable nested in another.
 *
 * Scoped to .dt-button-collection so a real Bootstrap dropdown -- which has a
 * toggle and works correctly -- keeps its keyboard navigation.
 */
(function () {
  var SUPPRESSED = ['Escape', 'ArrowUp', 'ArrowDown'];

  window.addEventListener('keydown', function (e) {
    if (SUPPRESSED.indexOf(e.key) === -1) {
      return;
    }
    var target = e.target;
    if (!target || !target.closest) {
      return;
    }
    var menu = target.closest('.dropdown-menu');
    if (!menu || !menu.closest('div.dt-button-collection')) {
      return;
    }
    e.stopPropagation();
  }, true);
})();

/**
 * AdminLTE's card tools, re-armed for FOG's AJAX navigation.
 *
 * adminlte4 binds them exactly once, inside its own DOMContentLoaded:
 *
 *   document.querySelectorAll('[data-lte-toggle="card-collapse"]')
 *           .forEach(el => el.addEventListener('click', ...))
 *
 * That is not delegated, and doPageLoad() replaces #ajaxPageWrapper wholesale
 * on every sidebar click -- so every card that arrives by navigation, which is
 * almost every card anyone ever sees, has a dead toggle. The three tools are
 * all bound that way: collapse, remove and maximize.
 *
 * It went unreported for as long as it did because a card that starts OPEN
 * only degrades to "cannot be collapsed", which reads as a UI nobody uses. A
 * card rendered `collapsed-card` is the loud version: its body is
 * display:none from AdminLTE's own CSS and the only thing that removes the
 * class is the listener that was never attached, so the content is
 * unreachable. That is how it was found (GH-1600).
 *
 * CAPTURE phase, deliberately. The handler has to run before the element's own
 * listener whether or not AdminLTE managed to attach one, because a card that
 * WAS present at DOMContentLoaded still carries it -- and two handlers both
 * calling toggle() would collapse and expand in the same click, leaving the
 * button looking just as dead. stopPropagation() during capture keeps the
 * event from reaching the target at all, so exactly one of the two runs and it
 * is this one. That also means the fix does not depend on script order, on
 * which cards were present when, or on AdminLTE keeping its current binding.
 *
 * Not re-running AdminLTE's own initializer after each page load: it is not
 * exported, and re-running it would re-bind the persistent chrome outside
 * #ajaxPageWrapper once per navigation.
 */
(function () {
  var METHODS = {
    'card-collapse': 'toggle',
    'card-remove': 'remove',
    'card-maximize': 'toggleMaximize'
  };
  var TOOLS = '[data-lte-toggle="card-collapse"],'
    + '[data-lte-toggle="card-remove"],'
    + '[data-lte-toggle="card-maximize"]';

  document.addEventListener('click', function (e) {
    var target = e.target;
    if (!target || !target.closest) {
      return;
    }
    var tool = target.closest(TOOLS);
    if (!tool) {
      return;
    }
    // If AdminLTE is not loaded there is nothing to take over, and swallowing
    // the click would be worse than leaving it alone -- the login page loads a
    // different, smaller asset list and has no cards at all.
    if (!window.adminlte || typeof window.adminlte.CardWidget !== 'function') {
      return;
    }
    e.preventDefault();
    e.stopPropagation();
    // The button, not e.target, which is the <i> whenever the tool carries an
    // icon. CardWidget resolves .closest('.card') either way, so this is the
    // same answer by a route that does not depend on where the click landed.
    new window.adminlte.CardWidget(tool, {})[METHODS[tool.dataset.lteToggle]]();
  }, true);
})();

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

  /**
   * The boot-file pickers on the host, group, mass edit and settings forms.
   *
   * The text input carries the field name and is the thing that posts; the
   * select has no name at all and only writes into it. That is what makes
   * these fields degrade to a plain text box if this never runs, rather than
   * posting nothing -- and it is why the picker can offer "type it myself"
   * without the server needing a second field to read.
   *
   * Delegated and namespaced, because pages arrive by AJAX and doPageLoad()
   * runs this again on every one of them.
   */
  var setupBootFilePickers = function() {
    var MANUAL = '__fog_manual__'; // FOGPage::BOOT_MANUAL_VALUE
    $(document)
      .off('change.fogBootFile')
      .on('change.fogBootFile', '.fog-bootfile-picker', function() {
        var $picker = $(this),
          $value = $('#' + $picker.data('target')),
          picked = $picker.val();
        if (!$value.length) {
          return;
        }
        if (picked === MANUAL) {
          $value.removeClass('d-none').val('').trigger('focus');
        } else {
          $value.addClass('d-none').val(picked);
        }
        // The server's "not a recognized file" note described the value the
        // form loaded with. Once you change the control it is stale.
        $picker.closest('.fog-bootfile').find('.fog-bootfile-note').remove();
      });
  };

  /**
   * The Local files tab's row actions on Kernel Update / Initrd Update.
   *
   * node=about is sent explicitly. The download endpoints are requested with
   * no node at all, which is why they needed entries in
   * GLOBAL_SUB_OVERRIDES to be permission-checked properly -- these resolve
   * against the page's own node instead, so their permissions are declared
   * where the rest of that page's are.
   *
   * Delegated and namespaced: the pane arrives with the page, by AJAX.
   */
  var setupBootFileActions = function() {
    var post = function(sub, data, confirmText) {
      if (confirmText && !window.confirm(confirmText)) {
        return;
      }
      $.apiCall(
          'post',
          '?node=about&sub=' + sub,
          data,
          function(err) {
            if (!err) {
              // Re-read rather than patch the row in place: the server
              // decides what is now in use as what, and which rows may
              // still offer a Delete button. Guessing that here would be a
              // second copy of those rules.
              //
              // location.reload(), the same way the certificates pane on
              // this page refreshes after a write. The API token pane calls
              // table.ajax.reload() instead, but that is a DataTable and
              // this pane deliberately is not.
              location.reload();
            }
          }
      );
    };

    $(document)
      .off('click.fogBootFileAct')
      .on('click.fogBootFileAct', '.fog-bootfile-keep', function(e) {
        e.preventDefault();
        var $b = $(this);
        post('bootfilekeep', {
          name: $b.data('name'),
          keep: $b.data('keep')
        });
      })
      .on('click.fogBootFileAct', '.fog-bootfile-default', function(e) {
        e.preventDefault();
        var $a = $(this);
        post('bootfiledefault', {
          name: $a.data('name'),
          key: $a.data('key')
        });
      })
      .on('click.fogBootFileAct', '.fog-bootfile-delete', function(e) {
        e.preventDefault();
        var name = $(this).data('name');
        post(
            'bootfiledelete',
            {name: name},
            'Delete ' + name + ' from the boot directory?'
        );
      });
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
  setupBootFilePickers();
  setupBootFileActions();
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
      url: baseURL,
      type: method,
      dataType: 'json',
      cache: false,
      // Body fields, not path segments: a term may contain / ? # or %,
      // none of which survives a trip through the URL path.
      data: function(params) {
        return {q: params.term, limit: resultLimit};
      },
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
    // styles[] already carries its ?ver= (added above, or sent by the
    // server); suffixing it again here produced "path?ver=N?ver=N", which
    // never matched a loaded href, so every navigation appended one more
    // copy of every stylesheet.
    for(var styleIndex in styles){
      var style = styles[styleIndex];
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
