(function($) {
    validatorOpts = {
        submitHandler: submithandlerfunc,
        rules: {
            name: {
                required: true,
                minlength: 1,
                maxlength: 255,
                regex: /^[-\w!@#$%^()'{}\\\.~ ]{1,255}$/
            },
            ip: {
                required: true
            },
            storagegroup: {
                required: true
            },
            path: {
                minlength: 1,
                required: true
            },
            ftppath: {
                minlength: 1,
                required: true
            },
            snapinpath: {
                minlength: 1,
                required: true
            },
            user: {
                required: true
            },
            pass: {
                required: true
            }
        }
    };
    setupTimeoutElement('#add, #update', '#name, #storagegroup, #ip, #path, #ftppath, #snapinpath, #user, #pass', 1000);
    $('#del-storage').on('click', function(e) {
        var checked = getChecked();
        $('input[name="'+node+'IDArray"]').val(checked.join(','));
        this.form.submit();
    });
    checkboxAssociations('.toggle-checkboxAction:checkbox','.toggle-action:checkbox');
})(jQuery);
