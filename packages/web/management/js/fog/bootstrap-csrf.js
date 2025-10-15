(function () {
  var CSRF_HEADER = 'X-CSRF-Token';
  var REQUESTED_WITH = 'X-Requested-With';

  function getToken() {
    var el = document.querySelector('meta[name="csrf-token"]');
    return el ? el.getAttribute('content') || '' : '';
  }
  function isAbsolute(url) {
    return /^([a-z][a-z0-9+\-.]*:)?\/\//i.test(url);
  }
  function defaultPort(proto) {
    return proto === 'http:' ? '80' : proto === 'https:' ? '443' : '';
  }
  function sameOrigin(url) {
    if (!isAbsolute(url)) return true; // relative => same origin
    var a = document.createElement('a'); a.href = url;
    return a.protocol === location.protocol &&
           a.hostname === location.hostname &&
           (a.port || defaultPort(a.protocol)) === (location.port || defaultPort(location.protocol));
  }

  // --- jQuery: cookies + headers (baseline), same-origin only
  if (window.jQuery) {
    jQuery.ajaxSetup({ xhrFields: { withCredentials: true } });
    jQuery.ajaxPrefilter(function (opts, orig, jqXHR) {
      if (opts && opts.crossDomain) return;
      // Add once here; XHR patch below will see it and NOT double-add
      jqXHR.setRequestHeader(CSRF_HEADER, getToken());
      jqXHR.setRequestHeader(REQUESTED_WITH, 'XMLHttpRequest');
    });
  }

  // --- fetch: add headers only if not already present; same-origin only
  if (window.fetch && window.Request && window.Headers) {
    var _fetch = window.fetch.bind(window);
    window.fetch = function (input, init) {
      var req = new Request(input, init);
      var headers = new Headers(req.headers || {});
      if (sameOrigin(req.url)) {
        if (!headers.has(CSRF_HEADER)) headers.set(CSRF_HEADER, getToken());
        if (!headers.has(REQUESTED_WITH)) headers.set(REQUESTED_WITH, 'XMLHttpRequest');
      }
      var rebuilt = new Request(req, {
        headers: headers,
        credentials: req.credentials || 'same-origin'
      });
      return _fetch(rebuilt);
    };
  }

  // --- raw XMLHttpRequest: inject headers only if not already present; same-origin only
  if (window.XMLHttpRequest) {
    var _open = XMLHttpRequest.prototype.open;
    var _send = XMLHttpRequest.prototype.send;
    var _setHeader = XMLHttpRequest.prototype.setRequestHeader;

    XMLHttpRequest.prototype.open = function (method, url) {
      this._fog_url = url || '';
      this._fog_headers = new Set();
      return _open.apply(this, arguments);
    };

    XMLHttpRequest.prototype.setRequestHeader = function (name, value) {
      try { if (name) this._fog_headers.add(String(name).toLowerCase()); } catch (e) {}
      return _setHeader.apply(this, arguments);
    };

    XMLHttpRequest.prototype.send = function (body) {
      try {
        var url = this._fog_url || '';
        var have = this._fog_headers || new Set();
        if (sameOrigin(url)) {
          this.withCredentials = true; // harmless if already true
          if (!have.has(CSRF_HEADER.toLowerCase())) {
            _setHeader.call(this, CSRF_HEADER, getToken());
            have.add(CSRF_HEADER.toLowerCase());
          }
          if (!have.has(REQUESTED_WITH.toLowerCase())) {
            _setHeader.call(this, REQUESTED_WITH, 'XMLHttpRequest');
            have.add(REQUESTED_WITH.toLowerCase());
          }
        }
      } catch (e) {}
      return _send.call(this, body);
    };
  }

  // Ensure normal <form method=POST> submits carry the CSRF too
  document.addEventListener('submit', function (ev) {
    try {
      var form = ev.target;
      if (!form || !form.method || String(form.method).toLowerCase() !== 'post') return;
      // Only same-origin actions (defensive; remove if you really want cross-origin)
      var action = form.getAttribute('action') || window.location.href;
      var a = document.createElement('a'); a.href = action;
      var same = (a.protocol === location.protocol && a.hostname === location.hostname &&
        (a.port || (a.protocol==='https:'?'443':'80')) === (location.port || (location.protocol==='https:'?'443':'80')));
      if (!same) return;

      // If missing, add hidden _csrf with current token
      if (!form.querySelector('input[name="_csrf"]')) {
        var inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = '_csrf';
        inp.value = getToken();
        form.appendChild(inp);
      }
    } catch (e) {}
  }, true);
})();
