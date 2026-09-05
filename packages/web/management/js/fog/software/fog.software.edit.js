(function($) {
    // ---------------------------------------------------------------
    // GENERAL TAB
    $.registerGeneralTab({
        nameInputSel: '#software',
        formSel: '#software-general-form'
    });

    // Version policy select shows the pinned-version input only when
    // "Pinned" is chosen; see fog.software.add.js for the identical wiring
    // on the create form.
    var generalForm = $('#software-general-form');

    function toggleVersionPolicy() {
        var pinned = generalForm.find('[name="versionPolicy"]').val() === 'pinned';
        generalForm.find('.softwareversion-pinned').toggleClass('d-none', !pinned);
    }

    toggleVersionPolicy();
    generalForm.on('change', '[name="versionPolicy"]', toggleVersionPolicy);

    // ASSOCIATIONS
    // ---------------------------------------------------------------
    // HOST TAB
    var softwareHostsTable = $.registerAssociationTab({
        slug: 'software-host',
        item: 'host',
        sub: 'getHostsList'
    });

    // ---------------------------------------------------------------
    // STATUS TAB (read only)
    var softwareStatusTable = $('#software-status-table').registerTable(null, {
        columns: [
            {data: 'hostLink'},
            $.escapedColumn('installedVersion'),
            $.escapedColumn('status'),
            $.escapedColumn('return'),
            $.escapedColumn('checked'),
            {
                data: 'details',
                render: function(d, t) {
                    var full = d === null ? '' : String(d),
                        clipped = full.length > 200
                            ? full.slice(0, 200) + '…'
                            : full;
                    if (t !== 'display') {
                        return full;
                    }
                    return '<span title="' + $.escapeHtml(full) + '">'
                        + $.escapeHtml(clipped)
                        + '</span>';
                }
            }
        ],
        order: [
            [4, 'desc']
        ],
        rowId: 'id',
        processing: true,
        serverSide: true,
        select: false,
        ajax: {
            url: '../management/index.php?node='
                + Common.node
                + '&sub=getStatusList&id='
                + Common.id,
            type: 'post'
        }
    });

    if (Common.search && Common.search.length > 0) {
        softwareHostsTable.search(Common.search).draw();
    }
})(jQuery);
