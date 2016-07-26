var runInterval;
$(function() {
    runDBCheck();
    runInterval = setInterval(runDBCheck,1000);
    $('form').submit(function(e) {
        clearInterval(runInterval);
    });
});
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
        }
    });
}
