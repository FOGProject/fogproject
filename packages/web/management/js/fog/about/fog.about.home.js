(function($) {
  // FOG Version information gathering.
  var vers = $('.placehere').attr('vers');
  $.ajax({
    url: '../status/mainversion.php',
    dataType: 'json',
    success: function(data, textStatus, jqXHR) {
      $('.placehere').append(data);
    },
    error: function(jqXHR, textStatus, errorThrown) {
      $('.placehere').append(textStatus);
    }
  });

  // Storage Node version and kernel version information.
  $('.kernvers').each(function() {
    URL = $(this).attr('urlcall');
    newelement = document.createElement('a');
    newelement.href = URL;
    mainurl = '..'+newelement.pathname+newelement.search;

    $.ajax({
      context: this,
      url: mainurl,
      type: 'post',
      data: {
        url: URL
      },
      success: function(data, textStatus, jqXHR) {
        if (typeof(data) == null || typeof(data) == 'undefined') {
          $(this).text('No data returned');
          return;
        }
        data = JSON.parse(data);
        $(this).html(
            '<div class="box box-solid">'
            + '<div class="box-header with-border">'
            + '<h4 class="box-title">' + data.node_version_lang + '</h4>'
            + '</div>'
            + '<div class="box-body">'
            + data.node_vers
            + '</div>'
            + '</div>'
            + '<div class="box box-solid">'
            + '<div class="box-header with-border">'
            + '<h4 class="box-title">' + data.kern_version_lang + '</h4>'
            + '</div>'
            + '<div class="box-body">'
            + '<dl>'
            + '<dt>Intel - 64 Bit</dt>'
            + '<dd>' + data.int64bit + '</dd>'
            + '<dt>Intel - 32 Bit</dt>'
            + '<dd>' + data.int32bit + '</dd>'
            + '<dt>ARM - 64 Bit</dt>'
            + '<dd>' + data.arm64bit + '</dd>'
            + '</dl>'
            + '</div>'
            + '</div>'
        );
        console.log(data);
      },
      error: function(jqXHR, textStatus, errorThrown) {
        $(this).text(textStatus);
      }
    });
  });
})(jQuery);
