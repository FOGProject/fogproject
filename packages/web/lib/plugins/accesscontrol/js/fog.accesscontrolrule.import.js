(function($) {
    var importFile = $('#importfile'),
        importForm = $('#import-form'),
        importFormBtn = $('#import-send');
    importForm.on('submit', function(e) {
        e.preventDefault();
    });
    importFormBtn.on('click', function() {
        importFormBtn.prop('disabled', true);
        importForm.processForm(function(err) {
            importFormBtn.prop('disabled', false);
        });
    });
})(jQuery);
