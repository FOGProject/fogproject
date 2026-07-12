$(function() {
    // Any special functions that can be commonized for this element.
    var onCheckboxSelect = function(event) {
    };
    // ---------------------------------------------------------------
    // GENERAL TAB
    $.registerGeneralTab({
        nameInputSel: '#windowskey',
        formSel: '#windowskey-general-form'
    });
    // ---------------------------------------------------------------
    // IMAGE TAB
    var imageAddBtn = $('#image-add'),
        imageRemoveBtn = $('#image-remove');

    disableImageButtons = function(disable) {
        imageAddBtn.prop('disabled', disable);
        imageRemoveBtn.prop('disabled', disable);
    };
    disableImageButtons(true);

    function onImagesSelect(selected) {
        var disabled = selected.count() == 0;
        disableImageButtons(disabled);
    }

    var imagesTable = $('#windowskey-image-table').registerTable(onImagesSelect, {
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
                    return '<div class="form-check">'
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

    imageAddBtn.on('click', function() {
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            rows = imagesTable.rows({selected: true}),
            toAdd = $.getSelectedIds(imagesTable),
            opts = {
                updateimages: 1,
                image: toAdd
            };
        $.apiCall(method, action, opts, function(err) {
            disableImageButtons(false);
            if (err) {
                return;
            }
            imagesTable.draw(false);
            imagesTable.rows({selected: true}).deselect();
        });
    });

    imageRemoveBtn.on('click', function(e) {
        e.preventDefault();
        $('#imageDelModal').modal('show');
    });
    $('#confirmimageDeleteModal').on('click', function(e) {
        $.deleteAssociated(imagesTable, imageRemoveBtn.attr('action'), function(err) {
            disableImageButtons(false);
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
