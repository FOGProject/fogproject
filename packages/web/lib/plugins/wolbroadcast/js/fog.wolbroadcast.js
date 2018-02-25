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
    setupTimeoutElement('#add, #updategen', '.wolinput-name, .wolinput-ip', 1000);
    $('.action-boxes').on('submit',function() {
        var checked = $('input.toggle-action:checked');
        var wolbroadcastIDArray = new Array();
        for (var i = 0,len = checked.size();i < len;i++) {
            wolbroadcastIDArray[wolbroadcastIDArray.length] = checked.eq(i).attr('value');
        }
        $('input[name="wolbroadcastIDArray"]').val(wolbroadcastIDArray.join(','));
    });
});
