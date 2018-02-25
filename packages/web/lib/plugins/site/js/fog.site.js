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
    if (sub == 'membership') return;
    setupTimeoutElement('#add, #updategen', '.sitename-input', 1000);
    $('.action-boxes.del').on('submit',function() {
        var checked = $('input.toggle-action:checked');
        var accesscontrolIDArray = new Array();
        for (var i = 0,len = checked.size();i < len;i++) {
            accesscontrolIDArray[accesscontrolIDArray.length] = checked.eq(i).attr('value');
        }
        $('input[name="accesscontrolIDArray"]').val(accesscontrolIDArray.join(','));
    });
    $('.action-boxes.host').on('submit',function() {
        var checked = $('input.toggle-action:checked');
        var accesscontrolruleIDArray = new Array();
        for (var i = 0,len = checked.size();i < len;i++) {
            accesscontrolruleIDArray[accesscontrolruleIDArray.length] = checked.eq(i).attr('value');
        }
        $('input[name="accesscontrolruleIDArray"]').val(accesscontrolruleIDArray.join(','));
    });
    $('#hostMeShow:checkbox').on('change',function(e) {
        if ($(this).is(':checked')) $('#hostNotInMe').show();
        else $('#hostNotInMe').hide();
        e.preventDefault();
    });
    $('#hostMeShow:checkbox').trigger('change');
    checkboxAssociations('.toggle-checkboxhost:checkbox','.toggle-host:checkbox');
    checkboxAssociations('.toggle-checkboxhostrm:checkbox','.toggle-hostrm:checkbox');
});
