(function($) {
    var vers = $('.placehere').attr('vers');
    validatorOpts = {
        submitHandler: submithandlerfunc
    };
    setTimeoutElement();
    $.ajax({
        url: '../status/mainversion.php',
        dataType: 'json',
        success: function(gdata) {
            $('.placehere').append(gdata);
        },
        error: function() {
            $('.placehere').append('Failed to get latest info');
        }
    });
    $('.kernvers').each(function() {
        URL = $(this).attr('urlcall');
        test = document.createElement('a');
        test.href = URL;
        test2 = '../'+test.pathname+test.search;
        $.ajax({
            context: this,
            url: test2,
            type: 'POST',
            data: {
                url: URL
            },
            success: function(gdata) {
                if (typeof(gdata) == null
                    || typeof(gdata) == 'undefined'
                ) {
                    $(this).text('No data returned');
                }
                gdata = gdata.split('\n');
                if (gdata.length < 2) {
                    $(this).text('No data returned');
                    return;
                }
                var nodevers = gdata.shift();
                $(this).text(gdata.join('\n'));
                var setter = $(this).parents('div.hidefirst').prev('a').find('.kernversionupdate');
                var nodename = setter.text();
                setter.text(nodename.replace(/\(.*\)/,'('+nodevers+')'));
            }
        });
    });
    $('#bannerimg').on('click', function(e) {
        e.preventDefault();
        $('input[name="banner"]').val('');
        name = $(this).attr('identi');
        $('#uploader').html('<input type="file" name="'+name+'" class="newbanner"/>').find('input').trigger('click');
    });
    $(document).on('change', '#FOG_CLIENT_BANNER_IMAGE', function(e) {
        filename = this.value;
        filename = filename.replace(/\\/g, '/').replace(/.*\//, "");
        $('input[name="banner"]').val(filename);
    });
    tokenreset();
})(jQuery);
function setTimeoutElement() {
    $('button[type="submit"]:not(#importbtn, #export, #upload, #Rebranding, #install), #menuSet, #hideSet, #exitSet, #advSet, button[name="saveform"], button[name="delform"], #deletecu').each(function(e) {
        if ($(this).is(':visible')) {
            $(this).on('click', function(e) {
                form = $(this).parents('form');
                validator = form.validate(validatorOpts);
            });
        }
    });
    setTimeout(setTimeoutElement, 1000);
}
