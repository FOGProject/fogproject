(function($) {
    var saveBtn = $('#service-send'),
        table = $('#ipxe-table').registerTable(null, {
        buttons: [],
        order: [
            [2, 'asc']
        ],
        columns: [
            {
                data: 'name',
                orderable: false
            },
            {
                data: 'inputValue',
                orderable: false
            },
            {
                data: 'category',
                visible: false
            }
        ],
        columnDefs: [
             {
                 render: function(data, type, row) {
                     return '<span data-toggle="tooltip" title="'
                         + row.description
                         + '">'
                         + data
                         + '</span>';
                 },
                 targets: 0
             }
        ],
        select: false,
        rowGroup: {
            dataSrc: 'category'
        },
        rowId: 'name',
        processing: true,
        serverSide: true,
        ajax: {
            url: '../management/index.php?node='
                + Common.node
                + '&sub=getIpxeList',
            type: 'post'
        },
    });
    table.on('draw', function() {
        var action = '../management/index.php?node='
            + Common.node
            + '&sub='
            + Common.sub,
            method = 'post';
        table.$('.input-group,.form-control').css({
            width: '100%'
        });
        Common.iCheck('#ipxe-table :input');
        table.$(':input').each(function() {
            $(this).on('change', function(e) {
                e.preventDefault();
                var opts = $(this).serialize();
                $.apiCall(method, action, opts, function(err) {
                    if (err) {
                        return;
                    }
                    table.draw(false);
                });
            });
        });
        table.$(':checkbox').on('ifChecked', function(e) {
            e.preventDefault();
            var key = $(this).attr('name'),
                val = 1,
                opts = {};
            opts[key] = val;
            $.apiCall(method, action, opts, function(err) {
                if (err) {
                    return;
                }
                table.draw(false);
            });
        }).on('ifUnchecked', function(e) {
            e.preventDefault();
            var key = $(this).attr('name'),
                val = 0,
                opts = {};
            opts[key] = val;
            $.apiCall(method, action, opts, function(err) {
                if (err) {
                    return;
                }
                table.draw(false);
            });
        });
    });
    if (Common.search && Common.search.length > 0) {
        table.search(Common.search).draw();
    }
})(jQuery);
