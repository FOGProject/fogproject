$(function() {
    checkboxToggleSearchListPages();
    $('#action-boxdel').submit(function() {
        var checked = $('input.toggle-action:checked');
        var locationIDArray = new Array();
        for (var i = 0,len = checked.size();i < len;i++) {
            locationIDArray[locationIDArray.length] = checked.eq(i).attr('value');
        }
        $('input[name="locationIDArray"]').val(locationIDArray.join(','));
    });
    // Show hide based on checked state.
    $('#hostNotInMe').hide();
    $('#hostNoGroup').hide();
    $('#hostMeShow').click(function() {
        $('#hostNotInMe').toggle();
    });
    $('#hostNoShow').click(function() {
        $('#hostNoGroup').toggle();
    });
});
