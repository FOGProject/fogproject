(function($) {
    // ----------------------------------------------------
    // GENERAL TAB
    var originalName = $('#storagenode').val();

    var updateName = function(newName) {
        var e = $('#pageTitle'),
            text = e.text();
        text = text.replace(": " + originalName, ": " + newName);
        document.title = text;
        e.text(text);
    };

    var generalForm = $('#storagenode-general-form'),
        generalFormBtn = $('#general-send'),
        generalDeleteBtn = $('#general-delete');

    generalForm.on('submit', function(e) {
        e.preventDefault();
    });
    generalFormBtn.on('click', function(e) {
        generalFormBtn.prop('disabled', true);
        generalDeleteBtn.prop('disabled', true);
        Common.processForm(generalForm, function(err) {
            generalFormBtn.prop('disabled', false);
            generalDeleteBtn.prop('disabled', false);
            if (err) {
                return;
            }
            updateName($('#storagenode').val());
            originalName = $('#storagenode').val();
        });
    });
})(jQuery);
