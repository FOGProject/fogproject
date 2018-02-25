$(function() {
    checkboxToggleSearchListPages();
    validatorOpts = {
        submitHandler: submithandlerfunc,
        rules: {
            name: {
                required: true,
                minlength: 1
            }
        }
    };
    setupTimeoutElement('#add, #update', 'input[name="name"]', 1000);
    $('.action-boxes').on('submit',function() {
        var checked = $('input.toggle-action:checked').parent().is(':visible');
        var tasktypeeditIDArray = new Array();
        for (var i = 0,len = checked.size();i < len;i++) {
            tasktypeeditIDArray[tasktypeeditIDArray.length] = checked.eq(i).prop('value');
        }
        $('input[name="tasktypeeditIDArray"]').val(tasktypeeditIDArray.join(','));
    });
});
