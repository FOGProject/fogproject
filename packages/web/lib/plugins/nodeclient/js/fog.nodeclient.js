$(function() {
		checkboxToggleSearchListPages();
		$('#action-boxdel').submit(function() {
			var checked = $('input.toggle-action:checked');
			var nodeclientIDArray = new Array();
			for (var i = 0,len = checked.size();i < len;i++) {
			nIDArray[nodeclientIDArray.length] = checked.eq(i).attr('value');
			}
			$('input[name="nodeclientIDArray"]').val(nodeclientIDArray.join(','));
			});
		});
