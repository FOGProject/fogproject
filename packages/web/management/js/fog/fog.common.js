var $_GET = getQueryParams(document.location.search),
    Common = {
        node: $_GET['node'],
        sub: $_GET['sub'],
        id: $_GET['id'],
        tab: $_GET['tab'],
        type: $_GET['type'],
        f: $_GET['f'],
        debug: $_GET['debug'],
        search: $_GET['search'],
        masks: {
            'mac': "##:##:##:##:##:##",
            'productKey': "*****-*****-*****-*****-*****",
            'hostname': "",
            'username': '^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^'
        }
    };

(function($) {
    var shouldReAuth,
        reAuthModal,
        deleteConfirmButton,
        deleteLang = 'Delete',
        pluginOptionsOpen = true,
        pluginOptionsAlt = $('.plugin-options-alternate');

    // Animate the plugin items.
    pluginOptionsAlt.on('click', function(event) {
        event.preventDefault();
        whenDone = function() {
            $(window).resize();
        };
        if (pluginOptionsOpen) {
            $('.plugin-options').slideUp('fast', whenDone);
            $('.plugin-options-alternate .fa')
                .removeClass('fa-minus')
                .addClass('fa-plus');
        }
        if (!pluginOptionsOpen) {
            $('.plugin-options').slideDown('fast', whenDone);
            $('.plugin-options-alternate .fa')
                .removeClass('fa-plus')
                .addClass('fa-minus');
        }
        pluginOptionsOpen = !pluginOptionsOpen;
    });

    Common.debugLog = function(obj) {
        if(Common.debug) {
            console.log(obj);
        }
    }
    Common.capitalizeFirstLetter = function(string) {
        return string.charAt(0).toUpperCase() + string.slice(1);
    }

    Common.notify = function(title, body, type) {
        new PNotify({
            title: title,
            text: body,
            type: type
        });
    };
    Common.notifyFromAPI = function(res, isError) {
        if (res === undefined) {
            res = {};
        }
        Common.notify(
            res.title || 'Bad Response',
            (isError ? res.error : res.msg) || 'Bad Response',
            (isError ? 'error' : 'success')
        );

        Common.debugLog(res);
    };
    Common.validateForm = function(form, input = ':input') {
        var scrolling = false;
        var isError = false;
        form.find(input).each(function(i, e) {
            var isValid = true;
            var invalidReason = undefined;

            // Grab the parent form-group, as we will need it to visually mark
            //   invalid fields
            var parent = $(e).closest('div[class^="form-group"]');;
            var required = $(e).prop('required');
            var val = $(e).inputmask('unmaskedvalue');
            if(required) {
                if (val.length == 0) {
                    isValid = false;
                    invalidReason = 'Field is required';
                }
            }

            if (required || val.length > 0) {
                var minLength = $(e).attr("minlength") || "-1",
                    maxLength = $(e).attr("maxlength") || "-1",
                    exactLength = $(e).attr("exactlength") || "-1";

                minLength = parseInt(minLength);
                maxLength = parseInt(maxLength) / 2;
                exactLength = parseInt(exactLength);

                if (beEqualTo) beEqualTo = "#" + beEqualTo;

                if (val.length < minLength) {
                    isValid = false;
                    if (maxLength == minLength) {
                        invalidReason = 'Field must be ' + minLength + ' characters';
                    } else {
                        invalidReason = 'Field must be between ' + minLength + ' and ' + maxLength +' characters';
                    }
                } else if (exactLength > 0) {
                    if (val.length !== exactLength) {
                        isValid = false;
                        invalidReason = 'Field is incomplete';
                    }
                }
            }

            equalCheck: if (isValid) {
                var beEqualTo = $(e).attr("beEqualTo");
                if (!beEqualTo) break equalCheck;

                if (! $("#" + beEqualTo).length) {
                    Common.debugLog("Missing target " + beEqualTo + " for " + e);
                    break equalCheck;
                }
                var target = $("#" + beEqualTo);
                if ($(e).val() !== target.val()) {
                    isValid = false;
                    invalidReason = 'Field does not match';
                }
            }

            if (parent.hasClass('has-error')) {
                var possibleHelpblock = $(e).next('span');
                if (possibleHelpblock.hasClass('help-block')) {
                    possibleHelpblock.remove();
                }
                if (isValid) {
                    parent.removeClass('has-error');
                }
            } else if (!isValid) {
                parent.addClass('has-error');
            }

            if (isValid) {
                return;
            }

            if (!scrolling) {
                scrolling = true;
                $('html, body').animate({
                    scrollTop: parent.offset().top
                }, 200);
            }

            var msgBlock = '<span class="help-block">' + invalidReason + '</span>'
            $(msgBlock).insertAfter(e)
            isError = true;
        });

        return !isError;
    };
    Common.apiCall = function(method, url, data, cb) {
        Pace.track(function(){
            $.ajax('', {
                type: method,
                url: url,
                async: true,
                data: data,
                success: function(res) {
                    Common.notifyFromAPI(res, false);
                    if (cb && typeof(cb) === 'function') {
                        cb(null, res);
                    }
                },
                error: function(res) {
                    Common.notifyFromAPI(res.responseJSON, true);
                    if (cb && typeof(cb) === 'function') {
                        cb(res,res.responseJSON);
                    }
                }
            });
        });
    };
    Common.processForm = function(form, cb, input = ':input') {
        // Serialize before disabling, so we can read inputs
        var opts = form.serialize();
        Common.setContainerDisable(form, true);
        if(!Common.validateForm(form, input)) {
            Common.setContainerDisable(form, false);
            if (cb && typeof(cb) === 'function')
                cb('invalid', null);
            return;
        }
        var method = form.attr('method'),
            action = form.attr('action');
        Common.apiCall(method,action,opts,function(err,data) {
            Common.setContainerDisable(form, false);
            if (cb && typeof(cb) === 'function')
                cb(err,data);
        });
    };
    Common.deleteSelected = function(table, cb, opts) {
        opts = opts || {};
        opts = _.defaults(opts, {
            node: Common.node,
            rows: table.rows({selected: true}),
            password: undefined
        });
        opts = _.defaults(opts, {
            ids: opts.rows.ids().toArray()
        });

        var ajaxOpts = {
            fogguipass: opts.password,
            confirmdel: 1,
            remitems: opts.ids
        };

        var numItems = ajaxOpts.remitems.length;

        // If we know in advance that the user should reauth,
        // prompt them with a modal to do so instead of wasting
        // an API call
        if (opts.password === undefined && shouldReAuth) {
            Common.reAuth(numItems, function(err, password) {
                if (err) {
                    if (cb && typeof(cb) === 'function') {
                        cb(err);
                    }
                    return;
                }
                opts.password = password;
                Common.deleteSelected(table, cb, opts);
            });
            return;
        }

        Pace.track(function(){
            $.ajax('', {
                type: 'post',
                url: '../management/index.php?node='
                + opts.node
                + '&sub=deletemulti',
                async: true,
                data: ajaxOpts,
                success: function(res) {
                    if (table !== undefined) {
                        opts.rows.remove().draw(false);
                        table.rows({selected: true}).deselect();
                    }
                    Common.finishReAuth();
                    Common.notifyFromAPI(res, false);
                    if (cb && typeof(cb) === 'function') {
                        cb(null,res);
                    }
                },
                error: function(res) {
                    if (res.status == 401) {
                        Common.notifyFromAPI(res.responseJSON, true);
                        Common.reAuth(numItems, function(err, password) {
                            if (err) {
                                if (cb && typeof(cb) === 'function') {
                                    cb(err,res.responseJSON);
                                }
                                return;
                            }
                            opts.password = password;
                            Common.deleteSelected(table, cb, opts);
                        });
                        return;
                    } else {
                        Common.finishReAuth();
                        Common.notifyFromAPI(res.responseJSON, true);
                        if (cb && typeof(cb) === 'function') {
                            cb(res,res.responseJSON);
                        }
                    }
                }
            });
        });
    };

    /**
     * Deletes associated elements without authentication requirement.
     *
     * @param table = the datatable element to get selected items from.
     * @param url   = the url to send the form through.
     * @param cb    = the callback function.
     * @param opts  = special items to send as a part of the form.
     *
     * @return void
     */
    Common.deleteAssociated = function(table, url, cb, opts) {
        opts = opts || {};
        opts = _.defaults(opts, {
            rows: table.rows({selected: true})
        });
        opts = _.defaults(opts, {
            ids: opts.rows.ids().toArray()
        });

        var ajaxOpts = {
            confirmdel: 1,
            remitems: opts.ids
        };

        Pace.track(function(){
            $.ajax('', {
                type: 'post',
                url: url,
                async: true,
                data: ajaxOpts,
                success: function(res) {
                    if (table !== undefined) {
                        table.$('.associated').each(function() {
                            if ($.inArray($(this).val(), opts.ids) != -1) {
                                $(this).iCheck('uncheck');
                            }
                        });
                        table.rows({selected: true}).deselect();
                    }
                    Common.notifyFromAPI(res, false);
                    if (cb && typeof(cb) === 'function') {
                        cb(null, res);
                    }
                },
                error: function(res) {
                    Common.notifyFromAPI(res.responseJSON, true);
                    if (cb && typeof(cb) === 'function') {
                        cb(res, res.responseJSON);
                    }
                }
            });
        });
    };

    Common.reAuth = function(count, cb) {
        deleteConfirmButton.text(deleteLang.replace('{0}', count));

        // enable all buttons / focus on the input box incase
        //   the modal is already being shown
        Common.setContainerDisable(reAuthModal, false);
        $("#deletePassword").trigger('focus');

        Common.registerModal(reAuthModal,
            // On show
            function(e) {
                $("#deletePassword").val('');
                $("#deletePassword").trigger('focus');
                Common.setContainerDisable(reAuthModal, false);
            },
            // On close
            function(e) {
                $("#deletePassword").val('');
                cb('authClose');
            }
        );
        // The auth modal is not a form, so
        //   the enter key must be manually bound
        //   to submit the password
        $("#deletePassword").off('keypress');
        $('#deletePassword').keypress(function (e) {
            if (e.which == 13) {
                Common.setContainerDisable(reAuthModal, true);
                cb(null, $("#deletePassword").val());
                return false;
            }
        });

        deleteConfirmButton.off('click');
        deleteConfirmButton.on('click', function(e) {
            Common.setContainerDisable(reAuthModal, true);
            cb(null, $("#deletePassword").val());
        });
        reAuthModal.modal('show');
    };

    Common.finishReAuth = function() {
        reAuthModal.modal('hide');
    };

    Common.registerTable = function(e, onSelect, opts) {
        opts = opts || {};
        opts = _.defaults(opts, {
            paging: true,
            lengthChange: true,
            searching: true,
            ordering: true,
            info: true,
            stateSave: false,
            autoWidth: false,
            responsive: true,
            lengthMenu: [
                [10, 25, 50, 100, -1],
                [10, 25, 50, 100, 'All']
            ],
            pageLength: $('#pageLength').val(),
            buttons: ['selectAll', 'selectNone'],
            pagingType: 'simple_numbers',
            select: { style: 'multi+shift' },
            dom: "<'row'<'col-sm-6'l><'col-sm-6'f>>B<'row'<'col-sm-12'tr>><'row'<'col-sm-5'i><'col-sm-7'p>>",
        });

        var table = e.DataTable(opts);

        if (onSelect !== undefined && typeof(onSelect) === 'function') {
            table.on('select deselect', function( e, dt, type, indexes) {
                onSelect(dt.rows({selected: true}));
            });
        }

        return table;
    };

    Common.getSelectedIds = function(table) {
        var rows = table.rows({selected: true});
        return rows.ids().toArray();
    };

    Common.setContainerDisable = function(container, disabled) {
        if(disabled !== false) {
            disabled = true;
        }
        if (container === undefined) {
            Common.debugLog("Was requested to disable an undefined container's children");
            return;
        }

        // Use native DOM query to select all the nested inputs
        // as it is faster
        var native = container[0];
        var inputs = native.querySelectorAll('input:not([type="checkbox"]), select, button, .btn, textarea');
        var ichecks = native.querySelectorAll('.checkbox');
        inputs.forEach(function(inputObj) {
            $(inputObj).prop('disabled', disabled);
        });
        ichecks.forEach(function(inputObj) {
            $(inputObj).iCheck((disabled) ? 'disable' : 'enable');
        });
    };

    Common.setLoading = function(container, loading) {
        if(loading !== false) {
            loading = true;
        }

        var loadingId = 'loadingOverlay';

        if (loading) {
            container.append(
                '<div class="overlay" id="' + loadingId  + '"><i class="fa fa-refresh fa-spin"></i></div>'
            );
        } else {
            container.children('#'+loadingId).remove();;
        }
    }

    Common.registerModal = function(e, onOpen, onClose, opts) {

        if (e._modalInit === undefined || !e._modalInit) {
            opts = opts || {};
            opts = _.defaults(opts, {
                backdrop: true,
                keyboard: true,
                focus: true,
                show: false
            });

            e.modal(opts);
            e._modalInit = true;
        }
        e.off('show.bs.modal');
        e.off('shown.bs.modal');
        e.off('hidden.bs.modal');

        if (onOpen && typeof(onOpen) === 'function')
            e.on('shown.bs.modal', onOpen);
        if (onClose && typeof(onClose) === 'function')
            e.on('hidden.bs.modal', onClose);
    }

    Common.iCheck = function(match) {
        match = match || 'input'
        $(match).iCheck({
            checkboxClass: 'icheckbox_square-blue',
            radioClass: 'iradio_square-blue',
            increaseArea: '20%' // optional
        });
    };

    Common.debugLog("=== DEBUG LOGGING ENABLED ===");
    setupIntegrations();
    $(":input").inputmask(); // Setup all input masks
    Common.iCheck(); // Setup all checkboxes
    $('.select2').select2({width: '100%'}); // Setup all select elements
    disableFormDefaults();
    setupPasswordReveal();
    setupUniversalSearch();

    // Reauth
    // =============
    // Detect if the user should be re-prompted for their password for certain tasks
    // Note, if this is false, but the server still rejects a request and requires re-auth,
    // then the password prompt modal will still be shown.
    shouldReAuth = ($("#reAuthDelete").val() == '1') ? true : false;
    reAuthModal = $("#deleteModal");
    deleteConfirmButton = $("#confirmDeleteModal");
    deleteLang = deleteConfirmButton.text();
})(jQuery);

