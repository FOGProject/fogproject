(function($) {
  var initrdData = {},
    downloadSelected = $('#download-send');
  downloadSelected.prop('disabled', true);
  Pace.track(function() {
    $.ajax({
      url: '../management/index.php?sub=getInitrds',
      type: 'get',
      cache: false,
      dataType: 'json',
      success: function(data, textStatus, jqXHR) {
        function onSelect(selected) {
          var disabled = selected.count() == 0;
          downloadSelected.prop('disabled', disabled);
        };
        var downloadModal = $('#downloadModal'),
          confirmDownloadBtn = $('#confirmDownload'),
          table = $('#dataTable').registerTable(onSelect, {
            data: data,
            select: {
              style: 'single'
            },
            order: [
              [3, 'asc']
            ],
            columns: [
              {data: 'tag_name'},
              {data: 'version'},
              {data: 'arch'},
              {data: 'type'},
              {data: 'date'}
            ],
            rowId: 'download',
            buttons: [],
            rowGroup: {
              dataSrc: 'date'
            }
          });
        table.on('draw', function() {
          onSelect(table.rows({selected: true}));
        });
        var downloadurl,
          downloadparts,
          parts = {};
        downloadSelected.on('click', function(e) {
          e.preventDefault();
          downloadModal.modal('show');
          downloadurl = table.rows({selected: true}).ids();
          rowData = table.rows({selected: true}).data();
          downloadparts = getQueryParams(downloadurl[0]);
          let buildroot = $(this).find('td:eq(2)').text();
          let tag_name = $(this).find('td:eq(1)').text();
          parts = {
            node: downloadparts['node'],
            sub: downloadparts['sub'],
            url: downloadparts['file'],
            arch: downloadparts['arch'],
            buildroot: rowData[0].version,
            tag_name: rowData[0].tag_name
          };
          val = parts.arch == 32 ? 'init_32.xz' : (parts.arch == 'arm64' ? 'arm_init.cpio.gz' : 'init.xz');
          $('#initrd-name').prop('placeholder', val).prop('value', val);
        });
        confirmDownloadBtn.on('click', function(e) {
          e.preventDefault();
          var dstName = $('#initrd-name').val(),
            opts = {
              install: 1,
              file: parts.url,
              dstName: dstName,
              buildroot: parts.buildroot,
              tag_name: parts.tag_name
            },
            fetchurl = '../management/index.php?node='
            + Common.node
            + '&sub='
            + parts.sub,
            dlurl = '../management/index.php?sub=initrdfetch';
          $.apiCall('post', fetchurl, opts, function(err) {
            if (err) {
              return;
            }
            $.apiCall('post', dlurl, {msg: 'dl', buildroot: opts.buildroot, tag_name: opts.tag_name}, function(err) {
              if (err) {
                return;
              }
              $.apiCall('post', dlurl, {msg: 'tftp', buildroot: opts.buildroot, tag_name: opts.tag_name}, function(err) {
                if (err) {
                  return;
                }
                downloadModal.modal('hide');
              });
            });
          });
        });
      },
      error: function(jqXHR, textStatus, errorThrown) {
        $.notifyFromAPI(jqXHR.responseJSON, jqXHR);
      }
    });
  });
})(jQuery);
