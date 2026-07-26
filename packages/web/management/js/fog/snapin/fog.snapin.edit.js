(function($) {
    // ---------------------------------------------------------------
    // GENERAL TAB
    var opts = {};

    // Shall we delete the snapin file as well?
    $('#andFile').on('change', function(e) {
        e.preventDefault();
        if (!this.checked) {
            return;
        }
        opts = {andFile: 1};
    });

    $.registerGeneralTab({
        nameInputSel: '#snapin',
        formSel: '#snapin-general-form',
        deleteOpts: function() {
            $('#andFile').trigger('change');
            return opts;
        }
    });

    // Shared command-builder UI (fog.common.js), scoped to the general form so
    // it cannot reach the association tables below. Edit form has .packhide
    // elements and wires packTypes.
    $('#snapin-general-form')
        .initSnapinCommandUI({packHide: true, wirePackTypes: true});
    // ASSOCIATIONS
    // ---------------------------------------------------------------
    // HOST TAB
    var snapinHostsTable = $.registerAssociationTab({
        slug: 'snapin-host',
        item: 'host',
        sub: 'getHostsList',
        onDraw: function() {
            // Preserved: the host table's draw historically also refreshed the
            // storagegroup primary selector (cross-tab). Kept behavior-neutral.
            snapinStoragegroupPrimarySelectorUpdate();
        }
    });

    // ---------------------------------------------------------------
    // STORAGEGROUP TAB
    //
    // Association area
    var snapinStoragegroupsTable = $.registerAssociationTab({
        slug: 'snapin-storagegroup',
        item: 'storagegroup',
        sub: 'getStoragegroupsList',
        afterCommit: function() {
            setTimeout(snapinStoragegroupPrimarySelectorUpdate, 1000);
        }
    });

    // Primary area
    var snapinStoragegroupPrimaryUpdateBtn = $('#snapin-storagegroup-primary-send'),
        snapinStoragegroupPrimarySelector = $('#storagegroupselector'),
        snapinStoragegroupPrimarySelectorUpdate = function() {
            var url = '../management/index.php?node='
                + Common.node
                + '&sub=getSnapinPrimaryStoragegroups&id='
                + Common.id;
            Pace.ignore(function() {
                snapinStoragegroupPrimarySelector.html('');
                $.get(url, function(data) {
                    snapinStoragegroupPrimarySelector.html(data.content);
                    snapinStoragegroupPrimaryUpdateBtn.prop('disabled', data.disablebtn);
                }, 'json');
            });
        };

    function disableStoragegroupPrimaryButtons(disable) {
        snapinStoragegroupPrimaryUpdateBtn.prop('disabled', disable);
    }

    snapinStoragegroupPrimarySelectorUpdate();

    snapinStoragegroupPrimaryUpdateBtn.on('click', function(e) {
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
        snapinStoragegroupsTable.search(Common.search).draw();
        snapinHostsTable.search(Common.search).draw();
    }
})(jQuery);
