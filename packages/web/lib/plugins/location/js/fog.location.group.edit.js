(function($) {
    var locationForm = $('#group-location-form'),
        locationFormBtn = $('#location-send');

    locationForm.on('submit', function(e) {
        e.preventDefault();
    });
    locationFormBtn.on('click',function(e) {
        locationFormBtn.prop('disabled', true);
        Common.processForm(locationForm, function(err) {
            locationFormBtn.prop('disabled', false);
            if (err) {
                return;
            }
        });
    });
})(jQuery);
