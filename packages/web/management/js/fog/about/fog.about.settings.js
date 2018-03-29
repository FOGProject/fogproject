(function($) {
    var updateButtons = $('.servicesend');

    $('form').on('submit', function(e) {
        e.preventDefault();
    });

    updateButtons.on('click', function(e) {
        e.preventDefault();
        var button = $(this),
            form = button.parents('form');
        button.prop('disabled', true);
        Common.processForm(form, function(err) {
            button.prop('disabled', false);
        });
    });

    $('.resettoken').on('click', function(e) {
        e.preventDefault();
        Pace.ignore(function() {
            $.ajax({
                url: '../status/newtoken.php',
                dataType: 'json',
                success: function(data, textStatus, jqXHR) {
                    $('.token').val(data);
                },
                error: function(jqXHR, textStatus, errorThrown) {
                }
            });
        });
    });
    $('.slider').slider();
})(jQuery);
