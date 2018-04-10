(function($) {
    var kernelData = {},
        downloadSelected = $('#download-send');
    downloadSelected.prop('disabled', true);
    Pace.track(function() {
        $.ajax({
            url: '../fog/availablekernels',
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
                    table = Common.registerTable($('#dataTable'), onSelect, {
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
                    downloadurl = table.row().id();
                    downloadparts = getQueryParams(downloadurl);
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
                            file: parts.file,
                            dstName: dstName
                        },
                        fetchurl = '../management/index.php?node='
                        + parts.node
                        + '&sub='
                        + parts.sub;
                    Common.apiCall('post', fetchurl, opts, function(err) {
                        if (err) {
                            return;
                        }
                        Pace.track(function() {
                            $.post('../management/index.php?sub=kernelfetch', {msg: 'dl'}, function(data, textStatus) {
                                if (textStatus == 'success') {
                                    $.post('../management/index.php?sub=kernelfetch', {msg: 'tftp'}, function() {
                                        if (textStatus == 'success') {
                                            Common.notifyFromAPI(data, false);
                                            downloadModal.modal('hide');
                                        } else {
                                            Common.notifyFromAPI(data, true);
                                        }
                                    }, 'json');
                                } else {
                                    Common.notifyFromAPI(data, true);
                                }
                            }, 'jaon');
                        });
                    });
                });
            },
            error: function(jqXHR, textStatus, errorThrown) {
                Common.notifyFromAPI(jqXHR.responseJSON, true);
            }
        });
    });
})(jQuery);
