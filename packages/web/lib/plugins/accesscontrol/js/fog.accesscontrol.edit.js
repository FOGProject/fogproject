$(function() {
    $('#userMeShow:checkbox').change(function(e) {
        if ($(this).is(':checked')) $('#userNotInMe').show();
        else $('#userNotInMe').hide();
        e.preventDefault();
    });
    $('#userMeShow:checkbox').trigger('change');
});
