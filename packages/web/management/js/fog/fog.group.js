$(function() {
    checkboxToggleSearchListPages();
    ProductUpdate();
    form = $('.groupname-input').parents('form');
    validator = form.validate({
        rules: {
            name: {
                required: true,
                minlength: 1,
                maxlength: 255
            }
        }
    });
    $('.groupname-input').rules('add',{regex: /^[-\w ]{1,255}$/});
});
