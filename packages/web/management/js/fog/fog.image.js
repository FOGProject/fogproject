$(function() {
		checkboxToggleSearchListPages();
		$('#action-boxdel').submit(function() {
			var checked = $('input.toggle-action:checked');
			var imageIDArray = new Array();
			for (var i = 0,len = checked.size();i < len;i++) {
			imageIDArray[imageIDArray.length] = checked.eq(i).attr('value');
			}
			$('input[name="imageIDArray"]').val(imageIDArray.join(','));
			});
		});
