/**
 * Named, saved grid filters.
 *
 * A filter someone SAVED and then PICKED is a different thing from a filter
 * silently restored on their behalf. The layout state deliberately drops
 * searches on the way out (fogStripStateSearch in fog.common.js) because a
 * restored filter looks exactly like missing data -- you open Hosts, see 4 of
 * 4000, and nothing on screen says why. Everything here is built around
 * keeping that distinction true:
 *
 *   - NOTHING is ever applied on page load. The list is fetched when the user
 *     opens the picker, and applied only when they click Apply.
 *   - while a filter is applied there is a CHIP next to the toolbar naming
 *     it, with an x that clears it in one click. Not a dropdown that quietly
 *     stays selected.
 *
 * Sharing: a filter is private until its owner shares it. It can go to named
 * users, to user groups, to roles, or -- for somebody holding
 * savedfilter.create -- to everyone. Sharing needs no permission because it
 * can only add one named entry to the picker of somebody the sharer could
 * already name; "everyone" is the one case that is an administrative act.
 */
(function () {
    'use strict';

    var MODAL_ID = 'fogFilterModal';

    function api(path) {
        return (window.fogApiBase ? fogApiBase() : '/fog/') + path;
    }

    /**
     * The grid this table's filters belong to.
     *
     * Deliberately the same key the saved LAYOUT uses: it already answers
     * "which grid is this", including the part that is not the DOM id -- FOG
     * builds most grids as #dataTable, so the host list and the image list
     * would otherwise share one set of filters.
     */
    function tableKey(dt) {
        return window.fogStateKey ? fogStateKey(dt.settings()[0]) : '';
    }

    /** Text node, never innerHTML: a filter name is somebody else's input. */
    function el(tag, className, text) {
        var node = document.createElement(tag);
        if (className) {
            node.className = className;
        }
        if (text !== undefined && text !== null) {
            node.textContent = text;
        }
        return node;
    }

    // ---------------------------------------------------------------- chip

    /**
     * The "a filter is on" indicator.
     *
     * Lives in the table's own container so a page with two grids gets two
     * chips, and is REMOVED rather than hidden when nothing is applied --
     * an empty chip element sitting in the layout is one CSS mistake away
     * from being invisible while claiming to be the visible indicator.
     */
    function chipHost(dt) {
        var container = dt.table().container();
        var host = container.querySelector('.fog-filter-chip-host');
        if (!host) {
            host = el('div', 'fog-filter-chip-host mb-2');
            container.insertBefore(host, container.firstChild);
        }
        return host;
    }

    function clearChip(dt) {
        var host = dt.table().container()
            .querySelector('.fog-filter-chip-host');
        if (host) {
            host.innerHTML = '';
        }
    }

    function showChip(dt, name) {
        var host = chipHost(dt);
        var chip = el('span', 'badge bg-primary d-inline-flex align-items-center gap-2');
        var close = el('button', 'btn-close btn-close-white');
        host.innerHTML = '';
        chip.appendChild(el('i', 'fas fa-filter'));
        chip.appendChild(el('span', null, name));
        close.setAttribute('type', 'button');
        close.setAttribute('aria-label', 'Clear filter');
        close.setAttribute('title', 'Clear this filter');
        close.addEventListener('click', function () {
            clearFilter(dt);
        });
        chip.appendChild(close);
        host.appendChild(chip);
    }

    // ------------------------------------------------------------- applying

    function applyFilter(dt, filter) {
        var details;
        try {
            details = JSON.parse(filter.value || '{}');
        } catch (e) {
            $.notify('Filter', 'That saved filter could not be read.', 'error');
            return;
        }
        dt.searchBuilder.rebuild(details);
        showChip(dt, filter.name);
    }

    function clearFilter(dt) {
        // rebuild({}) is SearchBuilder's own reset. Clearing the chip first
        // would leave the rows filtered with nothing on screen to say so if
        // the rebuild threw.
        dt.searchBuilder.rebuild({});
        clearChip(dt);
    }

    // -------------------------------------------------------------- fetching

    function loadFilters(dt, done) {
        $.ajax({
            url: api('system/savedfilters'),
            data: { table: tableKey(dt) },
            global: false,
            success: function (res) {
                done(null, res || {});
            },
            error: function (xhr) {
                done(xhr, {});
            }
        });
    }

    function loadTargets(done) {
        $.ajax({
            url: api('system/savedfiltertargets'),
            global: false,
            success: function (res) { done(res || {}); },
            error: function () { done({}); }
        });
    }

    // ---------------------------------------------------------------- modal

    function modal() {
        var node = document.getElementById(MODAL_ID);
        if (node) {
            return node;
        }
        node = el('div', 'modal fade');
        node.id = MODAL_ID;
        node.setAttribute('tabindex', '-1');
        node.innerHTML =
            '<div class="modal-dialog modal-lg">'
            + '<div class="modal-content">'
            + '<div class="modal-header">'
            + '<h5 class="modal-title">Saved filters</h5>'
            + '<button type="button" class="btn-close" data-bs-dismiss="modal"'
            + ' aria-label="Close"></button>'
            + '</div>'
            + '<div class="modal-body"><div class="fog-filter-body"></div></div>'
            + '<div class="modal-footer">'
            + '<button type="button" class="btn btn-outline-secondary float-start"'
            + ' data-bs-dismiss="modal">Close</button>'
            + '</div>'
            + '</div></div>';
        document.body.appendChild(node);
        return node;
    }

    /** A checkbox list of share targets, or nothing when the caller sees none. */
    function targetList(legend, items, selected) {
        var wrap;
        var box;
        if (!items || !items.length) {
            return null;
        }
        wrap = el('div', 'col-md-4');
        wrap.appendChild(el('div', 'fw-semibold small mb-1', legend));
        box = el('div', 'border rounded p-2 overflow-auto');
        box.style.maxHeight = '10rem';
        items.forEach(function (item) {
            var row = el('div', 'form-check');
            var input = el('input', 'form-check-input');
            var label = el('label', 'form-check-label', item.name);
            var id = 'fogshare-' + legend.toLowerCase() + '-' + item.id;
            input.type = 'checkbox';
            input.value = String(item.id);
            input.id = id;
            input.checked = (selected || []).indexOf(item.id) !== -1;
            label.setAttribute('for', id);
            row.appendChild(input);
            row.appendChild(label);
            box.appendChild(row);
        });
        wrap.appendChild(box);
        return wrap;
    }

    function checkedIds(scope, legend) {
        return Array.prototype.map.call(
            scope.querySelectorAll(
                '[id^="fogshare-' + legend.toLowerCase() + '-"]:checked'
            ),
            function (input) { return parseInt(input.value, 10); }
        );
    }

    /**
     * Why this filter is visible to the caller, in one badge.
     *
     * The wording names the ROUTE and not the target -- "via a group you are
     * in" rather than the group's name -- because the group and role lists
     * are permission-gated (a user may share to a group they cannot
     * enumerate), and naming them here would hand back exactly what that gate
     * withholds.
     */
    function sourceBadge(filter) {
        var by = filter.sharedBy ? ' by ' + filter.sharedBy : '';
        switch (filter.source) {
        case 'user':
            return el('span', 'badge bg-info ms-2', 'Shared with you' + by);
        case 'group':
            return el(
                'span', 'badge bg-info ms-2',
                'Shared with a group you are in' + by
            );
        case 'role':
            return el(
                'span', 'badge bg-info ms-2',
                'Shared with a role you hold' + by
            );
        default:
            return el('span', 'badge bg-secondary ms-2', 'Everyone');
        }
    }

    /**
     * Draws the picker.
     *
     * Rebuilt from the server's answer every time it opens rather than kept
     * in sync: it is opened by hand, rarely, and a stale share list is the
     * one thing here that would silently mislead.
     */
    function render(dt, body, data, targets) {
        var filters = data.filters || [];
        var current;

        body.innerHTML = '';

        if (!filters.length) {
            body.appendChild(
                el('p', 'text-body-secondary',
                    'No saved filters for this grid yet. Build a filter with'
                    + ' the Filter button, then save it here.')
            );
        } else {
            filters.forEach(function (filter) {
                var row = el('div', 'd-flex align-items-center gap-2 border-bottom py-2');
                var name = el('div', 'flex-grow-1');
                var apply = el('button', 'btn btn-sm btn-primary', 'Apply');
                name.appendChild(el('span', 'fw-semibold', filter.name));
                // One badge, never several: the server has already picked
                // the most specific reason this row is visible (see
                // SavedFilterManager::listFor), so a filter that arrives by
                // three routes at once still reads as one explanation.
                if (filter.source && filter.source !== 'mine') {
                    name.appendChild(sourceBadge(filter));
                }
                apply.type = 'button';
                apply.addEventListener('click', function () {
                    applyFilter(dt, filter);
                    bootstrap.Modal.getInstance(modal()).hide();
                });
                row.appendChild(name);

                if (filter.mine || (filter.global && data.mayShareGlobally)) {
                    row.appendChild(shareButton(dt, filter, targets));
                    row.appendChild(renameButton(dt, filter));
                    row.appendChild(deleteButton(dt, filter));
                }
                row.appendChild(apply);
                body.appendChild(row);
            });
        }

        // ---- save the filter that is on the table right now ----
        current = dt.searchBuilder.getDetails();
        body.appendChild(el('h6', 'mt-4', 'Save the current filter'));
        if (!current || !current.criteria || !current.criteria.length) {
            body.appendChild(
                el('p', 'text-body-secondary small',
                    'Nothing is filtered right now. Build a filter with the'
                    + ' Filter button and come back.')
            );
            return;
        }
        body.appendChild(saveForm(dt, data, targets, current));
    }

    function saveForm(dt, data, targets, current) {
        var form = el('div');
        var nameRow = el('div', 'input-group mb-3');
        var input = el('input', 'form-control');
        var save = el('button', 'btn btn-primary', 'Save');
        var shares = el('div', 'row g-3');
        var globalRow;

        input.type = 'text';
        input.maxLength = 64;
        input.placeholder = 'Name this filter';
        nameRow.appendChild(input);
        nameRow.appendChild(save);
        form.appendChild(nameRow);

        form.appendChild(
            el('div', 'fw-semibold small mb-1', 'Share it with (optional)')
        );
        [
            ['Users', targets.users],
            ['Groups', targets.groups],
            ['Roles', targets.roles]
        ].forEach(function (pair) {
            var list = targetList(pair[0], pair[1], []);
            if (list) {
                shares.appendChild(list);
            }
        });
        if (!shares.children.length) {
            shares.appendChild(
                el('div', 'text-body-secondary small',
                    'You do not have permission to list users, groups or'
                    + ' roles, so this filter can only be private.')
            );
        }
        form.appendChild(shares);

        if (data.mayShareGlobally) {
            globalRow = el('div', 'form-check mt-3');
            globalRow.innerHTML =
                '<input class="form-check-input" type="checkbox"'
                + ' id="fogFilterGlobal">'
                + '<label class="form-check-label" for="fogFilterGlobal">'
                + 'Share with everyone on this server</label>';
            form.appendChild(globalRow);
        }

        save.type = 'button';
        save.addEventListener('click', function () {
            var name = (input.value || '').trim();
            var globalBox = document.getElementById('fogFilterGlobal');
            if (!name) {
                input.focus();
                return;
            }
            save.disabled = true;
            $.ajax({
                url: api('system/savedfilters'),
                type: 'POST',
                contentType: 'application/json',
                global: false,
                data: JSON.stringify({
                    table: tableKey(dt),
                    name: name,
                    value: JSON.stringify(current),
                    global: !!(globalBox && globalBox.checked)
                }),
                success: function (res) {
                    var saved = (res.filters || []).filter(function (f) {
                        return f.name === name;
                    })[0];
                    var picked = {
                        users: checkedIds(form, 'Users'),
                        groups: checkedIds(form, 'Groups'),
                        roles: checkedIds(form, 'Roles')
                    };
                    // The share list is a second call on purpose: the save
                    // endpoint upserts by name, so it has no id to give back
                    // until the row exists. Nothing is lost if this one fails
                    // -- the filter is already saved, just not yet shared.
                    if (saved && (picked.users.length || picked.groups.length
                        || picked.roles.length)) {
                        $.ajax({
                            url: api('system/savedfilter/' + saved.id),
                            type: 'PUT',
                            contentType: 'application/json',
                            global: false,
                            data: JSON.stringify({ shares: picked })
                        });
                    }
                    // Applied immediately: the filter is already ON the table,
                    // so this only names it -- and the chip is what tells the
                    // user the save worked.
                    if (saved) {
                        showChip(dt, saved.name);
                    }
                    bootstrap.Modal.getInstance(modal()).hide();
                    $.notify('Filter', 'Saved.', 'success');
                },
                error: function (xhr) {
                    save.disabled = false;
                    $.notifyFromAPI(xhr.responseJSON, xhr);
                }
            });
        });

        return form;
    }

    function renameButton(dt, filter) {
        var button = el('button', 'btn btn-sm btn-outline-secondary', 'Rename');
        button.type = 'button';
        button.addEventListener('click', function () {
            var name = window.prompt('New name for this filter', filter.name);
            if (name === null) {
                return;
            }
            name = name.trim();
            if (!name || name === filter.name) {
                return;
            }
            $.ajax({
                url: api('system/savedfilter/' + filter.id),
                type: 'PUT',
                contentType: 'application/json',
                global: false,
                data: JSON.stringify({ name: name }),
                success: function () { open(dt); },
                error: function (xhr) {
                    $.notifyFromAPI(xhr.responseJSON, xhr);
                }
            });
        });
        return button;
    }

    function deleteButton(dt, filter) {
        // btn-danger and on the LEFT of the row's controls, per the house
        // rule: destroying something takes deliberate travel.
        var button = el('button', 'btn btn-sm btn-danger', 'Delete');
        button.type = 'button';
        button.addEventListener('click', function () {
            if (!window.confirm('Delete the filter "' + filter.name + '"?')) {
                return;
            }
            $.ajax({
                url: api('system/savedfilter/' + filter.id),
                type: 'DELETE',
                global: false,
                success: function () { open(dt); },
                error: function (xhr) {
                    $.notifyFromAPI(xhr.responseJSON, xhr);
                }
            });
        });
        return button;
    }

    function shareButton(dt, filter, targets) {
        var button = el('button', 'btn btn-sm btn-outline-secondary', 'Share');
        button.type = 'button';
        button.addEventListener('click', function () {
            $.ajax({
                url: api('system/savedfilter/' + filter.id),
                global: false,
                success: function (res) {
                    shareEditor(dt, filter, targets, res.shares || {
                        users: [], groups: [], roles: []
                    });
                },
                error: function (xhr) {
                    $.notifyFromAPI(xhr.responseJSON, xhr);
                }
            });
        });
        return button;
    }

    function shareEditor(dt, filter, targets, selected) {
        var body = modal().querySelector('.fog-filter-body');
        var shares = el('div', 'row g-3');
        var save = el('button', 'btn btn-primary mt-3', 'Save sharing');
        var back = el('button', 'btn btn-outline-secondary mt-3 me-2', 'Back');

        body.innerHTML = '';
        body.appendChild(el('h6', null, 'Share "' + filter.name + '"'));
        [
            ['Users', targets.users, selected.users],
            ['Groups', targets.groups, selected.groups],
            ['Roles', targets.roles, selected.roles]
        ].forEach(function (spec) {
            var list = targetList(spec[0], spec[1], spec[2]);
            if (list) {
                shares.appendChild(list);
            }
        });
        if (!shares.children.length) {
            shares.appendChild(
                el('div', 'text-body-secondary small',
                    'You do not have permission to list users, groups or roles.')
            );
        }
        body.appendChild(shares);
        back.type = 'button';
        back.addEventListener('click', function () { open(dt); });
        save.type = 'button';
        save.addEventListener('click', function () {
            save.disabled = true;
            $.ajax({
                url: api('system/savedfilter/' + filter.id),
                type: 'PUT',
                contentType: 'application/json',
                global: false,
                // Always sent, even when everything is unticked: an empty list
                // means "shared with nobody" and has to be able to say so.
                data: JSON.stringify({
                    shares: {
                        users: checkedIds(body, 'Users'),
                        groups: checkedIds(body, 'Groups'),
                        roles: checkedIds(body, 'Roles')
                    }
                }),
                success: function () {
                    $.notify('Filter', 'Sharing updated.', 'success');
                    open(dt);
                },
                error: function (xhr) {
                    save.disabled = false;
                    $.notifyFromAPI(xhr.responseJSON, xhr);
                }
            });
        });
        body.appendChild(back);
        body.appendChild(save);
    }

    function open(dt) {
        var node = modal();
        var body = node.querySelector('.fog-filter-body');
        body.innerHTML = '';
        body.appendChild(el('p', 'text-body-secondary', 'Loading...'));
        bootstrap.Modal.getOrCreateInstance(node).show();
        loadFilters(dt, function (err, data) {
            if (err) {
                body.innerHTML = '';
                body.appendChild(
                    el('p', 'text-danger',
                        'Your saved filters could not be loaded.')
                );
                return;
            }
            loadTargets(function (targets) {
                render(dt, body, data, targets);
            });
        });
    }

    window.fogSavedFilters = {
        open: open,
        clear: clearFilter,
        /**
         * Drops the chip when the filter behind it is gone.
         *
         * SearchBuilder is cleared from several places -- its own panel, the
         * Reset button, a rebuild -- and a chip left behind would name a
         * filter that is no longer applied, which is worse than no chip.
         */
        sync: function (dt) {
            var details = dt.searchBuilder.getDetails();
            if (!details || !details.criteria || !details.criteria.length) {
                clearChip(dt);
            }
        }
    };
}());
