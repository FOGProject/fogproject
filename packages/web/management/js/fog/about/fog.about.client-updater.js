(function($) {
    $('input[name="delcu"]').on('click', function(e) {
        e.preventDefault();
        this.form.submit();
        this.remove();
    });
})(jQuery);
