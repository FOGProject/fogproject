/**
 * The Certificates page: import a root CA, remove one, bring your own web
 * server certificate (adopt, sign a request, or upload), set the install
 * preferences.
 *
 * Everything here posts to ?node=about&sub=certificates, which Authorization
 * gates on system.pki for POST and settings.view for GET. A viewer without the
 * grant is served the same page with the controls absent, so nothing below
 * needs to reason about permissions -- the elements simply are not there.
 *
 * The downloads are plain links and deliberately have no JavaScript: they are
 * ordinary GETs that stream a file, so a middle-click or a right-click-save
 * behaves the way an administrator expects.
 */
(function($) {
  var rootForm = $('#pki-root-form'),
    importBtn = $('#pki-import-root'),
    clearBtn = $('#pki-clear-root'),
    leafForm = $('#pki-leaf-form'),
    adoptBtn = $('#pki-adopt-leaf'),
    leafBtn = $('#pki-import-leaf'),
    csrForm = $('#pki-csr-form'),
    csrBtn = $('#pki-make-csr'),
    csrInstallBtn = $('#pki-install-csr');

  rootForm.on('submit', function(e) {
    e.preventDefault();
  });

  leafForm.on('submit', function(e) {
    e.preventDefault();
  });

  csrForm.on('submit', function(e) {
    e.preventDefault();
  });

  csrBtn.on('click', function(e) {
    e.preventDefault();
    // Only worth a confirmation when a request is already pending: a new one
    // makes a new key, and a certificate the CA is still signing for the old
    // request will be refused afterward.
    if (csrInstallBtn.length && !window.confirm(
      'Generate a new signing request? The pending one is replaced along '
      + 'with its key, so a certificate issued from it would be refused.'
    )) {
      return;
    }
    csrBtn.prop('disabled', true);
    // The optional details ride along with the action. Collected by id rather
    // than serialized from a form, because these fields sit in the card that
    // routes one and two share and must not be dragged in by route three's
    // upload form -- and an empty field is simply not sent, which is what
    // makes an untouched form ask for FOG's own derived request.
    var payload = {action: 'makeLeafCsr'};
    $.each({
      csrcn: '#pki-csrcn',
      csro: '#pki-csro',
      csrou: '#pki-csrou',
      csrl: '#pki-csrl',
      csrst: '#pki-csrst',
      csrc: '#pki-csrc',
      csrnames: '#pki-csrnames'
    }, function(key, sel) {
      var el = $(sel);
      if (el.length && $.trim(el.val()) !== '') {
        payload[key] = el.val();
      }
    });
    $.apiCall('post', csrForm.attr('action'), payload,
      function(err) {
        csrBtn.prop('disabled', false);
        if (err) {
          return;
        }
        location.reload();
      });
  });

  csrInstallBtn.on('click', function(e) {
    e.preventDefault();
    if (!window.confirm(
      'Install the certificate issued from the pending request, and serve '
      + 'it? The web server is reloaded, so this takes effect now.'
    )) {
      return;
    }
    csrInstallBtn.prop('disabled', true);
    // processForm carries the file inputs and the hidden action together; no
    // key travels, the pair is the one the server generated.
    csrForm.processForm(function(err) {
      csrInstallBtn.prop('disabled', false);
      if (err) {
        return;
      }
      location.reload();
    });
  });

  importBtn.on('click', function(e) {
    e.preventDefault();
    // processForm builds a FormData from the form element itself, which is
    // what carries the uploaded file and the hidden action field together.
    rootForm.processForm(function(err) {
      if (err) {
        return;
      }
      // Reload rather than patch the card in place: an import changes the
      // chain table, the trust-anchor bundle's certificate count and whether
      // the Remove button exists. Re-rendering by hand would be three places
      // to keep in step with the server's own view of the same thing.
      location.reload();
    });
  });

  clearBtn.on('click', function(e) {
    e.preventDefault();
    if (!window.confirm(
      'Stop trusting the imported root CA on this server?'
    )) {
      return;
    }
    clearBtn.prop('disabled', true);
    $.apiCall('post', rootForm.attr('action'), {action: 'clearRoot'},
      function(err) {
        clearBtn.prop('disabled', false);
        if (err) {
          return;
        }
        location.reload();
      });
  });

  adoptBtn.on('click', function(e) {
    e.preventDefault();
    if (!window.confirm(
      'Serve the certificate in the customizations directory instead of the '
      + 'one FOG issued? The web server is reloaded, so this takes effect now.'
    )) {
      return;
    }
    adoptBtn.prop('disabled', true);
    $.apiCall('post', leafForm.attr('action'), {action: 'adoptCustomLeaf'},
      function(err) {
        adoptBtn.prop('disabled', false);
        if (err) {
          return;
        }
        location.reload();
      });
  });

  leafBtn.on('click', function(e) {
    e.preventDefault();
    if (!window.confirm(
      'Upload this certificate and its private key, and serve it? The key '
      + 'passes through the web application for this one request. If it is a '
      + 'wildcard, it covers hosts other than this server.'
    )) {
      return;
    }
    leafBtn.prop('disabled', true);
    // processForm builds the FormData from the form element, so the four file
    // inputs, the passphrase and the hidden action all travel in one POST --
    // the helper needs them under a single request id.
    leafForm.processForm(function(err) {
      leafBtn.prop('disabled', false);
      if (err) {
        return;
      }
      // Reloaded rather than patched: adopting a certificate changes the chain
      // table, the derived "managed elsewhere" state, whether the key-exposure
      // alarm fires, and this tab's own summary of the directory.
      location.reload();
    });
  });

  // The netboot transport is a radio group, not a switch, so it posts its own
  // word rather than a flag. Same one-key-per-call shape as the switches
  // below: the helper rewrites a single .fogsettings line per call, so two
  // administrators changing different settings cannot overwrite each other's.
  //
  // `change` fires only on the radio being SELECTED, never on the one being
  // cleared, so this runs once per click rather than twice.
  $('.pki-pref-radio').on('change', function() {
    var hit = $(this),
      wanted = hit.val(),
      // The whole group, so the revert below can re-check a sibling and so
      // both inputs are locked while the call is out. Grouped by name rather
      // than by data-key: name is what makes them mutually exclusive in the
      // first place, so it cannot drift from the group the browser sees.
      group = $('.pki-pref-radio[name="' + hit.attr('name') + '"]'),
      previous = group.filter(function() {
        return $(this).data('previous');
      }).val() || wanted;
    group.prop('disabled', true);
    $.apiCall('post', hit.data('action'), {
      action: 'setPreference',
      key: hit.data('key'),
      value: wanted
    }, function(err) {
      group.prop('disabled', false);
      if (err) {
        // Put it back to what the server still holds, for the reason the
        // switches do: this card's whole job is to say what the next installer
        // run will do, and a control that lies about that is worse than none.
        group.filter('[value="' + previous + '"]').prop('checked', true);
        return;
      }
      group.removeData('previous');
      hit.data('previous', true);
    });
  }).each(function() {
    // Remember which one started checked, so a rejected change has something
    // to revert to -- the browser has already moved the selection by the time
    // `change` reaches us.
    if (this.checked) {
      $(this).data('previous', true);
    }
  });

  // One handler for all three switches. Each posts only its own key, so two
  // administrators changing different preferences cannot overwrite each
  // other's -- the helper rewrites a single line of .fogsettings per call.
  $('.pki-pref').on('change', function() {
    var box = $(this),
      wanted = box.prop('checked');
    box.prop('disabled', true);
    $.apiCall('post', box.data('action'), {
      action: 'setPreference',
      key: box.data('key'),
      value: wanted ? 1 : ''
    }, function(err) {
      box.prop('disabled', false);
      if (err) {
        // Put the control back to what the server still holds. Leaving it
        // showing the rejected value is the worse failure here: the whole
        // point of the card is to say what the next installer run will do,
        // and a switch that lies about that is worse than no switch.
        box.prop('checked', !wanted);
      }
    });
  });
})(jQuery);
