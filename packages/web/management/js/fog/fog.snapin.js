$(function() {
    checkboxToggleSearchListPages();
    $('#action-boxdel').submit(function() {
        var checked = $('input.toggle-action:checked').not(':hidden');
        var snapinIDArray = new Array();
        for (var i = 0,len = checked.size();i < len;i++) {
            snapinIDArray[snapinIDArray.length] = checked.eq(i).prop('value');
        }
        $('input[name="snapinIDArray"]').val(snapinIDArray.join(','));
    });
    updateCmdStore();
    $('.cmdlet1,.cmdlet2,.cmdlet4').keyup(function() {
        updateCmdStore();
    });
    $('.cmdlet3').change(function() {
        updateCmdStore();
    });
});
function updateCmdStore() {
    var snapCMD = [$('.cmdlet1').val(),$('.cmdlet2').val(),$('.cmdlet3').val(),$('.cmdlet4').val()];
    $('.snapincmd').val(snapCMD.join(' ')).css({width: '100%',border: 0,resize: 'none'});
}
