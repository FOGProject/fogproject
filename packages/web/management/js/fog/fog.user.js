$(function() {
    checkboxToggleSearchListPages();
    $('.resettoken').click(function(e) {
        e.preventDefault();
        $.ajax({
            url: '../status/newtoken.php',
            dataType: 'json',
            success: function(data) {
                $('.token').val(data);
            }
        });
    });
    form = $('.username-input').parents('form');
    validator = form.validate({
        name: {
            required: true,
            minlength: 1,
            maxlength: 255
        }
    });
    pwform = $('.password-input1').parents('form');
    pwvalidator = pwform.validate({
        rules: {
            password: {
                required: true,
                minlength: 4
            },
            password_confirm: {
                minlength: 4,
                equalTo: '#password'
            }
        },
        messages: {
            password_confirm: {
                minlength: 'Password must be at least 4 characters long',
                equalTo: 'Passwords do not match',
            }
        }
    });
    $('.username-input').rules('add', {regex: /^[a-zA-Z0-9_-.]{3,40}$/});
    $('.username-input').on('keyup change blur',function() {
        return validator.element(this);
    });
    $('.password-input1,.password-input2').on('keyup change blur',function() {
        return pwvalidator.element(this);
    });
});
