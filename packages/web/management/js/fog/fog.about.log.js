var LogToView;
var LinesToView;
var LogTimer
$(function() {
	LogToView = $('#logToView').val();
	LinesToView = $('#linesToView').val();
	$('#logpause').val('Pause');
	LogGetData();
	$('#logpause').click(function(e) {
		e.preventDefault();
		if ($(this).hasClass('active')) {
			$(this).removeClass('active');
			$(this).val('Pause');
			LogGetData();
		} else {
			$(this).addClass('active');
			$(this).val('Continue');
			clearTimeout(LogTimer);
		}
	});
	$('#logToView, #linesToView').change(function() {
		LogToView = $('#logToView').val();
		LinesToView = $('#linesToView').val();
		LogGetData();
		return false;
	});
});
function LogGetData() {
	$.ajax({
		url: '../status/logtoview.php',
		cache: false,
		type: 'POST',
		data: {
			file: LogToView,
			lines: LinesToView,
		},
		dataType: 'json',
		success: displayLog,
		complete: function() {
			LogTimer = setTimeout(LogGetData,10000);
		}
	});
}
function displayLog(data) {
	$('#logsGoHere').html('<pre>'+data+'</pre>');
}
