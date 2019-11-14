(function($) {
    var createForm = $('#image-create-form'),
        createFormBtn = $('#send');
    createForm.on('submit', function(e) {
        e.preventDefault();
    });
    createFormBtn.on('click', function() {
        createFormBtn.prop('disabled', true);
        createForm.processForm(function(err) {
            createFormBtn.prop('disabled', false);
        });
    });
    $('.imagepath-input').on('keyup change blur focus focusout', function(e) {
        var start = this.selectionStart,
            end = this.selectionEnd;
        this.value = this.value.replace(/[^\w+\/\.\-]/g,'');
        this.setSelectionRange(start,end);
        e.preventDefault();
    });
    $('.imagename-input').on('keyup change blur focus focusout', function(e) {
        if ($('.imagepath-input').val().length > 0) return;
        $('.imagepath-input').val(this.value).trigger('change');
    });
    $('.slider').slider();
})(jQuery);
