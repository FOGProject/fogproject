(function($) {
    var createForm = $('#host-create-form'),
        createFormBtn = $('#send');
    createForm.on('submit',function(e) {
        e.preventDefault();
    });
    createFormBtn.on('click',function() {
        createFormBtn.prop('disabled', true);
        Common.processForm(createForm, function(err) {
            createFormBtn.prop('disabled', false);
        });
    });
    $('#mac').inputmask({mask: Common.masks.mac});
    $('#key').inputmask({mask: Common.masks.productKey});
})(jQuery);
