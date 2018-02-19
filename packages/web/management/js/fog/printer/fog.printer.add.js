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
    // Setup all fields to match when/where appropriate
    $('[name="printer"]').on('change', function() {
        var val = $(this).val();
        $(this).each(function() {
            $('[name="printer"]').val(val);
        });
    });
    $('[name="description"]').on('change', function() {
        var val = $(this).val();
        $(this).each(function() {
            $('[name="description"]').val(val);
        });
    });
    $('[name="inf"]').on('change', function() {
        var val = $(this).val();
        $(this).each(function() {
            $('[name="inf"]').val(val);
        });
    });
    $('[name="port"]').on('change', function() {
        var val = $(this).val();
        $(this).each(function() {
            $('[name="port"]').val(val);
        });
    });
    $('[name="ip"]').on('change', function() {
        var val = $(this).val();
        $(this).each(function() {
            $('[name="ip"]').val(val);
        });
    });
    $('[name="model"]').on('change', function() {
        var val = $(this).val();
        $(this).each(function() {
            $('[name="model"]').val(val);
        });
    });
    $('[name="configFile"]').on('change', function() {
        var val = $(this).val();
        $(this).each(function() {
            $('[name="configFile"]').val(val);
        });
    });
})(jQuery);
