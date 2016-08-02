$(function() {
    // Show hide based on checked state.
    $('#hostMeShow:checkbox').change(function(e) {
        if ($(this).is(':checked')) $('#hostNotInMe').show();
        else $('#hostNotInMe').hide();
        e.preventDefault();
    });
    $('#hostMeShow:checkbox').trigger('change');
    $('#hostNoShow:checkbox').change(function(e) {
        if ($(this).is(':checked')) $('#hostNoImage').show();
        else $('#hostNoImage').hide();
        e.preventDefault();
    });
    $('#hostNoShow:checkbox').trigger('change');
    $('#groupMeShow:checkbox').change(function(e) {
        if ($(this).is(':checked')) $('#groupNotInMe').show();
        else $('#groupNotInMe').hide();
        e.preventDefault();
    });
    $('#groupMeShow:checkbox').trigger('change');
    $('#groupNoShow:checkbox').change(function(e) {
        if ($(this).is(':checked')) $('#groupNoImage').show();
        else $('#groupNotImage').hide();
        e.preventDefault();
    });
    $('#groupNoShow:checkbox').trigger('change');
    checkboxAssociations('.toggle-checkbox1:checkbox','.toggle-group:checkbox,.toggle-host1:checkbox');
    checkboxAssociations('.toggle-checkbox2:checkbox','.toggle-host2:checkbox');
});
