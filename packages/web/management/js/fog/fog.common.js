var $_GET = getQueryParams(document.location.search),
    Common = {
        node: $_GET['node'],
        sub: $_GET['sub'],
        id: $_GET['id'],
        tab: $_GET['tab'],
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
    Common.validateForm = function(form) {
        var scrolling = false;
        var isError = false;
        form.find(":input").each(function(i, e) {
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
                var minLength = $(e).attr("minlength") || "-1";
                var maxLength = $(e).attr("maxlength") || "-1";
                var exactLength = $(e).attr("exactlength") || "-1";

                minLength = parseInt(minLength);
                maxLength = parseInt(maxLength);
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
    },
    Common.apiCall = function(method, url, opts, cb) {
        Pace.track(function(){
            $.ajax('', {
                type: method,
                url: url,
                async: true,
                data: opts,
                success: function(res) {
                    Common.notifyFromAPI(res, false);
                    if (cb && typeof(cb) === 'function') {
                        cb();
                    }
                },
                error: function(res) {
                    Common.notifyFromAPI(res.responseJSON, true);
                    if (cb && typeof(cb) === 'function') {
                        cb(res);
                    }
                }
            });
        });
    }
    Common.processForm = function(form, cb) {
        // Serialize before disabling, so we can read inputs
        var opts = form.serialize();
        Common.setContainerDisable(form, true);
        if(!Common.validateForm(form)) {
            Common.setContainerDisable(form, false);
            if (cb && typeof(cb) === 'function')
                cb('invalid');
            return;
        }
        var method = form.attr('method'),
            action = form.attr('action');
        Common.apiCall(method,action,opts,function(err) {
            Common.setContainerDisable(form, false);
            if (cb && typeof(cb) === 'function')
                cb(err);
        });
    };
    Common.massExport = function(password, cb) {
        var opts = {
            fogguipass: password
        };
        Pace.track(function() {
            $.ajax('', {
                type: 'POST',
                url: '../management/export.php?type='+Common.node,
                async: true,
                data: opts,
                success: function(res) {
                    Common.notifyFromAPI(res, false);
                    if (cb && typeof(cb) === 'function') {
                        cb();
                    }
                },
                error: function(res) {
                    if (res.status == 401) {
                        if (cb && typeof(cb) === 'function') {
                            cb(res);
                        }
                    } else {
                        Common.notifyFromAPI(res.responseJSON, true);
                        if (cb && typeof(cb) === 'function') {
                            cb(res);
                        }
                    }
                }
            });
        });
    };
    Common.massDelete = function(password, cb, table, remIds) {
        if(!remIds) {
            remIds = [];
        }

        var rows = undefined;

        if (table === undefined) {
            remIds = [Common.id];
        } else {
            rows = table.rows({selected: true});
            remIds = rows.ids().toArray();
        }

        var opts = {
            fogguipass: password,
            confirmdel: 1,
            remitems: remIds
        };

        Pace.track(function(){
            $.ajax('', {
                type: 'post',
                url: '../management/index.php?node='
                + Common.node
                + '&sub=deletemulti',
                async: true,
                data: opts,
                success: function(res) {
                    if (table !== undefined) {
                        rows.remove().draw(false);
                        table.rows({selected: true}).deselect();
                    }
                    Common.notifyFromAPI(res, false);
                    if (cb && typeof(cb) === 'function') {
                        cb();
                    }
                },
                error: function(res) {
                    if (res.status == 401) {
                        if (cb && typeof(cb) === 'function') {
                            cb(res);
                        }
                    } else {
                        Common.notifyFromAPI(res.responseJSON, true);
                        if (cb && typeof(cb) === 'function') {
                            cb(res);
                        }
                    }
                }
            });
        });
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

    var formatEntry = function (entry) {
        return 'wee';
    };

    uniSearchField.on("select2:selecting", function(e) { 
        e.preventDefault();
        var url = e.params.args.data.url;
        uniSearchField.prop('disable', true);
        window.location.href = url;
    });

    uniSearchField.select2({
        width: '100%',
        placeholder: 'Search...',
        minimumInputLength: 1,
        multiple: true,
        maximumSelectionSize: 1,
     //   templateResult: formatEntry,
      //    templateSelection: formatEntry,
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
    qs = qs.split("+").join(" ");
    var params = {},tokens,re = /[?&]?([^=]+)=([^&]*)/g;
    while (tokens = re.exec(qs)) {
        params[decodeURIComponent(tokens[1])] = decodeURIComponent(tokens[2]);
    }
    return params;
}
