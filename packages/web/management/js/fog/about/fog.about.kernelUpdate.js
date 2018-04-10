(function($) {
    var kernelData = {};

    Pace.track(function() {
        $.ajax({
            url: '../fog/availablekernels',
            type: 'get',
            cache: false,
            dataType: 'json',
            success: function(data, textStatus, jqXHR) {
                var table = Common.registerTable($('#dataTable'), null, {
                    data: data,
                    order: [
                        [3, 'desc']
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
            },
            error: function(jqXHR, textStatus, errorThrown) {
                  Common.notifyFromAPI(jqXHR.responseJSON, true);
            }
        });
    });

})(jQuery);
