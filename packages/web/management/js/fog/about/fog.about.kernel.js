(function($) {
    var kernelData = {},
        downloadSelected = $('#download-send');
    downloadSelected.prop('disabled', true);
    Pace.track(function() {
        $.ajax({
            url: '../management/index.php?sub=getKernels',
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
                            [3, 'desc']
                        ],
                        columns: [
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
                    downloadparts = getQueryParams(downloadurl[0]);
                    parts = {
                        node: downloadparts['node'],
                        sub: downloadparts['sub'],
                        url: downloadparts['file'],
                        arch: downloadparts['arch']
                    };
                    val = parts.arch == 32 ? 'bzImage32' : 'bzImage';
                    $('#kernel-name').prop('placeholder', val).prop('value', val);
                });
                confirmDownloadBtn.on('click', function(e) {
                    e.preventDefault();
                    var dstName = $('#kernel-name').val(),
                        opts = {
                            install: 1,
                            file: parts.url,
                            dstName: dstName
                        },
                        fetchurl = '../management/index.php?node='
                        + Common.node
                        + '&sub='
                        + parts.sub,
                        dlurl = '../management/index.php?sub=kernelfetch';
                    $.apiCall('post', fetchurl, opts, function(err) {
                        if (err) {
                            return;
                        }
                        $.apiCall('post', dlurl, {msg: 'dl'}, function(err) {
                            if (err) {
                                return;
                            }
                            $.apiCall('post', dlurl, {msg: 'tftp'}, function(err) {
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
                $.notifyFromAPI(jqXHR.responseJSON, true);
            }
        });
    });
})(jQuery);
