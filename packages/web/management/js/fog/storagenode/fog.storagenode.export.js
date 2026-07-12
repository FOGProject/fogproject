(function($) {
    $('#storagenode-export-table').registerExportTable([
        {data: 'name'},
        {data: 'description', visible: false},
        {data: 'isMaster', visible: false},
        {data: 'storagegroupID', visible: false},
        {data: 'isEnabled', visible: false},
        {data: 'isGraphEnabled', visible: false},
        {data: 'path'},
        {data: 'ftppath'},
        {data: 'bitrate', visible: false},
        {data: 'snapinpath'},
        {data: 'sslpath'},
        {data: 'ip'},
        {data: 'maxClients'},
        {data: 'user', visible: false},
        {data: 'pass', visible: false},
        {data: 'key', visible: false},
        {data: 'interface'},
        {data: 'bandwidth', visible: false},
        {data: 'webroot'}
    ]);
})(jQuery);
