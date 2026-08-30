/**
 * Starting an impersonation span from the user dropdown.
 *
 * The trigger and an EMPTY modal live in the page shell, so this is reachable
 * from every page rather than from one. The picker itself is fetched when the
 * modal opens: building the candidate list costs a users query plus a
 * refusalReason() per user -- both subset tests each -- and putting that on
 * every page render to populate a dialog almost nobody opens would sit on the
 * critical path of the whole UI.
 *
 * The button inside the picker is type="button" and there is no <form>.
 * disableFormDefaults() in fog.common.js binds preventDefault to every form on
 * the page, on every load and every AJAX navigation, so nothing in FOG submits
 * natively -- a submit button posts nothing and reports nothing.
 *
 * Delegated binding, not a direct one. The picker arrives after this file has
 * run, and #ajaxPageWrapper is replaced on every in-app navigation, so a
 * handler bound to the element itself would be lost on the first nav.
 */
(function() {
  'use strict';

  var MODAL = '#impersonate-modal';
  var BODY = '#impersonate-modal-body';
  var SEND = '#impersonate-send';
  var TARGET = '#impersonate-target';

  /**
   * Load the picker into the modal each time it opens.
   *
   * Re-fetched rather than cached: who you may impersonate depends on the
   * permissions and sites of both people, and a stale list would offer
   * somebody the server is about to refuse.
   */
  $(document).on('show.bs.modal', MODAL, function() {
    var body = $(BODY);
    body.html('<p class="text-body-secondary">Loading&hellip;</p>');
    $.ajax({
      url: '../management/index.php?node=impersonate&sub=startModal',
      type: 'GET',
      cache: false,
      success: function(html) {
        body.html(html);
      },
      error: function() {
        // Said on screen rather than left as a spinner. A dialog that never
        // finishes loading is the silent-failure shape ADR 0012 and the
        // GH-1370 toast bug are both about: the user cannot tell a slow
        // server from a broken one.
        body.html(
          '<p class="text-danger">Could not load the list of users.</p>'
        );
      }
    });
  });

  $(document).on('click', SEND, function() {
    var btn = $(this);
    var target = $(TARGET).val();
    if (!target) {
      return;
    }
    btn.prop('disabled', true);
    $.apiCall(
      'POST',
      '../management/index.php?node=impersonate&sub=start',
      {targetid: target},
      function(err, data) {
        if (err) {
          btn.prop('disabled', false);
          return;
        }
        // A span changes the sidebar, the menu, the theme, the timezone and
        // every permission this page was rendered under. There is no partial
        // update that leaves it honest, so the server asks for a reload and
        // this obeys rather than swapping a fragment.
        if (data && data.reload) {
          window.location.href = '../management/index.php?node=home';
        }
      }
    );
  });
}());
