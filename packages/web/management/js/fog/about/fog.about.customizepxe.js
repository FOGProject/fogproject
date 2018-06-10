(function($) {
    var saveBtn = $('#service-send'),
        table = $('#ipxe-table').registerTable(null, {
        buttons: [],
        columns: [
            {data: 'name'},
            {data: 'description'},
            {data: 'params'},
            {data: 'default'},
            {data: 'regMenu'},
            {data: 'args'},
            {data: 'hotkey'},
            {data: 'keysequence'}
        ],
        select: false,
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
                opts += '&id=' + table.row().id();
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
