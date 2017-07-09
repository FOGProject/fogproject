$(function() {
    checkboxToggleSearchListPages();
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
    setInterval(function() {
        $('#add, #updategen, #updatepw, #updateapi').each(function(e) {
            if ($(this).is(':visible')) {
                form = $(this).parents('form');
                validator = form.validate(validatorOpts);
            }
            $(this).on('click', function(e) {
                data = this.name;
            });
        });
        $('.username-input, .password-input1, .password-input2').each(function(e) {
            if ($(this).is(':visible')) {
                if (!$(this).hasClass('isvisible')) {
                    $(this).addClass('isvisible');
                }
                $(this).on('keyup change blur', function(e) {
                    return validator.element(this);
                }).trigger('change');
            } else {
                if ($(this).hasClass('isvisible')) {
                    $(this).removeClass('isvisible');
                }
            }
        });
    }, 1000);
    $('.resettoken').on('click', function(e) {
        e.preventDefault();
        $.ajax({
            url: '../status/newtoken.php',
            dataType: 'json',
            success: function(data) {
                $('.token').val(data);
            }
        });
    });
});
