(function($) {
    var uname = $('#uname'),
        loginButton = $('#loginSubmit'),
        loginForm = $('#loginForm');
    uname.select().focus();
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
