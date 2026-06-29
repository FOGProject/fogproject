/**
 * Bootstrap 5 jQuery compatibility shim.
 *
 * Bootstrap 5 dropped the jQuery plugin API ($.fn.modal/.tab/.tooltip) and the
 * jQuery event aliases. FOG has ~180 jQuery `.modal('show'|'hide')` call sites,
 * a handful of `.tooltip()`/`.tab('show')` calls, and code that binds the
 * `show/shown/hide/hidden.bs.modal` events via jQuery `.on()`. Rather than
 * rewrite every call site, this shim re-implements those jQuery methods on top
 * of the native `bootstrap.*` component API, and bridges the native component
 * events back into jQuery's event system so existing `.on('*.bs.modal')`
 * handlers keep firing.
 *
 * Loaded after bootstrap5.bundle and jQuery, before any FOG JS.
 */
(function ($) {
  if (!$ || !window.bootstrap) {
    return;
  }
  var bs = window.bootstrap;

  // Bridge native bootstrap component events -> jQuery events, once per element.
  // jQuery parses 'shown.bs.modal' as type 'shown' + namespaces, so its native
  // listener (type 'shown') never matches the native event type
  // 'shown.bs.modal'. We re-trigger a jQuery event of the same name so
  // .on('shown.bs.modal', fn) handlers fire.
  function bridgeEvents(el, names) {
    if (el._bsEventsBridged) {
      return;
    }
    el._bsEventsBridged = true;
    names.forEach(function (name) {
      el.addEventListener(name, function (ev) {
        var e = $.Event(name, {relatedTarget: ev.relatedTarget});
        $(el).trigger(e);
        if (e.isDefaultPrevented() && typeof ev.preventDefault === 'function') {
          ev.preventDefault();
        }
      });
    });
  }

  var MODAL_EVENTS = [
    'show.bs.modal', 'shown.bs.modal', 'hide.bs.modal', 'hidden.bs.modal'
  ];

  $.fn.modal = function (arg) {
    return this.each(function () {
      bridgeEvents(this, MODAL_EVENTS);
      var config = (typeof arg === 'object' && arg) ? arg : {};
      var inst = bs.Modal.getOrCreateInstance(this, config);
      if (arg === undefined) {
        inst.show();
      } else if (typeof arg === 'string') {
        if (typeof inst[arg] === 'function') {
          inst[arg]();
        }
      } else if (typeof arg === 'object' && arg.show) {
        // Mirror legacy `.modal({show:true})`. registerModal passes show:false,
        // so the common init path only constructs the instance here.
        inst.show();
      }
    });
  };

  $.fn.tab = function (arg) {
    return this.each(function () {
      var inst = bs.Tab.getOrCreateInstance(this);
      if (arg === 'show' || arg === undefined) {
        inst.show();
      } else if (typeof arg === 'string' && typeof inst[arg] === 'function') {
        inst[arg]();
      }
    });
  };

  $.fn.tooltip = function (arg) {
    return this.each(function () {
      var config = (typeof arg === 'object' && arg) ? arg : {};
      var inst = bs.Tooltip.getOrCreateInstance(this, config);
      if (typeof arg === 'string' && typeof inst[arg] === 'function') {
        inst[arg]();
      }
    });
  };
})(jQuery);
