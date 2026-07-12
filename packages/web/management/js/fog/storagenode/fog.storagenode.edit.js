(function($) {
    // ----------------------------------------------------
    // GENERAL TAB
    var jscolors = $('.jscolor');
    if ($(jscolors).length !== 0) {
        $(jscolors).each((index, element) => {
            let color = $('#graphcolor').val();
            new jscolor(element, {'value': color});
        });
    }

    $.registerGeneralTab({
        nameInputSel: '#storagenode',
        formSel: '#storagenode-general-form'
    });
})(jQuery);
