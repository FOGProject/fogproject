/****************************************************
 * * FOG Host Management - Edit - JavaScript
 *	Author:		Blackout
 *	Created:	9:34 AM 1/01/2012
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

	// Uncheck default printer boxes.
	$('input:checkbox[name="default"]').click(function() {
		var ischecked = $(this).attr('checked');
		$('input:checkbox').attr('checked',false);
		$(this).attr('checked', ischecked);
	});
	
	// Fetch MAC Manufactors
	$('.mac-manufactor').each(function()
	{
		var $this = $(this);
		var input = $this.parent().find('input');
		var mac = (input.size() ? input.val() : $this.parent().find('.mac').html());
		$this.load('./ajax/mac-getman.php?prefix=' + mac);
	});
	
	// Add MAC Buttons - TODO: Rewrite OLD CODE
	$('.add-mac').click(function()
	{
		$('#additionalMACsRow').show();
		$('#additionalMACsCell').append('<div><input class="addMac" type="text" name="additionalMACs[]" /><span class="icon icon-remove remove-mac hand" title="Remove MAC"></span><span class="mac-manufactor"></span></div>');
		
		HookTooltips();
		
		return false;
	});
	
	if ($('.additionalMAC').size())
	{
		$('#additionalMACsRow').show();
	}
	
	// Host Tasks - show advanced tasks on click
	$('.advanced-tasks-link').click(function(event)
	{
		$(this).parents('tr').toggle('slow', function()
		{
			$('#advanced-tasks').toggle('slow');
		});
		$(this).parents('tr').toggle('fast');
	
		event.preventDefault();
	});
});
