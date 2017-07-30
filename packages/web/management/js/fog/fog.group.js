$(function() {
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
    setupTimeoutElement('button[type="submit"]:not(#updatehosts, #taskingbtn), #add, #updategen, #updateimage, #delAllPM, #levelup, #update, #remove, #addsnapins, #remsnapins, #updatestatus, #updatedisplay, #updatealo, #group-add, #group-edit', '.groupname-input', 1000);
    ProductUpdate();
});
