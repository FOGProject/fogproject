$(function() {
    checkboxToggleSearchListPages();
    $('#action-boxdel').submit(function() {
        var checked = $('input.toggle-action:checked');
        var wolbroadcastIDArray = new Array();
        for (var i = 0,len = checked.size();i < len;i++) {
            wolbroadcastIDArray[wolbroadcastIDArray.length] = checked.eq(i).attr('value');
        }
        $('input[name="wolbroadcastIDArray"]').val(wolbroadcastIDArray.join(','));
    });
});
