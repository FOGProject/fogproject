(function($) {
    $('#uname').select().focus();

    var loginButton = $('#loginSubmit');
    var loginForm = $('#loginForm');

    loginButton.on('click',function() {
        Common.processForm(loginForm, function(err) {
            if (!err) {
                Common.setContainerDisable(loginForm);
                location.reload(true);
            }
        });
    });
})(jQuery);
