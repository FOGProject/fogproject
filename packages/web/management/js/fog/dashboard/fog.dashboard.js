/**
 * FOG dashboard charts.
 *
 * Renders the four dashboard graphs with Chart.js (replaces the former Flot
 * implementation):
 *   - Storage Group Activity   doughnut, polled every 5 min
 *   - Storage Node Disk Usage  doughnut, polled every 5 min
 *   - Imaging history          line/time, polled every 5 min
 *   - Bandwidth                line/time, live (polled every 2.5 s)
 *
 * Server JSON contracts are unchanged from the Flot version; only the client
 * rendering differs. moment + chartjs-adapter-moment provide the time axis.
 */
(function ($) {
  'use strict';

  var BASE = '../management/index.php?node=home';
  var POLL_SLOW = 300000; // 5 minutes, periodic charts
  var POLL_BW = 2500;     // bandwidth refresh (relaxed from 1s)
  var startTime = new Date().getTime();
  var charts = {};        // container selector -> Chart instance
  var redrawers = {};     // container selector -> rebuild-from-last-data fn

  Chart.defaults.maintainAspectRatio = false;
  Chart.defaults.animation.duration = 0;

  // --- theme -----------------------------------------------------------
  // Dataset colors are explicit and read fine on either theme; only the chart
  // "chrome" (axis tick labels, grid lines, legend text) needs to follow dark
  // mode. Those derive from Chart's global defaults, so set them up front for
  // charts built later, and recolor existing charts on a live theme toggle.
  // text  = axis tick labels (muted is fine against the plot)
  // legend = chart legend labels (needs more contrast than ticks; matches
  //          --fog-text-strong: #444 light / #c8ccd0 dark)
  var CHROME = {
    light: { text: '#666666', legend: '#444444', grid: 'rgba(0,0,0,0.1)' },
    dark:  { text: '#c8ccd0', legend: '#c8ccd0', grid: 'rgba(255,255,255,0.12)' }
  };

  // Mirror the CSS dark-mode rule: data-bs-theme on <html> is the single source
  // of truth (stamped from the cookie, or resolved from the OS by a pre-paint
  // head script). Fall back to the OS preference if it is somehow absent.
  function isDark() {
    var theme = document.documentElement.getAttribute('data-bs-theme');
    if (theme === 'dark') {
      return true;
    }
    if (theme === 'light') {
      return false;
    }
    return !!(window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
  }

  function chrome() {
    return isDark() ? CHROME.dark : CHROME.light;
  }

  function applyChartDefaults() {
    var c = chrome();
    Chart.defaults.color = c.text;
    Chart.defaults.borderColor = c.grid;
  }

  // A live toggle: Chart.js v4 caches each instance's resolved option colors,
  // so mutating tick/legend/grid colors in place no-ops on an already-rendered
  // canvas. Refresh the global defaults, then rebuild each chart from its
  // retained data so the new canvas paints with the current theme.
  function restyleCharts() {
    applyChartDefaults();
    $.each(redrawers, function (sel, redraw) {
      redraw();
    });
  }

  applyChartDefaults();
  document.addEventListener('fog:themechange', restyleCharts);

  // --- shared helpers --------------------------------------------------

  // Size a chart container and return a fresh 2d context inside it,
  // tearing down any prior chart or error markup first.
  function canvasIn(sel) {
    var $box = $(sel);
    if (charts[sel]) {
      charts[sel].destroy();
      delete charts[sel];
    }
    $box.css({ position: 'relative', height: '150px' }).empty();
    $box.append('<canvas></canvas>');
    return $box.find('canvas')[0].getContext('2d');
  }

  function boxLoad(sel, on) {
    $(sel).closest('.box').setLoading(on);
  }

  function showError(sel, title, msg) {
    if (charts[sel]) {
      charts[sel].destroy();
      delete charts[sel];
    }
    $(sel).html(
      '<div class="alert alert-warning"><h4>' + title + '</h4>' + msg + '</div>'
    );
  }

  // Keep the periodic polls aligned to a 5-minute boundary from page load,
  // as the old dashboard did, so requests stay batched.
  function nextSlow() {
    return POLL_SLOW - ((new Date().getTime() - startTime) % POLL_SLOW);
  }

  /**
   * Is this widget still on the page?
   *
   * Every chart below polls by rescheduling itself with setTimeout from its
   * own complete handler, and nothing ever stopped those chains. FOG's AJAX
   * navigation replaces the page body and cancels intervals -- fog.common.js
   * wraps window.setInterval and calls clearAllIntervals() in doPageLoad --
   * but it does not and cannot generically cancel timeouts, which are also
   * used for debounces and for deferring work by a tick. So a setTimeout
   * chain outlived the page that started it, and every visit to the dashboard
   * left another one running for the life of the tab.
   *
   * Measured on the 1.6 lab: one dashboard visit, then five and a half
   * minutes sitting on Host Management, produced 122 requests with nothing to
   * draw into. The bandwidth chain is the expensive one -- POLL_BW is 2.5s,
   * so roughly 24 requests a minute, per dashboard visit, indefinitely. It
   * answers 200, which is why it was never noticed; the only visible symptom
   * was the disk usage chain's 400 once every five minutes, because
   * $('.nodeid') is not on the page either and the endpoint reports a missing
   * node id as "Node is unavailable".
   *
   * Checked per widget rather than by tracking timers centrally, which keeps
   * it local and self-healing: a chain ends the first time it wakes up on a
   * page that no longer has its chart.
   *
   * @param {string} sel The widget's container selector.
   *
   * @return {boolean}
   */
  function alive(sel) {
    return !!document.querySelector(sel);
  }

  // Humanize a byte count using binary units.
  function humanBytes(v) {
    var units = [' iB', ' KiB', ' MiB', ' GiB', ' TiB', ' PiB', ' EiB', ' ZiB', ' YiB'];
    var i = 0;
    v = parseFloat(v) || 0;
    while (v >= 1024 && i < units.length - 1) {
      v /= 1024;
      i++;
    }
    return v.toFixed(2) + units[i];
  }

  // Set a hex color to an absolute lightness, returning rgb(). Replaces the
  // old jQuery.Color().lightness() dependency used for bandwidth shading.
  function shade(hex, lightness) {
    hex = String(hex || '3c8dbc').replace('#', '');
    if (hex.length === 3) {
      hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
    }
    if (!/^[0-9a-fA-F]{6}$/.test(hex)) {
      hex = '3c8dbc';
    }
    var r = parseInt(hex.substr(0, 2), 16) / 255;
    var g = parseInt(hex.substr(2, 2), 16) / 255;
    var b = parseInt(hex.substr(4, 2), 16) / 255;
    var max = Math.max(r, g, b);
    var min = Math.min(r, g, b);
    var d = max - min;
    var h = 0;
    var s = 0;
    if (d !== 0) {
      s = (max + min) > 1 ? d / (2 - max - min) : d / (max + min);
      if (max === r) {
        h = (g - b) / d + (g < b ? 6 : 0);
      } else if (max === g) {
        h = (b - r) / d + 2;
      } else {
        h = (r - g) / d + 4;
      }
      h /= 6;
    }
    var l = Math.min(1, Math.max(0, lightness));
    function hue2rgb(p, q, t) {
      if (t < 0) t += 1;
      if (t > 1) t -= 1;
      if (t < 1 / 6) return p + (q - p) * 6 * t;
      if (t < 1 / 2) return q;
      if (t < 2 / 3) return p + (q - p) * (2 / 3 - t) * 6;
      return p;
    }
    var q = l < 0.5 ? l * (1 + s) : l + s - l * s;
    var p = 2 * l - q;
    var R = Math.round(hue2rgb(p, q, h + 1 / 3) * 255);
    var G = Math.round(hue2rgb(p, q, h) * 255);
    var B = Math.round(hue2rgb(p, q, h - 1 / 3) * 255);
    return 'rgb(' + R + ',' + G + ',' + B + ')';
  }

  // Legend builder for the doughnut charts: "Label: value".
  function doughnutLegend(colors, format) {
    return function (chart) {
      var ds = chart.data.datasets[0];
      return chart.data.labels.map(function (label, i) {
        return {
          text: label + ': ' + format(ds.data[i]),
          fillStyle: colors[i],
          strokeStyle: colors[i],
          // Custom generateLabels bypasses Chart's default fontColor stamping,
          // so set it explicitly or the text renders canvas-default black in
          // dark mode. Use the higher-contrast legend color (muted tick gray
          // reads as "washed out" in light mode). Re-read on every update() so
          // theme toggles follow.
          fontColor: chrome().legend,
          lineWidth: 0,
          hidden: false,
          index: i
        };
      });
    };
  }

  // --- Storage Group Activity (doughnut) -------------------------------

  function setupActivity() {
    var SEL = '#graph-activity';
    var colors = ['#00c0ef', '#3c8dbc', '#0073b7']; // Free, Queued, Active
    var timer;
    var last = null;

    function draw(d) {
      if (d.error) {
        showError(SEL, d.title, d.error);
        return;
      }
      last = d;
      var values = [
        parseInt(d.ActivitySlots, 10) || 0,
        parseInt(d.ActivityQueued, 10) || 0,
        parseInt(d.ActivityActive, 10) || 0
      ];
      if (charts[SEL]) {
        charts[SEL].data.labels = d._labels;
        charts[SEL].data.datasets[0].data = values;
        charts[SEL].update('none');
        return;
      }
      charts[SEL] = new Chart(canvasIn(SEL), {
        type: 'doughnut',
        data: {
          labels: d._labels,
          datasets: [{ data: values, backgroundColor: colors, borderWidth: 0 }]
        },
        options: {
          cutout: '50%',
          plugins: {
            legend: {
              position: 'right',
              labels: { boxWidth: 12, generateLabels: doughnutLegend(colors, function (v) { return v; }) }
            },
            tooltip: { callbacks: { label: function (c) { return c.label + ': ' + c.parsed; } } }
          }
        }
      });
    }

    // Rebuild from the last payload so a live theme toggle repaints the canvas.
    redrawers[SEL] = function () {
      if (!last) {
        return;
      }
      if (charts[SEL]) {
        charts[SEL].destroy();
        delete charts[SEL];
      }
      draw(last);
    };

    function poll() {
      if (!alive(SEL)) {
        return;
      }
      boxLoad(SEL, true);
      Pace.ignore(function () {
        $.ajax({
          url: BASE + '&sub=clientcount',
          type: 'post',
          data: { id: $('.activity-count').val() },
          dataType: 'json',
          success: draw,
          error: function () { showError(SEL, 'Unavailable', 'Unable to get activity usage'); },
          complete: function () {
            boxLoad(SEL, false);
            clearTimeout(timer);
            timer = setTimeout(poll, nextSlow());
          }
        });
      });
    }

    // select2 so the control follows the theme (a native <select> stays white
    // in dark mode), matching the Disk Usage node picker below.
    $('.activity-count').select2({ width: 'auto', dropdownAutoWidth: true });
    $('.activity-count').on('change', function (e) {
      clearTimeout(timer);
      poll();
      e.preventDefault();
    });
    poll();
  }

  // --- Storage Node Disk Usage (doughnut) ------------------------------

  function setupDiskUsage() {
    var SEL = '#graph-diskusage';
    var colors = ['#00c0ef', '#3c8dbc']; // Free, Used
    var timer;
    var last = null;

    function linkHwInfo() {
      $('#hwinfolink').attr('href', BASE.replace('node=home', 'node=hwinfo') + '&id=' + $('.nodeid').val());
    }

    // Versions are fetched asynchronously and cached here, then rendered into
    // the select2 option templates below.
    var versions = {};

    // Render a node option: the master node gets a star icon, and the running
    // version (only for nodes we could reach) trails in small muted text.
    function fmtNode(state) {
      if (!state.id) {
        return state.text;
      }
      var $o = $(state.element);
      var $row = $('<span>').text($o.data('name') || state.text);
      var ver = versions[state.id];
      if (ver) {
        $row.append($('<small>', { 'class': 'text-muted', text: '  ' + ver }));
      }
      if ($o.data('master')) {
        $row.prepend(
          $('<i>', {
            'class': 'fa fa-star fa-fw text-yellow',
            title: 'Primary node',
            'aria-label': 'Primary node'
          })
        );
      }
      return $row;
    }

    function loadVersions() {
      $.ajax({
        url: BASE + '&sub=nodeversions',
        dataType: 'json',
        success: function (map) {
          versions = map || {};
          $('.nodeid').trigger('change.select2');
        }
      });
    }

    function draw(d) {
      if (d.error) {
        showError(SEL, d.title, d.error);
        return;
      }
      last = d;
      var values = [parseInt(d.free, 10) || 0, parseInt(d.used, 10) || 0];
      if (charts[SEL]) {
        charts[SEL].data.labels = d._labels;
        charts[SEL].data.datasets[0].data = values;
        charts[SEL].update('none');
        return;
      }
      charts[SEL] = new Chart(canvasIn(SEL), {
        type: 'doughnut',
        data: {
          labels: d._labels,
          datasets: [{ data: values, backgroundColor: colors, borderWidth: 0 }]
        },
        options: {
          cutout: '50%',
          plugins: {
            legend: {
              position: 'right',
              labels: { boxWidth: 12, generateLabels: doughnutLegend(colors, humanBytes) }
            },
            tooltip: { callbacks: { label: function (c) { return c.label + ': ' + humanBytes(c.parsed); } } }
          }
        }
      });
    }

    // Rebuild from the last payload so a live theme toggle repaints the canvas.
    redrawers[SEL] = function () {
      if (!last) {
        return;
      }
      if (charts[SEL]) {
        charts[SEL].destroy();
        delete charts[SEL];
      }
      draw(last);
    };

    function poll() {
      if (!alive(SEL)) {
        return;
      }
      boxLoad(SEL, true);
      Pace.ignore(function () {
        $.ajax({
          url: BASE + '&sub=diskusage',
          data: { id: $('.nodeid').val() },
          dataType: 'json',
          success: draw,
          error: function () { showError(SEL, 'Unavailable', 'Node is unavailable'); },
          complete: function () {
            boxLoad(SEL, false);
            clearTimeout(timer);
            timer = setTimeout(poll, nextSlow());
          }
        });
      });
    }

    $('.nodeid').select2({
      templateResult: fmtNode,
      templateSelection: fmtNode,
      width: 'auto',
      dropdownAutoWidth: true
    });
    linkHwInfo();
    loadVersions();
    $('.nodeid').on('change', function (e) {
      linkHwInfo();
      clearTimeout(timer);
      poll();
      e.preventDefault();
    });
    poll();
  }

  // --- Imaging history (line / time) -----------------------------------

  function setupImagingHistory() {
    var SEL = '#graph-30day';
    var days = $('.graph-days.active').prop('rel') || 30;
    var timer;
    var last = null;

    function draw(arr) {
      last = arr;
      var points = (arr || []).map(function (p) { return { x: p[0], y: p[1] }; });
      if (charts[SEL]) {
        charts[SEL].data.datasets[0].data = points;
        charts[SEL].update('none');
        return;
      }
      charts[SEL] = new Chart(canvasIn(SEL), {
        type: 'line',
        data: {
          datasets: [{
            label: 'Computers Imaged',
            data: points,
            borderColor: '#3c8dbc',
            backgroundColor: '#3c8dbc',
            tension: 0.3,
            pointRadius: 3,
            fill: false
          }]
        },
        options: {
          scales: {
            x: { type: 'time', time: { unit: 'day', tooltipFormat: 'ddd MMM DD YYYY' }, grid: { display: false } },
            y: { beginAtZero: true, ticks: { precision: 0 } }
          },
          plugins: {
            legend: { display: false },
            tooltip: { callbacks: { label: function (c) { return c.dataset.label + ': ' + c.parsed.y; } } }
          }
        }
      });
    }

    // Rebuild from the last payload so a live theme toggle repaints the canvas.
    redrawers[SEL] = function () {
      if (last === null) {
        return;
      }
      if (charts[SEL]) {
        charts[SEL].destroy();
        delete charts[SEL];
      }
      draw(last);
    };

    function poll() {
      if (!alive(SEL)) {
        return;
      }
      boxLoad(SEL, true);
      Pace.ignore(function () {
        $.ajax({
          url: BASE,
          type: 'post',
          data: { sub: 'get30day', days: days },
          dataType: 'json',
          success: draw,
          error: function () { showError(SEL, 'Unavailable', 'Unable to get imaging history'); },
          complete: function () {
            boxLoad(SEL, false);
            clearTimeout(timer);
            timer = setTimeout(poll, nextSlow());
          }
        });
      });
    }

    $('.type-days').on('click', function (e) {
      $(this).blur().addClass('active').siblings('a').removeClass('active');
      days = $(this).prop('rel');
      clearTimeout(timer);
      poll();
      e.preventDefault();
    });
    poll();
  }

  // --- Bandwidth (line / time, live) -----------------------------------

  function setupBandwidth() {
    var SEL = '#graph-bandwidth';
    var nodenames = ($('#nodeNames').val() || '').split(',');
    var nodecolors = ($('#nodeColors').val() || '').split(',');
    var nodeurls = ($('#bandwidthUrls').val() || '').split(',').filter(Boolean);

    var urls = [];
    var names = [];
    var colors = [];
    var store = [];          // [{name, dev, tx:[{x,y}], rx:[{x,y}]}]
    var realtime = 'on';
    var MAX_SECS = 3600;     // always retain up to an hour; buttons only change the view
    var windowSecs = parseInt($('.time-filters.active').prop('rel'), 10) || 120;
    var firstDone = false;
    var ajax;
    var timer;

    function mode() {
      return $('#graph-bandwidth-filters-transmit').hasClass('active') ? 'tx' : 'rx';
    }

    function setRealtime(on) {
      realtime = on ? 'on' : 'off';
      $('#btn-on').toggleClass('active', on);
      $('#btn-off').toggleClass('active', !on);
    }

    function ensureChart() {
      if (charts[SEL]) {
        return;
      }
      charts[SEL] = new Chart(canvasIn(SEL), {
        type: 'line',
        data: { datasets: [] },
        options: {
          parsing: false,
          scales: {
            x: { type: 'time', time: { unit: 'second', stepSize: 30, tooltipFormat: 'HH:mm:ss' }, grid: { display: false } },
            y: { beginAtZero: true, ticks: { callback: function (v) { return parseFloat(v).toFixed(2) + ' Mbps'; } } }
          },
          plugins: {
            legend: { position: 'top', labels: { color: chrome().legend } },
            tooltip: { callbacks: { label: function (c) { return c.dataset.label + ': ' + c.parsed.y.toFixed(2) + ' Mbps'; } } }
          }
        }
      });
    }

    function render() {
      ensureChart();
      var m = mode();
      var cutoff = new Date().getTime() - windowSecs * 1000;
      charts[SEL].data.datasets = store.map(function (s, i) {
        var col = shade(colors[i], 0.7 - i / (store.length * 1.2));
        return {
          label: s.name + ' (' + s.dev + ')',
          data: s[m].filter(function (p) { return p.x >= cutoff; }),
          borderColor: col,
          backgroundColor: col,
          pointRadius: 0,
          tension: 0.2,
          fill: false
        };
      });
      charts[SEL].update('none');
    }

    // Rebuild from the retained series so a live theme toggle repaints the
    // canvas; the bandwidth history lives in `store`, so nothing is lost.
    redrawers[SEL] = function () {
      if (!store.length) {
        return;
      }
      if (charts[SEL]) {
        charts[SEL].destroy();
        delete charts[SEL];
      }
      render();
    };

    // Only ever discard data older than the full retention window; the
    // time-filter buttons just narrow what render() displays.
    function trim(now) {
      store.forEach(function (s) {
        while (s.tx.length && now - s.tx[0].x > MAX_SECS * 1000) {
          s.tx.shift();
          s.rx.shift();
        }
      });
    }

    function onData(series) {
      var now = new Date().getTime();
      $.each(series, function (i, v) {
        if (!store[i]) {
          store[i] = { name: v.name, dev: v.dev, tx: [], rx: [] };
        }
        store[i].dev = v.dev;
        store[i].tx.push({ x: now, y: v.tx });
        store[i].rx.push({ x: now, y: v.rx });
      });
      trim(now);
      if (realtime === 'on') {
        render();
      }
    }

    function fetchData() {
      if (!alive(SEL)) {
        return;
      }
      ajax = $.ajax({
        url: BASE + '&sub=bandwidth',
        type: 'post',
        data: { url: urls, names: names },
        dataType: 'json',
        success: onData,
        complete: function () {
          if (!firstDone) {
            boxLoad('#realtime', false);
            firstDone = true;
          }
          clearTimeout(timer);
          timer = setTimeout(fetchData, POLL_BW);
        }
      });
    }

    function start(data) {
      names = data.names || [];
      urls = data.urls || [];
      // Re-align colors to the surviving nodes by name (testUrls may drop
      // unreachable nodes, shifting positions).
      colors = names.map(function (nm) {
        var idx = nodenames.indexOf(nm);
        return idx >= 0 ? nodecolors[idx] : '';
      });
      ensureChart();
      fetchData();
    }

    // Controls.
    $('#realtime .btn').on('click', function () {
      var on = $(this).data('toggle') === 'on';
      setRealtime(on);
      if (on) {
        render();
      }
    });

    $('.type-filters').on('click', function (e) {
      $('#graph-bandwidth-title > span').text($(this).text());
      $(this).blur().addClass('active').siblings('a').removeClass('active');
      render();
      e.preventDefault();
    });

    $('.time-filters').on('click', function (e) {
      $('#graph-bandwidth-time-title > span').text($(this).text());
      $(this).blur().addClass('active').siblings('a').removeClass('active');
      windowSecs = parseInt($(this).prop('rel'), 10) || 120;
      render();
      e.preventDefault();
    });

    // Find which nodes are actually reachable, then start polling.
    boxLoad('#realtime', true);
    Pace.ignore(function () {
      $.ajax({
        url: BASE + '&sub=testUrls',
        type: 'post',
        data: { url: nodeurls, names: nodenames },
        dataType: 'json',
        success: start,
        error: function () {
          boxLoad('#realtime', false);
          showError(SEL, 'Unavailable', 'Unable to get bandwidth information');
        }
      });
    });
  }

  // --- boot ------------------------------------------------------------

  $(function () {
    setupActivity();
    setupDiskUsage();
    setupImagingHistory();
    setupBandwidth();
  });
})(jQuery);
