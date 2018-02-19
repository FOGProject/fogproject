(function($) {
    // ---------------------------------------------------------------
    // GENERAL TAB
    var originalName = $('#name').val(),
        updateName = function(newName) {
            var e = $('#pageTitle'),
                text = e.text();
            text = text.replace(': ' + originalName, ': ' + newName);
            e.text(text);
        };

    $('#productKey').inputmask({mask: Common.masks.productKey});

    var generalForm = $('#group-general-form'),
        generalFormBtn = $('#general-send'),
        generalDeleteBtn = $('#general-delete');

    generalForm.on('submit',function(e) {
        e.preventDefault();
    });
    generalFormBtn.on('click',function() {
        generalFormBtn.prop('disabled', true);
        generalDeleteBtn.prop('disabled', true);
        Common.processForm(generalForm, function(err) {
            generalFormBtn.prop('disabled', false);
            generalDeleteBtn.prop('disabled', false);
            if (err) {
                return;
            }
            updateName($('#name').val());
            originalName = $('#name').val();
        });
    });
    generalDeleteBtn.on('click',function() {
        generalFormBtn.prop('disabled', true);
        generalDeleteBtn.prop('disabled', true);
        Common.massDelete(null, function(err) {
            if (err) {
                generalDeleteBtn.prop('disabled', false);
                generalFormBtn.prop('disabled', false);
                return;
            }
            window.location = '../management/index.php?node='+Common.node+'&sub=list';
        });
    });

    // ---------------------------------------------------------------
    // ACTIVE DIRECTORY TAB
    var ADForm = $('#active-directory-form'),
        ADFormBtn = $('#ad-send'),
        ADClearBtn = $('#ad-clear');

    ADForm.on('submit',function(e) {
        e.preventDefault();
    });
    ADFormBtn.on('click', function() {
        ADFormBtn.prop('disabled', true);
        ADClearBtn.prop('disabled', true);
        Common.processForm(ADForm, function(err) {
            ADFormBtn.prop('disabled', false);
            ADClearBtn.prop('disabled', false);
        });
    });
    ADClearBtn.on('click', function() {
        ADClearBtn.prop('disabled', true);
        ADFormBtn.prop('disabled', true);

        var restoreMap = [];
        ADForm.find('input[type="text"], input[type="password"], textarea').each(function(i, e) {
            restoreMap.push({checkbox: false, e: e, val: $(e).val()});
            $(e).val('');
            $(e).prop('disabled', true);
        });
        ADForm.find('input[type="checkbox"]').each(function(i, e) {
            restoreMap.push({checkbox: true, e: e, val: $(e).iCheck('update')[0].checked});
            $(e).iCheck('uncheck');
            $(e).iCheck('disable');
        });

        ADForm.find('input[type="text"], input[type="password"], textarea').val('');
        ADForm.find('input[type="checkbox"]').iCheck('uncheck');

        Common.processForm(ADForm, function(err) {
            for (var i = 0; i < restoreMap.length; i++) {
                field = restoreMap[i];
                if (field.checkbox) {
                    if (err) $(field.e).iCheck((field.val ? 'check' : 'uncheck'));
                    $(field.e).iCheck('enable');
                } else {
                    if (err) $(field.e).val(field.val);
                    $(field.e).prop('disabled', false);
                }
            }
            ADClearBtn.prop('disabled', false);
            ADFormBtn.prop('disabled', false);
        });
    });

    // ---------------------------------------------------------------
    // PRINTER TAB
    var printerConfigForm = $('#printer-config-form'),
        printerConfigBtn = $('#printer-config-send'),
        printerAddBtn = $('#printer-add'),
        printerDefaultBtn = $('#printer-default'),
        printerRemoveBtn = $('#printer-remove'),
        DEFAULT_PRINTER_ID = -1;

    printerAddBtn.prop('disabled', true);
    printerRemoveBtn.prop('disabled', true);

    function onPrintersToAddTableSelect (selected) {
        var disabled = selected.count() == 0;
        printerAddBtn.prop('disabled', disabled);
    }
    function onPrintersSelect (selected) {
        var disabled = selected.count() == 0;
        printerAddBtn.prop('disabled', disabled);
        printerRemoveBtn.prop('disabled', disabled);
    }

    var printersTable = Common.registerTable($('#group-printers-table'), onPrintersSelect, {
        order: [
            [1, 'asc']
        ],
        columns: [
            {data: 'isDefault'},
            {data: 'name'},
            {data: 'config'}
        ],
        rowId: 'id',
        columnDefs: [
            {
                responsivePriority: -1,
                render: function(data, type, row) {
                    var checkval = '';
                    if (row.isDefault) {
                        checkval = ' checked';
                    }
                    return '<div class="radio">'
                        + '<input belongsto="defaultPrinters" type="radio" class="default" name="default" id="printer_'
                        + row.id
                        + '" value="' + row.id + '"'
                        + ' wasoriginaldefault="'
                        + checkval
                        + '"'
                        + checkval
                        + '/>'
                        + '</div>'
                },
                targets: 0,
            },
            {
                responsivePriority: 0,
                render: function(data, type, row) {
                    return '<a href="../management/index.php?node=printer&sub=edit&id=' + row.id + '">' + data + '</a>';
                },
                targets: 1
            },
            {
                render: function(data, type, row) {
                    return row.config == 'Local' ? 'TCP/IP' : row.config;
                },
                targets: 2
            },
        ],
        processing: true,
        serverSide: true,
        ajax: {
            url: '../management/index.php?node='+Common.node+'&sub=getPrintersList&id='+Common.id,
            type: 'post'
        }
    });

    printersTable.on('draw', function() {
        Common.iCheck('#group-printers input');
        $('.default').on('ifClicked', onRadioSelect);
    });
    printerDefaultBtn.prop('disabled', true);

    var onRadioSelect = function(event) {
        if ($(this).attr('belongsto') === 'defaultPrinters') {
            var id = parseInt($(this).val());
            if (DEFAULT_PRINTER_ID === -1 && $(this).attr('wasoriginaldefault') === ' checked') {
                DEFAULT_PRINTER_ID = id;
            }
            if (id === DEFAULT_PRINTER_ID) {
                $(this).iCheck('uncheck');
                DEFAULT_PRINTER_ID = 0;
            } else {
                DEFAULT_PRINTER_ID = id;
            }
            printerDefaultBtn.prop('disabled', false);
        }
    };

    // Setup default printer watcher
    $('.default').on('ifClicked', onRadioSelect);

    printerDefaultBtn.on('click', function() {
        printerAddBtn.prop('disabled', true);
        printerRemoveBtn.prop('disabled', true);

        var method = printerDefaultBtn.attr('method'),
            action = printerDefaultBtn.attr('action'),
            opts = {
                'defaultsel': '1',
                'default': DEFAULT_PRINTER_ID
            };
        Common.apiCall(method,action,opts,function(err) {
            printerDefaultBtn.prop('disabled', !err);
            onPrintersSelect(printersTable.rows({selected: true}));
        });
    });

    printerConfigForm.serialize2 = printerConfigForm.serialize;
    printerConfigForm.serialize = function() {
        return printerConfigForm.serialize2() + '&levelup';
    };
    printerConfigForm.on('submit',function(e) {
        e.preventDefault();
    });
    printerConfigBtn.on('click', function() {
        printerConfigBtn.prop('disabled', true);
        Common.processForm(printerConfigForm, function(err) {
            printerConfigBtn.prop('disabled', false);
        });
    });
    printerAddBtn.on('click', function() {
        printerAddBtn.prop('disabled', true);

        var method = printerAddBtn.attr('method'),
            action = printerAddBtn.attr('action'),
            rows = printersToAddTable.rows({selected: true}),
            toAdd = Common.getSelectedIds(printersToAddTable),
            opts = {
                'updateprinters': '1',
                'printer': toAdd
            };

        Common.apiCall(method,action,opts,function(err) {
            if (!err) {
                printersTable.draw(false);
                printersTable.rows({
                    selected: true
                }).remove().draw(false);
                printersTable.rows({selected: true}).deselect();
            } else {
                printerAddBtn.prop('disabled', false);
            }
        });
    });

    printerRemoveBtn.on('click',function() {
        printerAddBtn.prop('disabled', true);
        printerRemoveBtn.prop('disabled', true);
        printerDefaultBtn.prop('disabled', true);

        var method = printerRemoveBtn.attr('method'),
            action = printerRemoveBtn.attr('method'),
            rows = printersTable.rows({selected: true}),
            toRemove = Common.getSelectedIds(printersTable),
            opts = {
                'printdel': '1',
                'printerRemove': toRemove
            };

        Common.apiCall(method,action,opts, function(err) {
            printerDefaultBtn.prop('disabled', false);
            if (!err) {
                printersTable.draw(false);
                printersTable.rows({
                    selected: true
                }).remove().draw(false);
                printersTable.rows({selected: true}).deselect();
            } else {
                printerRemoveBtn.prop('disabled', false);
            }
        });
    });

    if (Common.search && Common.search.length > 0) {
        printersTable.search(Common.search).draw();
    }

    // ---------------------------------------------------------------
    // SNAPINS TAB
    var snapinsAddBtn = $('#snapins-add'),
        snapinsRemoveBtn = $('#snapins-remove');

    snapinsAddBtn.prop('disabled', true);
    snapinsRemoveBtn.prop('disabled', true);

    function onSnapinsRemoveSelect (selected) {
        var disabled = selected.count() == 0;
        snapinsRemoveBtn.prop('disabled', disabled);
    }
    function onSnapinsAddSelect (selected) {
        var disabled = selected.count() == 0;
        snapinsAddBtn.prop('disable', disabled);
    }

    var snapinsTable = Common.registerTable($('#group-snapins-table'), onSnapinsRemoveSelect, {
        columns: [
            {data: 'name'},
            {data: 'createdTime'}
        ],
        rowId: 'id',
        columnDefs: [
            {
                responsivePriority: -1,
                render: function(data, type, row) {
                    return '<a href="../management/index.php?node=snapin&sub=edit&id=' + row.id + '">' + data + '</a>';
                },
                targets: 0
            }
        ],
        processing: true,
        serverSide: true,
        ajax: {
            url: '../management/index.php?node='+Common.node+'&sub=getSnapinsList&id='+Common.id,
            type: 'post'
        }
    });
    snapinsTable.on('draw', function() {
        Common.iCheck('#group-snapins-table input');
    });

    snapinsAddBtn.on('click', function() {
        snapinsAddBtn.prop('disabled', true);
        var method = snapinsAddBtn.attr('method'),
            action = snapinsAddBtn.attr('action'),
            rows = snapinsTable.rows({selected: true}),
            toAdd = Common.getSelectedIds(snapinsTable),
            opts = {
                'updatesnapins': '1',
                'snapin': toAdd
            };
        Common.apiCall(method,action,opts,function(err) {
            if (!err) {
                snapinsTable.draw(false);
                snapisnTable.rows({
                    selected: true
                }).remove().draw(false);
                snapinsTable.rows({selected: true}).deselect();
            } else {
                snapinsAddBtn.prop('disabled', false);
            }
        });
    });

    snapinsRemoveBtn.on('click', function() {
        snapinsRemoveBtn.prop('disable', true);
        var method = snapinsRemoveBtn.attr('method'),
            action = snapinsRemoveBtn.attr('action'),
            rows = snapinsTable.rows({selected: true}),
            toRemove = Common.getSelectedIds(snapinsTable),
            opts = {
                'snapdel': '1',
                'snapinRemove': toRemove
            };
        Common.apiCall(method,action,opts,function(err) {
            if (!err) {
                snapinsTable.draw(false);
                snapinsTable.rows({
                    selected: true
                }).remove().draw(false);
                snapinsTable.rows({selected: true}).deselect();
            } else {
                snapinsRemoveBtn.prop('disabled', false);
            }
        });
    });
    if (Common.search && Common.search.length > 0) {
        snapinsTable.search(Common.search).draw();
    }

    // ---------------------------------------------------------------
    // SERVICE TAB
    var modulesEnableBtn = $('#modules-enable'),
        modulesDisableBtn = $('#modules-disable'),
        modulesUpdateBtn = $('#modules-update');

    function onModulesDisable(selected) {
        var disabled = selected.count() == 0;
        modulesDisableBtn.prop('disabled', disabled);
    }
    function onModulesEnable(selected) {
        var disabled = selected.count() != 0;
        modulesEnableBtn.prop('disabled', disabled);
    }

    modulesEnableBtn.on('click', function(e) {
        e.preventDefault();
        $('#modules-to-update_wrapper .buttons-select-all').trigger('click');
        $(this).prop('disabled', true);
        modulesDisableBtn.prop('disabled', true);
    });
    modulesDisableBtn.on('click', function(e) {
        e.preventDefault();
        $('#modules-to-update_wrapper .buttons-select-none').trigger('click');
        $(this).prop('disabled', true);
        modulesEnableBtn.prop('disabled', true);
    });

    var modulesTable = Common.registerTable($("#modules-to-update"), onModulesEnable, {
        columns: [
            {data: 'name'}
        ],
        rowId: 'id',
        columnDefs: [
            {
                responsivePriority: -1,
                render: function(data, type, row) {
                    return row.name
                },
                targets: 0
            }
        ],
        processing: true,
        serverSide: true,
        ajax: {
            url: '../management/index.php?node='+Common.node+'&sub=getModulesList&id='+Common.id,
            type: 'post'
        }
    });
    modulesTable.on('draw', function() {
        Common.iCheck('#modules-to-update input');
    });
    if (Common.search && Common.search.length > 0) {
        modulesTable.search(Common.search).draw();
    }
})(jQuery)
