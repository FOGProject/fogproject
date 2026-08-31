/**
 * The Certificates page: import a root CA, remove one, set the three install
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
    clearBtn = $('#pki-clear-root');

  rootForm.on('submit', function(e) {
    e.preventDefault();
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
