$(function() {
    checkboxToggleSearchListPages();
    validatorOpts = {
        submitHandler, submithandlerfunc,
        rules: {
            name: {
                required: true,
                minlength:1,
                maxlength: 255
                regex: /^[-\w!@#$%^()'{}\\\.~ ]{1,255}$/
            }
        }
    };
    ProductUpdate();
    form = $('.groupname-input').parents('form');
    validator = form.validate(validatorOpts);
});
