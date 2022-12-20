(function($) {
    checkboxToggleSearchListPages();
    validatorOpts = {
        submitHandler: submithandlerfunc,
        rules: {
            alias: {
                required: true,
                minlength: 1,
                maxlength: 255,
                regex: /^[-\w!@#$%^()'{}\\\.~ ]{1,255}$/
            }
        }
    };
    if (sub == 'membership') {
        return;
    }
    $('select[name="printertype"]').on('change', function(e) {
        e.preventDefault();
        printertype = this.value.toLowerCase();
        switch(printertype) {
            case 'network':
                $('#iprint,#cups,#local').hide();
                $('#network').show();
                break;
            case 'iprint':
                $('#network,#cups,#local').hide();
                $('#iprint').show();
                validatorOpts['rules']['port'] = {
                    required: true,
                    minlength: 1
                };
                break;
            case 'cups':
                $('#network,#iprint,#local').hide();
                $('#cups').show();
                validatorOpts['rules']['inf'] = {
                    required: true,
                    minlength: 1
                };
                validatorOpts['rules']['ip'] = {
                    required: true,
                    regex: /^(([a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9])\.)*([A-Za-z0-9]|[A-Za-z0-9][A-Za-z0-9\-]*[A-Za-z0-9])$/
                };
                break;
            case 'local':
                $('#network,#iprint,#cups').hide();
                $('#local').show();
                validatorOpts['rules']['inf'] = {
                    required: true,
                    minlength: 1
                };
                validatorOpts['rules']['ip'] = {
                    required: true,
                    regex: /^(([a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9])\.)*([A-Za-z0-9]|[A-Za-z0-9][A-Za-z0-9\-]*[A-Za-z0-9])$/
                };
                validatorOpts['rules']['model'] = {
                    required: true,
                    minlength: 1
                };
                validatorOpts['rules']['port'] = {
                    required: true,
                    minlength: 1
                };
                break;
        }
    }).trigger('change');
    $('#printer-copy select[name="printer"]').on('change', function(e) {
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
    setupTimeoutElement('#add, #updategen', '.printername-input, .printerinf-input, .printerport-input, .printerip-input, .printermodel-input, .printerconfigFile-input', 1000);
})(jQuery);
