/****************************************************
 * FOG Host Management - Deploy - JavaScript
 *	Author:		Blackout
 *	Created:	9:18 AM 27/12/2011
 *	Revision:	$Revision$
 *	Last Update:	$LastChangedDate$
 ***/

$(function()
{
	// Bind radio buttons for 'Single' and 'Cron' scheduled task
	$('input[name="scheduleType"]').click(function()
	{
		var $this = $(this);
		var $content = $this.parents('p').parent().find('p').eq($this.parent().index());
		
		if ($this.is(':checked'))
		{
			$content.slideDown('fast').siblings('.hidden').slideUp('fast');
		}
		else
		{
			$content.slideDown('fast');
			$('.calendar').remove();
			$('.error').removeClass('error');
		}
	});
	
	// Basic validation on deployment page
	$('form#deploy-container').submit(function()
	{
		var result = true;
		var scheduleType = $('input[name="scheduleType"]:checked', $(this)).val();
		var inputsToValidate = $('#' + scheduleType + 'Options > input').removeClass('error');
	
		if (scheduleType == 'cron')
		{
			inputsToValidate.each(function()
			{
				var $min = $('#scheduleCronMin');
				var $hour = $('#scheduleCronHour');
				var $dom = $('#scheduleCronDOM');
				var $month = $('#scheduleCronMonth');
				var $dow = $('#scheduleCronDOW');
				
				// Basic checks
				if ($min.val() != '*' && ($min.val() == '' || parseInt($min.val(), 10) != $min.val() || $min.val() > 59 || $min.val() < 0))
				{
					result = false;
					$min.addClass('error');
				}
				if ($hour.val() != '*' && ($hour.val() == '' || parseInt($hour.val(), 10) != $hour.val() || $hour.val() > 23 || $hour.val() < 0))
				{
					result = false;
					$hour.addClass('error');
				}
				if ($dom.val() != '*' && ($dom.val() == '' || parseInt($dom.val(), 10) != $dom.val() || $dom.val() > 31 || $dom.val() < 1))
				{
					result = false;
					$dom.addClass('error');
				}
				if ($month.val() != '*' && ($month.val() == '' || parseInt($month.val(), 10) != $month.val() || $month.val() > 12 || $month.val() < 1))
				{
					result = false;
					$month.addClass('error');
				}
				if ($dow.val() != '*' && ($dow.val() == '' || parseInt($dow.val(), 10) != $dow.val() || $dow.val() > 6 || $dow.val() < 0))
				{
					result = false;
					$dow.addClass('error');
				}
			});
		}
		else if (scheduleType == 'single')
		{
			// Format check
			if (!inputsToValidate.val().match(/\d{4}\/\d{2}\/\d{2} \d{2}:\d{2}/))
			{
				result = false;
					
				inputsToValidate.addClass('error').click();
			}
		}
		
		return result;
	});
	
	// Fiddle with calendar to make it auto open and close
	// TODO: Find a better, modern calendar
	$('#scheduleSingle').click(function()
	{
		if ($(this).is(':checked'))
		{
			$('#scheduleSingleTime').parent().slideDown('fast', function()
			{
				var dayClickRemoveCalendar = function()
				{
					$('.daysrow .day').click(function()
					{
						$('.calendar').remove();
					});
				}
				
				$(this)	.children(0)
					.focus(function()
					{
						$(this).blur();
					})
					.click(function()
					{
						dayClickRemoveCalendar();
					}).click();
				
				dayClickRemoveCalendar();
			});
		}
	});
});
