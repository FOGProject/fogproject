(function($) {
    // ---------------------------------------------------------------
    // GENERAL TAB
    var originalName = $('#name').val();

    var updateName = function(newName) {
        var e = $('#pageTitle'),
            text = e.text();
        text = text.replace(": " + originalName, ": " + newName);
        e.text(text);
    };

    $("#productKey").inputmask({"mask": Common.masks.productKey});

    var generalForm = $('#group-general-form'),
        generalFormBtn = $('#general-send'),
        generalDeleteBtn = $('#general-delete');

    generalForm.submit(function(e) {
        e.preventDefault();
    });
    generalFormBtn.click(function() {
        generalFormBtn.prop("disabled", true);
        generalDeleteBtn.prop("disabled", true);
        Common.processForm(generalForm, function(err) {
            generalFormBtn.prop("disabled", false);
            generalDeleteBtn.prop("disabled", false);
            if (err)
                return;
            updateName($("#name").val());
            originalName = $("#name").val();
        });
    });
    generalDeleteBtn.click(function() {
        generalFormBtn.prop("disabled", true);
        generalDeleteBtn.prop("disabled", true);
        Common.massDelete(null, function(err) {
            if (err) {
                generalDeleteBtn.prop("disabled", false);
                generalFormBtn.prop("disabled", false);
                return;
            }
            window.location = '../management/index.php?node='+Common.node+'&sub=list';
        });
    });

    // ---------------------------------------------------------------
    // ACTIVE DIRECTORY TAB
    var ADForm = $("#active-directory-form");
    var ADFormBtn = $("#ad-send");
    var ADClearBtn = $("#ad-clear");

    ADForm.submit(function(e) {
        e.preventDefault();
    });
    ADFormBtn.on('click', function() {
        ADFormBtn.prop("disabled", true);
        ADClearBtn.prop("disabled", true);
        Common.processForm(ADForm, function(err) {
            ADFormBtn.prop("disabled", false);
            ADClearBtn.prop("disabled", false);
        });
    });
    ADClearBtn.on('click', function() {
        ADClearBtn.prop("disabled", true);
        ADFormBtn.prop("disabled", true);

        var restoreMap = [];
        ADForm.find("input[type=text], input[type=password], textarea").each(function(i, e) {
            restoreMap.push({checkbox: false, e: e, val: $(e).val()});
            $(e).val("");
            $(e).prop("disabled", true);
        });
        ADForm.find("input[type=checkbox]").each(function(i, e) {
            restoreMap.push({checkbox: true, e: e, val: $(e).iCheck('update')[0].checked});
            $(e).iCheck('uncheck');
            $(e).iCheck('disable');
        });

        ADForm.find("input[type=text], input[type=password], textarea").val("");
        ADForm.find("input[type=checkbox]").iCheck('uncheck');

        Common.processForm(ADForm, function(err) {
            for (var i = 0; i < restoreMap.length; i++) {
                field = restoreMap[i];
                if (field.checkbox) {
                    if (err) $(field.e).iCheck((field.val ? "check" : "uncheck"));
                    $(field.e).iCheck("enable");
                } else {
                    if (err) $(field.e).val(field.val);
                    $(field.e).prop("disabled", false);
                }
            }
            ADClearBtn.prop("disabled", false);
            ADFormBtn.prop("disabled", false);
        });
    });

    // ---------------------------------------------------------------
    // PRINTER TAB
    var printerConfigForm = $("#printer-config-form");
    var printerConfigBtn = $("#printer-config-send");
    var printerAddBtn = $("#printer-add");
    var printerDefaultBtn = $("#printer-default");
    var printerRemoveBtn = $("#printer-remove");
    var DEFAULT_PRINTER_ID = -1;

    printerAddBtn.prop("disabled", true);
    printerRemoveBtn.prop("disabled", true);

    function onPrintersToAddTableSelect (selected) {
        var disabled = selected.count() == 0;
        printerAddBtn.prop("disabled", disabled);
    }
    function onPrintersSelect (selected) {
        var disabled = selected.count() == 0;
        printerRemoveBtn.prop("disabled", disabled);
    }

    var printersTable = Common.registerTable($("#group-printers-table"), onPrintersSelect, {
        order: [
            [1, 'asc']
        ],
        columns: [
            {data: 'isDefault'},
            {data: 'name'},
            {data: 'config'},
            {data: 'association'}
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
            {
                render: function(data, type, row) {
                    var checkval = '';
                    if (row.association === 'associated') {
                        checkval = ' checked';
                    }
                    return '<div class="checkbox">'
                        + '<input type="checkbox" class="associated" name="associate[]" id="printerAssoc_'
                        + row.id
                        + '" value="' + row.id + '"'
                        + checkval
                        + '/>'
                        + '</div>';
                },
                targets: 3
            }
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
    printerDefaultBtn.prop("disabled", true);

    var onRadioSelect = function(event) {
        if ($(this).attr("belongsto") === 'defaultPrinters') {
            var id = parseInt($(this).attr("value"));
            if (DEFAULT_PRINTER_ID === -1 && $(this).attr("wasoriginaldefault") === ' checked') {
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
        printerRemoveBtn.prop("disabled", true);

        var opts = {
            'defaultsel': '1',
            'default': DEFAULT_PRINTER_ID
        };

        Common.apiCall(printerDefaultBtn.attr('method'), printerDefaultBtn.attr('action'), opts, function(err) {
            printerDefaultBtn.prop("disabled", !err);
            onPrintersSelect(printersTable.rows({selected: true}));
        });
    });

    printerConfigForm.serialize2 = printerConfigForm.serialize;
    printerConfigForm.serialize = function() {
        return printerConfigForm.serialize2() + '&levelup';
    };
    printerConfigForm.submit(function(e) {
        e.preventDefault();
    });
    printerConfigBtn.on('click', function() {
        printerConfigBtn.prop("disabled", true);
        Common.processForm(printerConfigForm, function(err) {
            printerConfigBtn.prop("disabled", false);
        });
    });
    printerAddBtn.on('click', function() {
        printerAddBtn.prop('disabled', true);

        var rows = printersToAddTable.rows({selected: true});
        var toAdd = Common.getSelectedIds(printersToAddTable);
        var opts = {
            'updateprinters': '1',
            'printer': toAdd
        };

        Common.apiCall(printerAddBtn.attr('method'), printerAddBtn.attr('action'), opts, function(err) {
            if (!err) {
                rows.every(function(idx, tableLoop, rowLoop) {
                    var data = this.data();
                    var id = this.id();
                    printersTable.row.add({
                        0: '<div class="radio"><input id="printers' + id + '" belongsto="defaultPrinters" type="radio" class="default" name="default" value="' + id + '" wasoriginaldefault="" /></div>',
                        1: data[0],
                        2: data[1],
                        3: data[2]
                    });
                });
                printersTable.draw(false);
                printersToAddTable.rows({
                    selected: true
                }).remove().draw(false);
                printersToAddTable.rows({selected: true}).deselect();
            } else {
                printerAddBtn.prop("disabled", false);
            }
        });
    });

    printerRemoveBtn.on('click', function() {
        printerRemoveBtn.prop("disabled", true);
        printerDefaultBtn.prop("disabled", true);

        var rows = printersTable.rows({selected: true});
        var toRemove = Common.getSelectedIds(printersTable);
        var opts = {
            'printdel': '1',
            'printerRemove': toRemove
        };

        Common.apiCall(printerRemoveBtn.attr('method'), printerRemoveBtn.attr('action'), opts, function(err) {
            printerDefaultBtn.prop("disabled", false);

            if (!err) {
                rows.every(function (idx, tableLoop, rowLoop) {
                    var data = this.data();
                    printersToAddTable.row.add({
                        0: data[1],
                        1: data[2]
                    });
                });
                printersToAddTable.draw(false);

                printersTable.rows({
                    selected: true
                }).remove().draw(false);
                printersTable.rows({selected: true}).deselect();
            } else {
                printerRemoveBtn.prop("disabled", false);
            }
        });
    });

    if (Common.search && Common.search.length > 0) {
        printersTable.search(Common.search).draw();
    }

    // ---------------------------------------------------------------
    // SNAPINS TAB
    var snapinsAddBtn = $('#snapins-add');
    var snapinsRemoveBtn = $('#snapins-remove');

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
            {data: 'createdTime'},
            {data: 'assocation'}
        ],
        rowId: 'id',
        columnDefs: [
            {
                responsivePriority: -1,
                render: function(data, type, row) {
                    return '<a href="../management/index.php?node=snapin&sub=edit&id=' + row.id + '">' + data + '</a>';
                },
                targets: 0
            },
            {
                render: function(data, type, row) {
                    var checkval = '';
                    if (row.association === 'associated') {
                        checkval = ' checked';
                    }
                    return '<div class="checkbox">'
                        + '<input type="checkbox" class="associated" name="associate[]" id="snapinAssoc_'
                        + row.id
                        + '" value="' + row.id + '"'
                        + checkval
                        + '/>'
                        + '</div>';
                },
                targets: 2
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
        var rows = snapinsTable.rows({selected: true});
        var toAdd = Common.getSelectedIds(snapinsTable);
        var opts = {
            'updatesnapins': '1',
            'snapin': toAdd
        };
        Common.apiCall(snapinsAddBtn.attr('method'), snapinsAddBtn.attr('action'), opts, function(err) {
            if (!err) {
                rows.every(function(idx, tableLoop, rowLoop) {
                    var data = this.data();
                    var id = this.id();
                    snapinsTable.row.add({
                        0: data[0],
                        1: data[1],
                        2: data[2]
                    });
                });
                snapinsTable.draw(false);
                snapisnAddTable.rows({
                    selected: true
                }).remove().draw(false);
                snapinsAddTable.rows({selected: true}).deselect();
            } else {
                snapinsAddBtn.prop('disabled', false);
            }
        });
    });

    snapinsRemoveBtn.on('click', function() {
        snapinsRemoveBtn.prop('disable', true);
        var rows = snapinsTable.rows({selected: true});
        var toRemove = Common.getSelectedIds(snapinsTable);
        var opts = {
            'snapdel': '1',
            'snapinRemove': toRemove
        };
        Common.apiCall(snapinsRemoveBtn.attr('method'), snapinsRemoveBtn.attr('action'), opts, function(err) {
            if (!err) {
                rows.every(function(idx, tableLoop, rowLoop) {
                    var data = this.data();
                    snapinsAddTable.row.add({
                        0: data[0],
                        1: data[1]
                    });
                });
                snapinsAddTable.draw(false);
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
    var modulesEnableBtn = $('#modules-enable');
    var modulesDisableBtn = $('#modules-disable');
    var modulesUpdateBtn = $('#modules-update');

    function onModulesDisable(selected) {
        var disabled = selected.count() == 0;
        modulesDisableBtn.prop('disabled', disabled);
    }
    function onModulesEnable(selected) {
        var disabled = selected.count() == 0;
        modulesEnableBtn.prop('disabled', disabled);
    }

    modulesEnableBtn.on('click', function(e) {
        e.preventDefault();
        $('.associated').iCheck('check');
    });
    modulesDisableBtn.on('click', function(e) {
        e.preventDefault();
        $('.associated').iCheck('uncheck');
    });

    var modulesTable = Common.registerTable($("#modules-to-update"), onModulesEnable, {
        columns: [
            {data: 'name'},
            {data: 'association'}
        ],
        rowId: 'id',
        columnDefs: [
            {
                responsivePriority: -1,
                render: function(data, type, row) {
                    return row.name
                },
                targets: 0
            },
            {
                render: function(data, type, row) {
                    var checkval = '';
                    if (row.association === 'associated') {
                        checkval = ' checked';
                    }
                    return '<div class="checkbox">'
                        + '<input type="checkbox" class="associated" name="associate[]" id="moduleAssoc_'
                        + row.id
                        + '" value="' + row.id + '"'
                        + checkval
                        + '/>'
                        + '</div>';
                },
                targets: 1
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
    //$('#resetSecData').val('Reset Encryption Data');
    //$('#delAllPM').val('Delete all power management for group');
    //resetEncData('groups hosts', 'group');
    //$('#delAllPM').on('click', function() {
    //    $('#delAllPMBox').html('Are you sure you wish to remove all power management tasks with this group?');
    //    $('#delAllPMBox').dialog({
    //        resizable: false,
    //        modal: true,
    //        title: 'Remove Power Management Tasks',
    //        buttons: {
    //            'Yes': function() {
    //                $.post('../management/index.php',{sub: 'clearPMTasks',groupid: $_GET.id});
    //                $(this).dialog('close');
    //            },
    //            'No': function() {
    //                $(this).dialog('close');
    //            }
    //        }
    //    });
    //});
    // Checkbox toggles
    //checkboxAssociations('.toggle-checkbox1:checkbox','.toggle-host1:checkbox');
    //checkboxAssociations('.toggle-checkbox2:checkbox','.toggle-host2:checkbox');
    //checkboxAssociations('.toggle-checkboxprint:checkbox','.toggle-print:checkbox');
    //checkboxAssociations('.toggle-checkboxprintrm:checkbox','.toggle-printrm:checkbox');
    //checkboxAssociations('.toggle-checkboxsnapin:checkbox','.toggle-snapin:checkbox');
    //checkboxAssociations('.toggle-checkboxsnapinrm:checkbox','.toggle-snapinrm:checkbox');
    //checkboxAssociations('#rempowerselectors:checkbox','.rempoweritems:checkbox');
    // Show hide based on checked state.
    //$('#hostMeShow:checkbox').change(function(e) {
    //    if ($(this).is(':checked')) $('#hostNotInMe').show();
    //    else $('#hostNotInMe').hide();
    //    e.preventDefault();
    //});
    //$('#hostMeShow:checkbox').trigger('change');
    //$('#hostNoShow:checkbox').change(function(e) {
    //    if ($(this).is(':checked')) $('#hostNoGroup').show();
    //    else $('#hostNoGroup').hide();
    //    e.preventDefault();
    //});
    //$('#hostNoShow:checkbox').trigger('change');
    //result = true;
    //$('#scheduleOnDemand').change(function() {
    //    if ($(this).is(':checked') === true) {
    //        $(this).parents('form').each(function() {
    //            $("input[name^='scheduleCron']",this).each(function() {
    //                $(this).val('').prop('readonly',true).hide().parents('tr').hide();
    //            });
    //        });
    //    } else {
    //        $(this).parents('form').each(function() {
    //            $("input[name^='scheduleCron']",this).each(function() {
    //                $(this).val('').prop('readonly',false).show().parents('tr').show();
    //            });
    //        });
    //    }
    //});
    //$("form.deploy-container").submit(function() {
    //    if ($('#scheduleOnDemand').is(':checked')) {
    //        $(".cronOptions > input[name^='scheduleCron']",$(this)).each(function() {
    //            $(this).val('').prop('disabled',true);
    //        });
    //        return true;
    //    } else {
    //        $(".cronOptions > input[name^='scheduleCron']",$(this)).each(function() {
    //            result = validateCronInputs($(this));
    //            if (result === false) return false;
    //        });
    //    }
    //    return result;
    //}).each(function() {
    //    $("input[name^='scheduleCron']",this).each(function(id,value) {
    //        if (!validateCronInputs($(this))) $(this).addClass('error');
    //    }).blur(function() {
    //        if (!validateCronInputs($(this))) $(this).addClass('error');
    //    });
    //});
    //specialCrons();
})(jQuery)
