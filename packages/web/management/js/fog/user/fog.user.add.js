(function($) {
    var createForm = $('#user-create-form'),
        createFormBtn = $('#send');
    createForm.on('submit', function(e) {
        e.preventDefault();
    });
    createFormBtn.on('click', function() {
        Common.processForm(createForm, function(err) {
            if (err) {
                return;
            }
            $(':input').val('');
        });
    });
    $('#user').inputmask({mask: Common.masks.username, placeholder: ''});
})(jQuery);
