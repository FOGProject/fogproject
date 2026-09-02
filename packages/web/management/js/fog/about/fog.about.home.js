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

  /**
   * Escape a value from the node before putting it in the page.
   *
   * These rows carry filenames off a disk and version banners out of a
   * kernel image, so they are data, not markup.
   */
  function esc(v) {
    return $('<div>').text(v === null || v === undefined ? '' : v).html();
  }

  function bootFileTable(data) {
    var rows = data.rows || [],
      html = '',
      i,
      row;
    if (!rows.length) {
      return '<div class="alert alert-warning">' + esc(data.empty_lang)
        + '</div>';
    }
    html = '<table class="table table-striped">'
      + '<tbody>'
      + '<tr>'
      + '<th>' + esc(data.file_lang) + '</th>'
      + '<th>' + esc(data.role_lang) + '</th>'
      + '<th>' + esc(data.version_lang) + '</th>'
      + '<th>' + esc(data.release_lang) + '</th>'
      + '<th>' + esc(data.ins_lang) + '</th>'
      + '</tr>';
    for (i = 0; i < rows.length; i++) {
      row = rows[i];
      html += '<tr>'
        + '<td>' + esc(row.name) + '</td>'
        + '<td>' + esc(row.role_label || row.role) + '</td>'
        // A value that could not be read says so, in muted text. It used to
        // render as "Unknown" whatever the reason was.
        + '<td>' + (row.version
          ? esc(row.version)
          : '<span class="text-muted">' + esc(data.unreadable_lang)
            + '</span>') + '</td>'
        + '<td>' + (row.release
          ? esc(row.release)
          : '<span class="text-muted">' + esc(row.note) + '</span>') + '</td>'
        + '<td>' + esc(row.installed) + '</td>'
        + '</tr>';
    }

    return html + '</tbody></table>';
  }

  // Storage Node version and boot file information.
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
        // The master proxies this request to the node, so a node that is
        // unreachable, or answering with an error page, arrives here as a
        // body that is not JSON. Parsing it unguarded threw and left the
        // panel blank, which looks identical to a node with nothing
        // installed.
        if (typeof data === 'string') {
          try {
            data = JSON.parse(data);
          } catch (e) {
            $(this).html(
                '<div class="alert alert-warning">'
                + esc('Storage Node did not return boot file information')
                + '</div>'
            );
            return;
          }
        }
        $(this).html(
            '<div class="card">'
            + '<div class="card-header">'
            + '<h4 class="card-title">' + esc(data.node_version_lang)
            + '</h4>'
            + '</div>'
            + '<div class="card-body">'
            + esc(data.node_vers)
            + '</div>'
            + '</div>'
            + '<div class="card">'
            + '<div class="card-header">'
            + '<h4 class="card-title">' + esc(data.boot_files_lang) + '</h4>'
            + '</div>'
            + '<div class="card-body">'
            + bootFileTable(data)
            + '</div>'
            + '</div>'
        );
      },
      error: function(jqXHR, textStatus, errorThrown) {
        $(this).text(textStatus);
      }
    });
  });
})(jQuery);
