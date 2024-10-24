(function($) {
    $('.currentdlstate').text('Downloading file...');
    $('.initrdinfo').html(
        '<div class="panel panel-warning">'
        + '<div class="panel-heading text-center">'
        + '<h4 class="title">'
        + 'Download Started'
        + '</h4>'
        + '</div>'
        + '<div class="panel-body">'
        + 'Download Started!'
        + '</div>'
        + '</div>'
    );
    $.post(
        '../management/index.php?sub=initrdfetch',
        {
            msg: 'dl'
        },
        dlComplete,
        'text'
    );
})(jQuery);
/**
 * Download complete message.
 */
function dlComplete(gdata, textStatus) {
    if (textStatus == "success") {
        if (gdata == "##OK##") {
            $('.initrdinfo').html(
                '<div class="panel panel-success">'
                + '<div class="panel-heading text-center">'
                + '<h4 class="title">'
                + 'Download Succeeded'
                + '</h4>'
                + '</div>'
                + '<div class="panel-body">'
                + 'Download Complete! Preparing to move to tftp server.'
                + '</div>'
                + '</div>'
            );
            $.post('?sub=initrdfetch',{msg: "tftp"},mvComplete, "text");
        } else {
            $('.initrdinfo').html(
                '<div class="panel panel-danger">'
                + '<div class="panel-heading text-center">'
                + '<h4 class="title">'
                + 'Download Failed'
                + '</h4>'
                + '</div>'
                + '<div class="panel-body">'
                + gdata
                + '</div>'
                + '</div>'
            );
        }
    } else {
        $('.initrdinfo').html(
            '<div class="panel panel-danger">'
            + '<div class="panel-heading text-center">'
            + '<h4 class="title">'
            + 'Download Failed'
            + '</h4>'
            + '</div>'
            + '<div class="panel-body">'
            + 'Download Failed!'
            + '</div>'
            + '</div>'
        );
    }
}
/**
 * Move complete message.
 */
function mvComplete(gdata, textStatus) {
    if (textStatus == 'success') {
        if (gdata == "##OK##") {
            $('.initrdinfo').html(
                '<div class="panel panel-success">'
                + '<div class="panel-heading text-center">'
                + '<h4 class="title">'
                + 'Transfer Succeeded'
                + '</h4>'
                + '</div>'
                + '<div class="panel-body">'
                + 'Your new FOG initrd has been installed!'
                + '</div>'
                + '</div>'
            );
        } else {
            $('.initrdinfo').html(
                '<div class="panel panel-danger">'
                + '<div class="panel-heading text-center">'
                + '<h4 class="title">'
                + 'Transfer Failed'
                + '</h4>'
                + '</div>'
                + '<div class="panel-body">'
                + gdata
                + '</div>'
                + '</div>'
            );
        }
    } else {
            $('.initrdinfo').html(
                '<div class="panel panel-danger">'
                + '<div class="panel-heading text-center">'
                + '<h4 class="title">'
                + 'Transfer Failed'
                + '</h4>'
                + '</div>'
                + '<div class="panel-body">'
                + 'Failed to load new initrd to TFTP Server!'
                + '</div>'
                + '</div>'
            );
    }
}
