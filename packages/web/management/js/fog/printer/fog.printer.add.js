(function($) {
    var createForm = $('#printer-create-form'),
        createFormBtn = $('#send'),
        printertype = $('#printertype'),
        printercopy = $('#printercopy');
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
    // Copy an existing printer's settings into the form. Each value is written
    // to every type section's matching input by class; only the visible one is
    // submitted. Name and description are left for the admin to fill in.
    function copyFromExisting(id) {
        if (!id) {
            return;
        }
        $.getJSON(
            '../management/index.php?node=' + Common.node
                + '&sub=getPrinterInfo&id=' + id,
            function(data) {
                if (!data) {
                    return;
                }
                $('.printerport-input').val(data.port);
                $('.printerinf-input').val(data.file);
                $('.printerip-input').val(data.ip);
                $('.printermodel-input').val(data.model);
                $('.printerconfigfile-input').val(data.configFile);
                var wanted = (data.config || '').toLowerCase(),
                    matched = null;
                printertype.find('option').each(function() {
                    if ($(this).val().toLowerCase() === wanted) {
                        matched = $(this).val();
                    }
                });
                if (matched !== null) {
                    printertype.val(matched).trigger('change');
                } else {
                    showType(wanted);
                }
            }
        );
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
    printercopy.on('change', function() {
        copyFromExisting($(this).val());
    });
})(jQuery);
