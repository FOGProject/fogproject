(function($) {
    var mintBtn = $('#mint'),
        mintModal = $('#mintModal'),
        confirmMint = $('#confirmMintModal'),
        showTokenModal = $('#showTokenModal'),
        mintedToken = $('#mintedToken'),
        copyToken = $('#copyMintedToken'),
        revokeBtn = $('#revoke'),
        revokeModal = $('#revokeModal'),
        confirmRevoke = $('#confirmRevokeModal'),
        unlimited = $('#tokenUnlimited'),
        uses = $('#tokenUses'),
        tokenForm = $('#agent-token-form'),
        method = tokenForm.attr('method'),
        base = '../management/index.php?node=' + Common.node + '&sub=';

    function esc (s) {
        return $('<div>').text(s == null ? '' : String(s)).html();
    }
    function onSelect (selected) {
        revokeBtn.prop('disabled', selected.count() == 0);
    }

    revokeBtn.prop('disabled', true);
    // Client-side table over the whitelisted payload (see
    // HostManagement::getAgentTokenList); the hash never leaves the server.
    var table = $('#dataTable').registerTable(onSelect, {
        order: [
            [5, 'desc']
        ],
        columns: [
            {data: 'name'},
            {data: 'state'},
            {data: 'uses'},
            {data: 'expires'},
            {data: 'createdBy'},
            {data: 'created'}
        ],
        columnDefs: [
            {
                render: function (data, type) {
                    if (type !== 'display') {
                        return data;
                    }
                    var cls = data === 'active' ? 'success' : 'secondary';
                    return '<span class="badge text-bg-' + cls + '">' + esc(data) + '</span>';
                },
                targets: 1
            },
            {
                render: function (data, type) {
                    if (type !== 'display') {
                        return data;
                    }
                    return data < 0 ? esc('unlimited') : esc(data);
                },
                targets: 2
            }
        ],
        rowId: 'id',
        processing: true,
        serverSide: false,
        ajax: {
            url: base + 'getAgentTokenList',
            type: 'post'
        }
    });

    unlimited.on('change', function() {
        uses.prop('disabled', unlimited.prop('checked'));
    });

    mintBtn.on('click', function() {
        // Each token is a fresh credential: start the form clean rather than
        // carrying the previous token's name into the next mint.
        $('#tokenName').val('');
        mintModal.modal('show');
    });
    confirmMint.on('click', function() {
        confirmMint.prop('disabled', true);
        var opts = {
            tokenName: $('#tokenName').val(),
            tokenUses: uses.val(),
            tokenExpires: $('#tokenExpires').val()
        };
        if (unlimited.prop('checked')) {
            opts.tokenUnlimited = 1;
        }
        $.apiCall(method, base + 'createAgentToken', opts, function(err, data) {
            confirmMint.prop('disabled', false);
            if (err) {
                return;
            }
            mintModal.modal('hide');
            // The only time the token exists on screen.
            mintedToken.val(data.token);
            showTokenModal.modal('show');
            table.ajax.reload(null, false);
        });
    });
    copyToken.on('click', function() {
        mintedToken.trigger('select');
        if (navigator.clipboard) {
            navigator.clipboard.writeText(mintedToken.val());
        } else {
            document.execCommand('copy');
        }
    });
    showTokenModal.on('hidden.bs.modal', function() {
        mintedToken.val('');
    });

    revokeBtn.on('click', function() {
        revokeModal.modal('show');
    });
    confirmRevoke.on('click', function() {
        revokeBtn.prop('disabled', true);
        $.apiCall(method, base + 'deleteAgentTokens', {tokens: $.getSelectedIds(table)}, function(err) {
            revokeModal.modal('hide');
            table.ajax.reload(null, false);
        });
    });
})(jQuery);
