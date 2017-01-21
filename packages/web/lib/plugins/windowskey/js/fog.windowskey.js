$(function() {
    checkboxToggleSearchListPages();
    checkboxAssociations('.toggle-checkboxAction1:checkbox','.toggle-image1:checkbox');
    ProductUpdate();
    $('#action-boxdel').submit(function() {
        var checked = $('input.toggle-image:checked');
        var windowskeyIDArray = new Array();
        for (var i = 0,len = checked.size();i < len;i++) {
            windowskeyIDArray[windowskeyIDArray.length] = checked.eq(i).attr('value');
        }
        $('input[name="windowskeyIDArray"]').val(windowskeyIDArray.join(','));
    });
    // Show hide based on checked state.
    $('#imageNotInMe').hide();
    $('#imageNoGroup').hide();
    $('#imageMeShow').click(function() {
        $('#imageNotInMe').toggle();
    });
    $('#imageNoShow').click(function() {
        $('#imageNoGroup').toggle();
    });
});
