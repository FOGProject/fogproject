(function($) {
    var updateButtons = $('.servicesend'),
        expand = $('#expandAll'),
        collapse = $('#collapseAll');

    $('form').on('submit', function(e) {
        e.preventDefault();
    });

    expand.on('click', function(e) {
        e.preventDefault();
        $(this).addClass('hidden');
        collapse.removeClass('hidden');
        $('.panel-collapse:not(.in)').addClass('in').slideDown();
    });
    collapse.on('click', function(e) {
        e.preventDefault();
        $(this).addClass('hidden');
        expand.removeClass('hidden');
        $('.panel-collapse.in').removeClass('in').slideUp();
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
