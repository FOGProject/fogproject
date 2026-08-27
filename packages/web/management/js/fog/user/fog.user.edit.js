(function($) {
    // ----------------------------------------------------
    // GENERAL TAB
    var originalDisplayName = $('.fog-user').text();

    var updateDisplayName = function(newName) {
        var e = $('.fog-user'),
            text = e.text();
        text = text.replace(originalDisplayName, newName)
        e.text(text);
    };

    $.registerGeneralTab({
        nameInputSel: '#user',
        formSel: '#user-general-form',
        trimName: true,
        onRenameSuccess: function(newName) {
            var anchorFields = getQueryParams($('.fog-user').attr('href')),
                foguser = {
                    node: anchorFields['node'],
                    sub: anchorFields['sub'],
                    id: anchorFields['id']
                };
            if (Common.id == foguser.id) {
                var newDisplay = $('#display').val().trim();
                if (!newDisplay) {
                    newDisplay = newName;
                }
                updateDisplayName(newDisplay);
                originalDisplayName = newDisplay;
            }
        }
    });

    // ----------------------------------------------------
    // PASSWORD TAB
    var passwordForm = $('#user-changepw-form'),
        passwordFormBtn = $('#changepw-send');

    passwordForm.on('submit',function(e) {
        e.preventDefault();
    });
    passwordFormBtn.on('click', function(e) {
        passwordFormBtn.prop('disabled', true);
        passwordForm.processForm(function(err) {
            passwordFormBtn.prop('disabled', false);
            if (err) {
                return;
            }
            $('.password1-input, .password2-input').val('');
        });
    });

    // ----------------------------------------------------
    // API TAB
    var apiForm = $('#user-api-form'),
        apiFormBtn = $('#api-send');

    apiForm.on('submit',function(e) {
        e.preventDefault();
    });
    apiFormBtn.on('click', function(e) {
        apiFormBtn.prop('disabled', true);
        apiForm.processForm(function(err) {
            apiFormBtn.prop('disabled', false);
            if (err) {
                return;
            }
        });
    });

    // ----------------------------------------------------
    // BEARER API TOKEN CARD
    //
    // A DataTable, wired the same way the central pane at
    // ?node=about&sub=apitokens is, so the two surfaces behave identically:
    // select rows, act on the selection, and the grid reloads itself.
    //
    // Not registerListPage(), which hardwires its ajax url to
    // '?node=' + Common.node + '&sub=list' and its table to '#dataTable'.
    // This grid lives on a TAB of the user edit page, so it needs both its
    // own id -- registerTable() passes retrieve:true, and a second init on
    // a duplicate id silently returns the FIRST table's instance -- and its
    // own endpoints, which carry &id= so the server can scope every action
    // to the account being edited.
    var tokenTable,
        tokenBase = '../management/index.php?node=user&sub=',
        tokenId = '&id=' + Common.id,
        tokenDeleteBtn = $('#apitoken-delete-selected'),
        tokenEnableBtn = $('#apitoken-enable-selected'),
        tokenDisableBtn = $('#apitoken-disable-selected'),
        issueTokenBtn = $('#issuetoken'),
        issueModal = $('#userIssueTokenModal'),
        freshModal = $('#userFreshTokenModal'),
        confirmIssue = $('#confirmUserIssueToken');

    function tokenButtons(disable) {
        tokenDeleteBtn.prop('disabled', disable);
        tokenEnableBtn.prop('disabled', disable);
        tokenDisableBtn.prop('disabled', disable);
    }

    if ($('#user-apitoken-table').length) {
        tokenButtons(true);

        tokenTable = $('#user-apitoken-table').registerTable(function(sel) {
            tokenButtons(sel.count() === 0);
        }, {
            order: [[0, 'asc']],
            rowId: 'id',
            ajax: {url: tokenBase + 'userAPITokenList' + tokenId, type: 'post'},
            columns: [
                {data: 'name'},
                {data: 'createdTime'},
                {data: 'createdBy'},
                {data: 'lastUsed'},
                {data: 'enabled'}
            ],
            columnDefs: [
                {
                    render: function(data, type) {
                        if (type !== 'display') {
                            // Sorting and filtering get the raw 0/1; handing
                            // them the badge markup would sort every row by
                            // the '<' character.
                            return data;
                        }
                        if (data > 0) {
                            return '<span class="badge bg-success">'
                                + '<i class="fas fa-circle-check"></i></span>';
                        }
                        return '<span class="badge bg-danger">'
                            + '<i class="fas fa-circle-xmark"></i></span>';
                    },
                    targets: 4
                }
            ]
        });

        // No shown.bs.tab handler of its own: fogBindTableAutosize() in
        // fog.common.js already re-measures every Scroller table one macrotask
        // after a tab is shown, which is what every other in-tab grid here
        // relies on. This card originally passed scroller:false and adjusted
        // its own columns synchronously -- and that combination is exactly
        // wrong twice over. fogSizeScroller() returns early for a non-Scroller
        // table, so the shared handler skipped it entirely; and a synchronous
        // columns.adjust() inside shown.bs.tab measures before the revealed
        // tab's layout is final. The result was a header row sized against a
        // zero-width table: one column squeezed to a single character with its
        // title stacked vertically.
    }

    function setTokensEnabled(enabled) {
        var ids = tokenTable.rows({selected: true}).ids().toArray();
        if (ids.length < 1) {
            return;
        }
        tokenButtons(true);
        $.apiCall(
            'post',
            tokenBase + 'userAPITokenEnable' + tokenId,
            {remitems: ids, enabled: enabled ? 1 : 0},
            function(err) {
                // Reloaded either way: on success the badges are stale, and
                // on failure the selection is still live, so the buttons come
                // back through the select handler rather than being forced on.
                tokenTable.ajax.reload(null, false);
                if (err) {
                    tokenButtons(false);
                }
            }
        );
    }

    tokenEnableBtn.on('click', function() { setTokensEnabled(true); });
    tokenDisableBtn.on('click', function() { setTokensEnabled(false); });

    tokenDeleteBtn.on('click', function() {
        tokenButtons(true);
        $.deleteSelected(tokenTable, function(err) {
            // $.deleteSelected redraws from its cache; this grid is fed by
            // ajax, so pull the rows again rather than redrawing one that
            // still holds the revoked tokens.
            tokenTable.ajax.reload(null, false);
            if (err) {
                tokenButtons(false);
            }
        }, {
            node: 'user',
            url: tokenBase + 'userAPITokenDelete' + tokenId,
            // Its own modal and its own noun. The page's shared #deleteModal
            // deletes the ACCOUNT, so borrowing it read "Delete 1 users",
            // had no password field, and unbound the General tab's delete.
            modal: '#apitokenDeleteModal',
            confirmSel: '#confirmAPITokenDelete',
            noun: 'API token'
        });
    });

    issueTokenBtn.on('click', function(e) {
        e.preventDefault();
        $('#newtokenname').val('');
        issueModal.modal('show');
    });

    // Issue has its own endpoint because the plaintext comes back in this
    // response and is shown once. Routing it through a tab form would mean
    // either putting the secret in the session and reloading -- landing the
    // user back on the General tab with it unseen -- or threading it through
    // handleEditPost()'s shared fixed-shape response.
    confirmIssue.on('click', function(e) {
        e.preventDefault();
        var name = $.trim($('#newtokenname').val() || '');

        // Checked here as well as on the server, which is the refusal that
        // counts -- this form is not the only way to reach that endpoint.
        // A token name is required and unique per account: it is the only
        // thing that tells one row from another when somebody is deciding
        // which credential to revoke.
        if ('' === name) {
            $.notifyFromAPI(
                {
                    error: 'Give the token a name saying what it is for.',
                    title: 'API Token Failed'
                },
                false
            );
            return;
        }

        confirmIssue.prop('disabled', true);
        $.apiCall(
            'post',
            tokenBase + 'issueAPIToken' + tokenId,
            {newtokenname: name},
            function(err, data) {
                confirmIssue.prop('disabled', false);
                if (err || !data || !data.token) {
                    // The modal stays open on failure, holding what was
                    // typed, so a rejected duplicate name can be edited
                    // rather than retyped.
                    return;
                }
                issueModal.modal('hide');
                // Shown, not stored. Nothing here writes the token anywhere
                // that survives the page: no localStorage, no data attribute
                // a later render reuses.
                $('#apitoken-fresh-value').val(data.token);
                $('#apitoken-fresh-header').text(data.token);
                freshModal.modal('show');
            }
        );
    });

    freshModal.on('shown.bs.modal', function() {
        $('#apitoken-fresh-value').trigger('focus').trigger('select');
    });

    // The grid reloads when the token is DISMISSED, not when it is issued.
    // Reloading at issue time would redraw the table underneath a modal
    // holding a credential, and the new row is the one thing on screen the
    // administrator does not need to look at yet.
    freshModal.on('hidden.bs.modal', function() {
        $('#apitoken-fresh-value').val('');
        $('#apitoken-fresh-header').text('');
        if (tokenTable) {
            tokenTable.ajax.reload(null, false);
        }
    });

    $('.resettoken').on('click', function(e) {
        e.preventDefault();
        Pace.ignore(function() {
            $.ajax({
                url: '../status/newtoken.php',
                dataType: 'json',
                success: function(data) {
                    $('.token').val(data);
                },
                error: function(jqXHR, textStatus, errorThrown) {
                }
            });
        });
    });

    // ----------------------------------------------------
    // ROLE ASSOCIATION TAB
    var userRolesTable = $.registerAssociationTab({
        slug: 'user-role',
        item: 'role',
        sub: 'getRolesList'
    });

    // ----------------------------------------------------
    // GROUP ASSOCIATION TAB
    var userGroupsTable = $.registerAssociationTab({
        slug: 'user-group',
        item: 'usergroup',
        sub: 'getGroupsList'
    });

    // ---------------------------------------------------------------
    // SITE TAB
    // Single dropdown, so registerSelectTab rather than the grid wiring.
    // node:'site' adds the create-and-select button when the user holds
    // site.create; without it the tab is just the select and Update.
    $.registerSelectTab({
        slug: 'user-site',
        send: 'site-send',
        node: 'site'
    });
})(jQuery);
