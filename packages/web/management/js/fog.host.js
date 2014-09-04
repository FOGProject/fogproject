/****************************************************
 * * FOG Host Management - Edit - JavaScript
 *	Author:		$CreatedBy$	
 *	Created:	$CreatedTime$
 *	Revision:	$Revision$
 *	Last Update:	$LastChangedDate$
 ***/

$(function()
{
	// Host ping
	$('.host-ping').fogPing({ 'Delay': 0, 'UpdateStatus': 0 }).removeClass('host-ping');
	
	// Checkbox toggle
	$('.toggle-checkbox').click(function() {
		$('input.toggle-host:checkbox').attr('checked', ($(this).attr('checked') ? 'checked' : false));
	});
	$('.toggle-checkboxgroup').click(function() {
		$('input.toggle-group:checkbox').attr('checked', ($(this).attr('checked') ? 'checked' : false));
	});
	//Action Box, had to remove action-box id search as it seems broken.
	$('#action-box').submit(function() {
		var checked = $('input.toggle-host:checked');
		var hostIDArray = new Array();
		for (var i = 0, len = checked.size(); i < len; i++)
		{
			hostIDArray[hostIDArray.length] = checked.eq(i).attr('value');
		}
		$('#hostIDArray',this).val(hostIDArray.join(','));
	});
});
