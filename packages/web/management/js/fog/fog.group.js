(function($) {
    setADFields();
    clearADFields();
    advancedTaskLink();
    checkboxToggleSearchListPages();
    validatorOpts = {
        submitHandler: submithandlerfunc,
        rules: {
            name: {
                required: true,
                minlength:1,
                maxlength: 255,
                regex: /^[-\w!@#$%^()'{}\\\.~ ]{1,255}$/
            }
        }
    };
    setupTimeoutElement('#add, #updategen, #updateimage, #group-edit, #levelup, #update, #remove, #addsnapins, #remsnapins, #updatestatus, #updatedisplay, #updatealo, #pmsubmit, #delAllPM, #group-add, #group-edit', '.groupname-input', 1000);
    ProductUpdate();
})(jQuery)
