(function($) {
  var uname = $('#uname'),
    loginButton = $('#loginSubmit'),
    loginForm = $('#loginForm');
  uname.select().focus();
  $('#ulang').on('change', function (e) {
    e.preventDefault();
    $('html').attr('lang', this.value);
  });
  $('#ulang').trigger('change');
  loginButton.on('click', function(e) {
    e.preventDefault();
    loginForm.processForm(function(err) {
      if (err) {
        return;
      }
      loginForm.setContainerDisable();
      location.reload(true);
    });
  });
})(jQuery);
