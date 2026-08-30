(function($) {
  // Remembered across visits via the per-user prefs endpoint (fogPrefStore/
  // fogPrefFetch, fog.common.js) -- the same mechanism the DataTables grids
  // use for their own layout state. No schema change: userPrefs is a
  // generic per-user key/value store already wired up for exactly this.
  var LINES_PREF_KEY = 'logviewer.linesToShow',
    REVERSE_PREF_KEY = 'logviewer.reverseOrder';

  var logSelect = $('#logToView'),
    lineSelect = $('#linesToView'),
    reverse = $('#reverse:checkbox'),
    reloadToggle = $('#logreload-toggle'),
    logviewerForm = $('#logviewer-form'),
    logsGoHere = $('#logsGoHere'),
    selectedLog = logSelect.val(),
    selectedLines = lineSelect.val(),
    splitLogItems = selectedLog.split('||'),
    reverseChecked = 0,
    logTimer,
    ip = splitLogItems[0],
    file = splitLogItems[1];

  logviewerForm.on('submit', function(e) {
    e.preventDefault();
  });

  function getLogData(ip, file, length, reversed) {
    var logdata,
      opts = {
        ip: ip,
        file: file,
        lines: length,
        reverse: reversed
      };
    Pace.ignore(function() {
      $.post(
        '../status/logtoview.php',
        opts,
        function(data) {
          logdata = '<pre>' + data + '</pre>';
        },
        'json'
      ).done(function() {
        logsGoHere.html(
          '<div class="card card-primary card-outline">'
          + '<div class="card-header">'
          + '<h4 class="card-title">'
          + file
          + '</h4>'
          + '</div>'
          + '<div class="card-body">'
          + logdata
          + '</div>'
          + '</div>'
        );
      });
    });
    logTimer = setTimeout(function() {
      // Stop polling once this page has been swapped out by an AJAX nav.
      // setTimeout isn't tracked by clearAllIntervals(), so without this the
      // 10s POST to logtoview.php would keep firing forever after you leave.
      if (!document.body.contains(logsGoHere[0])) {
        return;
      }
      getLogData(ip, file, length, reversed)
    }, 10000);
  }

  // Log file handling.
  logSelect.on('change', function(e) {
    e.preventDefault();
    selectedLog = this.value;
    splitLogItems = selectedLog.split('||');
    ip = splitLogItems[0];
    file = splitLogItems[1];
    if (logTimer) {
      clearTimeout(logTimer);
    }
    getLogData(ip, file, selectedLines, reverseChecked);
  });

  // Line handling.
  lineSelect.on('change', function(e) {
    e.preventDefault();
    selectedLines = this.value;
    fogPrefStore(LINES_PREF_KEY, selectedLines);
    if (logTimer) {
      clearTimeout(logTimer);
    }
    getLogData(ip, file, selectedLines, reverseChecked);
  });

  // Reverse file handling.
  reverse.on('change', function(e) {
    if (!e.target.checked) { return; }
    // Present newest first
    e.preventDefault();
    reverseChecked = 1;
    fogPrefStore(REVERSE_PREF_KEY, '1');
    if (logTimer) {
      clearTimeout(logTimer);
    }
    getLogData(ip, file, selectedLines, reverseChecked);
  }).on('change', function(e) {
    if (e.target.checked) { return; }
    // Present oldest first
    e.preventDefault();
    reverseChecked = 0;
    fogPrefStore(REVERSE_PREF_KEY, '0');
    if (logTimer) {
      clearTimeout(logTimer);
    }
    getLogData(ip, file, selectedLines, reverseChecked);
  });

  // Pause/resume the tail. One button, relabeled by the shared helper.
  $.registerReloadToggle(reloadToggle, {
    onPause: function() {
      if (logTimer) {
        clearTimeout(logTimer);
      }
    },
    onResume: function() {
      if (logTimer) {
        clearTimeout(logTimer);
      }
      getLogData(ip, file, selectedLines, reverseChecked);
    }
  });

  // Restore the remembered lines/reverse choice before the first fetch, so
  // returning to the page doesn't silently reset it to the form's defaults.
  // Both reads happen in parallel; the initial fetch waits for whichever
  // preference actually has a stored value.
  $.when(
    $.Deferred(function(d) {
      fogPrefFetch(LINES_PREF_KEY, function(err, value) {
        if (!err && value) {
          selectedLines = value;
          // Reflect the restored value in the widget without re-triggering
          // 'change' -- the initial getLogData call below already uses it.
          lineSelect.val(value);
        }
        d.resolve();
      });
    }),
    $.Deferred(function(d) {
      fogPrefFetch(REVERSE_PREF_KEY, function(err, value) {
        if (!err && value) {
          reverseChecked = value === '1' ? 1 : 0;
          reverse.prop('checked', reverseChecked === 1);
        }
        d.resolve();
      });
    })
  ).done(function() {
    // Start the reading!
    getLogData(ip, file, selectedLines, reverseChecked);
  });
})(jQuery);
