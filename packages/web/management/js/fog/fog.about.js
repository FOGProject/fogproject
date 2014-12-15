var LogToView;
var LinesToView;
$(function() {
	LogToView = $('#logToView').val();
	LinesToView = $('#linesToView').val();
	LogGetData();
	$('#logToView').change(function() {
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
		complete: setTimeout(LogGetData,1000)
	});
}
function displayLog(data) {
	$('#logsGoHere').html('<pre>'+data+'</pre>');
}
