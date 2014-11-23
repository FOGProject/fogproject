$(function() {
	// Show hide based on checked state.
	$('#hostNotInMe').hide();
	$('#hostNoSnapin').hide();
	$('#hostMeShow').click(function() {
		$('#hostNotInMe').toggle();
	});
	$('#hostNoShow').click(function() {
		$('#hostNoSnapin').toggle();
	});
	$('.toggle-checkbox1').click(function() {
		$('input.toggle-host1:checkbox').attr('checked', ($(this).attr('checked') ? 'checked' : false));
	});
	$('.toggle-checkbox2').click(function() {
		$('input.toggle-host2:checkbox').attr('checked', ($(this).attr('checked') ? 'checked' : false));
	});
	$('#groupNotInMe').hide();
	$('#groupNoSnapin').hide();
	$('#groupMeShow').click(function() {
		$('#groupNotInMe').toggle();
	});
	$('#groupNoShow').click(function() {
		$('#groupNoSnapin').toggle();
	});
	$('.toggle-checkbox1').click(function() {
		$('input.toggle-snapin1:checkbox').attr('checked', ($(this).attr('checked') ? 'checked' : false));
	});
	$('.toggle-checkbox2').click(function() {
		$('input.toggle-snapin2:checkbox').attr('checked', ($(this).attr('checked') ? 'checked' : false));
	});
});
