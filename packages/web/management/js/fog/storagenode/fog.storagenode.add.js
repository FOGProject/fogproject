(function($) {
    var createForm = $('#storagenode-create-form'),
        createFormBtn = $('#send'),
        jscolors = $('.jscolor');
    if ($(jscolors).length !== 0) {
        $(jscolors).each((index, element) => {
            let color = $('#graphcolor').val();
            new jscolor(element, {'value': color});
        });
    }
    createForm.on('submit', function(e) {
        e.preventDefault();
    });
    createFormBtn.on('click', function() {
        createFormBtn.prop('disabled', true);
        createForm.processForm(function(err) {
            createFormBtn.prop('disabled', false);
        });
    });
})(jQuery);
