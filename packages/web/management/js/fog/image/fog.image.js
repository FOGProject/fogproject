(function($) {
    checkboxToggleSearchListPages();
    validatorOpts = {
        submitHandler: submithandlerfunc,
        rules: {
            name: {
                required: true,
                minlength:1,
                maxlength: 255,
                regex: /^[-\w!@#$%^()'{}\\\.~ ]{1,255}$/
            },
            storagegroup: {
                required: true
            },
            os: {
                required: true
            },
            imagetype: {
                required: true
            },
            imagepartitiontype: {
                required: true
            },
            file: {
                required: true,
                minlength: 1
            }
        }
    };
    if (sub == 'membership') return;
    $('.imagefile-input').on('keyup change blur focus focusout', function(e) {
        var start = this.selectionStart,
            end = this.selectionEnd;
        this.value = this.value.replace(/[^\w+\/\.-]/g,'');
        this.setSelectionRange(start,end);
        iFileVal = this.value;
        e.preventDefault();
        form = $(this).parents('form');
        validator = form.validate(validatorOpts);
        return validator.element(this);
    });
    $('.imagename-input').on('keyup change blur focus focusout', function(e) {
        var start = this.selectionStart,
            end = this.selectionEnd;
        if (typeof iFileVal === 'undefined') return;
        if (iFileVal.length == 0) $('.imagefile-input').val(this.value.replace(/[^\w+\/\.-]/g,''));
        this.setSelectionRange(start,end);
        e.preventDefault();
        form = $(this).parents('form');
        validator = form.validate(validatorOpts);
        return validator.element(this);
    }).blur(function(e) {
        if (typeof iFileVal === 'undefined') return;
        if (iFileVal.length == 0) $('.imagefile-input').val(this.value.replace(/[^\w+\/.-]/g,''));
        iFileVal = $('.imagefile-input').val();
        e.preventDefault();
        form = $(this).parents('form');
        validator = form.validate(validatorOpts);
        return validator.element(this);
    });
    setTimeoutElement();
    var iFileVal = $('.imagefile-input').val();
})(jQuery);
function setTimeoutElement() {
    $('#add, #updategen, #updategroups, #primarysel, #groupdel').each(function(e) {
        if ($(this).is(':visible')) {
            form = $(this).parents('form');
            validator = form.validate(validatorOpts);
        }
    });
    $('#storagegroup, #os, #imagetype').each(function(e) {
        if ($(this).is(':visible')) {
            $(this).on('keyup change blur focus focusout', function(e) {
                form = $(this).parents('form');
                validator = form.validate(validatorOpts);
                return validator.element(this);
            }).trigger('change');
        }
    });
    setTimeout(setTimeoutElement, 1000);
}
