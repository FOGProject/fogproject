/**
 * FOG Main Javascript File.
 *
 * category: FOGProject
 * package: FOGProject
 * author: Tom Elliott <tommygunsster@gmail.com>
 * license: http://opensource.org/licenses/gpl-3.0 GPLv3
 * link: https://fogproject.org
 */
var startTime = new Date().getTime(),
    validatorOpts,
    screenview,
    callme,
    lastsub,
    pauseUpdate,
    cancelTasks,
    MACLookupTimer,
    MACLookupTimeout = 1000,
    pauseButton = $('#taskpause'),
    cancelButton = $('#taskcancel');
var $_GET = getQueryParams(document.location.search),
    node = $_GET['node'],
    sub = $_GET['sub'],
    id = $_GET['id'],
    tab = $_GET['tab'],
    AJAXTaskForceRequest,
    Container = $('.table-holder .table-responsive'),
    savedFilters,
    headParser = {
        0: {
            sorter: 'checkboxParser'
        }
    },
    ActionBox = $('.action-boxes.host'),
    ActionBoxDel = $('.action-boxes.del'),
    callme = '',
    checkedIDs,
    data = '',
    form,
    TimeoutRunning,
    submithandlerfunc,
    files,
    allRadios = $('.primary, .default, .action'),
    radioChecked,
    setCurrent = function(e) {
        var obj = e.target;
        radioChecked = $(obj).is(':checked');
    },
    setCheck = function(e) {
        if (e.type == 'keypress' && e.charCode != 32) {
            return valse;
        }
        var obj = e.target;
        $(obj).prop('checked', !radioChecked);
    };
/**
 * AJAX Search function.
 */
$.fn.fogAjaxSearch = function(opts) {
    if (this.length == 0) {
        return this;
    }
    var Defaults = {
        URL: $('.search-wrapper').prop('action'),
        SearchDelay: 400,
        SearchMinLength: 1
    },
        SearchAJAX = null,
        SearchTimer,
        SearchLastQuery,
        Options = $.extend({}, Defaults, opts || {});
    if (!Container.length) {
        return this;
    }
    callme = 'hide';
    if (!Container.hasClass('noresults')) {
        callme = 'show';
    }
    Container[callme]();
    ActionBox[callme]();
    ActionBoxDel[callme]();
    return this.each(function(evt) {
        var searchElement = $(this),
            SubmitButton = $('.search-submit');
        searchElement.on('keyup', function(e) {
            if (this.SearchTimer) {
                clearTimeout(this.SearchTimer);
            }
            var newurl = location.protocol
            + "//"
            + location.host
            + location.pathname
            + "?node="
            + node
            + '&sub=search';
            history.pushState(
                {
                    path: newurl
                },
                '',
                newurl
            );
            $('.nav.nav-tabs').remove();
            if ((sub != 'search' || sub != 'list') && typeof sub != 'undefined' && typeof sub != null && typeof sub != '') {
                Container.html(
                    '<div class="table-holder col-xs-12">'
                    + '<table class="table">'
                    + '<thead><tr class="header"></tr></thead>'
                    + '<tbody><tr></tr></tbody>'
                    + '</table>'
                    + '</div>'
                );
            }
            this.SearchTimer = setTimeout(PerformSearch, Options.SearchDelay);
        }).focus(function(e) {
            var searchElement = $(this).removeClass('placeholder');
            if (searchElement.val() == searchElement.prop('placeholder')) {
                searchElement.val('');
            }
        }).blur(function(e) {
            var searchElement = $(this);
            if (searchElement.val() == '') {
                searchElement.addClass('placeholder').val(searchElement.prop('placeholder'));
                if (this.SearchAJAX) {
                    this.SearchAJAX.abort();
                }
                if (this.SearchTimer) {
                    clearTimeout(this.SearchTimer);
                }
                $('tbody', Container).empty().parents('table').hide();
            }
        }).each(function(e) {
            var searchElement = $(this);
            if (searchElement.val() != searchElement.prop('placeholder')) {
                searchElement.val('');
            }
        }).parents('form').on('submit',function(e) {
            e.preventDefault();
        });
        function PerformSearch() {
            var Query = searchElement.val();
            if (Query == this.SearchLastQuery) return;
            this.SearchLastQuery = Query;
            if (Query.length < Options.SearchMinLength) {
                Container.hide();
                ActionBox.hide();
                ActionBoxDel.hide();
                return this;
            }
            if (this.SearchAJAX) this.SearchAJAX.abort();
            this.SearchAJAX = $.ajax({
                type: $('.search-wrapper').prop('method'),
                cache: false,
                url: $('.search-wrapper').prop('action'),
                dataType: 'json',
                data: {crit: Query},
                beforeSend: function() {
                    SubmitButton.addClass('searching').find('i').removeClass().addClass('fogsearch fa fa-spinner fa-pulse fa-fw');
                },
                success: function(response) {
                    dataLength = response === null || response.data === null ? dataLength = 0 : response.data.length;
                    SubmitButton.removeClass('searching').find('i').removeClass().addClass('fogsearch fa fa-search');
                    thead = $('thead', Container);
                    tbody = $('tbody', Container);
                    LastCount = dataLength;
                    if (dataLength > 0) {
                        buildHeaderRow(
                            response.headerData,
                            response.attributes
                        );
                        buildRow(
                            response.data,
                            response.templates,
                            response.attributes
                        );
                    }
                    TableCheck();
                    this.SearchAJAX = null;
                    checkboxToggleSearchListPages();
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    this.SearchAJAX = null;
                    this.SearchLastQuery = null;
                }
            });
        }
    });
};
/**
 * Function exists
 */
$.fn.exists = function() {
    return this.length > 0;
};
/**
 * Function is IE8
 */
$.fn.isIE8 = function() {
    return $.browser.msie && parseInt($.browser.version, 10) <= 8;
};
/**
 * Handles fogvariable elements.
 */
$.fn.fogVariable = function(opts) {
    if (this.length == 0) return this;
    return this.each(function() {
        window[$(this).prop('id').toString()] = $(this).html().toString();
        $(this).remove();
    });
};
/**
 * Main function.
 */
