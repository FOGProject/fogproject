(function($) {
    var createForm = $('#printer-create-form'),
        createFormBtn = $('#send'),
        printertype = $('#printertype');
    // Show only the selected type's section. Hidden sections are disabled so
    // their inputs stay out of the submitted FormData and out of validation.
    function showType(type) {
        $('.printer-type-section').each(function() {
            var section = $(this),
                match = section.hasClass(type);
            section.toggleClass('d-none', !match);
            section.find(':input').prop('disabled', !match);
        });
    }
    createForm.on('submit', function(e) {
        e.preventDefault();
    });
    createFormBtn.on('click', function() {
        createFormBtn.prop('disabled', true);
        createForm.processForm(function(err) {
            createFormBtn.prop('disabled', false);
        }, ':input:visible');
    });
    showType(printertype.val().toLowerCase());
    printertype.on('change', function(e) {
        e.preventDefault();
        showType(printertype.val().toLowerCase());
    });
})(jQuery);
