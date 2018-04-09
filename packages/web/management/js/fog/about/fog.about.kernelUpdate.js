(function($) {
    var table = Common.registerTable($('#dataTable'), null, {
        order: [
            [3, 'asc']
        ],
        columns: [
            {data: 'version'},
            {data: 'arch'},
            {data: 'type'},
            {data: 'date'}
        ],
        buttons: [],
        select: false,
        rowGroup: {
            dataSrc: 'date'
        }
    });
})(jQuery);
