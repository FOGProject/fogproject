$(function() {
    validatorOpts = {
        submitHandler: submithandlerfunc
    };
    setInterval(function() {
        $('#deletedc, #updatedc, #updatescreen, #deleteevent, #addevent, #deleteuc, #updateuc, button[name="updatestatus"], button[name="updatedefaults"]').each(function(e) {
            if ($(this).is(':visible')) {
                form = $(this).parents('form');
                validator = form.validate(validatorOpts);
            }
            $(this).on('click', function(e) {
                data = this.name;
            });
        });
    }, 1000);
    $('input[name=delcu]:checkbox').click(function(e) {
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
});
