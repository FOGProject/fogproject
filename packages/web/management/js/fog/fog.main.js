$(function() {
    // Multi delete stuff
    $('#action-box,#action-boxdel').submit(function() {
        var checked = getChecked()
        $('input[name="'+node+'IDArray"]').val(checked.join(','));
    });
    // Advanced Tasks stuff
    $('.advanced-tasks-link').click(function(e) {
        $('#advanced-tasks').toggle();
    });
    $('#FOG_QUICKREG_IMG_ID').change(function() {
        $.ajax({
            url: '?node=about',
            cache: false,
            type: 'POST',
            data: {
                sub: 'getOSID',
                image_id: $(this).val()
            },
            success: function(data) {
                $('#FOG_QUICKREG_OS_ID').html(data.replace(/\"/g,""));
            }
        });
    });
    // Make button
    $('#adClear').html('<br/><input type="button" id="clearAD" value="Clear Fields"></input>');
    // Clear fields
    $('#clearAD').on('click',function(event) {
        clearDoms = ['#adOU[type="text"]','#adDomain','#adUsername','#adPassword','#adPasswordLegacy'];
        $.each(clearDoms,function(index,value) {
            $(value).val('');
        });
        $('#adEnabled').prop('checked',false);
    });
    // Bind to AD Settings checkbox
    $('#adEnabled').click(function() {
        if (!this.checked) return this;
        $.ajax({
            url: '../management/index.php',
            type: 'POST',
            timeout: 1000,
            data: {sub: 'adInfo'},
            dataType: 'json',
            success: function(data) {
                if (!$('#adDomain[type=text]').val()) $("#adDomain").val(data.domainname);
                if (!$('#adOU[type=text]').val()) $("#adOU").val(data.ou);
                if (!$('#adUsername[type=text]').val()) $("#adUsername").val(data.domainuser);
                if (!$('#adPassword[type=text]').val()) $("#adPassword").val(data.domainpass);
                if (!$('#adPasswordLegacy[type=text]').val()) $("#adPasswordLegacy").val(data.domainpasslegacy);
            }
        });
    });
    var allRadios = $('.primary,.default,.action');
    var radioChecked;
    var setCurrent = function(e) {
        var obj = e.target;
        radioChecked = $(obj).is(':checked');
    }
    var setCheck = function(e) {
        if (e.type == 'keypress' && e.charCode != 32) return false;
        var obj = e.target;
        $(obj).prop('checked',!radioChecked);
    }
    $.each(allRadios, function(i, val) {
        if (this.id.length > 0) {
            var label = $('label[for='+this.id+']');
            var element = label.prev();
        } else {
            var label = $('input[value='+$(this).val()+'].action');
            var element = label;
        }
        $(this).bind('mousedown keydown', function(e) {setCurrent(e);});
        label.bind('mousedown keydown', function(e) {
            e.target = $(element);
            setCurrent(e);
        });
        $(this).bind('click', function(e) {setCheck(e);});
    });
    $('.trigger_expand').click(function() {
        var all = $('.expand_trigger'),
        active = all.filter('.active');
        if (all.length && all.length === active.length) {
            // All open; close them
            all.removeClass('active').next().slideUp();
            $('.trigger_expand').html('<a href="#" class="trigger_expand"><h3>Expand All</h3></a>');
        } else {
            all.not('.active').addClass('active').next().slideDown();
            $('.trigger_expand').html('<a href="#" class="trigger_expand"><h3>Collapse All</h3></a>');
        }
        return false;
    });
    // Assign DOM elements
    if (typeof($(".pigz").slider) == typeof(Function)) {
        $(".pigz").slider({
            min: 0,
            max: 22,
            range: 'min',
            value: $(".showVal.pigz").val(),
            slide: function(event, ui) {
                $(".showVal.pigz").val(ui.value);
            }
        });
    }
    if (typeof($(".loglvl").slider) == typeof(Function)) {
        $(".loglvl").slider({
            min: 0,
            max: 7,
            range: 'min',
            value: $(".showVal.loglvl").val(),
            slide: function(event, ui) {
                $(".showVal.loglvl").val(ui.value);
            }
        });
    }
    if (typeof($(".inact").slider) == typeof(Function)) {
        $(".inact").slider({
            min: 1,
            max: 24,
            range: 'min',
            value: $(".showVal.inact").val(),
            slide: function(event, ui) {
                $(".showVal.inact").val(ui.value);
            }
        });
    }
    if (typeof($(".regen").slider) == typeof(Function)) {
        $(".regen").slider({
            step: 0.25,
            min: 0.25,
            max: 24,
            range: 'min',
            value: $(".showVal.regen").val(),
            slide: function(event, ui) {
                $(".showVal.regen").val(ui.value);
            }
        });
    }
    // Show Password information
    $(':password').not('[name="fakepasswordremembered"]').after('&nbsp;<i class="fa fa-eye-slash fa-2x"></i>&nbsp;');
    $(':password').next('i').mousedown(function() {
        $(this).removeClass('fa-eye-slash').addClass('fa-eye');
        $(this).prev('input').prop('type','text');
    }).mouseup(function() {
        $(this).removeClass('fa-eye').addClass('fa-eye-slash');
        $(this).prev('input').prop('type','password');
    });
    // Process FOG JS Variables
    $('.fog-variable').fogVariable();
    // Process FOG Message Boxes
    $('.fog-message-box').fogMessageBox();
    // Placeholder support
    $('input[placeholder]').placeholder();
    // Nav Menu: Add hover label
    $('.menu li a').each(function() {
        $(this).tipsy({
            gravity: $.fn.tipsy.autoNS
        }).mouseenter(function() {
            $('.tipsy').css({
                'min-width': '35px'
            });
        });
    });
    // Tooltips
    HookTooltips();
    // Search boxes
    $('.search-input').fogAjaxSearch();
    $('#content-inner').fogTableInfo().trigger('updateAll');
    $(Container).fogTableInfo().trigger('updateAll');
    function format(icon) {
        if (!icon.id) return icon.text;
        var _icon = $('<i class="fa fa-'+icon.element.value.toLowerCase()+' fa-1x">'+icon.text+'</i>');
        return _icon;
    }
    $('select').not('[name="nodesel"],[name="groupsel"]').select2();
    $('[name="icon"]').select2({
        templateResult: format,
        templateSelection: format
    });
    $('#scheduleSingleTime').datetimepicker({
        dateFormat: 'yy/mm/dd',
        timeFormat: 'HH:mm'
    });
    // Snapin uploader for existing snapins
    $('#snapin-upload').click(function() {
        $('#uploader').html('<input class="snapinfile-input" type="file" name="snapin"/>').find('input').click();
    });
    // Host Management - Select all checkbox
    $('.header input[type="checkbox"][name="no"]').click(function() {
        $('input[type="checkbox"][name^="HID"]').prop('checked',this.checked);
    });
    $('#checkAll').click(function() {
        selectAll = this.checked;
        $('.checkboxes').each(function () {
            this.checked = selectAll;
        });
    });
    // Tabs
    // Blackout - 9:14 AM 30/11/2011
    $('.organic-tabs').organicTabs({targetID: '#tab-container'});
    // Hides all the divs in the Service menu
    $('#tab-container-1 > div').hide();
    // Shows the div of the containing element.
    $('#tab-container-1 > a').click(function() {
        $('#tab-container-1 div#'+$(this).attr('id')).fadeToggle('slow','swing');
        return false;
    });
    $('input[name=export]').click(function(e) {
        e.preventDefault();
        url = $(this).parents('form').attr('action');
        exportDialog(url);
    });
    $('input[name=delete]').click(function(e) {
        e.preventDefault();
        url = $(this).parents('form').attr('action');
        deleteDialog(url);
    });
    $('#csvsub,#pdfsub').click(function(e) {
        e.preventDefault();
        exportDialog($(this).prop('href'));
    });
});
function debug(txt) {
    if (console) console.log(txt);
}
function HookTooltips() {
    setTimeout(function() {
        $('.tipsy').remove();
        $('a[title],.remove-mac[title], .add-mac[title], .icon-help[title], .task-name[title], .icon[title], .icon-ping[title], .icon-ping-down[title], .icon-ping-up[title], img[title]', Content).tipsy({
            gravity: $.fn.tipsy.autoNS
        }).mouseenter(function() {
            $('.tipsy').css({
                'min-width': '35px'
            });
        });
    }, 400);
}
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
        result = funcs[this.id](val);
        if (result === false) {
            $(this).addClass('error');
            return false;
        }
    });
    return result;
}
function DeployStuff() {
    $('#checkDebug').change(function(e) {
        $('.hideFromDebug,.hidden').each(function(e) {
            if ($(this).prev('p').length > 0) $(this).prev('p').toggle();
            else $(this).toggle();
        });
        if (this.checked) {
            $('#scheduleInstant').prop('checked',true);
            $('.hidden').parent().is(':visible').not(':hidden').hide();
        }
        e.preventDefault();
    });
    // Bind radio buttons for 'Single' and 'Cron' scheduled task
    $('input[name="scheduleType"]').click(function() {
        var content = $(this).parents('p').parent().find('p').eq($(this).parent().index());
        if (this.checked && !$('#isDebugTask').is(':checked')) {
            content.slideDown('fast').siblings('.hidden').slideUp('fast');
        } else if (!$('#isDebugTask').is(':checked')) {
            content.slideDown('fast');
            $('.calendar').remove();
            $('.error').removeClass('error');
        }
    });
    $('#specialCrons').change(function(e) {
        e.preventDefault();
        switch(this.value) {
            case 'hourly':
                $('#scheduleCronMin').focus().val('0');
                $('#scheduleCronHour').focus().val('*');
                $('#scheduleCronDOM').focus().val('*');
                $('#scheduleCronMonth').focus().val('*');
                $('#scheduleCronDOW').focus().val('*');
                break;
            case 'daily':
                $('#scheduleCronMin').focus().val('0');
                $('#scheduleCronHour').focus().val('0');
                $('#scheduleCronDOM').focus().val('*');
                $('#scheduleCronMonth').focus().val('*');
                $('#scheduleCronDOW').focus().val('*');
                break;
            case 'weekly':
                $('#scheduleCronMin').focus().val('0');
                $('#scheduleCronHour').focus().val('0');
                $('#scheduleCronDOM').focus().val('*');
                $('#scheduleCronMonth').focus().val('*');
                $('#scheduleCronDOW').focus().val('0');
                break;
            case 'monthly':
                $('#scheduleCronMin').focus().val('0');
                $('#scheduleCronHour').focus().val('0');
                $('#scheduleCronDOM').focus().val('1');
                $('#scheduleCronMonth').focus().val('*');
                $('#scheduleCronDOW').focus().val('*');
                break;
            case 'yearly':
                $('#scheduleCronMin').focus().val('0');
                $('#scheduleCronHour').focus().val('0');
                $('#scheduleCronDOM').focus().val('1');
                $('#scheduleCronMonth').focus().val('1');
                $('#scheduleCronDOW').focus().val('*');
                break
        }
        $('.placeholder').each(function() {
            $(this).focus().val('*');
        });
    });
    // Basic validation on deployment page
    var scheduleType = $('input[name="scheduleType"]:checked').val();
    var result = true;
    $('input[name="scheduleType"]').change(function() {
        scheduleType = this.value;
        $('form#deploy-container').submit(function() {
            if (scheduleType == 'single') {
                // Format check
                validateInput = $('#'+scheduleType+'Options > input').removeClass('error');
                if (!validateInput.val().match(/\d{4}\/\d{2}\/\d{2} \d{2}:\d{2}/)) {
                    result = false;
                    validateInput.addClass('error');
                }
            } else if (scheduleType == 'cron') {
                $("p#cronOptions > input[name^='scheduleCron']",$(this)).each(function() {
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
    $('#scheduleSingle').click(function() {
        if (!this.checked) return this;
        $('#scheduleSingleTime').focus();
    });
}
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
function checkIntValue(value,min,max,extremity) {
    var val = parseInt(value,10);
    if (value != val) return false;
    if (!extremity) return true;
    if (val >= min && val <= max) return true;
    return false;
}
function checkMinutesField(minutes) {
    return checkField(minutes,0,59);
}
function checkHoursField(hours) {
    return checkField(hours,0,23);
}
function checkDOMField(DOM) {
    return checkField(DOM,1,31);
}
function checkMonthField(month) {
    return checkField(month,1,12);
}
function checkDOWField(DOW) {
    return checkField(DOW,0,7);
}
function checkboxAssociations(selector,checkselectors) {
    $(selector).change(function(e) {
        allchecked = this.checked;
        $(checkselectors).each(function() {
            if ($(this).parent().is(':visible')) {
                if (this.checked !== allchecked) this.checked = allchecked;
            }
        });
        e.preventDefault();
    });
}
function checkboxToggleSearchListPages() {
    checkboxAssociations('.toggle-checkboxAction:checkbox','.toggle-action:checkbox');
}
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
function loginDialog(selector,url,submitButton,closeButton,titleText,formid,target) {
    exportauth = $('.fog-export').val();
    deleteauth = $('.fog-delete').val();
    authneeded = true;
    switch (selector) {
        case '#exportDiv':
            if (exportauth == 0) {
                authneeded = false;
            }
            break;
        case '#deleteDiv':
            if (deleteauth == 0) {
                authneeded = false;
            }
            break;
    }
    if (authneeded) {
        $(selector).html('<p>Enter GUI Login</p><p>Username: <input type="text" name="fogguiuser"/></p><p>Password: <input type="password" name="fogguipass"/></p>').dialog({
            open: function() {
                $(this).find('[name=export]').hide();
                $(this).find('[name=delete]').hide();
            },
            buttons: [{
                text: submitButton,
                type: 'submit',
                click: function(e) {
                    e.preventDefault();
                    username = $('[name=fogguiuser]').val();
                    password = $('[name=fogguipass]').val();
                    ajaxRun(username,password,url,selector,formid,target,authneeded);
                }
            },{
                text: closeButton,
                click: function() {
                    $(this).dialog('close');
                }
            }],
            modal: true,
            resizable: false,
            draggable: false,
            autoResize: true,
            title: titleText
        });
        $('[name=fogguiuser],[name=fogguipass]').keypress(function(e) {
            if (e.keyCode == $.ui.keyCode.ENTER) {
                $(this).parents('.ui-dialog').find('button[type=submit]').trigger('click');
            }
        });
    } else {
        ajaxRun('', '', url, selector, formid, target, authneeded);
    }
}
function exportDialog(url) {
    loginDialog('#exportDiv',url,'Export','Close','Export Item(s)','exportform','exportDialog');
}
function deleteDialog(url) {
    loginDialog('#deleteDiv',url,'Delete','Close','Delete Item(s)','deleteform','deleteDialog');
}
function ajaxRun(username,password,url,selector,formid,target,authneeded) {
    ids = new Array();
    $('input[name="remitems[]"]').each(function() {
        ids[ids.length] = $(this).val();
    });
    $.ajax({
        url: url,
        type: 'POST',
        data: {
            fogguiuser: username,
            fogguipass: password,
            andFile: $('#andFile').is(':checked'),
            remitems: ids,
            storagegroup: $('input[name="storagegroup"]').val()
        },
        dataType: 'json',
        beforeSend: function() {
            if (authneeded) {
                $(selector).html('<p>Attempting to perform actions.</p>');
            }
        },
        complete: function(data) {
            str = new RegExp('^[#][#][#]');
            if (!str.test(data.responseText)) {
                if (ids.length > 0) {
                    location.href = '?node='+node;
                } else {
                    if (authneeded) {
                        $(selector).html('<form id="'+formid+'" method="post" action="'+url+'"><input type="hidden" name="fogguiuser" value="'+username+'"/><input type="hidden" name="fogguipass" value="'+password+'"/></form>').dialog('close');
                    } else {
                        $(selector).append('<form id="'+formid+'" method="post" action="'+url+'"><input type="hidden" name="fogguiuser" value="'+username+'"/><input type="hidden" name="fogguipass" value="'+password+'"/></form>');
                    }
                    $('#'+formid).submit();
                }
            } else {
                setTimeout(function() {
                    eval(target+'(url)');
                },3000);
                $(selector).html('<p>'+data.responseText.replace(/^[#][#][#]/g,'')+'</p>');
            }
        }
    });
}
