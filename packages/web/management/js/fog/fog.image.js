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
    if ($_GET['sub'] == 'membership') return;
    $('.imagefile-input').on('change keyup',function(e) {
        var start = this.selectionStart,
            end = this.selectionEnd;
        this.value = this.value.replace(/[^\w+\/\.-]/g,'');
        this.setSelectionRange(start,end);
        iFileVal = this.value;
        e.preventDefault();
    });
    $('.imagename-input').on('change keyup',function(e) {
        var start = this.selectionStart,
            end = this.selectionEnd;
        if (typeof iFileVal === 'undefined') return;
        if (iFileVal.length == 0) $('.imagefile-input').val(this.value.replace(/[^\w+\/\.-]/g,''));
        this.setSelectionRange(start,end);
        e.preventDefault();
    }).blur(function(e) {
        if (typeof iFileVal === 'undefined') return;
        if (iFileVal.length == 0) $('.imagefile-input').val(this.value.replace(/[^\w+\/.-]/g,''));
        iFileVal = $('.imagefile-input').val();
        e.preventDefault();
    });
    setInterval(function() {
        $('#add, #updategen, #updategroups, #primarysel, #groupdel').each(function(e) {
            if ($(this).is(':visible')) {
                form = $(this).parents('form');
                console.log(form);
                validator = form.validate(validatorOpts);
            }
            $(this).on('click', function(e) {
                data = this.name;
            });
        });
        $('.imagename-input, #storagegroup, #os, .imagefile-input, #imagetype').each(function(e) {
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
    var iFileVal = $('.imagefile-input').val();
});
