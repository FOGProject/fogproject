(function($) {
    validatorOpts = {
        submitHandler: submithandlerfunc,
        rules: {
            name: {
                required: true,
                minlength: 3,
                maxlength: 40,
                regex: /^[\w][\w0-9]*[._-]?[\w0-9]*[.]?[\w0-9]+$/
            },
            password: {
                required: true,
                minlength: 4
            },
            password_confirm: {
                equalTo: '#password'
            }
        },
        messages: {
            password_confirm: {
                equalTo: 'Passwords do not match'
            }
        }
    };
    setupTimeoutElement(
        '#add, #updategen, #updatepw, #updateapi',
        '.username-input, .password-input1, .password-input2',
        1000
    );
    tokenreset();
    checkboxToggleSearchListPages();
})(jQuery);
