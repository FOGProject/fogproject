$(function() {
    var generalForm = $('#ldap-global-form'),
        generalFormBtn = $('#general-send');

    generalForm.on('submit', function(e) {
        e.preventDefault();
    });
    generalFormBtn.on('click', function() {
        generalFormBtn.prop('disabled', true);
        generalForm.processForm(function(err) {
            generalFormBtn.prop('disabled', false);
            if (err) {
                return;
            }
        });
    });
});
