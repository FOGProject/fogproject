/****************************************************
 * FOG Dashboard JS
 *	Author:		Blackout
 *	Created:	12:22 PM 9/05/2011
 *	Revision:	$Revision: 642 $
 *	Last Update:	$LastChangedDate: 2011-06-03 07:41:37 +1000 (Fri, 03 Jun 2011) $
 ***/

var MACLookupTimer;
var MACLookupTimeout = 1000;

$(function()
{
	$('#adEnabled').change(function() {
		if ( $(this).attr('checked') )
		{
			if ( $('#adDomain').val() == '' && $('#adUsername').val() == '' && $('#adPassword').val() == '')
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

	MACUpdate = function()
	{
		var $this = $(this);
		
		$this.val($this.val().replace(/-/g, ':').toUpperCase());
		
		if (MACLookupTimer) clearTimeout(MACLookupTimer);
		MACLookupTimer = setTimeout(function()
		{
			$('#priMaker').load('./ajax/mac-getman.php?prefix=' + $this.val());
		}, MACLookupTimeout);
	};
	
	$('#mac').keyup(MACUpdate).blur(MACUpdate);
});
