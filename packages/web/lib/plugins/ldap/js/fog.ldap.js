$(function() {
	checkboxToggleSearchListPages();
	$('#action-boxdel').submit(function() {
		var checked = $('input.toggle-action:checked');
		var ldapIDArray = new Array();
		for (var i = 0,len = checked.size();i < len;i++) {
			ldapIDArray[ldapIDArray.length] = checked.eq(i).attr('value');
		}
		$('input[name="ldapIDArray"]').val(ldapIDArray.join(','));
	});
});
