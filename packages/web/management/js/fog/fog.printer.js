$(function() {
    checkboxToggleSearchListPages();
    $('select[name="printertype"]').change(function(e) {
        e.preventDefault();
        printertype = this.value.toLowerCase();
        switch(printertype) {
            case 'network':
                $('#iprint,#cups,#local').hide();
                $('#network').show();
                validateInputs('.printername-input',/^[\w!@#$%^()\-'{}\\\.~]{1,255}$/);
                break;
            case 'iprint':
                $('#network,#cups,#local').hide();
                $('#iprint').show();
                validateInputs('.printername-input,.printerinf-input,.printerport-input',/^[\w!@#$%^()\-'{}\\\.~]{1,255}$/);
                break;
            case 'cups':
                $('#network,#iprint,#local').hide();
                $('#cups').show();
                validateInputs('.printername-input,.printerinf-input',/^[\w!@#$%^()\-'{}\\\.~]{1,255}$/);
                validateInputs('.printerip-input',/^(([1-9]?\d|1\d\d|2[0-5][0-5]|2[0-4]\d)\.){3}([1-9]?\d|1\d\d|2[0-5][0-5]|2[0-4]\d)$/);
                break;
            case 'local':
                $('#network,#iprint,#cups').hide();
                $('#local').show();
                validateInputs('.printername-input,.printerinf-input,.printerport-input',/^[\w!@#$%^()\-'{}\\\.~]{1,255}$/);
                validateInputs('.printermodel-input',/^.{1,255}$/);
                validateInputs('.printerip-input',/^(([1-9]?\d|1\d\d|2[0-5][0-5]|2[0-4]\d)\.){3}([1-9]?\d|1\d\d|2[0-5][0-5]|2[0-4]\d)$/);
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
                $('.printerinf-input').each(function(e) {
                    $(this).val(data.file).trigger('keyup');
                });
                $('.printerport-input').each(function(e) {
                    $(this).val(data.port).trigger('keyup');
                });
                $('.printerip-input').each(function(e) {
                    $(this).val(data.ip).trigger('keyup');
                });
                $('.printermodel-input').each(function(e) {
                    $(this).val(data.model).trigger('keyup');
                });
                $('.printerconfigFile-input').each(function(e) {
                    $(this).val(data.configFile).trigger('keyup');
                });
            },
        });
    });
    submiturl = $('form').prop('action');
    $('.printerinf-input,.printerport-input,.printerip-input,.printermodel-input,.printerconfigFile-input').trigger('change');
    $('input[type="submit"][name="updateprinter"]').click(function(e) {
        e.preventDefault();
        someData = {
            alias: $('input[name="alias"]').val(),
            port: $('input[name="port"]').val(),
            inf: $('input[name="inf"]').val(),
            model: $('input[name="model"]').val(),
            ip: $('input[name="ip"]').val(),
            configFile: $('input[name="configFile"]').val(),
            description: $('textarea[name="description"]').val(),
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
