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
        $.ajax({
            context: this,
            url: $(this).attr('urlcall'),
            success: function(data) {
                $(this).text(data);
            }
        });
    });
});
