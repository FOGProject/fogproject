(function($) {
    var createForm = $('#user-create-form'),
        createFormBtn = $('#send');
    createForm.on('submit', function(e) {
        e.preventDefault();
    });
    createFormBtn.on('click', function() {
        createFormBtn.prop('disabled', true);
        Common.processForm(createForm, function(err) {
            createFormBtn.prop('disabled', false);
            if (err) {
                return;
            }
            $('.password1-input, .password2-input').val('');
        });
    });
    $('#user').inputmask({mask: Common.masks.username, placeholder: ''});
})(jQuery);
