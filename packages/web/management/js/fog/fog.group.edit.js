$(function() {
        $('#resetSecData').val('Reset Encryption Data');
        $('#resetSecData').click(function() {
            $('#resetSecDataBox').html('Are you sure you wish to reset this groups hosts encryption data?');
            $('#resetSecDataBox').dialog({
resizable: false,
modal: true,
title: 'Clear Encryption',
buttons: {
'Yes': function() {
$.ajax({
url: '../management/index.php',
type: 'POST',
timeout: 1000,
data: {
sub: 'clearAES',
groupid: $_GET['id'],
},
});
$(this).dialog('close');
},
'No': function() {
$(this).dialog('close');
}
}
});
});
		// Just hide the group info
		$('#hostNoGroup').hide();
		// Checkbox toggles
		$('.toggle-checkbox1').click(function() {
			$('input.toggle-host1:checkbox').attr('checked', ($(this).attr('checked') ? 'checked' : false));
			});
		$('.toggle-checkbox2').click(function() {
			$('input.toggle-host2:checkbox').attr('checked', ($(this).attr('checked') ? 'checked' : false));
			});
		$('.toggle-checkboxprint').click(function() {
			$('input.toggle-print:checkbox').attr('checked', ($(this).attr('checked') ? 'checked' : false));
			});
		$('.toggle-checkboxprintrm').click(function() {
			$('input.toggle-printrm:checkbox').attr('checked', ($(this).attr('checked') ? 'checked' : false));
			});
		$('.toggle-checkboxsnapin').click(function() {
			$('input.toggle-snapin:checkbox').attr('checked', ($(this).attr('checked') ? 'checked' : false));
			});
		$('.toggle-checkboxsnapinrm').click(function() {
			$('input.toggle-snapinrm:checkbox').attr('checked', ($(this).attr('checked') ? 'checked' : false));
			});
		// Show hide based on checked state.
		$('#hostNotInMe').hide();
			$('#hostNoGroup').hide();
			$('#hostMeShow').click(function() {
					$('#hostNotInMe').toggle();
					});
		$('#hostNoShow').click(function() {
				$('#hostNoGroup').toggle();
				});
});
