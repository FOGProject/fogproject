(function($) {
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
            success: function(data, textStatus, jqXHR) {
                if (typeof(data) == null
                    || typeof(data) == 'undefined'
                ) {
                    $(this).text('No data returned');
                }
                data = data.split('\n');
                if (data.length < 2) {
                    $(this).text('No data returned');
                    return;
                }
                var nodevers = data.shift();
                $(this).text(data.join('\n'));
                var setter = $(this).parents('div.hidefirst').prev('a').find('.kernversionupdate');
                var nodename = setter.text();
                setter.text(nodename.replace(/\(.*\)/,'('+nodevers+')'));
            },
            error: function (jqXHR, textStatus, errorThrown) {
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
