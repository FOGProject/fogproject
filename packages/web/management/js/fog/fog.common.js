var shouldReAuth = ($('#reAuthDelete').val() == '1') ? true : false,
    reAuthModal = $('#deleteModal'),
    deleteConfirmButton = $('#confirmDeleteModal'),
    deleteLang = deleteConfirmButton.text(),
    $_GET = getQueryParams(),
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
/**
 * Non-selector required functions.
 */
$.apiCall = function(method, action, data, cb) {
    Pace.track(function() {
        $.ajax('', {
            type: method,
            url: action,
            async: true,
            cache: false,
            data: data,
            contentType: false,
            processData: false,
            success: function(data, textStatus, jqXHR) {
                $.notifyFromAPI(data, false);
                if (cb && typeof cb === 'function') {
                    cb(null, data);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                $.notifyFromAPI(jqXHR.responseJSON, true);
                if (cb && typeof cb === 'function') {
                    cb(jqXHR, jqXHR.responseJSON);
                }
            }
        });
    });
};
$.capitalizeFirstLetter = function(string) {
    return string.charAt(0).toUpperCase() + string.slice(1);
}
$.debugLog = function(obj) {
    if(Common.debug) {
        console.log(obj);
    }
}
$.deleteAssociated = function(table, url, cb, opts) {
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
                    table.draw(false);
                }
                $.notifyFromAPI(res, false);
                if (cb && typeof(cb) === 'function') {
                    cb(null, res);
                }
            },
            error: function(res) {
                $.notifyFromAPI(res.responseJSON, true);
                if (cb && typeof(cb) === 'function') {
                    cb(res, res.responseJSON);
                }
            }
        });
    });
};
$.deleteSelected = function(table, cb, opts) {
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
        $.reAuth(numItems, function(err, password) {
            if (err) {
                if (cb && typeof(cb) === 'function') {
                    cb(err);
                }
                return;
            }
            opts.password = password;
            $.deleteSelected(table, cb, opts);
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
                    table.draw(false);
                }
                reAuthModal.finishReAuth();
                $.notifyFromAPI(res, false);
                if (cb && typeof(cb) === 'function') {
                    cb(null,res);
                }
            },
            error: function(res) {
                if (res.status == 401) {
                    $.notifyFromAPI(res.responseJSON, true);
                    $.reAuth(numItems, function(err, password) {
                        if (err) {
                            if (cb && typeof(cb) === 'function') {
                                cb(err,res.responseJSON);
                            }
                            return;
                        }
                        opts.password = password;
                        $.deleteSelected(table, cb, opts);
                    });
                    return;
                } else {
                    reAuthModal.finishReAuth();
                    $.notifyFromAPI(res.responseJSON, true);
                    if (cb && typeof(cb) === 'function') {
                        cb(res,res.responseJSON);
                    }
                }
            }
        });
    });
};
$.getSelectedIds = function(table) {
    var rows = table.rows({selected: true});
    return rows.ids().toArray();
};
$.notify = function(title, body, type) {
    new PNotify({
        title: title,
        text: body,
        type: type
    });
};
$.notifyFromAPI = function(res, isError) {
    var title = res.title || 'Bad Response',
        type = (isError ? 'error' : 'success'),
        msg = '';

    if (res === undefined) {
        res = {};
    }
    if (!isError) {
        if (res.msg) {
            $.notify(
                res.title || 'Bad Response',
                res.msg,
                'success'
            );
        }
        if (res.info) {
            $.notify(
                res.title || 'Bad Response',
                res.info,
                'info'
            );
        }
        if (res.warning) {
            $.notify(
                res.title || 'Bad Response',
                res.warning,
                'warning'
            );
        }
        if (res.error) {
            $.notify(
                res.title || 'Bad Response',
                res.error,
                'error'
            );
        }
        $.debugLog(res);
        return;
    }
    $.notify(
        res.title || 'Bad Response',
        (isError ? res.error : res.msg) || 'Bad Response',
        type
    );
    $.debugLog(res);
};
$.reAuth = function(count, cb) {
    deleteConfirmButton.text(deleteLang.replace('{0}', count));
    // enable all buttons / focus on the input box incase
    //   the modal is already being shown
    reAuthModal.setContainerDisable(false);
    $("#deletePassword").trigger('focus');
    reAuthModal.registerModal(
        // On show
        function(e) {
            $("#deletePassword").val('');
            $("#deletePassword").trigger('focus');
            reAuthModal.setContainerDisable(false);
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
            reAuthModal.setContainerDisable(true);
            cb(null, $("#deletePassword").val());
            return false;
        }
    });

    deleteConfirmButton.off('click');
    deleteConfirmButton.on('click', function(e) {
        reAuthModal.setContainerDisable(true);
        cb(null, $("#deletePassword").val());
    });
    reAuthModal.modal('show');
};
/**
 * Allows calling as $.funcname(element, ...args);
 */
