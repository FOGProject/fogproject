(function($) {
    var siteForm = $('#user-site-form'),
        siteFormBtn = $('#site-send');

    siteForm.on('submit', function(e) {
        e.preventDefault();
    });
    siteFormBtn.on('click',function(e) {
        siteFormBtn.prop('disabled', true);
        Common.processForm(siteForm, function(err) {
            siteFormBtn.prop('disabled', false);
            if (err) {
                return;
            }
        });
    });
})(jQuery);
