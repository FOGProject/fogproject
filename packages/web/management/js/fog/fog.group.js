$(function() {
	checkboxToggleSearchListPages();
	$('#action-boxdel').submit(function() {
		var checked = $('input.toggle-action:checked');
		var groupIDArray = new Array();
		for (var i = 0,len = checked.size();i < len;i++) {
			groupIDArray[groupIDArray.length] = checked.eq(i).attr('value');
		}
		$('input[name="groupIDArray"]').val(groupIDArray.join(','));
	});
});
