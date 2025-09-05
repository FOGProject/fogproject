(function () {
  function getToken() {
    const el = document.querySelector('meta[name="csrf-token"]');
    return el ? el.getAttribute('content') : '';
  }
  function setHeader(obj, name, value) {
    try { obj.setRequestHeader(name, value); } catch(e) {}
  }

  const token = getToken();

  // --- jQuery: set once for all $.ajax / $.get / $.post
  if (window.jQuery) {
    jQuery.ajaxSetup({
      xhrFields: { withCredentials: true },                   // send cookies
      headers:   { 'X-CSRF-Token': token }                    // add CSRF
    });
  }

  // --- fetch: wrap globally
  if (window.fetch) {
    const _fetch = window.fetch.bind(window);
    window.fetch = function (input, init) {
      init = init || {};
      // ensure cookies on same-site by default (change to 'include' if truly cross-site)
      if (!init.credentials) init.credentials = 'same-origin';
      const headers = new Headers(init.headers || {});
      if (!headers.has('X-CSRF-Token')) headers.set('X-CSRF-Token', token);
      init.headers = headers;
      return _fetch(input, init);
    };
  }

  // --- raw XMLHttpRequest: monkey-patch to inject header + withCredentials
  const _open = XMLHttpRequest.prototype.open;
  const _send = XMLHttpRequest.prototype.send;
  XMLHttpRequest.prototype.open = function () {
    this._fog_open_args = arguments;
    return _open.apply(this, arguments);
  };
  XMLHttpRequest.prototype.send = function (body) {
    try {
      // Send cookies for same-site requests; harmless if already true
      this.withCredentials = true;
      setHeader(this, 'X-CSRF-Token', token);
    } catch (e) {}
    return _send.call(this, body);
  };
})();
