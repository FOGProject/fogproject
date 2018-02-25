$(function() {
    checkboxToggleSearchListPages();
    validatorOpts = {
        submitHandler: submithandlerfunc,
        rules: {
            name: {
                required: true
            }
        }
    };
    $('#accesscontrolruleMeShow:checkbox').on('change', function(e) {
        console.log('Clicked');
        if ($(this).is(':checked')) $('#accesscontrolruleNotInMe').show();
        else $('#accesscontrolruleNotInMe').hide();
        e.preventDefault();
    });
    $('#accesscontrolruleMeShow:checkbox').trigger('change');
    if (sub == 'membership') return;
    if (sub == 'assocRule') return;
    $('.action-boxes.del').on('submit',function() {
        var checked = $('input.toggle-action:checked');
        var accesscontrolIDArray = new Array();
        for (var i = 0,len = checked.size();i < len;i++) {
            accesscontrolIDArray[accesscontrolIDArray.length] = checked.eq(i).attr('value');
        }
        $('input[name="accesscontrolIDArray"]').val(accesscontrolIDArray.join(','));
    });
    $('.action-boxes').show();
    $('.action-boxes.host').on('submit',function() {
        var checked = $('input.toggle-action:checked');
        var accesscontrolruleIDArray = new Array();
        for (var i = 0,len = checked.size();i < len;i++) {
            accesscontrolruleIDArray[accesscontrolruleIDArray.length] = checked.eq(i).attr('value');
        }
        $('input[name="accesscontrolruleIDArray"]').val(accesscontrolruleIDArray.join(','));
    });
    checkboxAssociations('.toggle-checkboxuser:checkbox','.toggle-user:checkbox');
    setupTimeoutElement('#add, #update', '#name', 1000);
});
