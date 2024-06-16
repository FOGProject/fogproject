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
        let [int64_ver, int64k_ins] = data.int64bit.split('|');
        let [int32_ver, int32k_ins] = data.int32bit.split('|');
        let [arm64_ver, arm64k_ins] = data.arm64bit.split('|');
        let [int64_rel, int64_brt, int64i_ins] = data.initI64.split('|');
        let [int32_rel, int32_brt, int32i_ins] = data.initI32.split('|');
        let [arm64_rel, arm64_brt, arm64i_ins] = data.initA64.split('|');
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
            + '<table class="table table-striped">'
            + '<tbody>'
            + '<tr>'
            + '<th>' + data.arch_lang + '</th>'
            + '<th>' + data.kern_lang + '</th>'
            + '<th>' + data.ins_lang + '</th>'
            + '</tr>'
            + '<tr>'
            + '<td>' + data.intel64_lang + '</td>'
            + '<td>' + int64_ver + '</td>'
            + '<td>' + int64k_ins + '</td>'
            + '</tr>'
            + '<tr>'
            + '<td>' + data.intel32_lang + '</td>'
            + '<td>' + int32_ver + '</td>'
            + '<td>' + int32k_ins + '</td>'
            + '</tr>'
            + '<tr>'
            + '<td>' + data.arm64_lang + '</td>'
            + '<td>' + arm64_ver + '</td>'
            + '<td>' + arm64k_ins + '</td>'
            + '</tr>'
            + '</tbody>'
            + '</table>'
            + '</div>'
            + '</div>'
            + '<div class="box box-solid">'
            + '<div class="box-header with-border">'
            + '<h4 class="box-title">' + data.init_version_lang + '</h4>'
            + '</div>'
            + '<div class="box-body">'
            + '<table class="table table-striped">'
            + '<tbody>'
            + '<tr>'
            + '<th>' + data.arch_lang + '</th>'
            + '<th>' + data.rel_lang + '</th>'
            + '<th>' + data.build_lang + '</th>'
            + '<th>' + data.ins_lang + '</th>'
            + '</tr>'
            + '<tr>'
            + '<td>' + data.intel64_lang + '</td>'
            + '<td>' + int64_rel + '</td>'
            + '<td>' + int64_brt + '</td>'
            + '<td>' + int64i_ins + '</td>'
            + '</tr>'
            + '<tr>'
            + '<td>' + data.intel32_lang + '</td>'
            + '<td>' + int32_rel + '</td>'
            + '<td>' + int32_brt + '</td>'
            + '<td>' + int32i_ins + '</td>'
            + '</tr>'
            + '<tr>'
            + '<td>' + data.arm64_lang + '</td>'
            + '<td>' + arm64_rel + '</td>'
            + '<td>' + arm64_brt + '</td>'
            + '<td>' + arm64i_ins + '</td>'
            + '</tr>'
            + '</tbody>'
            + '</table>'
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
