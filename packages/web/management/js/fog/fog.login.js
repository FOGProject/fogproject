(function($) {
    //Initialize Select2 Elements
    $('.select2').select2({
        width: '100%'
    });
    $('input').iCheck({
        checkboxClass: 'icheckbox_square-blue',
        radioClass: 'iradio_square-blue',
        increaseArea: '20%' // optional
      });
    $('#uname').select().focus();
})(jQuery);
