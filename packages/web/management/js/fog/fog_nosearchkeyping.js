/****************************************************
 * FOG Dashboard JS
 *	Author:		Blackout
 *	Created:	10:05 AM 16/04/2011
 *	Revision:	$Revision: 1632 $
 *	Last Update:	$LastChangedDate: 2014-05-08 12:27:00 -0400 (Thu, 08 May 2014) $
 ***/

// Language variables - move to PHP generated file to include
var _L = new Array();
// Search
_L['PERFORMING_SEARCH'] = 'Searching...';
_L['ERROR_SEARCHING'] = 'Search failed';
_L['SEARCH_LENGTH_MIN'] = 'Search query too short';
_L['SEARCH_RESULTS_FOUND'] = '%1 result%2 found';

// Active Tasks
_L['NO_ACTIVE_TASKS'] = "No active tasks found";
_L['ACTIVE_TASKS_UPDATE_FAILED'] = "Failed to fetch active tasks";
_L['UPDATING_ACTIVE_TASKS'] = "Fetching active tasks";
_L['ACTIVE_TASKS_FOUND'] = '%1 active task%2 found';
_L['ACTIVE_TASKS_LOADING'] = 'Loading...';

// Ping
_L['PING_START'] = 'Pinging %1 hosts...';
_L['PING_PROGRESS'] = '<p>Pinging: %1</p><p>Progress: %2/%3</p>';
_L['PING_COMPLETE'] = 'Pinging %1 hosts complete!';

// Variables
var PingActive = new Array();
var StatusAutoHideTimer;
var StatusAutoHideDelay = 3000;
var PingDelay = 2000;
// Active Tasks
var ActiveTasksUpdateTimer;
var ActiveTasksUpdateInterval = 5000;
var ActiveTasksRequests = new Array();
var ActiveTasksAJAX = null;

// DOM Elements used frequently
var Content;
var Loader;