$.finishReAuth = function(modal) {
    $(modal).modal('hide');
};
$.mirror = function(start, selector, regex, replace) {
    $(start).mirror(selector, regex, replace);
};
$.processForm = function(form, cb, input = ':input') {
    $(form).processForm(cb, input);
};
$.registerModal = function(modal, onOpen, onClose, opts) {
    $(modal).registerModal(onOpen, onClose, opts);
};
$.registerTable = function(e, onSelect, opts) {
    $(e).registerTable(onSelect, opts);
};
$.setContainerDisable = function(container, disable) {
    $(container).setContainerDisable(disable);
};
$.setLoading = function(container, loading) {
    $(container).setLoading(loading);
};
$.validateForm = function(form, input = ':input') {
    $(form).validateForm(input);
};
/**
 * Selector required elements.
 */
$.fn.finishReAuth = function() {
    $(this).modal('hide');
};
$.fn.mirror = function(selector, regex, replace) {
    return this.each(function() {
        var start = $(this),
            mirror = $(selector);
        start.on('keyup', function() {
            if (regex) {
                if (typeof replace === 'undefined') {
                    replace = '';
                }
                mirror.val(start.val().replace(regex, replace));
            } else {
                mirror.val(start.val());
            }
        });
    });
};
$.fn.processForm = function(cb, input = ':input') {
    var opts = new FormData($(this)[0]);
        method = $(this).attr('method'),
        action = $(this).attr('action');
    $(this).setContainerDisable(true);
    if (!$(this).validateForm(input)) {
        $(this).setContainerDisable(false);
        if (cb && typeof cb === 'function') {
            cb('invalid', null);
        }
        return;
    }
    $.apiCall(method, action, opts, function(err, data) {
        $(this).setContainerDisable(false);
        if (cb && typeof cb === 'function') {
            cb(err, data);
        }
    });
};
$.fn.registerModal = function(onOpen, onClose, opts) {
    var e = this;
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
$.fn.registerTable = function(onSelect, opts) {
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

    var table = $(this).DataTable(opts);

    if (onSelect !== undefined && typeof(onSelect) === 'function') {
        table.on('select deselect', function( e, dt, type, indexes) {
            onSelect(dt.rows({selected: true}));
        });
    }

    return table;
};
$.fn.setContainerDisable = function(disabled) {
    if(disabled !== false) {
        disabled = true;
    }
    // Use native DOM query to select all the nested inputs
    // as it is faster
    var inputs = document.querySelectorAll('input:not([type="checkbox"]), select, button, .btn, textarea');
    var ichecks = document.querySelectorAll('.checkbox');
    inputs.forEach(function(inputObj) {
        $(inputObj).prop('disabled', disabled);
    });
    ichecks.forEach(function(inputObj) {
        $(inputObj).iCheck((disabled) ? 'disable' : 'enable');
    });
};
$.fn.setLoading = function(loading) {
    if(loading !== false) {
        loading = true;
    }

    var loadingId = 'loadingOverlay';

    if (loading) {
        $(this).append(
            '<div class="overlay" id="' + loadingId  + '"><i class="fa fa-refresh fa-spin"></i></div>'
        );
    } else {
        $(this).children('#'+loadingId).remove();;
    }
}
$.fn.validateForm = function(input = ':input') {
    var scrolling = false,
        isError = false,
        form = $(this);
    form.find(input).each(function(i, e) {
        var isValid = true,
            invalidReason = undefined,
            // Grab the parent form-group, as we will need it to visually mark
            //   invalid fields
            parent = $(e).closest('div[class^="form-group"]'),
            required = $(e).prop('required'),
            val = $(e).inputmask('unmaskedvalue');
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

            if (beRegexTo) beRegexTo = '#' + beRegexTo;

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
                $.debugLog("Missing target " + beEqualTo + " for " + e);
                break equalCheck;
            }
            var target = $("#" + beEqualTo);
            if ($(e).val() !== target.val()) {
                isValid = false;
                invalidReason = 'Field does not match';
            }
        }

        regexCheck: if (isValid) {
            var beRegexTo = $(e).attr('beRegexTo'),
                regexID = $(e).attr('id'),
                helpMsg = $(e).attr('requirements'),
                localstr = $(e).val(),
                regex = new RegExp(beRegexTo);
            if (!regexID) break regexCheck;
            if (!$('#'+regexID).length) {
                $.debugLog('Missing target ' + regexID + ' for ' + e);
                break regexCheck;
            }
            if (!regex.test(localstr)) {
                isValid = false;
                invalidReason = 'Does not meet the requirements.';
                if (helpMsg) {
                    invalidReason += ' ' + helpMsg;
                }
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
// URL Variables. AKA GET variables.

(function($) {
    var pluginOptionsOpen = true,
        pluginOptionsAlt = $('.plugin-options-alternate');

    // Animate the plugin items.
    pluginOptionsAlt.on('click', function(event) {
        event.preventDefault();
        var whenDone = function() {
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
    Common.iCheck = function(match) {
        match = match || 'input'
        $(match).iCheck({
            checkboxClass: 'icheckbox_square-blue',
            radioClass: 'iradio_square-blue',
            increaseArea: '20%' // optional
        });
    };

    Common.createModalShow = function() {
        var form = $(this).find('#create-form'),
            btn = $('#send');
        form[0].reset();
        $(':input:first', this).trigger('focus');
        $(':input:not(textarea)', this).on('keypress', function(e) {
            if (e.which == 13) {
                btn.trigger('click');
            }
        });
    };

    Common.createModalHide = function() {
        // Find the form
        var form = $(this).find('#create-form');
        // Remove the errors if any.
        form.find('.has-error').removeClass('has-error').find('span.help-block').remove();
        // Unbind the keypress event.
        $(':input:not(textarea)', this).off('keypress');
    };

    $.debugLog("=== DEBUG LOGGING ENABLED ===");
    setupIntegrations();
    $(":input").inputmask(); // Setup all input masks
    Common.iCheck(); // Setup all checkboxes
    $('.select2').select2({width: '100%'}); // Setup all select elements
    disableFormDefaults();
    setupPasswordReveal();
    setupUniversalSearch();
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

    uniSearchField.on('select2:selecting', function(e) {
        e.preventDefault();
        var url = e.params.args.data.url;
        uniSearchField.prop('disabled', true);
        window.location.href = url;
    });

    uniSearchField.select2({
        width: '100%',
        dropdownAutoWidth: true,
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
                            id: id,
                            text: item.name,
                            url: '../management/index.php?node='
                            + (
                                key != 'service' ?
                                key + '&sub=edit&id=' + item.id :
                                'about&sub=settings&search=' + item.name
                            )
                        });
                    }
                    if (obj.length != data._results[key]) {
                        objData.push({
                            id: id,
                            text: "--> " + lang.AllResults,
                            url: '../management/index.php?node='
                            + (
                                key != 'service' ?
                                key + '&sub=list&search=' :
                                'about&sub=settings&search='
                            )
                            + data._query
                        });
                    }

                    results.push({
                        text: $.capitalizeFirstLetter(lang[key]),
                        children: objData
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
    var a = document.createElement('a'),
        params = {},
        tokens,
        re = /[?&]?([^=]+)=([^&]*)/g;
    a.href = (qs || document.location.href);
    qs = a.search
    qs = qs.replace(/\+/g, ' ');
    while (tokens = re.exec(qs)) {
        params[decodeURIComponent(tokens[1])] = decodeURIComponent(tokens[2]);
    }
    return params;
}
