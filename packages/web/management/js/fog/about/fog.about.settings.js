(function($) {
    var table = Common.registerTable($('#settings-table'), null, {
        order: [
            [2, 'asc']
        ],
        buttons: [],
        columns: [
            {data: 'name'},
            {data: 'inputValue'},
            {data: 'category', visible: false}
        ],
        columnDefs: [
            {
                orderable: false,
                targets: 0
            },
            {
                orderable: false,
                targets: 1
            },
        ],
        select: false,
        rowGroup: {
            dataSrc: 'category'
        },
        rowId: 'id',
        processing: true,
        serverSide: true,
        ajax: {
            url: '../management/index.php?node='
                + Common.node
                + '&sub=getSettingsList',
            type: 'post'
        },
    });
    table.on('draw', function() {
        $('.slider').slider();
        $('.resettoken').on('click', function(e) {
            e.preventDefault();
            Pace.ignore(function() {
                $.ajax({
                    url: '../status/newtoken.php',
                    dataType: 'json',
                    success: function(data, textStatus, jqXHR) {
                        $('.token').val(data);
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                    }
                });
            });
        });
        $(':password').before('<span class="input-group-addon"><i class="fa fa-eye-slash fogpasswordeye"></i></span>');
        Common.iCheck('#settings-table :input');
    });
})(jQuery);
