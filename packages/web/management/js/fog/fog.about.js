$(function() {
    var vers = $('#latestInfo').attr('vers');
    $.ajax({
        url: '../status/mainversion.php',
        dataType: 'json',
        timeout: 1000,
        success: function(data) {
            $('#latestInfo').append(data);
        },
        error: function() {
            $('#latestInfo').append('Failed to get latest info');
        }
    });
});
