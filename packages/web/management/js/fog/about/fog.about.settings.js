(function($) {
    var saveBtn = $('#service-send'),
        table = Common.registerTable($('#settings-table'), null, {
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
                + '&sub=getSettingsList',
            type: 'post'
        },
    });
    table.on('draw', function() {
        var action = '../management/index.php?node='
            + Common.node
            + '&sub='
            + Common.sub,
            method = 'post';
        $('.slider').slider();
        $('.resettoken').on('click', function(e) {
            e.preventDefault();
            Pace.ignore(function() {
                $.ajax({
                    url: '../status/newtoken.php',
                    dataType: 'json',
                    success: function(data, textStatus, jqXHR) {
                        $('.token').val(data);
                        var opts = $('.token').serialize();
                        Common.apiCall(method, action, opts, function(err) {
                            if (err) {
                                return;
                            }
                            table.draw(false);
                        });
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                    }
                });
            });
        });
        table.$('.input-group,.form-control').css({
            width: '100%'
        });
        $(':password').before('<span class="input-group-addon"><i class="fa fa-eye-slash fogpasswordeye"></i></span>');
        Common.iCheck('#settings-table :input');
        table.$(':input').each(function() {
            if ($(this).hasClass('slider')) {
                ev = 'slideStop';
            } else {
                ev = 'change';
            }
            $(this).on(ev, function(e) {
                e.preventDefault();
                var opts = $(this).serialize();
                Common.apiCall(method, action, opts, function(err) {
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
            Common.apiCall(method, action, opts, function(err) {
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
            Common.apiCall(method, action, opts, function(err) {
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
