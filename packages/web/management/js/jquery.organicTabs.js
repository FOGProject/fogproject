// Updated: Blackout - 12:22 PM 30/11/2011
(function($)
{
	$.organicTabs = function(el, options)
	{
		// Base of class
		var base = this;
		
		// Options
		base.options = $.extend({},$.organicTabs.defaultOptions, options);
		
		// Variables
		base.$el = $(el);
		base.$nav = base.$el.find('> ul:eq(0)');
		base.$content = (base.options.targetID ? $(base.options.targetID) : base.$el.find('> div:eq(0)'));
		base.anchor = location.hash.substring(1) || '';
		base.total = base.$nav.find('li > a[href*="#"]').size();
		
		// Init function
		base.init = function()
		{
			// Nav: Hook click event
			base.$nav.delegate('li > a[href*="#"]', 'click', function()
			{
				// New content and content ID
				var newContent = $(this);
				var newContentID = base.findAnchor(newContent.attr('href'));
				
				// Figure out current list via CSS class
				var currentContentID = base.findAnchor(base.$nav.find('a.organic-tabs-current').attr('href'));
				
				// DEBUG
				//if (window.console) window.console.log('current: ', currentContentID, ', new: ', newContentID);
				
				// Set content's outer wrapper height to (static) height of current inner content
				base.$content.height( base.$content.height() );
				
				if ((newContentID != currentContentID) && (base.$el.find(':animated').length == 0))
				{
					var newContentContainer = $('#' + newContentID);
					
					if (newContentContainer.length > 0)
					{
						// Found new content container - show
						// Fade out current list
						$('#' + currentContentID).fadeOut(base.options.speed, function()
						{
							$(this).hide();
						
							// Fade in new list on callback
							newContentContainer.fadeIn(base.options.speed);
							
							// Adjust outer wrapper to fit new list snuggly
							base.$content.animate({
								'height'	: $('#' + newContentID).height()
							});
							
							// Remove highlighting - Add to just-clicked tab
							base.$nav.find('li a').removeClass('organic-tabs-current');
							newContent.addClass('organic-tabs-current');
						});
						
						location.hash = newContentID;
					}
					else if (newContent.attr('href').substring(0, 1) != '#' && window.location.href.replace(new RegExp(window.location.hash), '') != newContent.attr('href'))
					{
						// Content container was not found - but URL differs from the current URL - forward browser to new URL with anchor so the tab can be auto displayed
						location = newContent.attr('href');
					}
					else
					{
						// Content container was not found - 
						alert('Failed to find new content container with the ID: #' + newContentID);
					}
				}
				else if ((newContentID == currentContentID) || (base.$el.find(':animated').length != 0))
				{
					location = newContent.attr('href');
				}
				
				// Don't behave like a regular link
				// Stop propegation and bubbling
				return false;
			});
			
			// Find start slide from anchor if it exists
			var startSlide = (base.anchor ? base.$nav.find('a[href$="#' + base.anchor + '"]').parent().index() : 0);
			
			// Content: Hide all tabs expect for the first tab
			base.$content.children().hide().removeClass('organic-tabs-hidden').eq(startSlide).show();
			
			// Nav: Make first tab the default selected
			base.$nav.find('li > a[href*="#"]').removeClass('organic-tabs-current').eq(startSlide).addClass('organic-tabs-current');
			
			// Content: On load -> Check anchor -> Click anchor link to change tab to content
			if (base.anchor)
			{
				// TODO: Fix - make seemless on load instead of animate
				//base.$nav.find('.organic-tabs-current').removeClass('organic-tabs-current').end().find('a[href="#' + base.anchor + '"]').addClass('organic-tabs-current');
				//base.$content.find('#' + base.anchor).show().siblings().hide();
				
				//$('a[href$="#' + base.anchor + '"]').click();
			}
		};
		
		// Function: Returns current active tab
		base.current = function()
		{
			return base.$nav.find('.organic-tabs-current').parent().index();
		};
		
		// Function: Selects a tab
		base.activate = function(position)
		{
			base.$nav.find('li > a[href*="#"]').eq(position).click();
		};
		
		// Function: Activates the next tab
		base.next = function()
		{
			var current = base.current();
			var next = (current == (base.total - 1) ? 0 : current + 1);
			
			base.activate(next);
			
			return next;
		};
		
		// Function: Activates the previous tab
		base.prev = function()
		{
			var current = base.current();
			var prev = (current == 0 ? (base.total - 1) : current - 1);
			
			base.activate(prev);
			
			return prev;
		};
		
		base.findAnchor = function(url)
		{
			return (url.substring(0, 1) == '#' ? url.substring(1) : url.split('#')[1]);
		}
		
		// Add 'organicTabs' data to element - can be used to call organicTabs
		// i.e. $('#tabs').data('organicTabs').next();
		base.$el.data('organicTabs', base);
		
		// Run Init
		base.init();
	};
	
	// Defaults
	$.organicTabs.defaultOptions = {
		'speed'			: 300,
		'targetID'		: ''
	};
	
	// jQuery Function
	$.fn.organicTabs = function(options)
	{
		return this.each(function()
		{
			(new $.organicTabs(this, options));
		});
	};
	// Form no redirect, but update page.
	$('form').not('#action-box,#search-wrapper').on('submit',function(e) {
		$.post(url);
		return false;
	});
})(jQuery);
