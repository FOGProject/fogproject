$(function() {
    checkboxToggleSearchListPages();
    $('#action-boxdel').submit(function() {
        var checked = $('input.toggle-action:checked');
        var windowskeysIDArray = new Array();
        for (var i = 0,len = checked.size();i < len;i++) {
            windowskeysIDArray[windowskeysIDArray.length] = checked.eq(i).attr('value');
        }
        $('input[name="windowskeysIDArray"]').val(windowskeysIDArray.join(','));
    });
    // Show hide based on checked state.
    $('#imagesNotInMe').hide();
    $('#imagesNoGroup').hide();
    $('#imageMeShow').click(function() {
        $('#imageNotInMe').toggle();
    });
    $('#imageNoShow').click(function() {
        $('#imageNoGroup').toggle();
    });
});