// Main FOG JQuery Functions
(function($)
{
	// Assign DOM elements
	Content = $('#content');
	Loader = $('#loader');

	// Custom FOG JQuery functions
	$.fn.fogAjaxSearch = function(opts)
	{
		// If no elements were found before this was called
		if (this.length == 0) return this;
		
		// Default Options
		var Defaults = {
			'URL':			'ajax/host.search.php',
			'Container':		'#search-content',
			'SearchDelay':		500,
			'SearchMinLength':	1,
			'Template':		function(data, i)
			{
				return '<tr><td>' + data['host_name'] + '</td></tr>';
			},
			'CallbackSearchSuccess': function() {}
		};
		
		// Variables
		var SearchAJAX = null;
		var SearchTimer;
		var SearchLastQuery;
		var Options = $.extend({}, Defaults, opts || {});
		var Container = $(Options.Container);
		var ActionBox = $('#action-box');
		
		// Check if containers exist
		if (!Container.length)
		{
			alert('No Container element found: ' + Options.Container);
			return this;
		}
		
		// If the container already contains data, show, else hide
		if ($('tbody > tr', Container).filter('.no-active-tasks').size() > 0)
		{
			Container.show();
			ActionBox.show();
		}
		else
		{
			Container.hide();
			ActionBox.hide();
		}

		
		// Iterate each element
		return this.each(function()
		{
			// Variables
			var $this = $(this);
			var SubmitButton = $('#' + $this.attr('id') + '-submit');
		
			// Bind search input
			// keyup - perform search
			$this.keyup(function(e)
			{
				if (e.keyCode == 13) {
					if (this.SearchTimer) clearTimeout(this.SearchTimer);				
					this.SearchTimer = setTimeout(function() {
						PerformSearch();
					}, 
					Options.SearchDelay);
				}
			// focus
			}).focus(function() {
				var $this = $(this).removeClass('placeholder');
				
				if ($this.val() == $this.attr('placeholder'))
				{
					$this.val('');
				}
			// blur - if the search textbox is empty, reset everything!
			}).blur(function() {
				var $this = $(this);
				
				if ($this.val() == '')
				{
					$this.addClass('placeholder').val($this.attr('placeholder'));
					if (this.SearchAJAX) this.SearchAJAX.abort();
					if (this.SearchTimer) clearTimeout(this.SearchTimer);
					Loader.fogStatusUpdate();
					$('tbody', Container).empty().parents('table').hide();
				}
			// set value to nothing - occurs on refresh for browsers that remember
			}).each(function()
			{
				var $this = $(this);
				
				if ($this.val() != $this.attr('placeholder'))
				{
					$this.val('')
				}
			// Stop submit event for parent form - when you press enter in the search box
			}).parents('form').submit(function()
			{
				return false;
			});
						
			function PerformSearch()
			{
				// Extract Query
				var Query = $this.val();
				
				// Is this query different from the last?
				if (Query == this.SearchLastQuery) return;
				this.SearchLastQuery = Query;
				
				// Length check
				if (Query.length < Options.SearchMinLength)
				{
					//Loader.fogStatusUpdate(_L['SEARCH_LENGTH_MIN'].replace(/%1/, Options.SearchMinLength), { 'Class': 'error' });
					return;
				}
				
				// Abort previous AJAX query if one is already running
				if (this.SearchAJAX) this.SearchAJAX.abort();
				
				// Run AJAX
				this.SearchAJAX = $.ajax({
					'type':		$('#search-wrapper').attr('method'),
					'cache':	false,
					'url':		$('#search-wrapper').attr('action'),
					'data':		{ 'crit': Query },
					'dataType':	'json',
					'beforeSend':	function()
					{
						// Abort all pings of current hosts
						$('.ping').fogPingAbort();
						
						// Update Status
						//Loader.fogStatusUpdate(_L['PERFORMING_SEARCH'], { 'Class': 'loading' });
						//Loader.fogStatusUpdate(_L['PERFORMING_SEARCH']);
						Loader.fogStatusUpdate();
						
						// Submit button spinner
						SubmitButton.addClass('searching');
					},
					'success':	function(response)
					{
						// Submit button spinner
						SubmitButton.removeClass('searching');
						
						// Variables
						var tbody = $('tbody', Container);
						var rows = '';
						
						// Empty search table
						tbody.empty();
						
						// Do we have search results?
						if (response['data'].length > 0)
						{
							// Status Update
							Loader.fogStatusUpdate(_L['SEARCH_RESULTS_FOUND'].replace(/%1/, response['data'].length).replace(/%2/, (response['data'].length == 1 ? '' : 's')), { 'Class': 'info' });
						
							// Iterate data
							for (var i in response['data'])
							{
								// Reset
								var row = "<tr>";
								
								// Add column templates
								for (var j in response['templates'])
								{
									// Add attributes to columns
									var attributes = [];
									for (var k in response['attributes'][j])
									{
										attributes[attributes.length] = k + '="' + response['attributes'][j][k] + '"';
									}
									
									// Create row
									row += "<td" + (attributes.length ? ' ' + attributes.join(' ') : '') + ">" + response['templates'][j] + "</td>";
								}
								
								// Replace variable data
								for (var k in response['data'][i])
								{
									row = row.replace(new RegExp('\\$\\{' + k + '\\}', 'g'), (typeof(response['data'][i][k]) != 'undefined' ? response['data'][i][k] : ''));
								}
								
								// Add to rows
								rows += row + "</tr>";
							}
							
							// Append rows into tbody
							tbody.append(rows);
							
							// Add data to new elements - elements should be in tbody, so we dont have to search all DOM
							var tr = $('tr', tbody);
							for (i in response['data']) tr.eq(i).addClass((i % 2 ? 'alt1' : 'alt2')).data({ 'id': response['data'][i]['id'], 'host_name': response['data'][i]['host_name'] });
							
							// Tooltips
							HookTooltips();
							
							// Show results
							Container.show();
							ActionBox.show();
							
							// Ping hosts
							//$('.ping', Container).fogPing();
							
							// Callback
							Options.CallbackSearchSuccess(Container);

						}
						else
						{
							// No results - hide content boxes, show nice message
							Container.hide();
							ActionBox.hide();
							
							// Show nice error
							Loader.fogStatusUpdate(_L['SEARCH_RESULTS_FOUND'].replace(/%1/, '0').replace(/%2/, 's'), { 'Class': 'error' });
						}
						
						this.SearchAJAX = null;
					},
					'error':	function(jqXHR, textStatus, errorThrown)
					{
						// Error - hide content boxes, show nice message
						Container.hide();
						ActionBox.hide();
						
						// Show nice error
						Loader.fogStatusUpdate(_L['ERROR_SEARCHING'] + (errorThrown != '' ? ': ' + (errorThrown == 'Not Found' ? 'URL Not Found' : errorThrown) : ''), { 'Class': 'error' });
						
						// Reset
						this.SearchAJAX = null;
						this.SearchLastQuery = null;
					}
				});
			}
		});
	}

	$.fn.fogTableInfo = function() {
		// Add table header sorting information.
		$('table:not(#search-content) > thead > tr > td').addClass('hand');
		$('table:not(#search-content)').tablesorter({ sortList: [[0,0]] });
	}
	
	$.fn.fogPing = function(opts)
	{	
		// If no elements were found before this was called
		if (this.length == 0) return this;
		// If Ping function has been disabled, return
		if (typeof(FOGPingActive) != 'undefined' && FOGPingActive != 1) return this;
		
		// Default Options
		var Defaults = {
			'Threads':	4,
			'Delay':	PingDelay,
			'UpdateStatus':	true
		};
		
		// Variables
		var Options = $.extend({}, Defaults, opts || {});
		// Row List
		var List = $(this).get();
		var ListTotal = List.length;
		var StartTime = new Date().getTime();
		var Timer;
		
		// Main
		if (Options.Delay)
		{
			setTimeout(function() { Run(); }, Options.Delay);
		}
		else
		{
			Run();
		}
		
		function Run()
		{
			// Log
			if (Options.UpdateStatus)
			{
				Loader.fogStatusUpdate(_L['PING_START'].replace(/%1/, ListTotal), { 'Class': 'info' });
			}
			
			// Start threads
			for (var i = 0; i < Options.Threads; i++)
			{
				PerformPing();
			}
		}
		
		// Ping()
		function PerformPing(start)
		{		

			// Variables
			var start = start || 0;
			// Extract element from List - dont turn into JQuery object yet (for speed)
			var element = List[start];
			// Remove element from List so no other thread can use it
			List.splice(start, 1);
			//var ListCount = List.length;
			// JQuery element
			element = $(element);
			// Get element's TR - this contains hostname data
			var tr = element.parents('tr');
			// Extract hostname
			var hostname =  tr.data('host_name') || tr.attr('id') && tr.attr('id').replace(/^host-/, '') || tr.children("[id]").attr('id').replace(/^host-/, ''); 

			// If we found the Hostname
			if (hostname)
			{
				element.data('fog-ping', $.ajax(
				{
					'type':		'GET',
					'cache':	false,
					'url':		'ajax/host.ping.php',
					'data':		{ 'ping': hostname },
					'dataType':	'text',
					'beforeSend':	function()
					{
						element.addClass('icon').addClass('icon-loading');
					},
					'success':	function(data)
					{						
						element.removeClass('icon-loading');
						//alert(hostname + data);
						if (data == "1")		
						{
							element.attr('title', 'Host up').addClass('icon-ping-up');
						}
						else if (data == "0")
						{
							element.attr('title', 'Host down').addClass('icon-ping-down');
						}
						else if (data == "99")
						{
							element.attr('title', 'FOG Session Invalid').addClass('icon-ping-error');
						}
						else if (data == "98")
						{
							element.attr('title', 'No host passed to ping').addClass('icon-ping-error');
						}
						else if (data == "97")
						{
							element.attr('title', 'Ping disabled via FOG Configuration').addClass('icon-ping-error');
						}
						else
						{
							element.attr('title', data).addClass('icon-ping-error');
						}
						
						// Tooltip
						element.tipsy({ 'gravity': 's' });
						
						var ListCount = List.length;
						
						// Start another Ping if there are still elements to process
						if (ListCount)
						{
							if (Options.UpdateStatus)
							{
								Loader.fogStatusUpdate(_L['PING_PROGRESS'].replace(/%1/, hostname).replace(/%2/, (ListTotal-ListCount)).replace(/%3/, ListTotal), { 'Progress': Math.round((ListTotal-ListCount)/ListTotal*100) });
							}
						
							PerformPing();
						}
						else if (Options.UpdateStatus)
						{
							Loader.fogStatusUpdate(_L['PING_COMPLETE'].replace(/%1/, ListTotal), { 'Progress': 100 });
						}
					},
					'error':	function(data)
					{
						element.attr('title', 'Ping Aborted').addClass('icon-ping-error').tipsy({ 'gravity': 's' });
					}
				}));
			}
		}
		
		return $(this);
	}
	
	$.fn.fogPingAbort = function(opts)
	{
		// If Ping function has been disabled, return
		if (typeof(FOGPingActive) != 'undefined' && FOGPingActive != 1) return this;
		
		// Process each ping element -> check data for AJAX request -> abort AJAX request if it exists
		return $(this).each(function()
		{
			var $this = $(this);
			
			if ($this.data('fog-ping'))
			{
				$this.data('fog-ping').abort();
				$this.data('fog-ping', '');
			}
		});
	}
	
	$.fn.fogMessageBox = function()
	{
		// If no elements were found before this was called
		if (this.length == 0) return this;
		
		// Variables
		var Messages = new Array;
		
		// Iterate each element
		this.each(function()
		{
			// Variables
			var $this = $(this);
			
			// Push message into array
			Messages[Messages.length] = $this.html();
		});
		
		// Display messages if any were found
		if (Messages.length > 0)
		{
			Loader.fogStatusUpdate(Messages.join('</p><p>')).hide().fadeIn();
		}
		
		return this;
	}
	
	// Common FOG Functions	
	$.fn.fogStatusUpdate = function(txt, opts)
	{
		// Defaults
		var Defaults = {
			'AutoHide':	0,
			'Class':	'',
			'Raw':		false,
			'Progress':	null
		};
		
		// Build Options
		var Options = $.extend({}, Defaults, opts || {});
		var Loader = $(this);
		var ProgressBar = $('#progress', this);
		
		// Progress bar update
		if (Options.Progress)
		{
			ProgressBar.show().progressBar(Options.Progress);
		}
		else
		{
			ProgressBar.hide().progressBar(0);
		}
		
		// Status text update
		if (!txt)
		{
			// Reset status and hide
			Loader.find('p').remove().end().hide();
		}
		else
		{
			// Set and show status
			Loader.find('p').remove().end().prepend((Options.Raw ? txt : '<p>' + txt + '</p>')).show();
		}
		
		// Class
		Loader.removeClass();
		if (Options.Class) Loader.addClass(Options.Class);
		
		// AutoHide
		if (StatusAutoHideTimer) clearTimeout(StatusAutoHideTimer);
		if (Options.AutoHide)
		{
			// Hide timeout
			StatusAutoHideTimer = setTimeout(function()
			{
				// Fade out Loader
				Loader.fadeOut('fast');
			}, Options.AutoHide);
		}
		
		return this;
	}
	
	$.fn.fogVariable = function(opts)
	{		
		// If no elements were found before this was called
		if (this.length == 0) return this;
		
		// Default Options
		var Defaults = {
			'Debug':	false
		};
		
		// Variables
		var Options = $.extend({}, Defaults, opts || {});
		var Variables = {};
		
		// Iterate each element
		return this.each(function()
		{
			// Variables
			var $this = $(this);
			
			// Set variable in window - this will make the variable 'global'
			window[$this.attr('id').toString()] = $this.html().toString();
			
			// DEBUG
			if (Options.Debug) alert($this.attr('id').toString() + ' = ' + $this.html().toString());
			
			// Remove element from DOM
			$this.remove();
		});
	}
	
	jQuery.fn.exists = function()
	{
		return this.length > 0;
	}
	jQuery.fn.isIE8 = function()
	{
		return $.browser.msie && parseInt($.browser.version, 10) <= 8;
	}

	$('#action-box').show();
	

	/*$.fn.fogTemplate = function(opts)
	{
		// If no elements were found before this was called
		if (this.length == 0) return this;
		
		// Default Options
		var Defaults = {
		};
		
		// Variables
		var Options = $.extend({}, Defaults, opts || {});
		
		// Iterate each element
		return this.each(function()
		{
			// Variables
			var $this = $(this);
		});
	}*/

})(jQuery);
