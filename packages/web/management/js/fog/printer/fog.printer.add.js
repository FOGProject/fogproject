(function($) {
    var createForm = $('#printer-create-form'),
        createFormBtn = $('#send'),
        printertype = $('#printertype'),
        printercopy = $('#printercopy'),
        type = printertype.val().toLowerCase();
    createForm.on('submit', function(e) {
        e.preventDefault();
    });
    createFormBtn.on('click', function() {
        createFormBtn.prop('disabled', true);
        Common.processForm(createForm, function(err) {
            createFormBtn.prop('disabled', false);
        });
    });
    // Hides the fields not currently selected.
    $('.network,.iprint,.cups,.local').not('.'+type).hide();
    // On change hide all the fields and show the appropriate type.
    printertype.on('change', function(e) {
        e.preventDefault();
        type = printertype.val().toLowerCase();
        $('.network,.iprint,.cups,.local').not('.'+type).hide();
        $('.'+type).show();
    });
})(jQuery);
