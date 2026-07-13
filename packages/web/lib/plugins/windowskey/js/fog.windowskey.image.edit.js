(function($) {
    var windowskeyForm = $('#image-windowskey-form'),
        windowskeyFormBtn = $('#windowskey-send');
    windowskeyForm.on('submit', function(e) {
        e.preventDefault();
    });
    windowskeyFormBtn.on('click', function(e) {
        windowskeyFormBtn.prop('disabled', true);
        windowskeyForm.processForm(function(err) {
            windowskeyFormBtn.prop('disabled', false);
            if (err) {
                return;
            }
        });
    });
})(jQuery);
