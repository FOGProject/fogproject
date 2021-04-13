(function($) {
  var logSelect = $('#logToView'),
    lineSelect = $('#linesToView'),
    reverse = $('#reverse:checkbox'),
    pauseBtn = $('#logpause'),
    resumeBtn = $('#logresume'),
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
  pauseBtn.prop('disabled', false);
  resumeBtn.prop('disabled', true);

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
          '<div class="box box-primary">'
          + '<div class="box-header with-border">'
          + '<h4 class="box-title">'
          + file
          + '</h4>'
          + '</div>'
          + '<div class="box-body">'
          + logdata
          + '</div>'
          + '</div>'
        );
      });
    });
    logTimer = setTimeout(function() {
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
    if (logTimer) {
      clearTimeout(logTimer);
    }
    getLogData(ip, file, selectedLines, reverseChecked);
  });

  // Reverse file handling.
  reverse.on('ifChecked', function(e) {
    // Present newest first
    e.preventDefault();
    reverseChecked = 1;
    if (logTimer) {
      clearTimeout(logTimer);
    }
    getLogData(ip, file, selectedLines, reverseChecked);
  }).on('ifUnchecked', function(e) {
    // Present oldest first
    e.preventDefault();
    reverseChecked = 0;
    if (logTimer) {
      clearTimeout(logTimer);
    }
    getLogData(ip, file, selectedLines, reverseChecked);
  });

  // Pause Button Clicked.
  pauseBtn.on('click', function(e) {
    e.preventDefault();
    resumeBtn.prop('disabled', false);
    $(this).prop('disabled', true);
    if (logTimer) {
      clearTimeout(logTimer);
    }
  });

  // Resume Button Clicked.
  resumeBtn.on('click', function(e) {
    e.preventDefault();
    pauseBtn.prop('disabled', false);
    $(this).prop('disabled', true);
    if (logTimer) {
      clearTimeout(logTimer);
    }
    getLogData(ip, file, selectedLines, reverseChecked);
  });

  // Start the reading!
  getLogData(ip, file, selectedLines, reverseChecked);
})(jQuery);
