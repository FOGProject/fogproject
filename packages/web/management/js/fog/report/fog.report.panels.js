/**
 * FOG report chart panels.
 *
 * Draws every `.fog-report-chart` container a report emitted through
 * FOGPageRender::renderChartPanel(). Each container is paired with a
 * `<script type="application/json">` block holding its series, so this file
 * makes NO requests: a report's window is fixed and already in the URL, and
 * re-fetching would run the same aggregation a second time to draw the same
 * picture -- and give the chart a chance to disagree with the grid beneath
 * it. See ADR 0030.
 *
 * Theme handling mirrors fog.dashboard.js deliberately rather than sharing
 * with it: that file is the homepage's own and is loaded only there, and the
 * one thing worth sharing (the CHROME palette) is six values.
 */
(function ($) {
  'use strict';

  // The window picker.
  //
  // FOGPageRender::renderReportWindow() emits a plain GET form, and a plain
  // GET form does not work here: disableFormDefaults() in fog.common.js
  // binds submit -> preventDefault on EVERY form on the page, so the
  // browser's own navigation never runs and Show does nothing at all --
  // silently, with no request and no error. Bound on the FORM rather than
  // on the button so that Enter in a date field works too, and because a
  // submit handler still runs after another handler has preventDefaulted:
  // preventDefault stops the default action, not the rest of the chain.
  //
  // serialize() carries node, sub and f (all hidden fields) alongside the
  // dates, so the URL that results addresses the same report. A GET submit
  // REPLACES the query string rather than merging into it, which is why
  // those three have to be in the form at all.
  //
  // Namespaced and rebound per reinitialize() like the rest of the AJAX-nav
  // wiring, so it cannot stack across visits.
  $('form[data-report-window]')
    .off('submit.fogreportwindow')
    .on('submit.fogreportwindow', function () {
      var form = $(this);
      window.location.assign(
        form.attr('action') + '?' + form.serialize()
      );
    });

  // Loaded for the whole `report` node, so most reports have no canvas and
  // some pages will not have Chart.js at all. Both are normal; leave.
  if (typeof Chart === 'undefined') {
    return;
  }

  var charts = {};   // element id -> Chart instance
  var boxes = [];    // element ids in render order

  // Chart "chrome" -- tick labels, legend text, grid lines -- is the only
  // part that has to follow dark mode; dataset colors below read on either.
  // Same values as the dashboard's, which match --fog-text-strong.
  var CHROME = {
    light: { text: '#666666', legend: '#444444', grid: 'rgba(0,0,0,0.1)' },
    dark:  { text: '#c8ccd0', legend: '#c8ccd0', grid: 'rgba(255,255,255,0.12)' }
  };

  // AdminLTE's own accent colors, in a fixed order so the same image keeps
  // the same slice color between two loads of the same report.
  var PALETTE = [
    '#3c8dbc', '#00a65a', '#f39c12', '#dd4b39', '#605ca8',
    '#00c0ef', '#d81b60', '#39cccc', '#ff851b', '#3d9970'
  ];

  Chart.defaults.maintainAspectRatio = false;
  Chart.defaults.animation.duration = 0;

  function isDark() {
    var theme = document.documentElement.getAttribute('data-bs-theme');
    if (theme === 'dark') {
      return true;
    }
    if (theme === 'light') {
      return false;
    }
    return !!(window.matchMedia
      && window.matchMedia('(prefers-color-scheme: dark)').matches);
  }

  function applyChartDefaults() {
    var c = isDark() ? CHROME.dark : CHROME.light;
    Chart.defaults.color = c.text;
    Chart.defaults.borderColor = c.grid;
  }

  // The series the server embedded beside this container. A missing or
  // unreadable block is reported in the panel rather than swallowed: a
  // silently blank chart reads as "nothing happened", which is a different
  // and much worse answer than "this did not load".
  function payload(id) {
    var el = document.getElementById(id + '-data');
    if (!el) {
      return null;
    }
    try {
      return JSON.parse(el.textContent || el.innerHTML || '');
    } catch (e) {
      return null;
    }
  }

  function message($box, text) {
    $box.empty().append(
      $('<p class="text-muted text-center my-4"></p>').text(text)
    );
  }

  function config(data) {
    var labels = data.labels || [];
    var series = data.series || [];
    var first = series[0] || {};
    var values = first.data || [];

    if (data.type === 'doughnut') {
      return {
        type: 'doughnut',
        data: {
          labels: labels,
          datasets: [{
            data: values,
            backgroundColor: labels.map(function (unused, i) {
              return PALETTE[i % PALETTE.length];
            }),
            borderWidth: 0
          }]
        },
        options: {
          cutout: '50%',
          plugins: {
            legend: {
              position: 'right',
              labels: { boxWidth: 12, color: chromeLegend() }
            }
          }
        }
      };
    }

    // One dataset per series. A second series is how "runs" and "failures"
    // share an axis, which is the only way either number means anything --
    // eleven failures is a crisis at twelve runs and noise at nine hundred.
    // Only the first is filled: two translucent fills stacked read as a
    // third color that is not in the data.
    return {
      type: 'line',
      data: {
        labels: labels,
        datasets: series.map(function (s, i) {
          return {
            label: s.label || '',
            data: s.data || [],
            borderColor: PALETTE[i % PALETTE.length],
            backgroundColor: i === 0
              ? 'rgba(60, 141, 188, 0.15)'
              : 'transparent',
            fill: i === 0,
            tension: 0.2,
            // A year-long window is 366 points in a panel a few hundred
            // pixels wide, where a marker per day is a solid band. Dropped
            // past the width at which they stop being readable; the tooltip
            // still names the exact day.
            pointRadius: (s.data || []).length > 60 ? 0 : 3
          };
        })
      },
      options: {
        plugins: { legend: { display: series.length > 1, labels: { color: chromeLegend() } } },
        scales: {
          y: { beginAtZero: true, ticks: { precision: 0 } },
          // Dates come from the server already formatted for the window, so
          // the axis is categorical -- no time adapter, and no dependence on
          // moment being loaded on a report page.
          x: { ticks: { autoSkip: true, maxRotation: 0 } }
        }
      }
    };
  }

  function chromeLegend() {
    return (isDark() ? CHROME.dark : CHROME.light).legend;
  }

  function draw(id) {
    var box = document.getElementById(id);
    if (!box) {
      return;
    }
    var $box = $(box);
    var data = payload(id);

    if (charts[id]) {
      charts[id].destroy();
      delete charts[id];
    }
    if (!data) {
      message($box, 'Chart data could not be read.');
      return;
    }
    var values = [];
    (data.series || []).forEach(function (s) {
      values = values.concat(s.data || []);
    });
    var total = values.reduce(function (sum, v) {
      return sum + (Number(v) || 0);
    }, 0);
    // An all-zero series is a real answer and has to say so. Chart.js draws
    // a flat line or an empty ring for it, which looks like a chart that
    // failed to load.
    if (!values.length || total === 0) {
      message($box, 'Nothing in this range.');
      return;
    }

    $box.css({
      position: 'relative',
      height: (parseInt(box.getAttribute('data-chart-height'), 10) || 260) + 'px'
    }).empty();
    $box.append('<canvas></canvas>');

    charts[id] = new Chart(
      $box.find('canvas')[0].getContext('2d'),
      config(data)
    );
  }

  function drawAll() {
    applyChartDefaults();
    $.each(boxes, function (i, id) {
      draw(id);
    });
  }

  $('.fog-report-chart').each(function () {
    if (this.id) {
      boxes.push(this.id);
    }
  });
  if (!boxes.length) {
    return;
  }

  drawAll();

  // Chart.js v4 caches each instance's resolved option colors, so recoloring
  // in place no-ops on a rendered canvas -- the charts are rebuilt instead.
  document.addEventListener('fog:themechange', drawAll);

  // A canvas in a hidden tab pane measures zero, so a chart built while its
  // tab was inactive comes back sized 0x0 when the tab is shown. Reports put
  // their grid on a second tab, so this fires whenever someone comes back.
  $(document).on('shown.bs.tab', function () {
    $.each(charts, function (id, chart) {
      chart.resize();
    });
  });
})(jQuery);
