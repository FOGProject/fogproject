$(function() {
    checkboxToggleSearchListPages();
    validatorOpts = {
        submitHandler: function(form) {
            data = $(form).find(':visible').serialize();
            url = $(form).attr('action');
            method = $(form).attr('method').toUpperCase();
            $.ajax({
                url: url,
                type: method,
                data: data,
                dataType: 'json',
                success: function(data) {
                    dialoginstance = new BootstrapDialog();
                    if (data.error) {
                        dialoginstance
                        .setTitle('Printer Update Failed')
                        .setMessage(data.error)
                        .setType(BootstrapDialog.TYPE_WARNING)
                        .open();
                    } else {
                        dialoginstance
                        .setTitle('Printer Update Success')
                        .setMessage(data.msg)
                        .setType(BootstrapDialog.TYPE_SUCCESS)
                        .open();
                    }
                }
            });
            return false;
        },
        rules: {
            alias: {
                required: true,
                minlength: 1,
                maxlength: 255,
                regex: /^[\w!@#$%^()\-'{}\\\.~ ]{1,255}$/
            }
        }
    };
    if ($_GET['sub'] == 'membership') return;
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
                    minlength: 1,
                    maxlength: 255,
                    regex: /^((6553[0-5])|(655[0-2][0-9])|(65[0-4][0-9]{2})|(6[0-4][0-9]{3})|([1-5][0-9]{4})|([0-5]{0,5})|([0-9]{1,4}))$/
                };
                break;
            case 'cups':
                $('#network,#iprint,#local').hide();
                $('#cups').show();
                validatorOpts['rules']['inf'] = {
                    required: true,
                    minlength: 1,
                    maxlength: 255,
                    regex: /^[\w!@#$%^()\-'{}\\\.~ ]{1,255}$/
                };
                validatorOpts['rules']['ip'] = {
                    required: true,
                    regex: /^(([1-9]?\d|1\d\d|2[0-5][0-5]|2[0-4]\d)\.){3}([1-9]?\d|1\d\d|2[0-5][0-5]|2[0-4]\d)$/
                };
                break;
            case 'local':
                $('#network,#iprint,#cups').hide();
                $('#local').show();
                validatorOpts['rules']['inf'] = {
                    required: true,
                    minlength: 1,
                    maxlength: 255,
                    regex: /^[\w!@#$%^()\-'{}\\\.~ ]{1,255}$/
                };
                validatorOpts['rules']['ip'] = {
                    required: true,
                    regex: /^(([1-9]?\d|1\d\d|2[0-5][0-5]|2[0-4]\d)\.){3}([1-9]?\d|1\d\d|2[0-5][0-5]|2[0-4]\d)$/
                };
                validatorOpts['rules']['model'] = {
                    required: true,
                    minlength: 1,
                    maxlength: 255,
                    regex: /^.{1,255}$/
                };
                validatorOpts['rules']['port'] = {
                    required: true,
                    minlength: 1,
                    maxlength: 5,
                    regex: /^((6553[0-5])|(655[0-2][0-9])|(65[0-4][0-9]{2})|(6[0-4][0-9]{3})|([1-5][0-9]{4})|([0-5]{0,5})|([0-9]{1,4}))$/
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
    form = $('#add, #updategen:not(:hidden)').parents('form');
    validator = form.validate(validatorOpts);
    $('.printername-input:not(:hidden),.printerinf-input:not(:hidden),.printerport-input:not(:hidden),.printerip-input:not(:hidden),.printermodel-input:not(:hidden),.printerconfigFile-input:not(:hidden)').on('keyup change blur',function() {
        return validator.element(this);
    }).trigger('change');
    $('.nav-tabs a').on('shown.bs.tab', function(e) {
        form = $('#add, #updategen:not(:hidden)').parents('form');
        validator = form.validate(validatorOpts);
        $('.printername-input:not(:hidden),.printerinf-input:not(:hidden),.printerport-input:not(:hidden),.printerip-input:not(:hidden),.printermodel-input:not(:hidden),.printerconfigFile-input:not(:hidden)').on('keyup change blur',function() {
            return validator.element(this);
        }).trigger('change');
    });
});
