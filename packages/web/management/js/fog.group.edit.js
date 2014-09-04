/****************************************************
 * FOG Group Management - Edit - JavaScript
 *	Author:		Blackout
 *	Created:	10:26 AM 1/01/2012
 *	Revision:	$Revision$
 *	Last Update:	$LastChangedDate$
 ***/

$(function()
{
	// Just hide the group info
	$('#hostNoGroup').hide();
	// Checkbox toggles
	$('.toggle-checkbox1').click(function() {
		$('input.toggle-host1:checkbox').attr('checked', ($(this).attr('checked') ? 'checked' : false));
	});
	$('.toggle-checkbox2').click(function() {
		$('input.toggle-host2:checkbox').attr('checked', ($(this).attr('checked') ? 'checked' : false));
	});
	// Bind to AD Settings checkbox
	$('#adEnabled').change(function() {
		
		if ( $(this).attr('checked') )
		{	
			if ( $('#adDomain').val() == '' && $('#adUsername').val() == '' &&  $('#adPassword').val() == '')
			{
				$.ajax({
					'type':		'GET',
					'url':		'ajax/host.adsettings.php',
					'cache':	false,
					'dataType':	'json',
					'success':	function(data)
					{	
						$('#adDomain').val(data['domainname']);
						$('#adUsername').val(data['domainuser']);
						$('#adPassword').val(data['domainpass']);
					}
				});
			}
			if ($('#adOU').is('input:text') && $('#adOU').val() == '')
			{
				$.ajax({
					'type': 'GET',
					'url': 'ajax/host.adsettings.php',
					'cache': false,
					'dataType': 'json',
					'success': function(data)
					{
						$('#adOU').val(data['ou']);
					}
				});
			}
		}
	});
	// Show hide based on checked state.
	$('#hostNoShow').change(function() {
		if ($(this).attr('checked'))
		{
			$('#hostNoGroup').show();
		}
		else
		{
			$('#hostNoGroup').hide();
		}
	});
	
	// Host Tasks - show advanced tasks on click
	$('.advanced-tasks-link').click(function()
	{
		$(this).parents('tr').toggle('fast', function()
		{
			$('#advanced-tasks').toggle('slow');
		});
		$(this).parents('tr').toggle('fast');
		
		//event.preventDefault();
	});
});
