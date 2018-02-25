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
            key: {
                required: true,
                minlength: 25,
                maxlength: 29
            }
        }
    }
    setupTimeoutElement('#add, #update', '#name, #productKey', 1000);
    checkboxAssociations('.toggle-checkboxAction1:checkbox','.toggle-image1:checkbox');
    ProductUpdate();
    $('.action-boxes').on('submit',function() {
        var checked = $('input.toggle-image:checked');
        var windowskeyIDArray = new Array();
        for (var i = 0,len = checked.size();i < len;i++) {
            windowskeyIDArray[windowskeyIDArray.length] = checked.eq(i).attr('value');
        }
        $('input[name="windowskeyIDArray"]').val(windowskeyIDArray.join(','));
    });
    // Show hide based on checked state.
    $('#imageNotInMe').hide();
    $('#imageNoGroup').hide();
    $('#imageMeShow').on('click',function() {
        $('#imageNotInMe').toggle();
    });
    $('#imageNoShow').on('click',function() {
        $('#imageNoGroup').toggle();
    });
});
