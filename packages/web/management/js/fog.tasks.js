<<<<<<< HEAD
/****************************************************
 * FOG JavaScript: Active Tasks
 *	Author:		Blackout
 *	Created:	1:48 PM 23/02/2011
 *	Revision:	$Revision: 835 $
 *	Last Update:	$LastChangedDate: 2012-01-12 11:57:46 +1000 (Thu, 12 Jan 2012) $
 ***/
// TODO: Merge this with $.fn.fogAjaxSearch()
var ActiveTasksContainer;
var ActiveTasksLastCount;
// Auto loader
$(function()
{
	// Show Task Container if we have items
	ActiveTasksContainer = $('#active-tasks');
	if (ActiveTasksContainer.find('tbody > tr').size() > 0) ActiveTasksContainer.show();
	// Hook buttons
	ActiveTasksButtonHook();
	// Update on load
	//ActiveTasksUpdate();
	// Update timer
	ActiveTasksUpdateTimerStart();
});

function ActiveTasksUpdateTimerStart()
{
	ActiveTasksUpdateTimer = setTimeout(function()
	{
		if (!ActiveTasksRequests.length)
		{
			ActiveTasksUpdate();
		}
		else
		{
			//alert('ajax requests processing, ignoring update');
		}
	}, ActiveTasksUpdateInterval);
}

