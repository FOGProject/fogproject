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
});
