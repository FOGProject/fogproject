$(function() {
    checkboxToggleSearchListPages();
    if ($_GET['sub'] == 'membership') return;
    $('select[name="printertype"]').change(function(e) {
        e.preventDefault();
        printertype = this.value.toLowerCase();
        switch(printertype) {
            case 'network':
                $('#iprint,#cups,#local').hide();
                $('#network').show();
                validateInputs('.printername-input:not(:hidden)',/^[\w!@#$%^()\-'{}\\\.~]{1,255}$/);
                break;
            case 'iprint':
                $('#network,#cups,#local').hide();
                $('#iprint').show();
                validateInputs('.printername-input:not(:hidden),.printerinf-input:not(:hidden),.printerport-input:not(:hidden)',/^[\w!@#$%^()\-'{}\\\.~]{1,255}$/);
                break;
            case 'cups':
                $('#network,#iprint,#local').hide();
                $('#cups').show();
                validateInputs('.printername-input:not(:hidden),.printerinf-input:not(:hidden)',/^[\w!@#$%^()\-'{}\\\.~]{1,255}$/);
                validateInputs('.printerip-input:not(:hidden)',/^(([1-9]?\d|1\d\d|2[0-5][0-5]|2[0-4]\d)\.){3}([1-9]?\d|1\d\d|2[0-5][0-5]|2[0-4]\d)$/);
                break;
            case 'local':
                $('#network,#iprint,#cups').hide();
                $('#local').show();
                validateInputs('.printername-input:not(:hidden),.printerinf-input:not(:hidden),.printerport-input:not(:hidden)',/^[\w!@#$%^()\-'{}\\\.~]{1,255}$/);
                validateInputs('.printermodel-input:not(:hidden)',/^.{1,255}$/);
                validateInputs('.printerip-input:not(:hidden)',/^(([1-9]?\d|1\d\d|2[0-5][0-5]|2[0-4]\d)\.){3}([1-9]?\d|1\d\d|2[0-5][0-5]|2[0-4]\d)$/);
                break;
        }
    });
    $('select[name="printertype"]').trigger('change');
    $('#printer-copy select[name="printer"]').change(function(e) {
        e.preventDefault();
        $.ajax({
            url: '../management/index.php',
            type: 'POST',
            data: {
                node: 'printer',
                sub: 'getPrinterInfo',
                id: this.value
            },
            dataType: 'json',
            success: function(data) {
                $('.printerinf-input:not(:hidden)').val(data.file).trigger('keyup');
                $('.printerport-input:not(:hidden)').val(data.port).trigger('keyup');
                $('.printermodel-input:not(:hidden)').val(data.model).trigger('keyup');
                $('.printerconfigFile-input:not(:hidden)').val(data.configFile).trigger('keyup');
            },
        });
    });
    submiturl = $('form').prop('action');
    $('.printername-input:not(:hidden),.printerinf-input:not(:hidden),.printerport-input:not(:hidden),.printerip-input:not(:hidden),.printermodel-input:not(:hidden),.printerconfigFile-input:not(:hidden)').trigger('change');
    $('form').submit(function(e) {
        if (submiturl.indexOf('delete') > -1) return;
        e.preventDefault();
        someData = {
            alias: $('.printername-input:not(:hidden)').val(),
            ip: $('.printerip-input:not(:hidden)').val(),
            port: $('.printerport-input:not(:hidden)').val(),
            inf: $('.printerinf-input:not(:hidden)').val(),
            model: $('.printermodel-input:not(:hidden)').val(),
            configFile: $('.printerconfigFile-input:not(:hidden)').val(),
            description: $('.printerdescription-input:not(:hidden)').val(),
        };
        $.extend(someData,{printertype});
        $.post(submiturl,someData,function(data) {
            data = $.parseJSON(data);
            if (data.error) Loader.fogStatusUpdate(data.error);
            else Loader.fogStatusUpdate(data.msg);
            setTimeout(function() {
                Loader.fadeOut();
            },5000);
        });
    });
});
