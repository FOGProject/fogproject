(function($) {
    var createForm = $('#user-create-form'),
        createFormBtn = $('#send');
    createForm.on('submit', function(e) {
        e.preventDefault();
    });
    createFormBtn.on('click', function() {
        createForm.processForm(function(err) {
            if (err) {
                return;
            }
            $(':input').val('');
        });
    });
})(jQuery);
