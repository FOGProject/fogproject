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
    setupTimeoutElement('#add', 'input[name="apiToken"]', 1000);
    $('.action-boxes').on('submit',function() {
        var checked = $('input.toggle-action:checked');
        var pushbulletIDArray = new Array();
        for (var i = 0,len = checked.size();i < len;i++) {
            pushbulletIDArray[pushbulletIDArray.length] = checked.eq(i).attr('value');
        }
        $('input[name="pushbulletIDArray"]').val(pushbulletIDArray.join(','));
    });
});
