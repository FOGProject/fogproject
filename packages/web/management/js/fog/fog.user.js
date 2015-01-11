$(function() {
	checkboxToggleSearchListPages();
	$('#action-boxdel').submit(function() {
		var checked = $('input.toggle-action:checked');
		var userIDArray = new Array();
		for (var i = 0,len = checked.size();i < len;i++) {
			userIDArray[userIDArray.length] = checked.eq(i).attr('value');
		}
		$('input[name="userIDArray"]').val(userIDArray.join(','));
	});
});
