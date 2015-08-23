$(function() {
    checkboxToggleSearchListPages();
    $('#action-boxdel').submit(function() {
        var checked = $('input.toggle-action:checked').not(':hidden');
        var groupIDArray = new Array();
        for (var i = 0,len = checked.size();i < len;i++) {
            groupIDArray[groupIDArray.length] = checked.eq(i).not(':hidden').attr('value');
        }
        $('input[name="groupIDArray"]').val(groupIDArray.join(','));
    });
});
