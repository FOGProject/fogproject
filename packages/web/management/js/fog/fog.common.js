var shouldReAuth,
    reAuthModal,
    deleteConfirmButton,
    deleteLang,
    $_GET,
    Common;
/**
 * Non-selector required functions.
 */
$.apiCall = function(method, action, data, cb, processData = true) {
    Pace.track(function() {
        $.ajax('', {
            type: method,
            url: action,
            async: true,
            cache: false,
            data: data,
            contentType: !processData ? false : 'application/x-www-form-urlencoded',
            processData: !processData ? false : true,
            success: function(data, textStatus, jqXHR) {
                $.notifyFromAPI(data, false);
                if (cb && typeof cb === 'function') {
                    cb(null, data);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                $.notifyFromAPI(jqXHR.responseJSON, jqXHR);
                if (cb && typeof cb === 'function') {
                    cb(jqXHR, jqXHR.responseJSON);
                }
            },
            xhr: function() {
                var xhr = new window.XMLHttpRequest();

                xhr.upload.addEventListener('progress', function(event) {
                    if (event.lengthComputable) {
                        var percentComplete = event.loaded / event.total;
                        percentComplete = parseInt(percentComplete * 100);
                        console.log(percentComplete);
                        if (percentComplete === 100) {
                            console.log('Finished Uploaded');
                        }
                    }
                }, false);

                return xhr;
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
                $.notifyFromAPI(res.responseJSON, res);
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
        ids: opts.rows.ids().toArray(),
        url: '../management/index.php?node=' + opts.node + '&sub=deletemulti',
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
            url: opts.url,
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
                    $.notifyFromAPI(res.responseJSON, res);
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
                    $.notifyFromAPI(res.responseJSON, res);
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
    if (res === undefined) {
        typemsg = "msg";
        res = {
            title: 'Generic ' + (isError ? 'Error' : 'Message'),
        };
        if (isError) {
            res.error = isError ? isError.statusText : 'Unknown issue';
        } else {
            res.msg = 'No message';
        }
    }
    var title = res.title,
        type = (isError ? 'error' : 'success');
    if (!isError) {
        if (res.msg) {
            $.notify(
                title || 'Bad Response',
                res.msg,
                'success'
            );
        }
        if (res.info) {
            $.notify(
                title || 'Bad Response',
                res.info,
                'info'
            );
        }
        if (res.warning) {
            $.notify(
                title || 'Bad Response',
                res.warning,
                'warning'
            );
        }
        if (res.error) {
            $.notify(
                title || 'Bad Response',
                res.error,
                'error'
            );
        }
        $.debugLog(res);
        return;
    }
    $.notify(
        title || 'Bad Response',
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
$.cachedScript = function(url, options) {
    // Allow user to set any option except for dataType, cache, and url
    options = $.extend(options || {}, {
        dataType: 'script',
        cache: true,
        url: url
    });

    // Use $.ajax() since it is more flexible than $.getScript
    // Return the jqXHR object so we can chain callbacks
    return $.ajax(options);
};
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
    var opts = $(this).attr('enctype') != 'multipart/form-data' ?
        $(this).serialize() :
        new FormData($(this)[0]);
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
    }, $(this).attr('enctype') != 'multipart/form-data');
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
        retrieve: true
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

function reinitialize() {
    $_GET = getQueryParams();
    shouldReAuth = ($('#reAuthDelete').val() == '1') ? true : false;
    reAuthModal = $('#deleteModal');
    deleteConfirmButton = $('#confirmDeleteModal');
    deleteLang = deleteConfirmButton.text();
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
            mac: "##:##:##:##:##:##",
            productKey: "*****-*****-*****-*****-*****",
            hostname: ""
        }
    };
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
    $('.fog-select2').select2({width: '100%'}); // Setup all select elements
    disableFormDefaults();
    setupPasswordReveal();
    setupUniversalSearch();
};

function setupIntegrations() {
    Pace.options = {
        ajax: false,
        restartOnRequestAfter: false
    };
    PNotify.prototype.options.styling = "fontawesome";

    // Extending input mask to add our types
    $.extend($.inputmask.defaults.definitions, {
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
        if (0 == $('.showpass').val()) {
            return;
        }
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
        $('[data-toggle="tooltip"]').tooltip({
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

/***** AJAX PAGE LOADING *****/
var AJAX_PAGE_LOADING_ENABLED = true;

/**
 * Override jQuery XHR to abort requests before page change.
 */
$.xhrPool = { pool: [] };

$.xhrPool.abortAll = function() {
    $(this.pool).each(function(i, jqXHR) {   //  cycle through list of recorded connection
        jqXHR.abort();  //  aborts connection
        $.xhrPool.pool.splice(i, 1); //  removes from list by index
    });
};

$.ajaxSetup({
    beforeSend: function(jqXHR) { $.xhrPool.pool.push(jqXHR); }, //  annd connection to list
    complete: function(jqXHR) {
        if($.xhrPool == null) return;
        var i = $.xhrPool.pool.indexOf(jqXHR);   //  get index for current connection completed
        if (i > -1) $.xhrPool.pool.splice(i, 1); //  removes from list by index
    }
});

/**
 * Override setInterval (to make sure all intervals can be cleared on page switch.)
 */
var intervals = [];
var realSetInterval = window.setInterval;
window.setInterval = function(...args){
    var handler = args.shift() || null;
    var timeout = args.shift() || null;
    var arguments = args.length > 0 ? args : null;

    var interval = realSetInterval(handler, timeout, arguments);
    intervals.push(interval);
    return interval;
};

function clearAllIntervals(){
    while(intervals.length > 0){
        clearInterval(intervals.pop());
    }
}

/**
 *  Handle 'ajax-ified' links.
 *  (.ajax-page-link)
 */
(function($){
    if(!AJAX_PAGE_LOADING_ENABLED) return;

    var ajaxPageLoading = false;

    // TODO: DRY - move all initialization code into one function that's executed here and on page reload.
    reinitialize();

    window.onpopstate = function(event){
        var target = event.state.target;
        var targetElement = $(".ajax-page-link[href='" + target + "']");
        doPageLoad(target, targetElement, false);
    };

    $(document).ready(function(){
        $(".ajax-page-link").click(function(event){
            event.preventDefault();
            var targetElement = $(this);
            var target = targetElement.attr('href');
            doPageLoad(target, targetElement);
        });
    });

    function doPageLoad(targetPage, targetElement, shouldPushState = true){

        // Setup the loading page state...
        ajaxPageLoading = true;
        $("#ajaxPageWrapper").setLoading(true);
        $("body").addClass("scroll-lock");
        $("html, body").animate({ scrollTop: 0 }, 300);

        // Prepare to display new page
        clearAllIntervals();
        $.xhrPool.abortAll();

        if($(".sidebar-menu.tree .treeview.menu-open").find(targetElement).length === 0){
            $(".sidebar-menu.tree .treeview.menu-open .treeview-menu").slideUp();
            $(".sidebar-menu.tree .treeview.menu-open").removeClass('menu-open');
        }

        // Load the page asynchronously.
        $.ajax(targetPage, {
            method: 'GET',
            headers: {
                // Stop FOG backend trying to helpful.
                // (We want HTML, not JSON.)
                'X-Requested-With': 'AjaxPageLink'
            },
            data: { 'contentOnly': true }
        }).done(function(data, status, req){
            var ajaxPageWrapper = $("#ajaxPageWrapper");
            ajaxPageWrapper.html(data);

            // Set new page information
            document.title = req.getResponseHeader('X-FOG-PageTitle');
            if(shouldPushState) history.pushState({ target: targetPage }, document.title, targetPage);

            // Reinitialize, render and display the new page.
            reinitialize();
            renderPage(req);

            // Remove the page loading state.
            ajaxPageWrapper.setLoading(false);
            $("body").removeClass("scroll-lock");

            ajaxPageLoading = false;

            // Update the sidebar
            $(".sidebar-menu.tree li").not(targetElement.parent('.treeview')).removeClass('active');
            targetElement.parent().addClass('active');
            targetElement.parents('.treeview').addClass('active menu-open');
            targetElement.parents('.treeview-menu').slideDown();
        });
    }

    function renderPage(req){
        // Get asset version
        var assetVersion = req.getResponseHeader('X-FOG-BCacheVer');

        /** UPDATE STYLESHEETS **/
        var styles = JSON.parse(req.getResponseHeader('X-FOG-Stylesheets'));
        styles.forEach(function(value, index){
            if(styles[index] == null) { delete styles[index]; return; }
            styles[index] = styles[index] + (styles[index].indexOf("?v") === -1 ? "?ver=" + assetVersion : "");
        });

        // Determine currently loaded stylesheets
        var loadedStyles = [];
        $("link[rel='stylesheet']").each(function(index, element){
            loadedStyles.push($(element).attr('href'));
        });

        // Calculate the style delta:
        var styleDelta = {};
        // -> If a style is loaded that the current page does not need, remove it.
        for(var styleIndex in loadedStyles){
            var style = loadedStyles[styleIndex];
            if(styles.indexOf(style) === -1) styleDelta[style] = -1;
        }
        // -> If a style is not loaded and the current page needs it, add it.
        for(var styleIndex in styles){
            var style = styles[styleIndex] + "?ver=" + assetVersion;
            if(loadedStyles.indexOf(style) === -1) styleDelta[style] = 1;
        }

        // Now act according to the style delta
        Object.keys(styleDelta).forEach(function(key){
            var value = styleDelta[key];
            switch(value){
                // Add script
                case 1:
                    $("head").append("<link rel='stylesheet' type='text/css' href='" + key + "' />");
                    break;
                // Remove script
                case -1:
                    $("link[rel='stylesheet'][src='" + key + "']").remove();
                    break;
            }
        });


        /** UPDATE SCRIPTS **/
        var scripts = JSON.parse(req.getResponseHeader('X-FOG-JavaScripts'));
        var commonScripts = JSON.parse(req.getResponseHeader('X-FOG-Common-JavaScripts'));

        scripts.forEach(function(value, index){
            if(scripts[index] == null) { delete scripts[index]; return; }
            scripts[index] = scripts[index] + (scripts[index].indexOf("?v") === -1 ? "?ver=" + assetVersion : "");
        });

        commonScripts.forEach(function(value, index){
            if(commonScripts[index] == null) { delete commonScripts[index]; return; }
            commonScripts[index] = commonScripts[index] + (commonScripts[index].indexOf("?v") === -1 ? "?ver=" + assetVersion : "");
        });

        // Determine the currently loaded scripts.
        var loadedScripts = [];
        $("#scripts").find("script").each(function(index, element){
            loadedScripts.push($(element).attr('src'));
        });

        // Calculate the script delta:
        var scriptDelta = {};
        // -> If a script is loaded and it isn't a script common to every page, remove it.
        for(var scriptIndex in loadedScripts){
            var script = loadedScripts[scriptIndex];
            if (commonScripts.indexOf(script) === -1) scriptDelta[script] = -1;
        }
        // -> Reload all scripts this page needs.
        for(var scriptIndex in scripts){
            var script = scripts[scriptIndex];
            scriptDelta[script] = 1;
        }

        // Now act according to the script delta:
        Object.keys(scriptDelta).forEach(function(key){
            var value = scriptDelta[key];
            switch(value){
                // Add script
                case 1:
                    $("#scripts").append("<script src='" + key + "' type='text/javascript'></script>");
                    $.cachedScript(key);
                    break;
                // Remove script
                case -1:
                    $("script[src='" + key + "']").remove();
                    break;
            }
        });
    }
})(jQuery);
