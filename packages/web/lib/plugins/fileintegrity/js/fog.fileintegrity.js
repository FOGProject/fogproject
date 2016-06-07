$(function() {
    checkboxToggleSearchListPages();
    $('#action-boxdel').submit(function() {
        var checked = $('input.toggle-action:checked').not(':hidden');
        var taskstateeditIDArray = new Array();
        for (var i = 0,len = checked.size();i < len;i++) {
            fileintegrityIDArray[fileintegrityIDArray.length] = checked.eq(i).prop('value');
        }
        $('input[name="fileintegrityIDArray"]').val(fileintegrityIDArray.join(','));
    });
});
