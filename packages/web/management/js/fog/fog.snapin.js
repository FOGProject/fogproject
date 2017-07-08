$(function() {
    checkboxToggleSearchListPages();
    validatorOpts = {
        submitHandler: function(form) {
            data = $(form).find(':visible').serialize();
            url = $(form).attr('action');
            method = $(form).attr('method').toUpperCase();
            $.ajax({
                url: url,
                type: method,
                data: data,
                dataType: 'json',
                success: function(data) {
                    dialoginstance = new BootstrapDialog();
                    if (data.error) {
                        dialoginstance
                        .setTitle('Snapin Update Failed')
                        .setMessage(data.error)
                        .setType(BootstrapDialog.TYPE_WARNING)
                        .open();
                    } else {
                        dialoginstance
                        .setTitle('Snapin Update Success')
                        .setMessage(data.msg)
                        .setType(BootstrapDialog.TYPE_SUCCESS)
                        .open();
                    }
                }
            });
            return false;
        },
        rules: {
            name: {
                required: true,
                minlength: 1,
                maxlength: 255
            }
        }
    };
    if ($_GET['sub'] == 'membership') return;
    form = $('#add, #updategen:not(:hidden), #primarysel:not(:hidden), #groupdel:not(:hidden)').parents('form');
    validator = form.validate(validatorOpts);
    $('.snapinname-input:not(:hidden)').rules(
        'add',
        {
            regex: /^[-\w!@#$%^()'{}\\\.~\+ ]{1,255}$/
        }
    ).on('keyup change blur', function() {
        return validator.element(this);
    }).trigger('change');
    $('.nav-tabs a').on('shown.bs.tab', function(e) {
        form = $('#add, #updategen:not(:hidden), #primarysel:not(:hidden), #groupdel:not(:hidden)').parents('form');
        validator = form.validate(validatorOpts);
        $('.snapinname-input:not(:hidden)').rules(
            'add',
            {
                regex: /^[-\w!@#$%^()'{}\\\.~\+ ]{1,255}$/
            }
        ).on('keyup change blur', function() {
            return validator.element(this);
        }).trigger('change');
    });
    $('#argTypes').on('change', function() {
        if ($('option:selected',this).attr('value')) $("input[name=rw]").val($('option:selected',this).attr('value'));
        $("input[name=rwa]").val($('option:selected',this).attr('rwargs'));
        $("input[name=args]").val($('option:selected',this).attr('args'));
        updateCmdStore();
    });
    $('#packTypes').on('change', function() {
        $("input[name=rw]").val($('option:selected',this).attr('file'));
        $("input[name=rwa]").val($('option:selected',this).attr('args'));
    });
    updateCmdStore();
    $('.cmdlet1,.cmdlet2,.cmdlet3,.cmdlet4').on('change keyup',function(e) {
        updateCmdStore();
    });
    $('.cmdlet3').on('change', function(e) {
        updateCmdStore();
    })
    $('.snapinpack-input').on('change blur',function(e) {
        if (this.value == 1) {
            $('.packnotemplate, .packnochangerw, .packnochangerwa, .packhide').hide();
            $('.packtemplate, .packchangerw, .packchangerwa').show();
        } else {
            $('.packnotemplate, .packnochangerw, .packnochangerwa, .packhide').show();
            $('.packtemplate, .packchangerw, .packchangerwa').hide();
        }
        updateCmdStore();
    });
    $('.snapinpack-input').trigger('change');
});
function updateCmdStore() {
    if (typeof $('.cmdlet3').val() === 'undefined') return;
    cmd1 = $('.cmdlet1').val();
    cmd2 = $('.cmdlet2').val();
    cmd3 = $('.cmdlet3').val();
    cmd4 = $('.cmdlet4').val();
    test = $('[type="file"]')
    if (test.length < 1) {
        cmd3 = $('select.cmdlet3').val();
    } else {
        test = test[0].files.length;
        if (test < 1) cmd3 = $('select.cmdlet3').val();
        else cmd3 = $('[type="file"]')[0].files[0].name;
    }
    if ($('.snapinpack-input').val() == 1) {
        cmd3 = '';
    }
    var snapCMD = [cmd1,cmd2,cmd3,cmd4];
    $('.snapincmd').val(snapCMD.join(' ')).css({width: '100%',border: 0,resize: 'none'});
}
