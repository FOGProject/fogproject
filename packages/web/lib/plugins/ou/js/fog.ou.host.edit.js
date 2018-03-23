(function($) {
    var ouForm = $('#host-ou-form'),
        ouFormBtn = $('#ou-send');

    ouForm.on('submit', function(e) {
        e.preventDefault();
    });
    ouFormBtn.on('click',function(e) {
        ouFormBtn.prop('disabled', true);
        Common.processForm(ouForm, function(err) {
            ouFormBtn.prop('disabled', false);
            if (err) {
                return;
            }
        });
    });
})(jQuery);
