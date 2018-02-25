$(function() {
    $('#userMeShow:checkbox').on('change',function(e) {
        if ($(this).is(':checked')) $('#userNotInMe').show();
        else $('#userNotInMe').hide();
        e.preventDefault();
    });
    $('#userMeShow:checkbox').trigger('change');
    checkboxAssociations('.toggle-checkboxuser:checkbox','.toggle-user:checkbox');
    checkboxAssociations('.toggle-checkboxuserrm:checkbox','.toggle-userrm:checkbox');
});
