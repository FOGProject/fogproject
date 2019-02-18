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
            subnets: {
                required: true,
                regex: /^([0-9]{1,3}\.){3}[0-9]{1,3}(\/([0-9]|[1-2][0-9]|3[0-2]))(( )*,( )*([0-9]{1,3}\.){3}[0-9]{1,3}(\/([0-9]|[1-2][0-9]|3[0-2]))+)*$/g
            },
            group: {
                required: true
            }
        }
    };
    setupTimeoutElement('#add, #updategen', 'sgsubnet-input', 1000);
    $('.action-boxes').on('submit',function() {
        var checked = $('input.toggle-action:checked');
        var subnetgroupIDArray = new Array();
        for (var i = 0,len = checked.size();i < len;i++) {
            subnetgroupIDArray[subnetgroupIDArray.length] = checked.eq(i).attr('value');
        }
        $('input[name="subnetgroupIDArray"]').val(subnetgroupIDArray.join(','));
    });
});
