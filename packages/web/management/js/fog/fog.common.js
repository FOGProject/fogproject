var $_GET = getQueryParams(document.location.search),
    Common = {
        node: $_GET['node'],
        sub: $_GET['sub'],
        id: $_GET['id'],
        tab: $_GET['tab'],
        masks: {
            'mac': "##:##:##:##:##:##", 
            'productKey': "*****-*****-*****-*****-*****",
            'hostname': ""
        }
    };

(function($) {
    PNotify.prototype.options.styling = "fontawesome";
    
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
            ((isError) ? res.error : res.msg) || 'Bad Response', 
            (isError) ? 'error' : 'success'
        );
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
        $.ajax('', {
            type: method,
            url: url,
            async: true,
            data: opts,
            success: function(res) {
                Common.notifyFromAPI(res, false);
                if (cb && typeof(cb) === 'function')
                    cb();
            },
            error: function(res) {
                Common.notifyFromAPI(res.responseJSON, true);
                if (cb && typeof(cb) === 'function')
                    cb(res);
            }
        });
    }
    Common.processForm = function(form, cb) {
        if(!Common.validateForm(form)) {
            if (cb && typeof(cb) === 'function')
                cb('invalid');
            return;
        }
        var opts = form.serialize();
        Common.apiCall(form.attr('method'), form.attr('action'), opts, cb);
    };
    Common.massDelete = function(password, cb, table) {
        var remIds = [];
        var rows = undefined;
        if (table === undefined) {
            remIds = [Common.id];
        } else {
            rows = table.rows({selected: true});
            remIds = Common.getSelectedIds(rows);
        }
        
        var opts = {
            fogguipass: password,
            remitems: remIds
        };
        $.ajax('', {
            type: 'POST',
            url: '../management/index.php?node='+Common.node+'&sub=deletemulti',
            async: true,
            data: opts,
            success: function(res) {
                if(table !== undefined) {
                    rows.remove().draw(false);
                    table.rows({selected: true}).deselect();
                }
                Common.notifyFromAPI(res, false);
                if (cb && typeof(cb) === 'function')
                    cb();
            },
            error: function(res) {
                if (res.status == 401) {
                    bootbox.prompt({
                        title: 'Please re-enter your password',
                        inputType: 'password',
                        callback: function(result) {
                            if (result !== null) {
                                Common.massDelete(result, cb, table);
                            } else {
                                if (cb && typeof(cb) === 'function')
                                    cb('cancel');
                            }
                        }
                    });
                } else {
                    Common.notifyFromAPI(res.responseJSON, true);
                    if (cb && typeof(cb) === 'function')
                        cb(res);
                }
            }
        });
    };
    Common.registerTable = function(e, onSelect, opts) {
        if (opts === undefined)
            opts = {};
        
        if (opts.paging === undefined)
            opts.paging = true;
        if (opts.lengthChange === undefined)
            opts.lengthChange = true;
        if (opts.searching === undefined)
            opts.searching = true;
        if (opts.ordering === undefined)
            opts.ordering = true;
        if (opts.info === undefined)
            opts.info = true;
        if (opts.autoWidth === undefined)
            opts.autoWidth = false;
        if (opts.select === undefined)
            opts.select = true;
        if (opts.dom === undefined)
            opts.dom = 'Bfrtip';
        if (opts.buttons === undefined)
            opts.buttons = [
                'selected',
                'selectAll',
                'selectNone'
            ];
        var table = e.DataTable(opts);

        if (onSelect && typeof(onSelect) === 'function') {
            table.on('select', function( e, dt, type, indexes) {        
                onSelect(dt.rows({selected: true}));
            }).on('deselect', function( e, dt, type, indexes) {
                onSelect(dt.rows({selected: true}));
            });
        }

        return table;
    };
    Common.getSelectedIds = function(rows) {
        return rows.ids().toArray();
    }

    //Initialize Select2 Elements
    $('.select2').select2({
        width: '100%'
    });

    Common.iCheck = function(match) {
        match = match || 'input'
        $(match).iCheck({
            checkboxClass: 'icheckbox_square-blue',
            radioClass: 'iradio_square-blue',
            increaseArea: '20%' // optional
        });
    }
    Common.iCheck();

    $(":input").inputmask();
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
