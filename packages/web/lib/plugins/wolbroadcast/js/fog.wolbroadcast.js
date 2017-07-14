$(function() {
    checkboxToggleSearchListPages();
    validatorOpts = {
        submitHandler: submithandlerfunc,
        rules: {
            name: {
                required: true,
                minlength: 1,
                maxlength: 255
            },
            broadcast: {
                required: true,
                regex: /^(([1-9]?\d|1\d\d|2[0-5][0-5]|2[0-4]\d)\.){3}([1-9]?\d|1\d\d|2[0-5][0-5]|2[0-4]\d)$/
            }
        }
    };
    setInterval(function() {
        $('#add, #updategen').each(function(e) {
            if ($(this).is(':visible')) {
                form = $(this).parents('form');
                validator = form.validate(validatorOpts);
            }
            $(this).on('click', function(e) {
                data = this.name;
            });
            $('.wolinput-name, .wolinput-ip').each(function(e) {
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
        });
    }, 1000);
    $('.action-boxes').submit(function() {
        var checked = $('input.toggle-action:checked');
        var wolbroadcastIDArray = new Array();
        for (var i = 0,len = checked.size();i < len;i++) {
            wolbroadcastIDArray[wolbroadcastIDArray.length] = checked.eq(i).attr('value');
        }
        $('input[name="wolbroadcastIDArray"]').val(wolbroadcastIDArray.join(','));
    });
});
