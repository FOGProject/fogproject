(function($) {
    var createForm = $("#host-create-form");
    var createFormBtn = $("#send");
    createForm.submit(function(e) {
        e.preventDefault();   
    });
    createFormBtn.click(function() {
        createFormBtn.prop("disabled", true);
        Common.processForm(createForm, function(err) {
            createFormBtn.prop("disabled", false);
        });
    });
    $("#mac").inputmask({"mask": Common.masks.mac});
    $("#productKey").inputmask({"mask": Common.masks.productKey});

})(jQuery);
