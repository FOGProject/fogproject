(function($) {
    var jscolors = $('.jscolor');
    if ($(jscolors).length !== 0) {
        $(jscolors).each((index, element) => {
            let color = $('#graphcolor').val();
            new jscolor(element, {'value': color});
        });
    }
    $('#storagenode-create-form').wireCreateForm();
})(jQuery);
