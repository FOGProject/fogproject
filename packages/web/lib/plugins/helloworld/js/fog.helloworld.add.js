/**
 * Hello World standalone create page (sub=add).
 *
 * Submits the create form through processForm(), which POSTs to addPost and
 * surfaces success/error via the standard notification helpers.
 */
(function($) {
    var createForm = $('#helloworld-create-form'),
        createFormBtn = $('#send');

    createForm.on('submit', function(e) {
        e.preventDefault();
    });
    createFormBtn.on('click', function() {
        createFormBtn.prop('disabled', true);
        createForm.processForm(function(err) {
            createFormBtn.prop('disabled', false);
        });
    });
})(jQuery);