function setupIntegrations() {
    Pace.options = {
        ajax: false,
        restartOnRequestAfter: false
    };
    PNotify.prototype.options.styling = "fontawesome";

    // Extending input mask to add our types
    $.extend($.inputmask.defaults.definitions, {
        '^': {
            validator: "[A-Za-z0-9\_\.]",
            cardinality: 1
        },
        '#': {
            validator: "[A-Fa-f0-9]",
            cardinality: 1
        }
    });
}

function setupUniversalSearch() {
    var uniSearchForm = $('#universal-search-form');
    if (!uniSearchForm.length)
        return;

    var resultLimit = 5;

    var uniSearchField = $('#universal-search-select');
    var baseURL = uniSearchForm.attr('action');
    var method = uniSearchForm.attr('method');

    uniSearchField.on("select2:selecting", function(e) {
        e.preventDefault();
        var url = e.params.args.data.url;
        uniSearchField.prop('disabled', true);
        window.location.href = url;
    });

    uniSearchField.select2({
        width: '100%',
        placeholder: 'Search...',
        minimumInputLength: 1,
        multiple: true,
        maximumSelectionSize: 1,
        ajax: {
            delay: 250,
            url: function(params)  {
                return baseURL + '/' + params.term + '/' + resultLimit;
            },
            type: method,
            dataType: 'json',
            cache: false,
            processResults: function (data) {
                var results = [];

                var lang = data._lang;
                var id = 0;
                for (var key in data) {
                    if (!data.hasOwnProperty(key)) continue;
                    if (key.startsWith("_")) continue;

                    var obj = data[key];
                    if (obj.length == 0) continue;
                    var objData = [];

                    for (var i = 0; i < obj.length; i++) {
                        var item = obj[i];
                        objData.push({
                            "id": id,
                            "text": item.name,
                            "url": '../management/index.php?node=' + key + '&sub=edit&id=' + item.id,
                        })
                    }
                    if (obj.length != data._results[key]) {
                        objData.push({
                            "id": id,
                            "text": "--> " + lang.AllResults,
                            "url": '../management/index.php?node=' + key + '&sub=list&search=' + data._query
                        })
                    }

                    results.push({
                        "text": Common.capitalizeFirstLetter(lang[key]),
                        "children": objData
                    });
                }
                return {
                    results: results
                };
            }
        }
    });
}

