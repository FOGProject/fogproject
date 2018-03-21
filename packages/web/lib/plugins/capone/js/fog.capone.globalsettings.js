$(function() {
    var generalForm = $('#capone-global-form'),
        generalFormBtn = $('#general-send');

    generalForm.on('submit',function(e) {
        e.preventDefault();
    });
    generalFormBtn.on('click',function() {
        generalFormBtn.prop('disabled', true);
        Common.processForm(generalForm, function(err) {
            generalFormBtn.prop('disabled', false);
            if (err) {
                return;
            }
        });
    });
});
