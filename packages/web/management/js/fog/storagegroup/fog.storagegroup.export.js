(function($) {
    var exportTable = $('#storagegroup-export-table').registerTable(null, {
        buttons: exportButtons,
        order: [
            [0, 'asc']
        ],
        columns: [
            {data: 'name'},
            {data: 'description'},
        ],
        rowId: 'id',
        processing: true,
        serverSide: true,
        select: false,
        ajax: {
            url: '../management/index.php?node='
            + Common.node
            + '&sub=getExportList',
            type: 'post'
        }
    });

    // Enable searching
    if (Common.search && Common.search.length > 0) {
        exportTable.search(Common.search).draw();
    }
})(jQuery);
