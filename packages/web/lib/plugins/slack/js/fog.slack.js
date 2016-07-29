$(function() {
    checkboxToggleSearchListPages();
    $('#action-boxdel').submit(function() {
        var checked = $('input.toggle-action:checked');
        var slackIDArray = new Array();
        for (var i = 0,len = checked.size();i < len;i++) {
            slackIDArray[slackIDArray.length] = checked.eq(i).attr('value');
        }
        $('input[name="slackIDArray"]').val(slackIDArray.join(','));
    });
});