function ActiveTasksUpdate()
{
	if (ActiveTasksAJAX) return;
	ActiveTasksAJAX = $.ajax({
		'type':		'GET',
		'url':		'?node=tasks',
		'cache':	false,
		'dataType':	'json',
		'beforeSend':	function()
		{
			if (ActiveTasksLastCount)
				Loader.fogStatusUpdate(_L['ACTIVE_TASKS_FOUND'].replace(/%1/, ActiveTasksLastCount).replace(/%2/, (ActiveTasksLastCount == 1 ? '' : 's')), { 'Class': 'loading' });
			else
				Loader.fogStatusUpdate(_L['ACTIVE_TASKS_LOADING'], { 'Class': 'loading' });
		},
		'success':	function(response)
		{
			// Loader
			Loader.fogStatusUpdate(_L['ACTIVE_TASKS_FOUND'].replace(/%1/, response['data'].length).replace(/%2/, (response['data'].length == 1 ? '' : 's')));
			// Variables
			ActiveTasksAJAX = null;
			var tbody = $('tbody', ActiveTasksContainer);
			ActiveTasksLastCount = response['data'].length;
			// Empty search table
			tbody.empty();
			// Do we have search results?
			if (response['data'].length > 0)
			{
				var rows = '';
				// Iterate data
				for (var i in response['data'])
				{
					// Reset
					var row = '<tr id="task-' + response['data'][i]['id'] + '" class="' + (i % 2 ? 'alt2' : 'alt1')  + (response['data'][i]['percent'] ? ' with-progress' : '') + '">';
					// Add column templates
					for (var j in response['templates'])
					{
						// Add attributes to columns
						var attributes = [];
						for (var k in response['attributes'][j])
						{
							attributes[attributes.length] = k + '="' + response['attributes'][j][k] + '"';
						}
						// Add
						row += "<td" + (attributes.length ? ' ' + attributes.join(' ') : '') + ">" + response['templates'][j] + "</td>";
					}
					//response['data'][i]['percentText'].remove();
					// Replace variable data
					row += '<td colspan="7">' + '<tr id="progress-${host_id}" class="${class}"><td colspan="7" class="task-progress-td min"><div class="task-progress-fill min" style="width: ${width}px"></div><div class="task-progress min"><ul><li>${elapsed}/${remains}</li><li>${percent}%</li><li>${copied} of ${total} (${bpm}/min)</li></ul></div></td></tr></td>';
					for (var k in response['data'][i])
					{
						row = row.replace(new RegExp('\\$\\{' + k + '\\}', 'g'), response['data'][i][k]);
					}
					row = row.replace(new RegExp('\\$\\{\w+\\}', 'g'), '');
					// Add to rows
					rows += row + "</tr>";
					// Percentage data
					if (response['data'][i]['percent'])
					{
						rows += response['data'][i]['percent'];
					}
				}
				// Append rows into tbody
				tbody.append(rows);
				// Add data to new elements - elements should be in tbody, so we dont have to search all DOM
				var tr = $('tr', tbody);
				for (i in response['data']) tr.eq(i).data({ 'host_id': response['data'][i]['host_id'], 'host_name': response['data'][i]['host_name'] });
				// Tooltips
				HookTooltips();
				// Hook buttons
				ActiveTasksButtonHook();
				// Show results
				ActiveTasksContainer.show();
				// Ping hosts
				$('.ping').fogPing().removeClass('.ping');
			}
			else
				ActiveTasksTableCheck();
			// Schedule another update
			ActiveTasksUpdateTimerStart();
		},
		'error':	function()
		{
			// Reset
			ActiveTasksAJAX = null;
			// Display error in loader
			Loader.fogStatusUpdate(_L['ACTIVE_TASKS_UPDATE_FAILED'], { 'Class': 'error' });
			// Schedule another update
			ActiveTasksUpdateTimerStart();
		}
	});
}
function ActiveTasksButtonHook()
{
	// Hook: Click: Kill Button - Legacy GET call still works if AJAX fails
	$('.icon-kill').parent().unbind('click').click(function()
	{
		var $this = $(this);
		var ID = $this.parents('tr').attr('id').replace(/^host-/, '');
		var ProgressBar = $('#progress-' + ID, ActiveTasksContainer);
		ActiveTasksRequests[ActiveTasksRequests.length] = $.ajax({
			'type':		'GET',
			'url':		$this.attr('href'),
			//'url':		'ajax/sleep.php',
			'beforeSend':	function()
			{
				// Loader
				$this.find('span').removeClass().addClass('icon icon-loading');
				
				// Unhook this button - multiple clicks now do nothing
				$this.unbind('click').click(function() { return false; });
			},
			'success':	function(data)
			{
				// Fade row out
				$this.parents('tr').fadeOut('fast', function()
				{
					// Remove tr element
					$(this).remove();
					// Remove progress bar
					ProgressBar.remove();
					// Adjust row colours / check for empty table
					ActiveTasksTableCheck();
					// Update tooltips
					HookTooltips();
				});
				// Remove this request from our AJAX request tracking
				ActiveTasksRequests.splice(0, 1);
			},
			'error':	function()
			{
				// Re-hook buttons
				ActiveTasksButtonHook();
				// Remove this request from our AJAX request tracking
				ActiveTasksRequests.splice(0, 1);
			}
		});
		
		// Stop default event
		return false;
	});
	// Hook: Click: Force Button - Legacy GET call still works if AJAX fails
	$('.icon-force').parent().unbind('click').click(function()
	{
		var $this = $(this);
		ActiveTasksRequests[ActiveTasksRequests.length] = $.ajax({
			'type':		'GET',
			'url':		$this.attr('href'),
			'beforeSend':	function()
			{
				// Loader
				$this.find('span').removeClass().addClass('icon icon-loading');
				// Unhook this button - multiple clicks now do nothing
				$this.unbind('click').click(function() { return false; });
			},
			'success':	function(data)
			{
				// Indicate job has been forced
				$this.find('span').removeClass().addClass('icon icon-forced');
				// Remove this request from our AJAX request tracking
				ActiveTasksRequests.splice(0, 1);
			},
			'error':	function()
			{
				// Remove this request from our AJAX request tracking
				ActiveTasksRequests.splice(0, 1);
			}
		});
		// Stop default event
		return false;
	});
	// Hook: Hover: Show Progress Bar on Active Task
	$('.with-progress').hover(function()
	{
		var id = $(this).attr('id').replace(/^host-/, '');
		var progress = $('#progress-' + id);
		progress.show();
		progress.find('.min').addClass('no-min').removeClass('min').end().find('ul').show();
	}, function()
	{
		var id = $(this).attr('id').replace(/^host-/, '');
		var progress = $('#progress-' + id);
		progress.find('.no-min').addClass('min').removeClass('no-min').end().find('ul').hide();
	});
	// Hook: Hover: Show Progress Bar on Progress Bar
	$('tr[id^="progress-"]').hover(function()
	{
		$(this).find('.min').addClass('no-min').removeClass('min').end().find('ul').show();
	}, function()
	{
		$(this).find('.no-min').addClass('min').removeClass('no-min').end().find('ul').hide();
	});
}
function ActiveTasksTableCheck()
{
	// Variables
	var tbody = $('tbody', ActiveTasksContainer);
	var tbodyRows = tbody.find('tr');
	// If we have rows in the table
	if (tbodyRows.size() > 0)
	{
		// Adjust alt colours
		var i = 0;
		tbodyRows.each(function()
		{
			$(this).removeClass().addClass('alt' + (i++ % 2 ? '2' : '1'));
		});
	}
	// No rows in the table
	else
		tbody.html('<tr><td colspan="7" class="no-active-tasks">' + _L['NO_ACTIVE_TASKS'] + '</td></tr>');
}
=======
/****************************************************
 * FOG JavaScript: Active Tasks
 *	Author:		Blackout
 *	Created:	1:48 PM 23/02/2011
 *	Revision:	$Revision: 835 $
 *	Last Update:	$LastChangedDate: 2012-01-12 11:57:46 +1000 (Thu, 12 Jan 2012) $
 ***/
