// Blackout - 10:28 AM 27/12/2011
(function($){
 var methods = {
 'init': function(options)
 {
 return this.each(function()
	 {
	 var $this = $(this);
	 
	 $this	.addClass('placeholder')
	 .data('placeholder', $this.attr('placeholder'))
	 .val($this.attr('placeholder'))
	 .focus(function()
		 {
		 var $this = $(this);
		 
		 if ($this.val() == $this.data('placeholder') || $this.val() == '')
		 {
		 $(this).removeClass('placeholder').val('');
		 }
		 })
	 .blur(function()
		 {
		 var $this = $(this);
		 
		 if ($this.val() == '')
		 {
		 $(this).addClass('placeholder').val($this.data('placeholder'));
		 }
		 })
	 .parents('form')
		 .submit(function()
				 {
				 $(this).find('.placeholder').val('');
				 
				 return true;
				 });
	 });
 }
 };
 
	 $.fn.placeholder = function(method)
	 {
		 if (methods[method])
		 {
			 return methods[method].apply(this, Array.prototype.slice.call(arguments, 1));
		 }
		 else if (typeof method === 'object' || ! method)
		 {
			 return methods.init.apply(this, arguments);
		 }
		 else
		 {
			 $.error('Method ' + method + ' does not exist on jQuery.placeholder');
		 }
	 };
 
})(jQuery);
