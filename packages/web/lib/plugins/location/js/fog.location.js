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
            storagegroup: {
                required: true
            }
        }
    };
    setupTimeoutElement('#add, #update', '.locationname-input, #storagegroup', 1000);
    $('.action-boxes').on('submit',function() {
        var checked = $('input.toggle-action:checked');
        var locationIDArray = new Array();
        for (var i = 0,len = checked.size();i < len;i++) {
            locationIDArray[locationIDArray.length] = checked.eq(i).attr('value');
        }
        $('input[name="locationIDArray"]').val(locationIDArray.join(','));
    });
    // Show hide based on checked state.
    $('#hostNotInMe').hide();
    $('#hostNoGroup').hide();
    $('#hostMeShow').on('click',function() {
        $('#hostNotInMe').toggle();
    });
    $('#hostNoShow').on('click',function() {
        $('#hostNoGroup').toggle();
    });
});
