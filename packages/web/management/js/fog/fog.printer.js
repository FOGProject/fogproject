$(function() {
		checkboxToggleSearchListPages();
		$('#action-boxdel').submit(function() {
			var checked = $('input.toggle-action:checked');
			var printerIDArray = new Array();
			for (var i = 0,len = checked.size();i < len;i++) {
			printerIDArray[printerIDArray.length] = checked.eq(i).attr('value');
			}
			$('input[name="printerIDArray"]').val(printerIDArray.join(','));
			});
		});
