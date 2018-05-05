$(function() {
    // Any special functions that can be commonized for this element.
    var onCheckboxSelect = function(event) {
    };
    // ---------------------------------------------------------------
    // GENERAL TAB
    var originalName = $('#windowskey').val(),
        updateName = function(newName) {
            var e = $('#pageTitle'),
                text = e.text();
            text = text.replace(': ' + originalName, ': ' + newName);
            document.title = text;
            e.text(text);
        };

    var generalForm = $('#windowskey-general-form'),
        generalFormBtn = $('#general-send'),
        generalDeleteBtn = $('#general-delete'),
        generalDeleteModal = $('#deleteModal'),
        generalDeleteModalConfirm $('#confirmDeleteModal'),
        generalDeleteModalCancel = $('#closeDeleteModal');

    generalForm.on('submit',function(e) {
        e.preventDefault();
    });
    generalFormBtn.on('click',function() {
        generalFormBtn.prop('disabled', true);
        generalDeleteBtn.prop('disabled', true);
        generalForm.processForm(function(err) {
            generalFormBtn.prop('disabled', false);
            generalDeleteBtn.prop('disabled', false);
            if (err) {
                return;
            }
            updateName($('#windowskey').val());
            originalName = $('#windowskey').val();
        });
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
    // ---------------------------------------------------------------
    // IMAGE TAB
    var imageAddBtn = $('#images-add'),
        imageRemoveBtn = $('#images-remote');

    disableImageButtons = function(disable) {
        imageAddBtn.prop('disabled', disable);
        imageRemoveBtn.prop('disabled', disable);
    };
    disableImageButtons(true);

    function onImagesSelect(selected) {
        var disabled = selected.count() == 0;
        disableImageButtons(disabled);
    }

    var imagesTable = Common.registerTable($('#windowskey-image-table'), onImagesSelect, {
        columns: [
            {data: 'name'},
            {data: 'associated'}
        ],
        rowId: 'id',
        columnDefs: [
            {
                responsivePriority: -1,
                render: function(data, type, row) {
                    return '<a href="../management/index.php?node=image&sub=edit&id='
                        + row.id
                        + '">'
                        + data
                        + '</a>';
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
                        + '<input type="checkbox" class="associated" name="associate[]" id="imageAssoc_'
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
                + '&sub=getImagesList&id='
                + Common.id,
            type: 'post'
        }
    });

    imagesTable.on('draw', function() {
        Common.iCheck('#windowskey-image-table input');
        onImagesSelect(imagesTable.rows({selected: true}));
    });

    imagesAddBtn.on('click', function() {
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            rows = imagesTable.rows({selected: true}),
            toAdd = Common.getSelectedIds(imagesTable),
            opts = {
                updateimages: 1,
                image: toAdd
            };
        $.apiCall(method, action, opts, function(err) {
            if (err) {
                return;
            }
            imagesTable.draw(false);
            hostsTable.rows({selected: true}).deselect();
        });
    });

    imagesRemoveBtn.on('click', function() {
        $('#hostDelModal').modal('show');
    });
    $('#confirmimageDeleteModal').on('click', function(e) {
        Common.deleteAssociated(imagesTable, imagesRemoveBtn.attr('action'), function(err) {
            if (err) {
                return;
            }
            $('#imageDelModal').modal('hide');
            imagesTable.draw(false);
            imagesTable.rows({selected: true}).deselect();
        });
    });

    if (Common.search && Common.search.length > 0) {
        imagesTable.search(Common.search).draw();
    }
});
