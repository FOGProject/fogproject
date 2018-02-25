$(function() {
    checkboxToggleSearchListPages();
    validatorOpts = {
        submitHandler: submithandlerfunc,
        rules: {
            apiToken: {
                required: true,
                minlength: 1,
                maxlength: 255,
            },
            user: {
                required: true,
                minlength: 1,
                maxlength: 255,
                regex: /^[@]|^[#]/
            }
        }
    };
    setupTimeoutElement('#add', 'input[name="apiToken"], input[name="user"]', 1000);
    $('.action-boxes').on('submit',function() {
        var checked = $('input.toggle-action:checked');
        var slackIDArray = new Array();
        for (var i = 0,len = checked.size();i < len;i++) {
            slackIDArray[slackIDArray.length] = checked.eq(i).attr('value');
        }
        $('input[name="slackIDArray"]').val(slackIDArray.join(','));
    });
});
