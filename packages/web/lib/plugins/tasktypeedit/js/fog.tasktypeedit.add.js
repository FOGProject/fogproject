(function($) {
    var createForm = $('#tasktype-create-form'),
        createFormBtn = $('#send');
    createForm.on('submit', function(e) {
        e.preventDefault();
    });
    createFormBtn.on('click', function() {
        createFormBtn.prop('disabled', true);
        Common.processForm(createForm, function(err) {
            createFormBtn.prop('disabled', false);
        });
    });
})(jQuery);
