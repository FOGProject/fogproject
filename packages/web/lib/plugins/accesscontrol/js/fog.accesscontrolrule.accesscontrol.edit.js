(function($) {
    var ruleForm = $('#role-accesscontrolrule-form'),
        ruleFormBtn = $('#accesscontrolrule-send');

    ruleForm.on('submit', function(e) {
        e.preventDefault();
    });
    ruleFormBtn.on('click', function(e) {
        $(this).prop('disabled', true);
        ruleForm.processForm(function(err) {
            ruleFormBtn.prop('disabled', false);
        });
    });
})(jQuery);
