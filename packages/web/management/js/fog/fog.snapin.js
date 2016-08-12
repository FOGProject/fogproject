$(function() {
    checkboxToggleSearchListPages();
    form = $('.snapinname-input:not(:hidden)').parents('form');
    validator = form.validate({
        rules: {
            name: {
                required: true,
                minlength: 1,
                maxlength: 255
            }
        }
    });
    $('.snapinname-input:not(:hidden)').rules('add', {regex: /^[-\w!@#$%^()'{}\\\.~\+ ]{1,255}$/});
    $('.snapinname-input:not(:hidden)').on('keyup change blur',function() {
        return validator.element(this);
    });
    $('.snapinname-input:not(:hidden)').trigger('change');
    $('#argTypes').change(function() {
        if ($('option:selected',this).attr('value')) $("input[name=rw]").val($('option:selected',this).attr('value'));
        $("input[name=rwa]").val($('option:selected',this).attr('rwargs'));
        $("input[name=args]").val($('option:selected',this).attr('args'));
        updateCmdStore();
    });
    $('#packTypes').change(function() {
        $("input[name=rw]").val($('option:selected',this).attr('file'));
        $("input[name=rwa]").val($('option:selected',this).attr('args'));
    });
    updateCmdStore();
    $('.cmdlet1,.cmdlet2,.cmdlet3,.cmdlet4').on('change, keyup',function(e) {
        updateCmdStore();
    });
    $('.cmdlet3').change(function(e) {
        updateCmdStore();
    })
    $('.snapinpack-input').on('change blur',function(e) {
        if (this.value == 1) {
            $('.packnotemplate').hide();
            $('.packtemplate').show();
            $('.packnochangerw').hide();
            $('.packchangerw').show();
            $('.packnochangerwa').hide();
            $('.packchangerwa').show();
            $('.packhide').hide();
        } else {
            $('.packnotemplate').show();
            $('.packtemplate').hide();
            $('.packnochangerw').show();
            $('.packchangerw').hide();
            $('.packnochangerwa').show();
            $('.packchangerwa').hide();
            $('.packhide').show();
        }
    });
    $('.snapinpack-input').trigger('change');
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
