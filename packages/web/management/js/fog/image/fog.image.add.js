(function($) {
    $('#image-create-form').wireCreateForm();
    $('.imagepath-input').on('keyup change blur focus focusout', function(e) {
        var start = this.selectionStart,
            end = this.selectionEnd;
        this.value = this.value.replace(/[^\w+\/\.\-]/g,'');
        this.setSelectionRange(start,end);
        e.preventDefault();
    });
    if ($('.imagepath-input').val().length <= 0) {
        $('.imagename-input').on('keyup change blur focus focusout', function(e) {
            $('.imagepath-input').val(this.value).trigger('change');
        });
    }
    $('.slider').slider();
    var image = $('#image'),
        path = $('#path');
    if (path.val().length == 0 || path.val() == null) {
        image.mirror(path, /[^\w+\/\.-]/g);
    }
    path.on('change', function(e) {
        var start = this.selectionStart,
            end = this.selectionEnd;
        this.value = this.value.replace(/[^\w+\/\.-]/g);
        this.setSelectionRange(start, end);
    });
})(jQuery);
