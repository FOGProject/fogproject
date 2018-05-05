(function($) {
    $('#uname').select().focus();

    var loginButton = $('#loginSubmit');
    var loginForm = $('#loginForm');

    loginButton.on('click',function() {
        loginForm.processForm(function(err) {
            if (!err) {
                loginForm.setContainerDisable();
                location.reload(true);
            }
        });
    });
})(jQuery);
