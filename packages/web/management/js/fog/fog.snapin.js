$(function() {
    checkboxToggleSearchListPages();
    $('#argTypes').change(function() {
        if ($('option:selected',this).attr('value')) $("input[name=rw]").val($('option:selected',this).attr('value'));
        $("input[name=rwa]").val($('option:selected',this).attr('rwargs'));
        $("input[name=args]").val($('option:selected',this).attr('args'));
        updateCmdStore();
    });
    updateCmdStore();
    $('.cmdlet1,.cmdlet2,.cmdlet4').on('change, keyup',function(e) {
        updateCmdStore();
    });
    $('.cmdlet3').on('change, keyup',function(e) {
        updateCmdStore();
    });
});
function updateCmdStore() {
    var snapCMD = [$('.cmdlet1').val(),$('.cmdlet2').val(),$('.cmdlet3').val(),$('.cmdlet4').val()];
    $('.snapincmd').val(snapCMD.join(' ')).css({width: '100%',border: 0,resize: 'none'});
}
