var LogToView;
var LinesToView;
var LogTimer;
$(function() {
    LogToView = $('#logToView').val();
    LinesToView = $('#linesToView').val();
    $('#logpause').val('Pause');
    LogGetData();
    $('input[name=reverse]').click(function(e) {
        e.preventDefault();
        LogGetData();
    });
    $('#logpause').click(function(e) {
        e.preventDefault();
        if ($(this).hasClass('active')) {
            $(this).removeClass('active').val('Pause');
            LogGetData;
        } else {
            $(this).addClass('active').val('Continue');
            clearTimeout(LogTimer);
        }
    });
    $('#logToView, #linesToView').change(function(e) {
        e.preventDefault();
        LogToView = $('#logToView').val();
        LinesToView = $('#linesToView').val();
        $('#logpause').val('Pause');
        if ($('#logpause').hasClass('active')) $('#logpause').removeClass('active');
        LogGetData();
    });
})
function LogGetData() {
    if (! $('#logpause').hasClass('active')) {
        splitUs = LogToView.split('||');
        ip = splitUs[0];
        file = splitUs[1];
        $.ajax({
            url: '../status/logtoview.php',
            cache: false,
            type: 'POST',
            data: {
                ip: ip,
                file: file,
                lines: LinesToView,
                reverse: $('input[name=reverse]').prop('checked'),
            },
            dataType: 'json',
            success: displayLog,
            complete: function() {
                LogTimer = setTimeout(LogGetData,10000);
            }
        })
    }
}
function displayLog(data) {
    $('#logsGoHere').html('<pre>'+data+'</pre>');
}
