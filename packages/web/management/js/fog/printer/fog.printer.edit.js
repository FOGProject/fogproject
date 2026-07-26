(function($) {
    // ---------------------------------------------------------------
    // GENERAL TAB
    $.registerGeneralTab({
        nameInputSel: '#printer',
        formSel: '#printer-general-form',
        processTarget: ':input:visible',
        onRenameSuccess: function(newName, oldName) {
            $('#printercopy option').each(function() {
                var opttext = $(this).text().split(' - ');
                if (opttext[0] == oldName) {
                    opttext[0] = newName;
                    opttext = opttext.join(' - ');
                    $(this).text(opttext);
                }
            });
            $('#printercopy').select2();
        }
    });

    // Type-section + copy-from-existing wiring, shared with the printer add and
    // list pages via $.fn.initPrinterFormUI (fog.common.js). Scoped to the
    // general form so it cannot reach the association tables below.
    $('#printer-general-form').initPrinterFormUI();

    // Associations
    // ---------------------------------------------------------------
    // HOST TAB

    // Host Associations
    var printerHostsTable = $.registerAssociationTab({
        slug: 'printer-host',
        item: 'host',
        sub: 'getHostsList',
        afterCommit: function() {
            // Keep the "default settings" table in sync whenever a host
            // association is added, removed, or toggled.
            printerHostsDefaultTable.draw(false);
        }
    });

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
            toAdd = $.getSelectedIds(printerHostsDefaultTable),
            opts = {
                confirmadddefault: 1,
                additems: toAdd
            };
        $.apiCall(method,action,opts,function(err) {
            disableHostDefaultButtons(false);
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
            [1, 'desc'],
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
                + '&sub=getHostsDefaultList&id='
                + Common.id,
            type: 'post'
        }
    });

    printerHostDefaultDeleteConfirmBtn.on('click', function(e) {
        var method = printerHostDefaultUpdateBtn.attr('method'),
            action = printerHostDefaultUpdateBtn.attr('action'),
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