// TODO: Merge this with $.fn.fogAjaxSearch()
var ActiveTasksContainer;
var ActiveTasksLastCount;
// Auto loader
$(function()
{
	// Show Task Container if we have items
	ActiveTasksContainer = $('#active-tasks');
	if (ActiveTasksContainer.find('tbody > tr').size() > 0) ActiveTasksContainer.show();
	// Hook buttons
	ActiveTasksButtonHook();
	// Update on load
	//ActiveTasksUpdate();
	// Update timer
	ActiveTasksUpdateTimerStart();
});

function ActiveTasksUpdateTimerStart()
{
	ActiveTasksUpdateTimer = setTimeout(function()
	{
		if (!ActiveTasksRequests.length)
		{
			ActiveTasksUpdate();
		}
		else
		{
			//alert('ajax requests processing, ignoring update');
		}
	}, ActiveTasksUpdateInterval);
}

function ActiveTasksUpdate()
{
	if (ActiveTasksAJAX) return;
	ActiveTasksAJAX = $.ajax({
		'type':		'GET',
		'url':		'?node=tasks',
		'cache':	false,
		'dataType':	'json',
		'beforeSend':	function()
		{
			if (ActiveTasksLastCount)
				Loader.fogStatusUpdate(_L['ACTIVE_TASKS_FOUND'].replace(/%1/, ActiveTasksLastCount).replace(/%2/, (ActiveTasksLastCount == 1 ? '' : 's')), { 'Class': 'loading' });
			else
				Loader.fogStatusUpdate(_L['ACTIVE_TASKS_LOADING'], { 'Class': 'loading' });
		},
		'success':	function(response)
		{
			// Loader
			Loader.fogStatusUpdate(_L['ACTIVE_TASKS_FOUND'].replace(/%1/, response['data'].length).replace(/%2/, (response['data'].length == 1 ? '' : 's')));
			// Variables
			ActiveTasksAJAX = null;
			var tbody = $('tbody', ActiveTasksContainer);
			ActiveTasksLastCount = response['data'].length;
			// Empty search table
			tbody.empty();
			// Do we have search results?
			if (response['data'].length > 0)
			{
				var rows = '';
				// Iterate data
				for (var i in response['data'])
				{
					// Reset
					var row = '<tr id="task-' + response['data'][i]['id'] + '" class="' + (i % 2 ? 'alt2' : 'alt1')  + (response['data'][i]['percent'] ? ' with-progress' : '') + '">';
					// Add column templates
					for (var j in response['templates'])
					{
						// Add attributes to columns
						var attributes = [];
						for (var k in response['attributes'][j])
						{
							attributes[attributes.length] = k + '="' + response['attributes'][j][k] + '"';
						}
						// Add
						row += "<td" + (attributes.length ? ' ' + attributes.join(' ') : '') + ">" + response['templates'][j] + "</td>";
					}
					//response['data'][i]['percentText'].remove();
					// Replace variable data
					if (response['data'][i]['percent'] > 0 && response['data'][i]['percent'] < 100)
					{
						row += '<td colspan="7">' + '<tr id="progress-${host_id}" class="${class}"><td colspan="7" class="task-progress-td min"><div class="task-progress-fill min" style="width: ${width}px"></div><div class="task-progress min"><ul><li>${elapsed}/${remains}</li><li>${percentText}%</li><li>${copied} of ${total} (${bpm}/min)</li></ul></div></td></tr></td>';
					}
					for (var k in response['data'][i])
					{
						row = row.replace(new RegExp('\\$\\{' + k + '\\}', 'g'), response['data'][i][k]);
					}
					row = row.replace(new RegExp('\\$\\{\w+\\}', 'g'), '');
					// Add to rows
					rows += row + "</tr>";
					// Percentage data
					if (response['data'][i]['percent'])
					{
						rows += response['data'][i]['percent'];
					}
				}
				// Append rows into tbody
				tbody.append(rows);
				// Add data to new elements - elements should be in tbody, so we dont have to search all DOM
				var tr = $('tr', tbody);
				for (i in response['data']) tr.eq(i).data({ 'host_id': response['data'][i]['host_id'], 'host_name': response['data'][i]['host_name'] });
				// Tooltips
				HookTooltips();
				// Hook buttons
				ActiveTasksButtonHook();
				// Show results
				ActiveTasksContainer.show();
				// Ping hosts
				$('.ping').fogPing().removeClass('.ping');
			}
			else
				ActiveTasksTableCheck();
			// Schedule another update
			ActiveTasksUpdateTimerStart();
		},
		'error':	function()
		{
			// Reset
			ActiveTasksAJAX = null;
			// Display error in loader
			Loader.fogStatusUpdate(_L['ACTIVE_TASKS_UPDATE_FAILED'], { 'Class': 'error' });
			// Schedule another update
			ActiveTasksUpdateTimerStart();
		}
	});
}
function ActiveTasksButtonHook()
{
	// Hook: Click: Kill Button - Legacy GET call still works if AJAX fails
	$('.icon-kill').parent().unbind('click').click(function()
	{
		var $this = $(this);
		var ID = $this.parents('tr').attr('id').replace(/^host-/, '');
		var ProgressBar = $('#progress-' + ID, ActiveTasksContainer);
		ActiveTasksRequests[ActiveTasksRequests.length] = $.ajax({
			'type':		'GET',
			'url':		$this.attr('href'),
			//'url':		'ajax/sleep.php',
			'beforeSend':	function()
			{
				// Loader
				$this.find('span').removeClass().addClass('icon icon-loading');
				
				// Unhook this button - multiple clicks now do nothing
				$this.unbind('click').click(function() { return false; });
			},
			'success':	function(data)
			{
				// Fade row out
				$this.parents('tr').fadeOut('fast', function()
				{
					// Remove tr element
					$(this).remove();
					// Remove progress bar
					ProgressBar.remove();
					// Adjust row colours / check for empty table
					ActiveTasksTableCheck();
					// Update tooltips
					HookTooltips();
				});
				// Remove this request from our AJAX request tracking
				ActiveTasksRequests.splice(0, 1);
			},
			'error':	function()
			{
				// Re-hook buttons
				ActiveTasksButtonHook();
				// Remove this request from our AJAX request tracking
				ActiveTasksRequests.splice(0, 1);
			}
		});
		
		// Stop default event
		return false;
	});
	// Hook: Click: Force Button - Legacy GET call still works if AJAX fails
	$('.icon-force').parent().unbind('click').click(function()
	{
		var $this = $(this);
		ActiveTasksRequests[ActiveTasksRequests.length] = $.ajax({
			'type':		'GET',
			'url':		$this.attr('href'),
			'beforeSend':	function()
			{
				// Loader
				$this.find('span').removeClass().addClass('icon icon-loading');
				// Unhook this button - multiple clicks now do nothing
				$this.unbind('click').click(function() { return false; });
			},
			'success':	function(data)
			{
				// Indicate job has been forced
				$this.find('span').removeClass().addClass('icon icon-forced');
				// Remove this request from our AJAX request tracking
				ActiveTasksRequests.splice(0, 1);
			},
			'error':	function()
			{
				// Remove this request from our AJAX request tracking
				ActiveTasksRequests.splice(0, 1);
			}
		});
		// Stop default event
		return false;
	});
	// Hook: Hover: Show Progress Bar on Active Task
	$('.with-progress').hover(function()
	{
		var id = $(this).attr('id').replace(/^host-/, '');
		var progress = $('#progress-' + id);
		progress.show();
		progress.find('.min').addClass('no-min').removeClass('min').end().find('ul').show();
	}, function()
	{
		var id = $(this).attr('id').replace(/^host-/, '');
		var progress = $('#progress-' + id);
		progress.find('.no-min').addClass('min').removeClass('no-min').end().find('ul').hide();
	});
	// Hook: Hover: Show Progress Bar on Progress Bar
	$('tr[id^="progress-"]').hover(function()
	{
		$(this).find('.min').addClass('no-min').removeClass('min').end().find('ul').show();
	}, function()
	{
		$(this).find('.no-min').addClass('min').removeClass('no-min').end().find('ul').hide();
	});
}
function ActiveTasksTableCheck()
{
	// Variables
	var tbody = $('tbody', ActiveTasksContainer);
	var tbodyRows = tbody.find('tr');
	// If we have rows in the table
	if (tbodyRows.size() > 0)
	{
		// Adjust alt colours
		var i = 0;
		tbodyRows.each(function()
		{
			$(this).removeClass().addClass('alt' + (i++ % 2 ? '2' : '1'));
		});
	}
	// No rows in the table
	else
		tbody.html('<tr><td colspan="7" class="no-active-tasks">' + _L['NO_ACTIVE_TASKS'] + '</td></tr>');
}
>>>>>>> dev-branch
