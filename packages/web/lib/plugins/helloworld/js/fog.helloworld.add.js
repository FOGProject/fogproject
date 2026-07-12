/**
 * Hello World standalone create page (sub=add).
 *
 * wireCreateForm() (fog.common.js) wires the #send button to submit the create
 * form through processForm(), which POSTs to addPost and surfaces success/error
 * via the standard notification helpers.
 */
(function($) {
    $('#helloworld-create-form').wireCreateForm();
})(jQuery);
