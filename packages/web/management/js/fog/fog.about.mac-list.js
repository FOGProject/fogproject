// 12:21 PM 9/05/2011

// TODO: JAVASCRIPT: Move this to seperate file
function clearMacs()
{
	if ( confirm( 'Are you sure you wish to clear all mac address listings?') )
	{
		hideButtons();
		window.location.href='?node=about&sub=mac-list&clear=1'
	}
}

function updateMacs()
{
	if ( confirm( 'Are you sure you wish to update the mac address listing?'  ) )
	{
		hideButtons();
		window.location.href='?node=about&sub=mac-list&update=1'			
	}	
}		

function hideButtons()
{
	$("#delete").fadeOut('slow');
	$("#update").fadeOut('slow');
}
