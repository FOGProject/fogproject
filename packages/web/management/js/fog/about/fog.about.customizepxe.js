(function($) {
        table = $('#ipxe-table').registerTable(null, {
        buttons: [],
        columns: [
            {data: 'name'},
            {data: 'description'},
            {data: 'default'},
            {data: 'regMenu'},
            {data: 'hotkey'},
            {data: 'keysequence'}
        ],
        rowId: 'id',
        processing: true,
        serverSide: true,
        ajax: {
            url: '../management/index.php?node='
                + Common.node
                + '&sub=getMenuList',
            type: 'post'
        },
    });
    if (Common.search && Common.search.length > 0) {
        table.search(Common.search).draw();
    }
})(jQuery);
