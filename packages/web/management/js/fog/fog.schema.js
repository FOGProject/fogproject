$(function() {
    runDBCheck();
    setInterval(runDBCheck,1000);
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
                if (data.redirect) location.href = location.href;
                else {
                    $('#dbNotRunning').hide();
                    $('#dbRunning').show();
                }
            }
        }
    });
}
