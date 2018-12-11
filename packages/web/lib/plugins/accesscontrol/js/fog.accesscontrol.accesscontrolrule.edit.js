(function($) {
    var roleForm = $('#rule-accesscontrol-form'),
        roleFormBtn = $('#accesscontrol-send');

    roleForm.on('submit', function(e) {
        e.preventDefault();
    });
    roleFormBtn.on('click', function(e) {
        $(this).prop('disabled', true);
        roleForm.processForm(function(err) {
            roleFormBtn.prop('disabled', false);
        });
    });
})(jQuery);
