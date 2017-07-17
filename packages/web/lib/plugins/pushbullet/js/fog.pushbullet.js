$(function() {
    checkboxToggleSearchListPages();
    validatorOpts = {
        submitHandler: submithandlerfunc,
        rules: {
            apiToken: {
                required: true,
                minlength: 1,
                maxlength: 255
            }
        }
    };
    setInterval(function() {
        $('#add').each(function(e) {
            if ($(this).is(':visible')) {
                form = $(this).parents('form');
                validator = form.validate(validatorOpts);
            }
            $(this).on('click', function(e) {
                data = this.name;
            });
        });
        $('input[name="apiToken"]').each(function(e) {
            if ($(this).is(':visible')) {
                $(this).on('keyup change blur', function(e) {
                    return validator.element(this);
                }).trigger('change');
            }
        });
    }, 1000);
    $('.action-boxes').submit(function() {
        var checked = $('input.toggle-action:checked');
        var pushbulletIDArray = new Array();
        for (var i = 0,len = checked.size();i < len;i++) {
            pushbulletIDArray[pushbulletIDArray.length] = checked.eq(i).attr('value');
        }
        $('input[name="pushbulletIDArray"]').val(pushbulletIDArray.join(','));
    });
});
