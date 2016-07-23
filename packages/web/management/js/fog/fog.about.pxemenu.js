$(function() {
    $('#advancedTextArea').hide();
    $('#pxeAdvancedLink').click(function(e) {
        e.preventDefault();
        $('#advancedTextArea').toggle();
    });
});
