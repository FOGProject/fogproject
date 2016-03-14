var LogToView;
var LinesToView;
var LogTimer;
$(function() {
    LogToView = $('#logToView').val();
    LinesToView = $('#linesToView').val();
    $('#logpause').val('Pause');
    LogGetData();
    $("input[name='reverse']:checkbox").change(LogGetData);
    $('#logpause').click(function(e) {
        if ($(this).hasClass('active')) {
            $(this).removeClass('active').val('Pause');
            LogGetData;
        } else {
            $(this).addClass('active').val('Continue');
            clearTimeout(LogTimer);
        }
        e.preventDefault();
    });
    $('#logToView, #linesToView').change(function(e) {
        LogToView = $('#logToView').val();
        LinesToView = $('#linesToView').val();
        $('#logpause').val('Pause');
        if ($('#logpause').hasClass('active')) $('#logpause').removeClass('active');
        LogGetData();
        e.preventDefault();
    });
})
function LogGetData() {
    if (!$('#logpause').hasClass('active')) {
        splitUs = LogToView.split('||');
        ip = splitUs[0];
        file = splitUs[1];
        reverse = $("input[name='reverse']").is(':checked') ? 1 : 0;
        $.post('../status/logtoview.php',{ip: ip,file: file,lines: LinesToView,reverse: reverse},displayLog,'json').done(function() {LogTimer = setTimeout(LogGetData,10000)});
    }
}
function displayLog(data) {
    $('#logsGoHere').html('<pre>'+data+'</pre>');
}
