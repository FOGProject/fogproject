$(function() {
    checkboxToggleSearchListPages();
    validatorOpts = {
        submitHandler: submithandlerfunc,
        rules: {
            serverURL: {
                required: true,
                minlength: 1,
                maxlength: 255
            },
            topicEndpoint: {
                required: true,
                minlength: 1,
                maxlength: 255
            }
        }
    };
    setupTimeoutElement('#add', 'input[name="serverURL"], input[name="topicEndpoint"]', 1000);
    $('.action-boxes').on('submit',function() {
        var checked = $('input.toggle-action:checked');
        var ntfyIDArray = new Array();
        for (var i = 0,len = checked.size();i < len;i++) {
            ntfyIDArray[ntfyIDArray.length] = checked.eq(i).attr('value');
        }
        $('input[name="ntfyIDArray"]').val(ntfyIDArray.join(','));
    });
});