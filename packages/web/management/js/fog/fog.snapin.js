$(function() {
    checkboxToggleSearchListPages();
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
