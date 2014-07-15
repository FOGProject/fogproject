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
	// Bind to AD Settings checkbox
	$('#adEnabled').change(function() {
		
		if ( $(this).attr('checked') )
		{	
<<<<<<< HEAD
			if ( $('#adDomain').val() == '' && /*$('#adOU').val() == '' &&*/ $('#adUsername').val() == '' &&  $('#adPassword').val() == '' )
=======
			if ( $('#adDomain').val() == '' && $('#adUsername').val() == '' &&  $('#adPassword').val() == '')
>>>>>>> 5e6f2ff5445db9f6ab2678bfad76acfcacc85157
			{
				$.ajax({
					'type':		'GET',
					'url':		'ajax/host.adsettings.php',
					'cache':	false,
					'dataType':	'json',
					'success':	function(data)
					{	
						$('#adDomain').val(data['domainname']);
<<<<<<< HEAD
						//$('#adOU').val(data['ou']);
=======
>>>>>>> 5e6f2ff5445db9f6ab2678bfad76acfcacc85157
						$('#adUsername').val(data['domainuser']);
						$('#adPassword').val(data['domainpass']);
					}
				});
			}
<<<<<<< HEAD

=======
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
>>>>>>> 5e6f2ff5445db9f6ab2678bfad76acfcacc85157
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
