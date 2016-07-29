$(function() {
    // Show hide based on checked state.
    $('#hostNotInMe,#hostNoSnapin').hide();
    $('#hostMeShow').change(function(e) {
        $('#hostNotInMe').toggle();
        e.preventDefault();
    });
    $('#hostNoShow').change(function(e) {
        $('#hostNoSnapin').toggle();
        e.preventDefault();
    });
    $('#groupMeShow').change(function(e) {
        $('#groupNotInMe').toggle();
        e.preventDefault();
    });
    $('#groupNoShow').change(function(e) {
        $('#groupNoSnapin').toggle();
        e.preventDefault();
    });
    checkboxAssociations('.toggle-checkbox1:checkbox','.toggle-host1:checkbox,.toggle-snapin1:checkbox');
    checkboxAssociations('.toggle-checkbox2:checkbox','.toggle-host2:checkbox,.toggle-snapin2:checkbox');
});
