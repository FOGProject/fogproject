(function($) {
    var createForm = $('#snapin-create-form'),
        createFormBtn = $('#send'),
        packval = $('#snapinpack').val(),
        ACTION_VAL = -1;
    createForm.on('submit', function(e) {
        e.preventDefault();
    });
    createFormBtn.on('click', function() {
        createFormBtn.prop('disabled', true);
        Common.processForm(createForm, function(err) {
            createFormBtn.prop('disabled', false);
        });
    });

    // Setup the changer as a function so I'm not typing
    // the same information twice in the same file.
    var packchanger = function(packval) {
        switch (packval) {
            case '0':
                $('.packnotemplate').removeClass('hidden');
                $('.packtemplate').addClass('hidden');
                $('.packhide').addClass('hidden');
                break;
            case '1':
                $('.packnotemplate').addClass('hidden');
                $('.packtemplate').removeClass('hidden');
                $('.packhide').removeClass('hidden');
                break;
        }
    };
    // Make sure selectors are select2 friendly
    packchanger(packval);
    // Make the change when the snapin pack selector changes.
    $('#snapinpack').on('change', function() {
        packchanger($(this).val());
    });
    $('#argTypes').on('change', function() {
        var option = $('option:selected', this),
            value = option.attr('value'),
            rwarg = option.attr('rwargs'),
            args = option.attr('args'),
            rwinp = $('input[name=rw]'),
            rwainp = $('input[name=rwa]'),
            argsinp = $('input[name=args]');
        if (value) {
            rwinp.val(value);
        }
        rwainp.val(rwarg);
        argsinp.val(args);
        updateCmdStore();
    });
    $('#packTypes').on('change', function() {
        var option = $('option:selected', this),
            file = option.attr('file'),
            args = option.attr('args'),
            rwinp = $('input[name=rw]'),
            rwainp = $('input[name=rwa]');
        rwinp.val(file);
        rwainp.val(args);
    });

    // Allow radio to change properly but also be unset as maybe
    // the user doesn't want an action to occur after the snapin
    // completes.
    var onRadioSelect = function(event) {
        var action = $(this).val();
        if (ACTION_VAL === -1) {
            ACTION_VAL = action;
        }
        if (action === ACTION_VAL) {
            $(this).iCheck('uncheck');
            ACTION_VAL = 0;
        } else {
            ACTION_VAL = action;
        }
    };
    // Setup action radio selector
    $('.snapin-action').on('ifClicked', onRadioSelect);
    var updateCmdStore = function() {
        if (typeof $('.cmdlet3').val() === 'undefined') {
            return;
        }
        var cmd1 = $('.cmdlet1').val(),
            cmd2 = $('.cmdlet2').val(),
            cmd3 = $('.cmdlet3').val(),
            cmd4 = $('.cmdlet4').val(),
            test = $('[type="file"]');
        if (test.length < 1) {
            cmd3 = $('select.cmdlet3').val();
        } else {
            test = test[0].files.length;
            if (test < 1) {
                cmd3 = $('select.cmdlet3').val();
            } else {
                cmd3 = $('[type="file"]')[0].files[0].name;
            }
            if ($('#snapinpack').val() == 1) {
                cmd = '';
            }
        }
        var snapCMD = [cmd1,cmd2,cmd3,cmd4];
        $('.snapincmd').val(snapCMD.join(' '));
    };

    updateCmdStore();
    $('.cmdlet1,.cmdlet2,.cmdlet3,.cmdlet4').on('change keyup', function(e) {
        e.preventDefault();
        updateCmdStore();
    });
    $('.cmdlet3').on('change blur', function(e) {
        updateCmdStore();
    });
    $('#snapinfileexist').select2({width: '100%'});
})(jQuery);
