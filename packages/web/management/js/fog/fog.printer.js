$(function() {
    checkboxToggleSearchListPages();
    $('#action-boxdel').submit(function() {
        var checked = $('input.toggle-action:checked').not(':hidden');
        var printerIDArray = new Array();
        for (var i = 0,len = checked.size();i < len;i++) {
            printerIDArray[printerIDArray.length] = checked.eq(i).prop('value');
        }
        $('input[name="printerIDArray"]').val(printerIDArray.join(','));
    });
});
