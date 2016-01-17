$(function() {
    // Show hide based on checked state.
    $('#hostNotInMe,#hostNoImage,#groupNotInMe,#groupNoImage').hide();
    $('#hostMeShow:checkbox').change(function(e) {
        $('#hostNotInMe').toggle();
        e.preventDefault();
    });
    $('#hostNoShow:checkbox').change(function(e) {
        $('#hostNoImage').toggle();
        e.preventDefault();
    });
    $('#groupMeShow:checkbox').change(function(e) {
        $('#groupNotInMe').toggle();
        e.preventDefault();
    });
    $('#groupNoShow:checkbox').change(function(e) {
        $('#groupNoImage').toggle();
        e.preventDefault();
    });
    checkboxAssociations('.toggle-checkbox1:checkbox','.toggle-host1:checkbox');
    checkboxAssociations('.toggle-checkbox2:checkbox','.toggle-host2:checkbox');
});
