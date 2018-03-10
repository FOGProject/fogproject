(function($) {
    var createForm = $('#windowskey-create-form'),
        createFormBtn = $('#send'),
        groupSelector = $('#storagegroup'),
        nodeSelector = $('#storagenode');

    $('#key').inputmask({mask: Common.masks.productKey});
    createForm.on('submit', function(e) {
        e.preventDefault();
    });
    createFormBtn.on('click', function() {
        createFormBtn.prop('disabled', true);
        Common.processForm(createForm, function(err) {
            createFormBtn.prop('disabled', false);
        });
    });
})(jQuery);
