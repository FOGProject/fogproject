(function($) {
    // ---------------------------------------------------------------
    // GENERAL TAB
    var opts = {};

    // Should we delete the image files too?
    $('#andFile').on('change', function(e) {
        e.preventDefault();
        if (!this.checked) {
            return;
        }
        opts = {andFile: 1};
    });

    $.registerGeneralTab({
        nameInputSel: '#image',
        formSel: '#image-general-form',
        deleteOpts: function() {
            $('#andFile').trigger('change');
            return opts;
        }
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
    var imageHostsTable = $.registerAssociationTab({
        slug: 'image-host',
        item: 'host',
        sub: 'getHostsList'
    });

    // ---------------------------------------------------------------
    // STORAGEGROUP TAB
    var imageStoragegroupsTable = $.registerAssociationTab({
        slug: 'image-storagegroup',
        item: 'storagegroup',
        sub: 'getStoragegroupsList',
        afterCommit: function() {
            setTimeout(imageStoragegroupPrimarySelectorUpdate, 1000);
        }
    });

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
