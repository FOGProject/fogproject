$(function() {
    // Process FOG JS Variables
    $('.fog-variable').fogVariable();
    // Process FOG Message Boxes
    $('.fog-message-box').fogMessageBox();
    var ReturnIndexes = new Array('sites', 'version');
    var ResultContainers = $('#login-form-info b');
    $.ajax({
        url: '../management/index.php',
        type: 'POST',
        data: {
            node: 'client',
            sub: 'loginInfo'
        },
        dataType: 'json',
        success: function (data) {
            console.log(data);
            var index = 0;
            ResultContainers.each(function(ind,val) {
                if (index === 0) {
                    if (!data['error-sites']) {
                        $(this).html(data['sites']);
                    } else {
                        $(this).html(data['error-sites']);
                    }
                } else {
                    if (!data['error-version']) {
                        if (index === 1) $(this).html(data['version'].stable);
                        if (index === 2) $(this).html(data['version'].dev);
                        if (index === 3) $(this).html(data['version'].svn);
                    } else {
                        if (index === 1) $(this).html(data['error-version']);
                        if (index === 2) $(this).html(data['error-version']);
                        if (index === 3) $(this).html(data['error-version']);
                    }
                }
                index++;
            });
        },
        error: function() {
            ResultContainers.find('span').removeClass().addClass('icon icon-kill').attr('title', 'Failed to connect!');
        }
    });
    $('#username').select().focus();
})
