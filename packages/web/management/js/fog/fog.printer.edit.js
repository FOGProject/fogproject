$(function() {
		// Show hide based on checked state.
		$('#hostNotInMe').hide();
		$('#hostNoPrinter').hide();
		$('#hostMeShow').click(function() {
			$('#hostNotInMe').toggle();
			});
		$('#hostNoShow').click(function() {
			$('#hostNoPrinter').toggle();
			});
		$('.toggle-checkbox1').click(function() {
			$('input.toggle-host1:checkbox').prop('checked', $(this).is(':checked'));
			});
		$('.toggle-checkbox2').click(function() {
			$('input.toggle-host2:checkbox').prop('checked', $(this).is(':checked'));
			});
		$('.toggle-actiondef').click(function() {
			$('.default').prop('checked', $(this).is(':checked'));
			});
		});
