$(function() {
    checkboxToggleSearchListPages();
    validatorOpts = {
        submitHandler: submithandlerfunc,
        rules: {
            name: {
                required: true,
                minlength:1,
                maxlength: 255,
                regex: /^[-\w!@#$%^()'{}\\\.~ ]{1,255}$/
            }
        }
    };
    setInterval(function() {
        $('#add, #updategen, #updateimage, #delAllPM, #levelup, #update, #remove, #addsnapins, #remsnapins, #updatestatus, #updatedisplay, #updatealo, #group-add, #group-edit').each(function(e) {
            if ($(this).is(':visible')) {
                form = $(this).parents('form');
                validator = form.validate(validatorOpts);
            }
            $(this).on('click', function(e) {
                data = this.name;
            });
        });
        $('.groupname-input').each(function(e) {
            if ($(this).is(':visible')) {
                if (!$(this).hasClass('isvisible')) {
                    $(this).addClass('isvisible');
                }
                $(this).on('keyup change blur', function(e) {
                    return validator.element(this);
                });
            } else {
                if ($(this).hasClass('isvisible')) {
                    $(this).removeClass('isvisible');
                }
            }
        });
    }, 1000);
    ProductUpdate();
});
