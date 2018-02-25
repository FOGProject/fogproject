$(function() {
    checkboxToggleSearchListPages();
    validatorOpts = {
        submitHandler: submithandlerfunc,
        rules: {
            name: {
                required: true
            },
            parent: {
                required: true
            },
            type: {
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
        if (sub != 'ruleList') {
            $('input[name="accesscontrolIDArray"]').val(accesscontrolIDArray.join(','));
        } else {
            $('input[name="accesscontrolruleIDArray"]').val(accesscontrolIDArray.join(','));
        }

    });
    $('.action-boxes').show();
    checkboxAssociations('.toggle-checkboxuser:checkbox','.toggle-user:checkbox');
    setupTimeoutElement('#add, #update, #updaterule', '#name, .ruletype-input, .ruleparent-input', 1000);
});
