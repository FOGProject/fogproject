var runInterval;
(function($) {
    runDBCheck();
    $('form').on('submit',function(e) {
        clearInterval(runInterval);
    });
})(jQuery);
function runDBCheck() {
    $.ajax({
        url: '../status/dbrunning.php',
        dataType: 'json',
        success: function(data) {
            if (data.running === false) {
                $('#dbNotRunning').show();
                $('#dbRunning').hide();
            } else {
                $('#dbNotRunning').hide();
                $('#dbRunning').show();
            }
        },
        complete: function() {
            setTimeout(runDBCheck, 1000 - ((new Date().getTime() - startTime) % 1000));
        }
    });
}
