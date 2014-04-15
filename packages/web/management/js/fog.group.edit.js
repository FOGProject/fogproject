/****************************************************
 * FOG Group Management - Edit - JavaScript
 *	Author:		Blackout
 *	Created:	10:26 AM 1/01/2012
 *	Revision:	$Revision$
 *	Last Update:	$LastChangedDate$
 ***/

$(function()
{
	// Bind to AD Settings checkbox
	$('#adEnabled').change(function() {
		
		if ( $(this).attr('checked') )
		{	
			if ( $('#adDomain').val() == '' && $('#adOU').val() == '' && $('#adUsername').val() == '' &&  $('#adPassword').val() == '' )
			{
				$.ajax({
					'type':		'GET',
					'url':		'ajax/host.adsettings.php',
					'cache':	false,
					'dataType':	'json',
					'success':	function(data)
					{	
						$('#adDomain').val(data['domainname']);
						$('#adOU').val(data['ou']);
						$('#adUsername').val(data['domainuser']);
						$('#adPassword').val(data['domainpass']);
					}
				});
			}

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
