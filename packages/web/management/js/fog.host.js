/****************************************************
 * * FOG Host Management - Edit - JavaScript
 *	Author:		Blackout
 *	Created:	9:34 AM 1/01/2012
 *	Revision:	$Revision$
 *	Last Update:	$LastChangedDate$
 ***/

$(function()
{
	// Host ping
	$('.host-ping').fogPing({ 'Delay': 0, 'UpdateStatus': 0 }).removeClass('host-ping');
	
	// Checkbox toggle
	$('.toggle-checkbox').click(function()
	{
		var $this = $(this);
		var checked = $this.attr('checked');
		$this.parents('table').find('tbody').find('input[type="checkbox"]').attr('checked', (checked ? 'checked' : ''));
	});
	
	// Action box submit
	$('#action-box').submit(function()
	{
		var checked = $('input.toggle-host:checked');
		var hostIDArray = new Array();
		for (var i = 0, len = checked.size(); i < len; i++)
		{
			hostIDArray[hostIDArray.length] = checked.eq(i).attr('value');
		}
		$('#hostIDArray', this).val( hostIDArray.join(',') );
	});
});
