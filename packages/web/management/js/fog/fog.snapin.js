$(function() {
    checkboxToggleSearchListPages();
    $('#argTypes').change(function() {
        if ($('option:selected',this).attr('value')) $("input[name=rw]").val($('option:selected',this).attr('value'));
        $("input[name=rwa]").val($('option:selected',this).attr('rwargs'));
        $("input[name=args]").val($('option:selected',this).attr('args'));
        updateCmdStore();
    });
    updateCmdStore();
    $('.cmdlet1,.cmdlet2,.cmdlet3,.cmdlet4').on('change, keyup',function(e) {
        updateCmdStore();
    });
    $('.cmdlet3').change(function(e) {
        updateCmdStore();
    })
});
function updateCmdStore() {
    if (typeof $('.cmdlet3').val() === 'undefined') return;
    test = $('[type="file"]')
    if (test.length < 1) {
        test = $('select.cmdlet3').val();
    } else {
        test = test[0].files.length;
        if (test < 1) test = $('select.cmdlet3').val();
        else test = $('[type="file"]')[0].files[0].name;
    }
    var snapCMD = [$('.cmdlet1').val(),$('.cmdlet2').val(),test,$('.cmdlet4').val()];
    $('.snapincmd').val(snapCMD.join(' ')).css({width: '100%',border: 0,resize: 'none'});
}
