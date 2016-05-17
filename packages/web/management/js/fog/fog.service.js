$(function() {
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
