$(function() {
    checkboxToggleSearchListPages();
    validatorOpts = {
        submitHandler: function(form) {
            data = $(form).find(':visible').serialize();
            console.log(data);
            url = $(form).attr('action');
            method = $(form).attr('method').toUpperCase();
            $.ajax({
                url: url,
                type: method,
                data: data,
                dataType: 'json',
                success: function(data) {
                    dialoginstance = new BootstrapDialog();
                    if (data.error) {
                        dialoginstance
                        .setTitle('Printer Update Failed')
                        .setMessage(data.error)
                        .setType(BootstrapDialog.TYPE_WARNING)
                        .open();
                    } else {
                        dialoginstance
                        .setTitle('Printer Update Success')
                        .setMessage(data.msg)
                        .setType(BootstrapDialog.TYPE_SUCCESS)
                        .open();
                    }
                }
            });
            return false;
        },
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
                equalTo: '#password',
                minlength: 4
            }
        },
        messages: {
            password_confirm: {
                minlength: 'Password must be at least 4 characters long',
                equalTo: 'Passwords do not match'
            }
        }
    };
    form = $('#add, #updategen:not(:hidden), #updatepw:not(:hidden), #updateapi:not(:hidden)').parents('form');
    validator = form.validate(validatorOpts);
    $('.username-input:not(:hidden), .password-input1:not(:hidden), .password-input2:not(:hidden)').on('keyup change blur', function(e) {
        return validator.element(this);
    }).trigger('change');
    $('.nav-tabs a').on('shown.bs.tab', function(e) {
        form = $('#add, #updategen:not(:hidden), #updatepw:not(:hidden), #updateapi:not(:hidden)').parents('form');
        validator = form.validate(validatorOpts);
        $('.username-input:not(:hidden), .password-input1:not(:hidden), .password-input2:not(:hidden)').on('keyup change blur', function(e) {
            return validator.element(this);
        }).trigger('change');
    });
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
