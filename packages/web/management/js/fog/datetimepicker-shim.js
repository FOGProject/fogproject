/**
 * Tempus Dominus 6 jQuery compatibility shim.
 *
 * FOG used the abandoned Eonasdan bootstrap-datetimepicker v4 (a BS3-era jQuery
 * plugin) via `$('#el').datetimepicker({...})` and `$('#el').datetimepicker('show')`.
 * Bootstrap 5 / AdminLTE 4 ships no such plugin; the modern replacement is
 * Tempus Dominus 6, which exposes a vanilla-JS constructor only. Rather than
 * rewrite the few call sites, this shim re-implements `$.fn.datetimepicker` on
 * top of `tempusDominus.TempusDominus`, mirroring the modal/tab/tooltip shim
 * already in bootstrap-jquery-shim.js.
 *
 * Tempus Dominus 6 needs `window.Popper.createPopper`; it is loaded (popper.min.js)
 * before tempus-dominus.min.js so the otherwise-failing dynamic Popper import in
 * a no-build flat-file setup never runs.
 *
 * Loaded after tempus-dominus.min.js, jQuery, and the bootstrap shim.
 */
(function ($) {
  if (!$ || !window.tempusDominus) {
    return;
  }
  var td = window.tempusDominus;

  // The old plugin took moment.js format tokens (e.g. 'YYYY-MM-DD HH:mm:ss').
  // Tempus Dominus 6 uses Luxon/Unicode tokens, which differ only for the
  // year (YYYY -> yyyy) and day-of-month (DD -> dd); month/hour/minute/second
  // tokens (MM/HH/mm/ss) are identical, so a targeted replace is sufficient.
  function momentToTD(fmt) {
    return String(fmt)
      .replace(/YYYY/g, 'yyyy')
      .replace(/YY/g, 'yy')
      .replace(/DD/g, 'dd')
      .replace(/D/g, 'd');
  }

  // Build a Tempus Dominus config from the legacy options object. Only `format`
  // was used in moment-token form across FOG; anything else falls back to a
  // sensible default that matches the server-side parser (niceDate, 'Y-m-d H:i:s').
  function buildConfig(opts) {
    var format = (opts && opts.format)
      ? momentToTD(opts.format)
      : 'yyyy-MM-dd HH:mm:ss';
    return {
      localization: {
        format: format
      }
    };
  }

  function getInstance(el) {
    return $(el).data('td-instance') || null;
  }

  $.fn.datetimepicker = function (arg) {
    return this.each(function () {
      var inst = getInstance(this);
      if (typeof arg === 'string') {
        // Method call, e.g. .datetimepicker('show'). Construct lazily so a
        // 'show' before an explicit init still works.
        if (!inst) {
          inst = new td.TempusDominus(this, buildConfig({}));
          $(this).data('td-instance', inst);
        }
        if (typeof inst[arg] === 'function') {
          inst[arg]();
        }
        return;
      }
      // Options-object (or no-arg) init. Dispose any prior instance so a
      // re-init with a new format does not stack listeners.
      if (inst && typeof inst.dispose === 'function') {
        inst.dispose();
      }
      inst = new td.TempusDominus(this, buildConfig(arg || {}));
      $(this).data('td-instance', inst);
    });
  };
})(jQuery);