(function($) {
    /**
     * FOG Settings quick image os display.
     */
    $('#FOG_QUICKREG_IMG_ID').on('change',function() {
        $.ajax({
            url: '../management/index.php?node=about',
            cache: false,
            type: 'POST',
            data: {
                sub: 'getOSID',
                image_id: $(this).val()
            },
            success: function(idata) {
                $('#FOG_QUICKREG_OS_ID').html(idata.replace(/\"/g,""));
            }
        });
    });
    $.each(allRadios, function(i, val) {
        if (this.id.length > 0) {
            var label = $('label[for='+this.id+']');
            var element = label.prev();
        } else {
            var label = $('input[value='+$(this).val()+'].action');
            var element = label;
        }
        $(this).on('mousedown keydown', function(e) {
            setCurrent(e);
        }).on('click', function(e) {
            setCheck(e);
        });
        label.on('mousedown keydown', function(e) {
            e.target = $(element);
            setCurrent(e);
        });
    });
    /**
     * Assign range sliders
     */
    if (typeof $('div.pigz').slider == typeof Function) {
        $('div.pigz').slider({
            min: 0,
            max: 22,
            range: 'min',
            value: $('.showVal.pigz').val(),
            slide: function(event, ui) {
                $('.showVal.pigz').val(ui.value);
            }
        });
    }
    if (typeof $('div.loglvl').slider == typeof Function) {
        $('div.loglvl').slider({
            min: 0,
            max: 7,
            range: 'min',
            value: $('.showVal.loglvl').val(),
            slide: function(event, ui) {
                $('.showVal.loglvl').val(ui.value);
            }
        });
    }
    if (typeof $('div.inact').slider == typeof Function) {
        $('div.inact').slider({
            min: 1,
            max: 24,
            range: 'min',
            value: $('.showVal.inact').val(),
            slide: function(event, ui) {
                $('.showVal.inact').val(ui.value);
            }
        });
    }
    if (typeof $('div.regen').slider == typeof Function) {
        $('div.regen').slider({
            step: 0.25,
            min: 0.25,
            max: 24,
            range: 'min',
            value: $('.showVal.regen').val(),
            slide: function(event, ui) {
                $('.showVal.regen').val(ui.value);
            }
        });
    }
    /**
     * Enable search to operate.
     */
    $('.search-input').fogAjaxSearch();
    /**
     * The FOG variable definer.
     */
    $('.fog-variable').fogVariable();
    /**
     * Get table sorter parser setup.
     */
    setupParserInfo();
    /**
     * Setup Table with sorter.
     */
    setupFogTableInformation();
    /**
     * Handles server time updating.
     */
    AJAXServerTime();
    /**
     * Body resize.
     */
    $(document.body).css(
        /**
         * Ensures panels/content are properly spaced below navbar.
         * On page load.
         */
        'padding-top',
        $('.navbar-fixed-top').height() + 10
    );
    /**
     * Window resize.
     */
    $(window).resize(function() {
        /**
         * Ensures panels/content are properly spaced below navbar.
         * As window resizes.
         */
        $(document.body).css(
            'padding-top',
            $('.navbar-fixed-top').height() + 10
        );
    });
    /**
     * File input updater.
     */
    $(document).on('change', ':file', function() {
        /**
         * Bootstrap style file input text updater.
         */
        var input = $(this),
            numFiles = input.get(0).files ? input.get(0).files.length : 1,
            label = input.val().replace(/\\/g, '/').replace(/.*\//, '');
        /**
         * Triggers the fileselect event.
         */
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
        /**
         * Sets the tooltips to operate.
         */
        $('[data-toggle="tooltip"]').tooltip({
            container: 'body'
        });
    }).on('click', '.fogpasswordeye', function(e) {
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
    });
    /**
     * Collapse/Expand All button trigger
     */
    $('.trigger_expand').on('click', function(e) {
        e.preventDefault();
        var all = $('.expand_trigger');
        if (!$(this).hasClass('activeclicked')) {
            $(this).addClass('activeclicked').html('<h4 class="title">Collapse All</h4>');
            all = $('.expand_trigger').not('.activenow');
        } else {
            $(this).removeClass('activeclicked').html('<h4 class="title">Expand All</h4>');
            all = $('.activenow');
        }
        all.each(function() {
            $(this).trigger('click');
        });
    });
    /**
     * Button click trigger for individual items.
     */
    $('.expand_trigger').on('click', function(e) {
        e.preventDefault();
        if ($(this).hasClass('activenow')) {
            $(this).removeClass('activenow');
        } else {
            $(this).addClass('activenow');
        }
        $('div#'+this.id).not('.panel-heading').fadeToggle('slow', 'swing');
    });
    /**
     * Password field hide/show as needed.
     */
    $(':password')
        .not('[name="fakepasswordremembered"], [name="upass"]')
        .before('<span class="input-group-addon"><i class="fa fa-eye-slash fogpasswordeye"></i></span>');
    /**
     * Sets up input fields to place the pencil in the form
     */
    setEditFocus();
    /**
     * Action box parser for checked boxes.
     */
    $('.action-boxes').on('submit', function(e) {
        var checked = getChecked();
        $('input[name="'+node+'IDArray"]').val(checked.join(','));
    });
    /**
     * Export authorization testing.
     */
    $('[name="export"]').on('click', function(e) {
        e.preventDefault();
        url = $(this).parents('form').attr('action');
        exportDialog(url);
    });
    /**
     * CSV and PDF export authorization.
     */
    $('#csvsub, #pdfsub').on('click', function(e) {
        e.preventDefault();
        exportDialog($(this).prop('href'));
    });
    /**
     * Delete authorization testing.
     */
    $('[name="delete"]').on('click', function(e) {
        e.preventDefault();
        url = $(this).parents('form').attr('action');
        deleteDialog(url);
    });
    /**
     * Sets up our validator elements.
     */
    $.validator.setDefaults(
        {
            highlight: function(element) {
                $(element).closest('.form-group').addClass('has-error');
            },
            unhighlight: function(element) {
                $(element).closest('.form-group').removeClass('has-error');
            },
            errorElement: 'span',
            errorClass: 'label label-danger',
            errorPlacement: function(error, element) {
                if (element.parent('.input-group').length) {
                    error.insertAfter(element.parent());
                } else {
                    error.insertAfter(element);
                }
            }
        }
    );
    /**
     * Adds the regex method to the validator.
     */
    $.validator.addMethod(
        'regex',
        function(value, element, regexp) {
            var re = new RegExp(regexp);
            return this.optional(element) || re.test(value);
        },
        "Invalid Input"
    );
    /**
     * Handles url linking/div linking.
     */
    var url = location.toString();
    if (url.match('#')) {
        $('.nav-tabs a[href*="#'+url.split('#')[1]+'"]').tab('show');
    }
    /**
     * Set the url in the window appropriately.
     */
    $('.nav-tabs a').on('shown.bs.tab', function(e) {
        if (history.pushState) {
            history.pushState(null, null, e.target.hash);
        } else {
            location.hash = e.target.hash;
        }
        $(this).parent().addClass('active');
    });
    /**
     * If we don't have a hash such as when initially entering
     * an edit pge, dispaly the first item.
     */
    if ($('.tab-content').length > 0) {
        if (location.hash == "") {
            firstid = $('.tab-content > div:first').prop('id');
            $('.nav-tabs a[href*="#'+firstid+'"]').parent().addClass('active');
        }
    }
    /**
     * Set the tab.
     */
    $('a[data-toggle="tab"]').on('click', function(e) {
        var newLoadedHtml = $(this).prop('href'),
            hash = newLoadedHtml.split('#'),
            link = hash[0];
        hash = hash[1];
        if ($('#'+hash).length < 1) {
            location.href = newLoadedHtml;
        }
    });
    function format(icon) {
        if (!icon.id) {
            return icon.text;
        }
        var _icon = $(
            '<i class="fa fa-'+icon.element.value.toLowerCase()+' fa-fw">'
            + icon.text
            + '</i>'
        );
        return _icon;
    }
    $('select').not('[name="nodesel"], [name="groupsel"]').select2();
    $('[name="icon"]').select2({
        templateResult: format,
        templateSelection: format
    });
    $('#scheduleSingleTime').datetimepicker({
        dateFormat: 'yy/mm/dd',
        timeFormat: 'HH:mm'
    });
    specialCrons();
    $('#checkAll').on('click', function(e) {
        selectAll = this.checked;
        $('.checkboxes').each(function(f) {
            this.checked = selectAll;
        });
    });
    callme = 'hide';
    if (!$('tbody > tr', Container).length < 1
        || !Container.hasClass('noresults')
    ) {
        callme = 'show';
    }
    Container[callme]();
    ActionBox[callme]();
    ActionBoxDel[callme]();
})(jQuery);
/**
 * Gets the GET params from the URL.
 */
function getQueryParams(qs) {
    qs = qs.split("+").join(" ");
    var params = {},tokens,re = /[?&]?([^=]+)=([^&]*)/g;
    while (tokens = re.exec(qs)) {
        params[decodeURIComponent(tokens[1])] = decodeURIComponent(tokens[2]);
    }
    return params;
}
/**
 * Common handler for form submissions.
 */
submithandlerfunc = function(form) {
    var data = new FormData(),
        fields = $(form).find(':visible, [type="radio"], [type="hidden"]'),
        serialdata = fields.serializeArray(),
        files = $(form).find('[type="file"]');
    if (files.length > 0) {
        files = files[0].files;
        $.each(files, function(i, file) {
            data.append('snapin', file);
        });
    }
    $.each(serialdata, function(i, val) {
        data.append(val.name, val.value);
    });
    url = $(form).attr('action');
    method = $(form).attr('method');
    $.ajax({
        url: url,
        type: method,
        data: data,
        dataType: 'json',
        mimeType: 'multipart/form-data',
        processData: false,
        contentType: false,
        cache: false,
        success: function(data) {
            title = data.title;
            if (data.error) {
                msg = data.error;
                type = BootstrapDialog.TYPE_WARNING;
                sleeptime = 5000;
            } else {
                msg = data.msg;
                type = BootstrapDialog.TYPE_SUCCESS;
                sleeptime = 2000;
            }
            BootstrapDialog.show({
                title: title,
                message: msg,
                type: type,
                onshown: function(dialogRef) {
                    bootstrapdialogopen = setTimeout(function() {
                        dialogRef.close();
                    }, sleeptime);
                },
                onhidden: function(dialogRef) {
                    clearTimeout(bootstrapdialogopen);
                }
            });
        }
    });
    return false;
};
/**
 * Gets the checked items so refresh can reset/forms can process.
 */
function getChecked() {
    var val = [];
    $('.toggle-action:checkbox:checked').each(function(i) {
        if ($(this).parent().is(':visible')) {
            val[i] = this.value;
        }
    });
    return val;
}
/**
 * Sets the checked items on refresh if they were checked.
 */
function setChecked(ids) {
    $('.toggle-action:checkbox').not(':checked').each(function(i) {
        if ($(this).parent().is(':visible')) {
            if ($.inArray(this.value, ids) < 0) {
                return;
            }
            this.checked = true;
        }
    });
}
/**
 * Sets the pencil in the fields when focused.
 */
function setEditFocus() {
    $('input, textarea').not('[type="number"], [type="checkbox"], [name="groupsel"], [name="nodesel"], [name="ulang"], #uname, #upass, .system-search, .search-input, [type="radio"], [readonly], .tablesorter-filter').on('focus', function(e) {
        e.preventDefault();
        field = $(this);
        $(this).after(
            '<span class="input-group-addon fogpencil">'
            + '<i class="fa fa-pencil fa-fw fogpencil"></i>'
            + '</span>'
        );
    }).blur(function(e) {
        e.preventDefault();
        field = $(this);
        $('.fogpencil').remove();
    });
}
/**
 * Sets the Server time for the navbar and keeps it updating.
 */
function AJAXServerTime() {
    $.ajax({
        url: '../status/getservertime.php',
        type: 'post',
        success: function(data) {
            $('#showtime').html(data);
        },
        complete: function() {
            setTimeout(
                AJAXServerTime,
                60000 - ((new Date().getTime() - startTime) % 60000)
            )
        }
    });
}
/**
 * Handles clearing ad fields.
 * Used by hosts and groups.
 */
function clearADFields() {
    $('#clearAD').on('click', function(e) {
        e.preventDefault();
        clearDoms = $('#adOU[type="text"], #adDomain, #adUsername, #adPassword, #adPasswordLegacy').val('');
        $('#adEnabled').prop('checked', false);
    });
}
/**
 * Handles updating ad fields.
 * Used by hosts and groups.
 */
function setADFields() {
    $('#adEnabled').on('click', function(e) {
        if (!$(this).is(':checked')) {
            return;
        }
        $.ajax({
            url: '../management/index.php',
            type: 'post',
            data: {
                sub: 'adInfo'
            },
            dataType: 'json',
            success: function(jdata) {
                if (!$('#adDomain[type="text"]').val()) {
                    $('#adDomain').val(jdata.domainname);
                }
                if (!$('#adOU[type="text"]').val()) {
                    $('#adOU').val(jdata.ou);
                }
                if (!$('adUsername[type="text"]').val()) {
                    $('#adUsername').val(jdata.domainuser)
                }
                if (!$('adPassword[type="text"]').val()) {
                    $('#adPassword').val(jdata.domainpass)
                }
                if (!$('adPasswordLegacy[type="text"]').val()) {
                    $('#adPasswordLegacy').val(jdata.domainpasslegacy)
                }
            }
        });
    });
}
/**
 * Sets the timeout to rescan DOM.
 */
function setupTimeoutElement(selectors1, selectors2, timeout) {
    if (selectors1.length > 0) {
        $(selectors1).each(function(e) {
            if ($(this).is(':visible')) {
                form = $(this).parents('form');
                validator = form.validate(validatorOpts);
            }
        });
    }
    if (selectors2.length > 0) {
        $(selectors2).each(function(e) {
            if ($(this).is(':visible')) {
                $(this).on('keyup change blur focus focusout', function(e) {
                    return $(this).parents('form').validate(validatorOpts).element(this);
                });
            }
        });
    }
    setTimeout(function() {
        setupTimeoutElement(selectors1, selectors2, timeout);
    }, timeout);
}
/**
 * Presents the actual Login dialogs as required.
 */
function loginDialog(
    selector,
    url,
    submitButton,
    closeButton,
    titleText,
    formid,
    target,
    dialog
) {
    exportauth = $('.fog-export').val();
    deleteauth = $('.fog-delete').val();
    authneeded = true;
    label = submitButton;
    close = closeButton;
    title = titleText;
    switch (selector) {
        case '#exportDiv':
            css = 'btn-info';
            if (exportauth == 0) {
                authneeded = false;
            }
            break;
        case '#deleteDiv':
            css = 'btn-warning';
            if (deleteauth == 0) {
                authneeded = false;
            }
            break;
    }
    if (dialog) {
        dialog.close();
    }
    if (authneeded) {
        BootstrapDialog.show({
            title: title,
            message: 'Enter GUI Information<br/>'
                + '<div class="form-group">'
                + '<label for="username">'
                + 'Username:'
                + '</label>'
                + '<div class="input-group">'
                + '<input type="text" name="fogguiuser" id="username" class="form-control" required/>'
                + '</div>'
                + '</div>'
                + '<div class="form-group">'
                + '<label for="password">'
                + 'Password:'
                + '</label>'
                + '<div class="input-group">'
                + '<input type="password" name="fogguipass" id="password" class="form-control" required/>'
                + '</div>'
                + '</div>',
            buttons: [{
                label: label,
                cssClass: css,
                action: function(dialogItself) {
                    username = $('[name="fogguiuser"]').val();
                    password = $('[name="fogguipass"]').val();
                    ajaxRun(
                        username,
                        password,
                        url,
                        selector,
                        formid,
                        target,
                        authneeded,
                        dialogItself
                    );
                }
            }, {
                label: close,
                action: function(dialogItself) {
                    dialogItself.close();
                }
            }]
        });
    } else {
        sleeptime = 3000;
        BootstrapDialog.show({
            title: title,
            message: 'Preparing Actions',
            buttons: [{
                label: label,
                cssClass: css + ' ' + label.toLowerCase(),
                action: function(dialogItself) {
                    ajaxRun(
                        '',
                        '',
                        url,
                        selector,
                        formid,
                        target,
                        authneeded,
                        dialogItself
                    );
                }
            }, {
                label: close,
                action: function(dialogItself) {
                    dialogItself.close();
                }
            }],
            onshown: function(dialogRef) {
                bootstrapdialogopen = setTimeout(function() {
                    dialogRef.close();
                }, sleeptime);
            },
            onhidden: function(dialogRef) {
                clearTimeout(bootstrapdialogopen);
            }
        });
        setTimeout(function() {
            $('.'+label.toLowerCase()).trigger('click');
        }, 1000);
    }
}
/**
 * Export Dialog items.
 */
function exportDialog(url, dialog) {
    loginDialog(
        '#exportDiv',
        url,
        'Export',
        'Close',
        'Export Item(s)',
        'exportform',
        'exportDialog',
        dialog
    );
}
/**
 * Delete Dialog items.
 */
function deleteDialog(url, dialog) {
    loginDialog(
        '#deleteDiv',
        url,
        'Delete',
        'Close',
        'Delete Item(s)',
        'deleteform',
        'deleteDialog',
        dialog
    );
}
/**
 * Perform action in ajax.
 */
function ajaxRun(
    username,
    password,
    url,
    selector,
    formid,
    target,
    authneeded,
    dialog
) {
    ids = [];
    $('input[name="remitems[]"]').each(function(e) {
        ids[ids.length] = $(this).val();
    });
    var gdata = {};
    if (authneeded) {
        $.extend(
            gdata,
            {
                fogguiuser: username,
                fogguipass: password
            }
        );
    }
    if ($('#andFile').is(':checked')) {
        $.extend(
            gdata,
            {
                andFile: true
            }
        );
    }
    if (ids.length > 0) {
        $.extend(
            gdata,
            {
                remitems: ids
            }
        );
    }
    if ($('input[name="storagegroup"]').val()) {
        $.extend(
            gdata,
            {
                storagegroup: $('input[name="storagegroup"]').val()
            }
        );
    }
    $.ajax({
        url: url,
        type: 'post',
        data: gdata,
        dataType: 'json',
        beforeSend: function() {
            if (authneeded) {
                dialog.setMessage('Attempting to perform actions.');
            }
        },
        success: function(data) {
            if (data.error) {
                msg = data.error;
                type = BootstrapDialog.TYPE_WARNING;
                sleeptime = 5000;
            } else {
                msg = data.msg;
                type = BootstrapDialog.TYPE_SUCCESS;
                sleeptime = 2000;
            }
            dialog
                .setTitle(title)
                .setMessage(msg)
                .setType(type);
            if (data.error) {
                if (authneeded) {
                    setTimeout(function() {
                        eval(target+'(url, dialog)');
                    }, 3000);
                }
            } else {
                dialog.close();
                if (authneeded) {
                    $('<form id="'+formid+'" method="post" action="'+url+'"><input type="hidden" name="fogguiuser" value="'+username+'"/><input type="hidden" name="fogguipass" value="'+password+'"/><input type="hidden" name="nojson"/></form>').appendTo('body').submit().remove();
                } else {
                    $('<form id="'+formid+'" method="post" action="'+url+'"><input type="hidden" name="nojson"/></form>').appendTo('body').submit().remove();
                }
            }
        }
    });
}
/**
 * Advanced link clicked, only for hosts/groups/tasks.
 */
function advancedTaskLink() {
    $('.advanced-tasks-link').on('click', function(e) {
        e.preventDefault();
        $('.advanced-tasks').toggle();
    });
}
/**
 * Force click element for tasks.
 */
function forceClick(e) {
    $(this).off('click', function(evt) {
        evt.preventDefault();
    });
    if (AJAXTaskForceRequest) {
        AJAXTaskForceRequest.abort();
    }
    AJAXTaskForceRequest = $.ajax({
        url: $(this).attr('href'),
        type: 'post',
        beforeSend: function() {
            $(this).off('click').removeClass().addClass(
                'fa fa-refresh fa-spin fa-fw icon'
            );
        },
        success: function(gdata) {
            if (typeof gdata == 'undefined' || gdata === null) {
                return;
            }
            $(this).off('click').removeClass().addClass(
                'fa fa-angle-double-right fa-fw icon'
            );
        },
        error: function() {
            $(this).on('click').removeClass().addClass(
                'fa fa-bolt fa-fw icon'
            );
        }
    });
}
/**
 * Force click button display for tasks.
 */
function showForceButton() {
    $('.icon-forced').addClass('fa fa-angle-double-right fa-fw icon');
    $('.icon-force').addClass('fa fa-bolt fa-fw hand').on('click', forceClick);
}
/**
 * Shows progress bars.
 */
function showProgressBar() {
    $('.with-progress').hover(function(e) {
        var id = this.id.replace(/^progress[-_]/, ''),
            progress = $('#progress-'+id);
        progress
            .find('.min')
            .removeClass('min')
            .addClass('no-min')
            .end()
            .find('ul')
            .show();
    }, function(e) {
        var id = this.id.replace(/^progress[-_]/, ''),
            progress = $('#progress-'+id);
        progress
            .find('.no-min')
            .removeClass('no-min')
            .addClass('min')
            .end()
            .find('ul')
            .show();
    });
}
/**
 * Build the header row as needed.
 */
function buildHeaderRow(
    data,
    attributes
) {
    var rows = [];
    savedFilters = $.tablesorter.getFilters(Container);
    $.each(data, function(index, value) {
        var attribs = [];
        $.each(attributes[index], function(ind, val) {
            attribs[attribs.length] = ind + '="' + val + '"';
        });
        var row = '<th'
            + (
                attribs.length ?
                ' ' + attribs.join(' ') :
                ''
            )
            + ' data-column="'
            + index
            + '">'
            + value
            + '</th>';
        rows[rows.length] = row;
    });
    thead.html(
        '<tr class="header" role="row">'
        + rows.join()
        + '</tr>'
    );
    thead = $('thead', Container);
}
/**
 * Build the main rows of the tables.
 */
function buildRow(
    data,
    templates,
    attributes
) {
    var colspan = templates.length;
    var rows = [];
    checkedIDs = getChecked();
    $.each(data, function(index, value) {
        var row = '<tr id="'
        + node
        + '-'
        + value.id
        + '">';
        $.each(templates, function(ind, val) {
            var attribs = [];
            $.each(attributes[ind], function(i, v) {
                attribs[attribs.length] = i
                + '="'
                + v
                + '"';
            });
            row += '<td'
            + (
                attribs.length ?
                ' ' + attribs.join(' ') :
                ''
            )
            + '>'
            + val
            + '</td>';
        });
        $.each(value, function(ind, val) {
            row = row.replace(new RegExp('\\$\\{' + ind + '\\}', 'g'), $.trim(val));
        });
        rows[rows.length] = row
            + '</tr>';
    });
    tbody.empty().html(rows.join());
    rows = [];
    if (node == 'task' && (typeof sub == 'undefined' || sub == 'active')) {
        $.each(data, function(index, value) {
            $('#progress-'+value.host_id).remove();
            var percentRow = '';
            if (value.percent > 0 && value.percent < 100) {
                percentRow = '<tr id="progress-'
                + value.host_id
                + '" class="tablesorter-childRow with-progress">'
                + '<td colspan="'
                + colspan
                + '">'
                + '<div class="progress">'
                + '<div class="progress-bar progress-bar-success progress-bar-striped active" role="progressbar" aria-valuenow="'
                + parseInt(value.percent)
                + '" aria-valuemin="0" aria-valuemax="100" style="width:'
                + parseInt(value.percent)
                + '%">'
                + '<ul><li>'
                + value.elapsed
                + '/'
                + value.remains
                + '</li><li>'
                + parseInt(value.percent)
                + '%'
                + '</li><li>'
                + value.copied
                + ' of '
                + value.total
                + ' ('
                + value.bpm
                + '/min)'
                + '</li></ul>'
                + '</div>'
                + '</div>'
                + '</td>'
                + '</tr>';
                $('#'+node+'-'+value.id).addClass('with-progress').after(percentRow);
            }
        });
        showForceButton();
        showProgressBar();
    }
    $('.toggle-action:checkbox, .toggle-checkboxAction:checkbox').on('change',function(e) {
        checkedIDs = getChecked();
        console.log(checkedIDs);
    });
    setChecked(checkedIDs);
    tbody = $('tbody', Container);
    Container.trigger('updateAll');
}
/**
 * Checks the table.
 */
function TableCheck() {
    callme = 'hide';
    if ($('tbody > tr td', Container).length > 1) {
        if (Container.hasClass('noresults')) {
            Container.removeClass('noresults');
        }
        callme = 'show';
    }
    Container[callme]();
    ActionBox[callme]();
    ActionBoxDel[callme]();
    if (node == 'task' && $.inArray(sub, ['search', 'listhosts', 'listgroups']) < 0) {
        pauseUpdate = pauseButton.parent('p');
        cancelTasks = cancelButton.parent('p');
        pauseUpdate[callme]();
        cancelTasks[callme]();
    }
}
/**
 * Token Reset function.
 */
function tokenreset() {
    $('.resettoken').on('click', function(e) {
        e.preventDefault();
        $.ajax({
            url: '../status/newtoken.php',
            dataType: 'json',
            success: function(data) {
                $('.token').val(data);
            }
        });
    });
}
/**
 * Setup tablesorter parsing.
 */
function setupParserInfo() {
    if (typeof $.tablesorter == 'undefined') {
        return;
    }
    $.tablesorter.addParser({
        id: 'statusParser',
        is: function(s) {
            return false;
        },
        format: function (s, table, cell, cellIndex) {
            var tdField = $(cell);
            var i = tdField.find('i.state');
            var val = i.attr('data-state');
            if (val == 3) return 0;
            if (val == 2) return 1;
            if (val == 1) return 2;
        },
        type: 'numeric'
    });
    $.tablesorter.addParser({
        id: 'checkboxParser',
        is: function(s) {
            return false;
        },
        format: function (s, table, cell, cellIndex) {
            if (s.length < 1) return;
            checkbox = $(cell).find('input:checkbox');
            if (checkbox.length > -1) return checkbox.prop('value');
        },
        type: 'text'
    });
    $.tablesorter.addParser({
        id: 'dateParser',
        is: function(s) {
            return /\d{1,4}-\d{1,2}-\d{1,2} \d{1,2}:\d{1,2}:\d{1,2}/.test(s);
        },
        format: function(s) {
            s = s.replace(/\-/g,' ');
            s = s.replace(/:/g,' ');
            s = s.split(' ');
            return $.tablesorter.formatFloat(new Date(s[0], s[1], s[2], s[3], s[4], s[5]).getTime());
        },
        type: 'numeric'
    });
    $.tablesorter.addParser({
        id: 'questionParser',
        is: function(s) {
            return false;
        },
        format: function(s, table, cell, cellIndex) {
            if (s.length < 1) return;
            span = $(cell).find('span');
            if (span.length > -1) return span.prop('original-title');
        },
        type: 'text'
    });
    $.tablesorter.addParser({
        id: 'iParser',
        is: function(s) {
            return false;
        },
        format: function(s, table, cell, cellIndex) {
            i = $(cell).find('i');
            title = i.prop('original-title');
            return title;
        },
        type: 'text'
    });
    $.tablesorter.addParser({
        id: 'sizeParser',
        is: function(s) {
            return s.match(new RegExp(/[0-9]+(\.[0-9]+)?\ (iB|KiB|MiB|GiB|TiB|EiB|ZiB|YiB)/));
        },
        format: function(s) {
            if (s.length < 1) return;
            var suf = s.match(new RegExp(/(iB|KiB|MiB|GiB|TiB|EiB|ZiB|YiB)$/));
            if (typeof suf == 'null' || typeof suf == 'undefined' || suf == null) return;
            var num = parseFloat(suf.input.match(new RegExp(/^[0-9]+(\.[0-9]+)?/))[0]);
            switch(suf[0]) {
                case 'iB':
                    return num;
                case 'KiB':
                    return num*1024;
                case 'MiB':
                    return num*1024*1024;
                case 'GiB':
                    return num*1024*1024*1024;
                case 'TiB':
                    return num*1024*1024*1024*1024;
                case 'EiB':
                    return num*1024*1024*1024*1024*1024;
                case 'ZiB':
                    return num*1024*1024*1024*1024*1024*1024;
                case 'YiB':
                    return num*1024*1024*1024*1024*1024*1024*1024;
            }
        },
        type: 'numeric'
    });
    switch (node) {
        case 'task':
            if (typeof sub == 'undefined' || sub.indexOf('list') > -1) {
                headParser = {
                    5: {
                        sorter: 'statusParser'
                    }
                };
            } else {
                headParser = {
                    5: {
                        sorter: 'statusParser'
                    }
                };
            }
            break;
        case 'report':
            if (typeof sub != 'undefined') {
                switch (sub) {
                    case 'inventory':
                        headParser = {
                            0: {
                                sorter: 'checkboxParser'
                            },
                            1: {
                                sorter: 'sizeParser'
                            }
                        }
                        break;
                    case 'imaging-log':
                        headParser = {
                            2: {
                                sorter: 'dateParser'
                            },
                            3: {
                                sorter: 'dateParser'
                            }
                        };
                        break;
                    default:
                        headParser = {
                            0: {
                                sorter: 'checkboxParser'
                            }
                        };
                        break;
                }
            }
            break;
        case 'host':
            headParser = {
                0: {
                    sorter: 'questionParser'
                },
                1: {
                    sorter: 'checkboxParser'
                },
                2: {
                    sorter: 'iParser'
                }
            };
            break;
        case 'printer':
            headParser = {
                0: {
                    sorter: 'questionParser'
                },
                1: {
                    sorter: 'checkboxParser'
                }
            };
            break;
        case 'image':
            headParser = {
                0: {
                    sorter: 'iParser'
                },
                1: {
                    sorter: 'checkboxParser'
                },
                6: {
                    sorter: 'sizeParser'
                }
            };
            break;
        case 'snapin':
            headParser = {
                0: {
                    sorter: 'iParser'
                },
                1: {
                    sorter: 'checkboxParser'
                }
            };
            break;
        case 'storage':
        case 'user':
        case 'group':
        default:
            headParser = {
                0: {
                    sorter: 'checkboxParser'
                }
            };
            break;
    }
    if (lastsub == sub) {
        Container.trigger('filterResetSaved');
    }
    lastsub = sub;
    TableCheck();
}
/**
 * Table parsing information.
 */
function setupFogTableInformation() {
    if (typeof $.tablesorter == 'undefined') {
        return;
    }
    if (Container.length == 0 || !Container.has('thead')) {
        Container.hide();
    }
    Container.find('thead > tr').addClass('hand');
    if ($('tbody', Container).length < 1) {
        Container.hide();
    }
    $.tablesorter.themes.bootstrap = {
        table: 'table table-bordered table-striped table-responsive',
        header: 'bootstrap-header',
        iconSortNone: 'bootstrap-icon-unsorted',
        iconSortAsc: 'fa fa-chevron-up',
        iconSortDesc: 'fa fa-chevron-down'
    };
    Container.tablesorter({
        headers: headParser,
        headerTemplate: '{content} {icon}',
        theme: 'bootstrap',
        widgets: [
            "uitheme",
            "filter",
            "columns",
            "zebra"
        ],
        widgetOptions: {
            zebra: [
                "even",
                "odd"
            ],
            filter_reset: '.reset',
            filter_cssFilter: "form-control",
            filter_ignoreCase: true,
            filter_hideFilters: false,
            filter_hideEmpty: false,
            filter_liveSearch: true,
            filter_columnFilters: true,
            filter_placeholder: {
                search: 'Search...'
            },
            filter_childRows: false,
            filter_saveFilters: true // This is the magic that keeps it in place.
        }
    }).trigger('filterResetSaved'); // This is what resets it for page to page.
    setTimeout(setupParserInfo, 1000);
}
/**
 * Checkbox associations.
 */
function checkboxAssociations(selector,checkselectors) {
    $(selector).on('change',function(e) {
        allchecked = this.checked;
        $(checkselectors).each(function() {
            if ($(this).parent().is(':visible')) {
                if (this.checked !== allchecked) this.checked = allchecked;
            }
        });
        e.preventDefault();
    });
}
/**
 * More checkbox but default layout
 */
function checkboxToggleSearchListPages() {
    checkboxAssociations('.toggle-checkboxAction:checkbox','.toggle-action:checkbox');
}
/**
 * Checks the field is valid.
 */
function checkField(field, min, max) {
    // Trim the values to ensure we have valid data.
    field = field.trim();
    // If the format is not in # or * or */# or #-#/# fail.
    if (field === '' || field === undefined || field === null || !field.match(/^((\*(\/[0-9]+)?)|[0-9\-\,\/]+)/)) {
        return false;
    }
    // Split the field on commas.
    var v = field.split(',');
    // Loop through all of them.
    $.each(v,function(key,vv) {
        // Split the values on slash
        vvv = vv.split('/');
        // Set the step pattern
        step = (vvv[1] === '' || vvv[1] === undefined || vvv[1] === null ? 1 : vvv[1]);
        // Split the values on dash
        vvvv = vvv[0].split('-');
        // Set the new min and max values.
        _min = vvvv.length == 2 ? vvvv[0] : (vvv[0] == '*' ? min : vvv[0]);
        _max = vvvv.length == 2 ? vvvv[1] : (vvv[0] == '*' ? max : vvv[0]);
        result = true;
        if (!checkIntValue(step,min,max,true)) {
            result = false;
        } else if (!checkIntValue(_min,min,max,true)) {
            result = false;
        } else if (!checkIntValue(_max,min,max,true)) {
            result = false;
        }
    });
    return result;
}
/**
 * Chesk integer value.
 */
function checkIntValue(value,min,max,extremity) {
    var val = parseInt(value,10);
    if (value != val) return false;
    if (!extremity) return true;
    if (val >= min && val <= max) return true;
    return false;
}
/**
 * Check minute value.
 */
function checkMinutesField(minutes) {
    return checkField(minutes,0,59);
}
/**
 * Check Hours field.
 */
function checkHoursField(hours) {
    return checkField(hours,0,23);
}
/**
 * Check DOM field.
 */
function checkDOMField(DOM) {
    return checkField(DOM,1,31);
}
/**
 * Check Month Field.
 */
function checkMonthField(month) {
    return checkField(month,1,12);
}
/**
 * Check DOW Field.
 */
function checkDOWField(DOW) {
    return checkField(DOW,0,7);
}
/**
 * Checks product keys for hosts/groups.
 */
function ProductUpdate() {
    if (typeof($('#productKey').val()) == 'undefined') return;
    $('#productKey').val($('#productKey').val().replace(/[^\w+]|[_]/g,'').replace(/([\w+]{5})/g,'$1-').substring(0,29));
    $('#productKey').on('change keyup',function(e) {
        var start = this.selectionStart,
        end = this.selectionEnd;
        $(this).val($(this).val().replace(/[^\w+]|[_]/g,'').toUpperCase());
        $(this).val($(this).val().substring(0,25));
        this.setSelectionRange(start,end);
        e.preventDefault();
    }).focus(function(e) {
        var start = this.selectionStart,
        end = this.selectionEnd;
        $(this).val($(this).val().replace(/[^\w+]|[_]/g,'').toUpperCase());
        $(this).val($(this).val().substring(0,25));
        this.setSelectionRange(start,end);
        e.preventDefault();
    }).blur(function(e) {
        $(this).val($(this).val().replace(/([\w+]{5})/g,'$1-'));
        $(this).val($(this).val().substring(0,29));
        e.preventDefault();
    });
}
/**
 * Deploy Stuff
 */
function DeployStuff() {
    $('#checkDebug').on('change',function(e) {
        $('.hideFromDebug,.hiddeninitially').each(function(e) {
            $(this).toggle();
        });
        if (this.checked) {
            $('#scheduleInstant').prop('checked',true);
            $('.hiddeninitially').not(':hidden').hide();
        }
        e.preventDefault();
    });
    $('#isDebugTask').on('click, change', function() {
        if (this.checked) {
            $('.hideFromDebug, .hiddeninitially').not(':hidden').slideUp('fast');
        } else {
            $('.hideFromDebug').slideDown('fast');
        }
    });
    // Bind radio buttons for 'Single' and 'Cron' scheduled task
    $('input[name="scheduleType"]').on('click, change', function() {
        if (this.checked) {
            var content = $(this).closest('div');
            $('.hiddeninitially').not($(this)).slideUp('fast');
            content.next('.form-group.hiddeninitially').slideDown('fast');
            if ($(this).prop('id') == 'scheduleSingle') {
                $('#scheduleSingleTime').focus();
            }
        }
    });
    // Basic validation on deployment page
    var scheduleType = $('input[name="scheduleType"]:checked').val();
    var result = true;
    $('input[name="scheduleType"]').on('change',function() {
        scheduleType = this.value;
        $('form.deploy-container').on('submit',function() {
            if (scheduleType == 'single') {
                // Format check
                validateInput = $('#'+scheduleType+'Options > input').removeClass('error');
                if (!validateInput.val().match(/\d{4}\/\d{2}\/\d{2} \d{2}:\d{2}/)) {
                    result = false;
                    validateInput.addClass('error');
                }
            } else if (scheduleType == 'cron') {
                $(".cronOptions > input[name^='scheduleCron']",$(this)).each(function() {
                    result = validateCronInputs($(this));
                    if (result === false) return false;
                });
            }
            return result;
        }).each(function() {
            $("input[name^='scheduleCron']",this).each(function(id,value) {
                if (!validateCronInputs($(this))) $(this).addClass('error');
            }).blur(function() {
                if (!validateCronInputs($(this))) $(this).addClass('error');
            });
        });
    });
    // Auto open the calendar when chosen
    $('#scheduleSingle').on('click', function() {
        if (!this.checked) return this;
        $('#scheduleSingleTime').focus();
    });
}
/**
 * Remove mac fields
 */
function removeMACField() {
    $('.remove-mac').on('click', function(e) {
        $(e.target).tooltip('hide');
        e.preventDefault();
        remove = $(this).parents('.addrow');
        tr = remove.parents('tr');
        val = remove.closest('input[type="text"]').val();
        if (typeof(val) == 'undefined' || !val.length) {
            remove.remove();
            if ($('.addrow').length < 1) {
                tr.hide();
            }
            return;
        }
        url = remove.parents('form').prop('action');
        $.post(url,{additionalMACsRM: val});
        remove.remove();
        if ($('.addrow').length < 1) {
            tr.hide();
            $('.additionalMACsRow').hide().parents('tr').hide();
        }
    });
}
/**
 * Change mac element.
 */
function MACChange(data) {
    if (MACLookupTimer) clearTimeout(MACLookupTimer);
    MACLookupTimer = setTimeout(function(e) {
        $('#primaker').load('?sub=getmacman&prefix='+mac);
    }, MACLookupTimeout);
}
/**
 * Update mac fields.
 */
function MACUpdate() {
    setTimeout(function() {
        $('.mac-manufactor').each(function(evt) {
            input = $(this).parent().find('input');
            var mac = (
                input.size() ?
                input.val() :
                $(this).parent().find('.mac').html()
            );
            $(this).load('../management/index.php?sub=getmacman&prefix='+mac);
        });
    }, 1000);
    $('#mac, .additionalMAC').on('change keyup blur',function(e) {
        e.preventDefault();
        MACChange($(this));
    });
    setTimeout(function() {
        $('.add-mac').on('click', function(e) {
            var addrow = $('.addrowempty').clone().removeClass().addClass('addrow');
            $('.additionalMACsRow').show().parents('tr').show();
            $('.additionalMACsCell').append(addrow);
            removeMACField();
            e.preventDefault();
        });
    }, 1000);
    if ($('.additionalMACsCell').find('.addrow').size() < 1) {
        $('.additionalMACsRow').hide().parents('tr').hide();
    } else {
        $('.additionalMACsRow').show();
    }
    if ($('.pendingMACsCell').find('.addrow').length < 1) {
        $('.pendingMACsRow').hide().parents('tr').hide();
    } else {
        $('.pendingMACsRow').show();
    }
    removeMACField();
}
/**
 * Validate cron inputs as appropriate.
 */
function validateCronInputs(selector) {
    var funcs = {
        'scheduleCronMin': checkMinutesField,
        'scheduleCronHour': checkHoursField,
        'scheduleCronDOM': checkDOMField,
        'scheduleCronMonth': checkMonthField,
        'scheduleCronDOW': checkDOWField,
    };
    result = true;
    inputsToValidate = selector.removeClass('error');
    inputsToValidate.each(function() {
        var val = this.value;
        result = funcs[this.name](val);
        if (result === false) {
            $(this).addClass('error');
            return false;
        }
    });
    return result;
}
/**
 * Reset encryption data handler.
 */
function resetEncData(type, typeID) {
    $('#resetSecData').on('click', function() {
        postdata = {
            sub: 'clearAES'
        };
        if (typeID == 'host') {
            postdata = {id: id};
        } else {
            postdata = {groupid: id};
        }
        BootstrapDialog.show({
            title: 'Clear Encryption',
            message: 'Are you sure you wish to reset this '+type+' encryption data?',
            buttons: [{
                label: 'Yes',
                cssClass: 'btn-warning',
                action: function(dialogItself) {
                    $.post(
                        '../management/index.php?sub=clearAES',
                        postdata
                    );
                    dialogItself.close();
                }
            }, {
                label: 'No',
                cssClass: 'btn-info',
                action: function(dialogItself) {
                    dialogItself.close();
                }
            }]
        });
    });
}
/**
 * Special crons define.
 */
function specialCrons() {
    $('.specialCrons').on('change focus focusout', function(e) {
        e.preventDefault();
        switch(this.value) {
            case 'hourly':
                $(this).parents('.cronOptions').next().find('.scheduleCronMin').focus().val('0');
                $(this).parents('.cronOptions').next().find('.scheduleCronHour').focus().val('*');
                $(this).parents('.cronOptions').next().find('.scheduleCronDOM').focus().val('*');
                $(this).parents('.cronOptions').next().find('.scheduleCronMonth').focus().val('*');
                $(this).parents('.cronOptions').next().find('.scheduleCronDOW').focus().val('*');
                break;
            case 'daily':
                $(this).parents('.cronOptions').next().find('.scheduleCronMin').focus().val('0');
                $(this).parents('.cronOptions').next().find('.scheduleCronHour').focus().val('0');
                $(this).parents('.cronOptions').next().find('.scheduleCronDOM').focus().val('*');
                $(this).parents('.cronOptions').next().find('.scheduleCronMonth').focus().val('*');
                $(this).parents('.cronOptions').next().find('.scheduleCronDOW').focus().val('*');
                break;
            case 'weekly':
                $(this).parents('.cronOptions').next().find('.scheduleCronMin').focus().val('0');
                $(this).parents('.cronOptions').next().find('.scheduleCronHour').focus().val('0');
                $(this).parents('.cronOptions').next().find('.scheduleCronDOM').focus().val('*');
                $(this).parents('.cronOptions').next().find('.scheduleCronMonth').focus().val('*');
                $(this).parents('.cronOptions').next().find('.scheduleCronDOW').focus().val('0');
                break;
            case 'monthly':
                $(this).parents('.cronOptions').next().find('.scheduleCronMin').focus().val('0');
                $(this).parents('.cronOptions').next().find('.scheduleCronHour').focus().val('0');
                $(this).parents('.cronOptions').next().find('.scheduleCronDOM').focus().val('1');
                $(this).parents('.cronOptions').next().find('.scheduleCronMonth').focus().val('*');
                $(this).parents('.cronOptions').next().find('.scheduleCronDOW').focus().val('*');
                break;
            case 'yearly':
                $(this).parents('.cronOptions').next().find('.scheduleCronMin').focus().val('0');
                $(this).parents('.cronOptions').next().find('.scheduleCronHour').focus().val('0');
                $(this).parents('.cronOptions').next().find('.scheduleCronDOM').focus().val('1');
                $(this).parents('.cronOptions').next().find('.scheduleCronMonth').focus().val('1');
                $(this).parents('.cronOptions').next().find('.scheduleCronDOW').focus().val('*');
                break
        }
    });
}
