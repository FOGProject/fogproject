(function($) {
    // ---------------------------------------------------------------
    // GENERAL TAB
    var originalName = $('#image').val(),
        updateName = function(newName) {
            var e = $('#pageTitle'),
                text = e.text();
            text = text.replace(': ' + originalName, ': ' + newName);
            document.title = text;
            e.text(text);
        },
        generalForm = $('#image-general-form'),
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
            if (err)
                return;
            updateName($('#image').val());
            originalName = $('#image').val();
        });
    });
    generalDeleteBtn.on('click', function() {
        generalDeleteModal.modal('show');
    });
    // Should we delete the image files too?
    $('#andFile').on('ifChanged', function(e) {
        e.preventDefault();
        $(this).iCheck('update');
        if (!this.checked) {
            opts = {};
            return;
        }
        opts = {andFile: 1};
    });
    generalDeleteModalConfirm.on('click', function() {
        var method = 'post',
            action = '../management/index.php?node='
                + Common.node
                + '&sub=delete&id='
                + Common.id,
            opts = {};
        $('#andFile').trigger('change');
        $.apiCall(method, action, opts, function(err) {
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

    $('.imagepath-input').on('keyup change blur focus focusout', function(e) {
        var start = this.selectionStart,
            end = this.selectionEnd;
        this.value = this.value.replace(/[^\w+\/\.\-]/g,'');
        this.setSelectionRange(start,end);
        e.preventDefault();
    });
    if ($('.imagepath-input').val().length <= 0) {
        $('.imagename-input').on('keyup change blur focus focusout', function(e) {
            $('.imagepath-input').val(this.value).trigger('change');
        });
    }

    $('.slider').slider();

    // ASSOCIATIONS
    // ---------------------------------------------------------------
    // HOST TAB
    var imageHostUpdateBtn = $('#image-host-send'),
        imageHostRemoveBtn = $('#image-host-remove'),
        imageHostDeleteConfirmBtn = $('#confirmhostDeleteModal');

    function disableHostButtons(disable) {
        imageHostUpdateBtn.prop('disabled', disable);
        imageHostRemoveBtn.prop('disabled', disable);
    }

    function onHostSelect(selected) {
        var disabled = selected.count() == 0;
        disableHostButtons(disabled);
    }

    imageHostUpdateBtn.on('click', function(e) {
        e.preventDefault();
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            rows = imageHostsTable.rows({selected: true}),
            toAdd = $.getSelectedIds(imageHostsTable),
            opts = {
                confirmadd: 1,
                additems: toAdd
            };
        $.apiCall(method,action,opts,function(err) {
            disableHostButtons(false);
            if (err) {
                return;
            }
            imageHostsTable.draw(false);
            imageHostsTable.rows({selected: true}).deselect();
        });
    });

    imageHostRemoveBtn.on('click', function(e) {
        e.preventDefault();
        $('#hostDelModal').modal('show');
    });

    var imageHostsTable = $('#image-host-table').registerTable(onHostSelect, {
        order: [
            [1, 'asc'],
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
                    return '<div class="checkbox">'
                        + '<input type="checkbox" class="associated" name="associate[]" id="imageHostAssoc_'
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

    imageHostDeleteConfirmBtn.on('click', function(e) {
        $.deleteAssociated(imageHostsTable, imageHostUpdateBtn.attr('action'), function(err) {
            $('#hostDelModal').modal('hide');
            if (err) {
                return;
            }
            imageHostsTable.draw(false);
            imageHostsTable.rows({selected: true}).deselect();
        });
    });

    imageHostsTable.on('draw', function(e) {
        Common.iCheck('#image-host-table input');
        $('#image-host-table input.associated').on('ifChanged', onImageHostCheckboxSelect);
        onHostSelect(imageHostsTable.rows({selected: true}));
    });

    var onImageHostCheckboxSelect = function(e) {
        $.checkItemUpdate(imageHostsTable, this, e, imageHostUpdateBtn);
    };

    // ---------------------------------------------------------------
    // STORAGEGROUP TAB
    var imageStoragegroupUpdateBtn = $('#image-storagegroup-send'),
        imageStoragegroupRemoveBtn = $('#image-storagegroup-remove'),
        imageStoragegroupDeleteConfirmBtn = $('#confirmstoragegroupDeleteModal');

    function disableStoragegroupButtons(disable) {
        imageStoragegroupUpdateBtn.prop('disabled', disable);
        imageStoragegroupRemoveBtn.prop('disabled', disable);
    }

    function onStoragegroupSelect(selected) {
        var disabled = selected.count() == 0;
        disableStoragegroupButtons(disabled);
    }

    imageStoragegroupUpdateBtn.on('click', function(e) {
        e.preventDefault();
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            rows = imageStoragegroupsTable.rows({selected: true}),
            toAdd = $.getSelectedIds(imageStoragegroupsTable),
            opts = {
                confirmadd: 1,
                additems: toAdd
            };
        $.apiCall(method,action,opts,function(err) {
            disableStoragegroupButtons(false);
            if (err) {
                return;
            }
            imageStoragegroupsTable.draw(false);
            imageStoragegroupsTable.rows({selected: true}).deselect();
            setTimeout(imageStoragegroupPrimarySelectorUpdate, 1000);
        });
    });

    imageStoragegroupRemoveBtn.on('click', function(e) {
        e.preventDefault();
        $('#storagegroupDelModal').modal('show');
    });

    var imageStoragegroupsTable = $('#image-storagegroup-table').registerTable(onStoragegroupSelect, {
        order: [
            [1, 'asc'],
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
                    return '<div class="checkbox">'
                        + '<input type="checkbox" class="associated" name="associate[]" id="imageStoragegroupAssoc_'
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
                + '&sub=getStoragegroupsList&id='
                + Common.id,
            type: 'post'
        }
    });

    imageStoragegroupDeleteConfirmBtn.on('click', function(e) {
        $.deleteAssociated(imageStoragegroupsTable, imageStoragegroupUpdateBtn.attr('action'), function(err) {
            $('#storagegroupDelModal').modal('hide');
            if (err) {
                return;
            }
            imageStoragegroupsTable.draw(false);
            imageStoragegroupsTable.rows({selected: true}).deselect();
            setTimeout(imageStoragegroupPrimarySelectorUpdate, 1000);
        });
    });

    imageStoragegroupsTable.on('draw', function() {
        Common.iCheck('#image-storagegroup-table input');
        $('#image-storagegroup-table input.associated').on('ifChanged', onImageStoragegroupCheckboxSelect);
        onStoragegroupSelect(imageStoragegroupsTable.rows({selected: true}));
    });

    var onImageStoragegroupCheckboxSelect = function(e) {
        $.checkItemUpdate(imageStoragegroupsTable, this, e, imageStoragegroupUpdateBtn);
        setTimeout(imageStoragegroupPrimarySelectorUpdate, 1000);
    };

    // Primary area
    var imageStoragegroupPrimaryUpdateBtn = $('#image-storagegroup-primary-send'),
        imageStoragegroupPrimarySelector = $('#storagegroupselector'),
        imageStoragegroupPrimarySelectorUpdate = function() {
            var url = '../management/index.php?node='
                + Common.node
                + '&sub=getImagePrimaryStoragegroups&id='
                + Common.id;
            Pace.ignore(function() {
                imageStoragegroupPrimarySelector.html('');
                $.get(url, function(data) {
                    imageStoragegroupPrimarySelector.html(data.content);
                    imageStoragegroupPrimaryUpdateBtn.prop('disabled', data.disablebtn);
                }, 'json');
            });
        };

    function disableStoragegroupPrimaryButtons(disable) {
        imageStoragegroupPrimaryUpdateBtn.prop('disabled', disable);
    }

    imageStoragegroupPrimarySelectorUpdate();

    imageStoragegroupPrimaryUpdateBtn.on('click', function(e) {
        e.preventDefault();
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            opts = {
                confirmprimary: 1,
                primary: $('#storagegroup option:selected').val()
            };
        $.apiCall(method,action,opts,function(err) {
            disableStoragegroupPrimaryButtons(false);
            if (err) {
                return;
            }
        });
    });

    if (Common.search && Common.search.length > 0) {
        imageStoragegroupsTable.search(Common.search).draw();
        imageHostsTable.search(Common.search).draw();
    }
})(jQuery);
