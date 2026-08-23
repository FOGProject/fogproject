(function($) {
  // The estate-wide API token pane. Auto-loaded by the js/fog/<node>/
  // fog.<node>.<sub>.js convention, so nothing registers this file.
  //
  // Two controls, wired separately for the same reason they are separate on
  // the user's own API tab:
  //
  //   - Update rides the form. processForm() posts new FormData(form), which
  //     carries the checkbox arrays and omits submit buttons -- so the save
  //     needs no discriminator here, because this form has only one submit
  //     path.
  //   - Issue does NOT ride the form. It returns a plaintext credential in
  //     its reply and that value is shown once, so it goes to its own
  //     endpoint rather than through a save that re-renders the page.
  //
  // FOG has no generic binding that picks a tab or page form up --
  // disableFormDefaults() only suppresses the native submit -- so an unwired
  // control here renders perfectly and does nothing at all on click.
  var form = $('#apitoken-central-form'),
    saveBtn = $('#apitoken-central-send'),
    issueBtn = $('#centralissuetoken');

  form.on('submit', function(e) {
    e.preventDefault();
  });

  saveBtn.on('click', function(e) {
    saveBtn.prop('disabled', true);
    form.processForm(function(err) {
      saveBtn.prop('disabled', false);
      if (err) {
        return;
      }
      // Every row on screen is stale after a save: a deleted token still has
      // a row and a toggled one still shows its old state. Nothing here can
      // patch that up correctly from the client, because the server decides
      // which ids were in scope.
      location.reload();
    });
  });

  issueBtn.on('click', function(e) {
    var forUser = $('#issuefor').val(),
      name = $('#centraltokenname').val();
    issueBtn.prop('disabled', true);
    $.apiCall(
      'post',
      '../management/index.php?node=about&sub=issueAPITokenFor',
      {
        issuefor: forUser,
        newtokenname: name
      },
      function(err, data) {
        issueBtn.prop('disabled', false);
        if (err || !data || !data.token) {
          return;
        }
        // Shown, never stored. Deliberately not written to localStorage, a
        // data attribute, or anywhere else a later render could recover it
        // from -- the server cannot show it again and neither should this.
        $('#central-token-fresh-value').val(data.token);
        $('#central-token-fresh').removeClass('d-none');
        $('#centraltokenname').val('');
        $('#central-token-fresh-value').trigger('focus').trigger('select');
      }
    );
  });
})(jQuery);
