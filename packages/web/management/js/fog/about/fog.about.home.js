(function($) {
    // FOG Version information gathering.
    var vers = $('.placehere').attr('vers');
    $.ajax({
        url: '../status/mainversion.php',
        dataType: 'json',
        success: function(data, textStatus, jqXHR) {
            $('.placehere').append(data);
        },
        error: function(jqXHR, textStatus, errorThrown) {
            $('.placehere').append(textStatus);
        }
    });

    // Storage Node version and kernel version information.
    $('.kernvers').each(function() {
        URL = $(this).attr('urlcall');
        newelement = document.createElement('a');
        newelement.href = URL;
        mainurl = '..'+newelement.pathname+newelement.search;

        $.ajax({
            context: this,
            url: mainurl,
            type: 'post',
            data: {
                url: URL
            },
            success: function(data, textStatus, jqXHR) {
                if (typeof(data) == null || typeof(data) == 'undefined') {
                    $(this).text('No data returned');
                    return;
                }
                data = data.split('\n');
                if (data.length < 2) {
                    $(this).text('No data returned');
                    return;
                }
                var nodevers = 'Node Version: ' + data.shift();
                data.unshift(nodevers);
                $(this).text(data.join('\n'));
            },
            error: function(jqXHR, textStatus, errorThrown) {
                $(this).text(textStatus);
            }
        });
    });
})(jQuery);
