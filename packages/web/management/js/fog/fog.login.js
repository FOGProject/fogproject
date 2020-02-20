(function($) {
    // Process FOG JS Variables
    var ReturnIndexes = ['sites', 'version'];
    var ResultContainers = $('#login-form-info b');
    $.ajax({
        url: '../management/index.php',
        type: 'post',
        data: {
            node: 'client',
            sub: 'loginInfo'
        },
        dataType: 'json',
        success: function (data) {
            if (!data.sites) {
                sites = 'Error contacting server';
            } else {
                sites = data.sites;
            }
            if (!data.version) {
                version = 'Error contacting server';
            } else {
                version = $.parseJSON(data.version);
            }
            if (typeof(version.stable) == 'undefined'
                    || !version.stable) {
                stable = 'Error contacting server';
            } else {
                stable = version.stable;
            }
            if (typeof(version.dev) == 'undefined'
                    || !version.dev) {
                dev = 'Error contacting server';
            } else {
                dev = version.dev;
            }
            ResultContainers.each(function(ind,val) {
                if (ind === 0) {
                    $(this).html(sites);
                } else {
                    if (ind === 1) $(this).html(stable);
                    if (ind === 2) $(this).html(dev);
                }
            });
        },
        error: function() {
            ResultContainers.find('span').removeClass().addClass('icon icon-kill').attr('title', 'Failed to connect!');
        }
    });
    $('#username').select().focus();
})(jQuery);
