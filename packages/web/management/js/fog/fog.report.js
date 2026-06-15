(function($) {
    checkboxAssociations('.toggle-checkboxAction:checkbox','.toggle-action:checkbox');
    // Submit the report form when a virus-history delete checkbox is toggled.
    // Replaces an inline onclick="this.form.submit()" that violated the
    // Content-Security-Policy script-src 'self' directive. Delegated so it
    // also covers rows injected after an AJAX search.
    $(document).on('click', '.delvid', function() {
        $(this).parents('form').submit();
    });
})(jQuery);
