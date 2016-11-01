$(function() {
    checkboxToggleSearchListPages();
    validatorOpts = {
        submitHandler: function(form) {
            data = $(form).find(':visible').serialize();
            $.ajax({
                url: $(form).attr('action'),
                type: $(form).attr('method').toUpperCase(),
                data: data,
                dataType: 'json'
            }).done(function(response) {
                Loader.fogStatusUpdate(response.error ? response.error : response.msg);
                setTimeout(function() {
                    Loader.fadeOut();
                }, 5000);
            })
            return false;
        },
        rules: {
            alias: {
                required: true,
                minlength: 1,
                maxlength: 255
            }
        }
    };
    if ($_GET['sub'] == 'membership') return;
    $('select[name="printertype"]').change(function(e) {
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
                    minlength: 1,
                    maxlength: 255
                };
                break;
            case 'cups':
                $('#network,#iprint,#local').hide();
                $('#cups').show();
                validatorOpts['rules']['inf'] = {
                    required: true,
                    minlength: 1,
                    maxlength: 255
                };
                validatorOpts['rules']['ip'] = {
                    required: true
                };
                break;
            case 'local':
                $('#network,#iprint,#cups').hide();
                $('#local').show();
                validatorOpts['rules']['inf'] = {
                    required: true,
                    minlength: 1,
                    maxlength: 255
                };
                validatorOpts['rules']['ip'] = {
                    required: true
                };
                validatorOpts['rules']['model'] = {
                    required: true,
                    minlength: 1,
                    maxlength: 255
                };
                validatorOpts['rules']['port'] = {
                    required: true,
                    minlength: 1,
                    maxlength: 255
                };
                break;
        }
    });
    $('select[name="printertype"]').trigger('change');
    form = $('.printername-input:not(:hidden)').parents('form');
    validator = form.validate(validatorOpts);
    $('.printername-input:not(:hidden),.printerinf-input:not(:hidden),.printerport-input:not(:hidden)').rules('add', {regex: /^[\w!@#$%^()\-'{}\\\.~ ]{1,255}$/});
    $('.printermodel-input:not(:hidden)').rules('add', {regex: /^.{1,255}$/});
    $('.printerip-input:not(:hidden)').rules('add', {regex: /^(([1-9]?\d|1\d\d|2[0-5][0-5]|2[0-4]\d)\.){3}([1-9]?\d|1\d\d|2[0-5][0-5]|2[0-4]\d)$/});
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
    $('.printername-input:not(:hidden),.printerinf-input:not(:hidden),.printerport-input:not(:hidden),.printerip-input:not(:hidden),.printermodel-input:not(:hidden),.printerconfigFile-input:not(:hidden)').on('keyup change blur',function() {
        return validator.element(this);
    });
    $('.printername-input:not(:hidden),.printerinf-input:not(:hidden),.printerport-input:not(:hidden),.printerip-input:not(:hidden),.printermodel-input:not(:hidden),.printerconfigFile-input:not(:hidden)').trigger('change');
});
