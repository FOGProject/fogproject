$(function() {
    checkboxToggleSearchListPages();
    $('#action-boxdel').submit(function() {
        var checked = $('input.toggle-action:checked');
        var IDArray = new Array();
        for (var i = 0,len = checked.size();i < len;i++) {
            IDArray[IDArray.length] = checked.eq(i).attr('value');
        }
        $('input[name="IDArray"]').val(IDArray.join(','));
    });
    $('#action-box').show();
    $('#action-boxdel').show();
    $('#action-box').submit(function() {
        var checked = $('input.toggle-action:checked');
        var ruleIDArray = new Array();
        for (var i = 0,len = checked.size();i < len;i++) {
            ruleIDArray[ruleIDArray.length] = checked.eq(i).attr('value');
        }
        $('input[name="ruleIDArray"]').val(ruleIDArray.join(','));
    });
});
