(function($) {
    var printertype = $('#printertype'),
        printercopy = $('#printercopy');

    // Show only the selected type's section. Hidden sections are disabled so
    // their inputs stay out of the submitted FormData and out of validation.
    function showType(type) {
        $('.printer-type-section').each(function() {
            var section = $(this),
                match = section.hasClass(type);
            section.toggleClass('d-none', !match);
            section.find(':input').prop('disabled', !match);
        });
    }
    // Copy another printer's settings into this form. Each value is written to
    // every type section's matching input by class; only the visible one is
    // submitted. Name and description are left as the admin entered them.
    function copyFromExisting(id) {
        if (!id) {
            return;
        }
        $.getJSON(
            '../management/index.php?node=' + Common.node
                + '&sub=getPrinterInfo&id=' + id,
            function(data) {
                if (!data) {
                    return;
                }
                $('.printerport-input').val(data.port);
                $('.printerinf-input').val(data.file);
                $('.printerip-input').val(data.ip);
                $('.printermodel-input').val(data.model);
                $('.printerconfigfile-input').val(data.configFile);
                var wanted = (data.config || '').toLowerCase(),
                    matched = null;
                printertype.find('option').each(function() {
                    if ($(this).val().toLowerCase() === wanted) {
                        matched = $(this).val();
                    }
                });
                if (matched !== null) {
                    printertype.val(matched).trigger('change');
                } else {
                    showType(wanted);
                }
            }
        );
    }

    // ---------------------------------------------------------------
    // GENERAL TAB
    var originalName = $('#printer').val(),
        updateName = function(newName) {
        var e = $('#pageTitle'),
            text = e.text();
            text = text.replace(': ' + originalName, ': ' + newName);
            $('#printercopy option').each(function() {
                opttext = $(this).text().split(' - ');
                if (opttext[0] == originalName) {
                    opttext[0] = newName;
                    opttext = opttext.join(' - ');
                    $(this).text(opttext);
                }
            });
            $('#printercopy').select2();
            document.title = text;
            e.text(text);
        },
        generalForm = $('#printer-general-form'),
        generalFormBtn = $('#general-send'),
        generalDeleteBtn = $('#general-delete'),
        generalDeleteModal = $('#deleteModal'),
        generalDeleteModalConfirm = $('#confirmDeleteModal'),
        generalDeleteModalCancel = $('#closeDeleteModal');

    generalForm.on('submit',function(e) {
        e.preventDefault();
    });
    generalFormBtn.on('click', function() {
        generalFormBtn.prop('disabled', true);
        generalDeleteBtn.prop('disabled', true);
        generalForm.processForm(function(err) {
            generalFormBtn.prop('disabled', false);
            generalDeleteBtn.prop('disabled', false);
            if (err) {
                return;
            }
            updateName($('#printer').val());
            originalName = $('#printer').val();
        }, ':input:visible');
    });
    generalDeleteBtn.on('click', function() {
        generalDeleteModal.modal('show');
    });
    generalDeleteModalConfirm.on('click', function() {
        var method = 'post',
            action = '../management/index.php?node='
                + Common.node
                + '&sub=delete&id='
                + Common.id;
        $.apiCall(method, action, null, function(err) {
            if (err) {
                return;
            }
            setTimeout(function() {
                window.location = '../management/index.php?node='
                    + Common.node
                    + '&sub=list';
            }, 2000);
        });
    });

    showType(printertype.val().toLowerCase());
    printertype.on('change', function(e) {
        e.preventDefault();
        showType(printertype.val().toLowerCase());
    });
    printercopy.on('change', function() {
        copyFromExisting($(this).val());
    });

    // Associations
    // ---------------------------------------------------------------
    // HOST TAB

    // Host Associations
    var printerHostUpdateBtn = $('#printer-host-send'),
        printerHostRemoveBtn = $('#printer-host-remove'),
        printerHostDeleteConfirmBtn = $('#confirmhostDeleteModal');

    function disableHostButtons(disable) {
        printerHostUpdateBtn.prop('disabled', disable);
        printerHostRemoveBtn.prop('disabled', disable);
    }

    function onHostSelect(selected) {
        var disabled = selected.count() == 0;
        disableHostButtons(disabled);
    }

    printerHostUpdateBtn.on('click', function(e) {
        e.preventDefault();
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            rows = printerHostsTable.rows({selected: true}),
            toAdd = $.getSelectedIds(printerHostsTable),
            opts = {
                confirmadd: 1,
                additems: toAdd
            };
        $.apiCall(method,action,opts,function(err) {
            disableHostButtons(false);
            if (err) {
                return;
            }
            printerHostsTable.draw(false);
            printerHostsTable.rows({selected: true}).deselect();
        });
    });

    printerHostRemoveBtn.on('click', function(e) {
        e.preventDefault();
        $('#hostDelModal').modal('show');
    });

    var printerHostsTable = $('#printer-host-table').registerTable(onHostSelect, {
        order: [
            [0, 'asc']
        ],
        columns: [
            {data: 'mainLink'},
            {data: 'association'}
        ],
        rowId: 'id',
        columnDefs: [
            {
                render: function(data, type, row) {
                    var checkval = '';
                    if (row.association === 'associated') {
                        checkval = ' checked';
                    }
                    return '<div class="form-check">'
                        + '<input type="checkbox" class="associated" name="associate[]" id="printerHostAssoc_'
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
            url: '../management/index.php?node='
                + Common.node
                + '&sub=getHostsList&id='
                + Common.id,
            type: 'post'
        }
    });

    printerHostDeleteConfirmBtn.on('click', function(e) {
        $.deleteAssociated(printerHostsTable, printerHostUpdateBtn.attr('action'), function(err) {
            $('#hostDelModal').modal('hide');
            if (err) {
                return;
            }
            printerHostsTable.draw(false);
            printerHostsDefaultTable.draw(false);
            printerHostsTable.rows({selected: true}).deselect();
        });
    });

    printerHostsTable.on('draw', function(e) {
        Common.iCheck('#printer-host-table input');
        $('#printer-host-table input.associated').on('change', onPrinterHostCheckboxSelect);
        onHostSelect(printerHostsTable.rows({selected: true}));
    });

    var onPrinterHostCheckboxSelect = function(e) {
        $.checkItemUpdate(printerHostsTable, this, e, printerHostUpdateBtn);
    };

    // Host Default Settings
    var printerHostDefaultUpdateBtn = $('#printer-host-default-send'),
        printerHostDefaultRemoveBtn = $('#printer-host-default-remove'),
        printerHostDefaultDeleteConfirmBtn = $('#confirmHostDefaultDeleteModal');

    function disableHostDefaultButtons(disable) {
        printerHostDefaultUpdateBtn.prop('disabled', disable);
        printerHostDefaultRemoveBtn.prop('disabled', disable);
    }

    function onHostDefaultSelect(selected) {
        var disabled = selected.count() == 0;
        disableHostDefaultButtons(disabled);
    }

    printerHostDefaultUpdateBtn.on('click', function(e) {
        e.preventDefault();
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            rows = printerHostsDefaultTable.rows({selected: true}),
            toAdd = $.getSelectedIds(printerHostsDefaultTable),
            opts = {
                confirmadddefault: 1,
                additems: toAdd
            };
        $.apiCall(method,action,opts,function(err) {
            disableHostButtons(false);
            if (err) {
                return;
            }
            printerHostsTable.draw(false);
            printerHostsDefaultTable.draw(false);
            printerHostsDefaultTable.rows({selected: true}).deselect();
        });
    });

    printerHostDefaultRemoveBtn.on('click', function(e) {
        e.preventDefault();
        $('#unsetHostDefaultModal').modal('show');
    });

    var printerHostsDefaultTable = $('#printer-host-default-table').registerTable(onHostDefaultSelect, {
        order: [
            [0, 'asc']
        ],
        columns: [
            {data: 'mainLink'},
            {data: 'isDefault'}
        ],
        rowId: 'id',
        columnDefs: [
            {
                render: function(data, type, row) {
                    var checkval = '';
                    if (data >= 1) {
                        checkval = ' checked';
                    }
                    return '<div class="form-check">'
                        + '<input type="checkbox" class="default" name="default[]" id="printerHostDefault_'
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
            url: '../management/index.php?node='
                + Common.node
                + '&sub=getHostsList&id='
                + Common.id,
            type: 'post'
        }
    });

    printerHostDefaultDeleteConfirmBtn.on('click', function(e) {
        var method = printerHostDefaultUpdateBtn.attr('method'),
            action = printerHostDefaultUpdateBtn.attr('action'),
            rows = printerHostsDefaultTable.rows({selected: true}),
            opts = {
                confirmdeldefault: 1,
                remitems: $.getSelectedIds(printerHostsDefaultTable)
            };
        $.apiCall(method,action,opts,function(err) {
            $('#unsetHostDefaultModal').modal('hide');
            if (err) {
                return;
            }
            printerHostsDefaultTable.draw(false);
            printerHostsDefaultTable.rows({selected: true}).deselect();
        });
    });

    printerHostsDefaultTable.on('draw', function(e) {
        Common.iCheck('#printer-host-default-table input');
        $('#printer-host-default-table input.default').on('change', onPrinterHostDefaultCheckboxSelect);
        onHostDefaultSelect(printerHostsDefaultTable.rows({selected: true}));
    });

    var onPrinterHostDefaultCheckboxSelect = function(e) {
        var method = printerHostDefaultUpdateBtn.attr('method'),
            action = printerHostDefaultUpdateBtn.attr('action'),
            opts = {};
        if (this.checked) {
            opts = {
                confirmadddefault: 1,
                additems: [e.target.value]
            };
        } else {
            opts = {
                confirmdeldefault: 1,
                remitems: [e.target.value]
            };
        }
        $.apiCall(method,action,opts,function(err) {
            if (err) {
                return;
            }
            printerHostsTable.draw(false);
            printerHostsDefaultTable.draw(false);
            printerHostsDefaultTable.rows({selected: true}).deselect();
        });
    };

    if (Common.search && Common.search.length > 0) {
        printerHostsTable.search(Common.search).draw();
        printerHostsDefaultTable.search(Common.search).draw();
    }
})(jQuery);
