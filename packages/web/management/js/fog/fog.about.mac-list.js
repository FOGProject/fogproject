function clearMacs() {
	$('#delete').html('Are you sure you wish to clear all mac address listings?');
	$('#delete').dialog({
		resizable: false,
		modal: true,
		title: 'Delete MACs',
		buttons: {
			'Yes': function() {
				$('.macButtons').fadeOut('slow');
				$(this).dialog('close');
				location.href='?node=about&sub=mac-list-post&clear=1';
			},
			'No': function() {
				$(this).dialog('close');
			}
		}
	});
}
function updateMacs() {
	$('#update').html('Are you sure you wish to update the mac address listings?');
	$('#update').dialog({
		resizable: false,
		modal: true,
		title: 'Update MACs',
		buttons: {
			'Yes': function() {
				$('.macButtons').fadeOut('slow');
				$(this).dialog('close');
				location.href='?node=about&sub=mac-list-post&update=1';
			},
			'No': function() {
				$(this).dialog('close');
			}
		}
	});
}
