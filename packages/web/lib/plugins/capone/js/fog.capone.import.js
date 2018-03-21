(function($) {
    var importFile = $('#importfile'),
        importForm = $('#import-form'),
        importFormBtn = $('#import-send');
    importForm.on('submit', function(e) {
        e.preventDefault();
    });
    importFormBtn.on('click', function() {
        importFormBtn.prop('disabled', true);
        // TODO: Start uploading file.
        // Send for processing.
        // complete data set and reset our layout.
        importFormBtn.prop('disabled', false);
    });
})(jQuery);
