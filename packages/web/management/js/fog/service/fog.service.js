(function($) {
    validatorOpts = {
        submitHandler: submithandlerfunc
    };
    setTimeoutElement();
    $('input[name=delcu]:checkbox').on('click', function(e) {
        e.preventDefault();
        urlForm = $(this).closest('form').attr('action');
        $(this).closest('tr').remove();
        $.ajax({
            url: urlForm,
            type: 'POST',
            data: {
                delcu: $(this).val()
            },
        });
    });
})(jQuery);
function setTimeoutElement() {
    $('#updateuc, #deleteuc, #adddc, #deletedc, #updatedc, #updatescreen, #deleteevent, #addevent, #deletecu, button[name="updatestatus"], button[name="updatedefaults"]').each(function(e) {
        if ($(this).is(':visible')) {
            $(this).on('click', function(e) {
                form = $(this).parents('form');
                validator = form.validate(validatorOpts);
            });
        }
    });
    setTimeout(setTimeoutElement, 1000);
}
