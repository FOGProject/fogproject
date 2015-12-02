$(function() {
    checkboxToggleSearchListPages();
    $('#action-boxdel').submit(function() {
        var checked = $('input.toggle-action:checked').not(':hidden');
        var tasktypeeditIDArray = new Array();
        for (var i = 0,len = checked.size();i < len;i++) {
            tasktypeeditIDArray[tasktypeeditIDArray.length] = checked.eq(i).prop('value');
        }
        $('input[name="tasktypeeditIDArray"]').val(tasktypeeditIDArray.join(','));
    });
});
