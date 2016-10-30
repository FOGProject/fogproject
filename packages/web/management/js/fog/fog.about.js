$(function() {
    var vers = $('#latestInfo').attr('vers');
    $.ajax({
        url: '../status/mainversion.php',
        dataType: 'json',
        success: function(data) {
            $('#latestInfo').append(data);
        },
        error: function() {
            $('#latestInfo').append('Failed to get latest info');
        }
    });
    $('.kernvers').each(function() {
        URL = $(this).attr('urlcall');
        test = document.createElement('a');
        test.href = URL;
        test2 = test.pathname+test.search;
        $.ajax({
            context: this,
            url: test.pathname+test.search,
            data: {
                url: URL
            },
            success: function(data) {
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
                var h2 = $(this).prev();
                var nodename = h2.text();
                h2.text(nodename.replace(/\(.*\)/,'('+nodevers+')'));
            }
        });
    });
    $('#kernelsel').change(function(e) {
        this.form.submit();
    });
});