function setupPasswordReveal() {
    $(':password')
        .not('.fakes, [name="upass"]')
        .before('<span class="input-group-addon"><i class="fa fa-eye-slash fogpasswordeye"></i></span>');
    $(document).on('click', '.fogpasswordeye', function(e) {
        e.preventDefault();
        if (!$(this).hasClass('clicked')) {
            $(this)
                .addClass('clicked')
                .removeClass('fa-eye-slash')
                .addClass('fa-eye')
                .closest('.input-group')
                .find('input[type="password"]')
                .prop('type', 'text');
        } else {
            $(this)
                .removeClass('clicked')
                .addClass('fa-eye-slash')
                .removeClass('fa-eye')
                .closest('.input-group')
                .find('input[type="text"]')
                .prop('type', 'password');
        }
    }).on('change', ':file', function() {
        var input = $(this),
            numFiles = input.get(0).files ? input.get(0).files.length : 1,
            label = input
            .val()
            .replace(/\\/g, '/')
            .replace(/.*\//, '');
        input.trigger('fileselect', [numFiles, label]);
        /**
         * If only one file display the value in the text field.
         * Otherwise show the number of files selected.
         */
        if (numFiles == 1) {
            $('.filedisp').val(label);
        } else {
            $('.filedisp').val(numFiles + ' files selected');
        }
    }).on('mouseover', function() {
        $('[data-toggle="tooltip"').tooltip({
            container: 'body'
        });
    });
}

function disableFormDefaults() {
    var forms = document.querySelectorAll('form');
    forms.forEach(function(form) {
        $(form).on('submit',function(e) {
            e.preventDefault();
        });
    });
}

/**
 * Gets the GET params from the URL.
 */
function getQueryParams(qs) {
    qs = qs.replace(/\+/g, ' ');
    var params = {},tokens,re = /[?&]?([^=]+)=([^&]*)/g;
    while (tokens = re.exec(qs)) {
        params[decodeURIComponent(tokens[1])] = decodeURIComponent(tokens[2]);
    }
    return params;
}
