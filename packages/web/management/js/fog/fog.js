/****************************************************
 * FOG Dashboard JS
 *	Author:		Blackout
 *	Created:	10:05 AM 16/04/2011
 *	Revision:	$Revision: 2430 $
 *	Last Update:	$LastChangedDate: 2014-10-16 11:55:06 -0400 (Thu, 16 Oct 2014) $
 ***/
var startTime = new Date().getTime();
var validatoropts,
    screenview,
    callme;
var $_GET = getQueryParams(document.location.search),
    node = $_GET['node'],
    sub = $_GET['sub'],
    tab = $_GET['tab'],
    _L = new Array(),
    StatusAutoHideTimer,
    StatusAutoHideDelay = 30000,
    AJAXTaskUpdate,
    AJAXTaskForceRequest,
    AJAXTaskRunning,
    ActiveTasksUpdateInterval = 5000,
    ActionBox,
    ActionBoxDel,
    Content,
    Container,
    Loader,
    savedFilters,
    checkedIDs;
// Searching
_L['PERFORMING_SEARCH'] = 'Searching...';
_L['ERROR_SEARCHING'] = 'Search failed';
_L['SEARCH_LENGTH_MIN'] = 'Search query too short';
_L['SEARCH_RESULTS_FOUND'] = '%1 result%2 found';
// Active Tasks
_L['NO_ACTIVE_TASKS'] = "No results found";
_L['UPDATING_ACTIVE_TASKS'] = "Fetching active tasks";
_L['ACTIVE_TASKS_FOUND'] = '%1 active task%2 found';
_L['ACTIVE_TASKS_LOADING'] = 'Loading...';
function getChecked() {
    var val = [];
    $('.toggle-action:checkbox:checked').each(function(i) {
        if ($(this).parent().is(':visible')) {
            val[i] = this.value;
        }
    });
    return val;
}
function setTipsyStuff() {
    $('.box,.icon,.icon-ping-up,.icon-ping-down,#logo > h1 > a > img').tipsy({
        gravity: $.fn.tipsy.autoNS
    }).mouseenter(function() {
        $('.tipsy').css({
            'min-width': '35px',
        });
    });
}
function setEditFocus() {
    $('input,select,textarea').not('[type="checkbox"],[name="groupsel"],[name="nodesel"],[name="ulang"]').change(function(e) {
        e.preventDefault();
        field = $(this);
        field.not(':focus') ? field.next('i').hide() : field.append('<i class="fa fa-pencil fa-fw"></i>');
    });
}
function setChecked(ids) {
    $('.toggle-action:checkbox').not(':checked').each(function(i) {
        if ($(this).parent().is(':visible')) {
            if ($.inArray(this.value,ids) < 0) return;
            this.checked = true;
        }
    });
}
function getQueryParams(qs) {
    qs = qs.split("+").join(" ");
    var params = {},tokens,re = /[?&]?([^=]+)=([^&]*)/g;
    while (tokens = re.exec(qs)) params[decodeURIComponent(tokens[1])] = decodeURIComponent(tokens[2]);
    return params;
}
function AJAXServerTime() {
    $.ajax({
        url: '../status/getservertime.php',
        type: 'post',
        success: function(data) {
            $('#showtime').html(data);
        },
        complete : function() {
            setTimeout(AJAXServerTime, 60000 - ((new Date().getTime() - startTime) % 60000));
        }
    });
}
(function($) {
    $.validator.addMethod(
            'regex',
            function(value, element,regexp) {
                var re = new RegExp(regexp);
                return this.optional(element) || re.test(value);
            },
            "Invalid Input"
            );
    screenview = $('#screenview').attr('value');
    setTipsyStuff();
    setEditFocus();
    Content = $('#content');
    Loader = $('#loader');
    Loader.append('&nbsp;<i></i>&nbsp;');
    i = Loader.find('i');
    ActionBox = $('#action-box');
    ActionBoxDel = $('#action-boxdel');
    callme = 'hide';
    if ((typeof(sub) == 'undefined' || $.inArray(sub,['list','search','storageGroup','listhosts','listgroups']) > -1) && $('.no-active-tasks').length < 1) callme = 'show';
    ActionBox[callme]();
    ActionBoxDel[callme]();
    setupParserInfo();
    setupFogTableInfoFunction();
    AJAXServerTime();
    $('.list,.search,.storageGroup,.listhosts,.listgroups').click(function(e) {
        if (sub && $.inArray(sub,['list','search','storageGroup','listhosts','listgroups']) < 0) {
            return;
        }
        e.preventDefault();
        url = $(this).prop('href');
        this.listAJAX = $.ajax({
            cache: false,
            context: this,
            url: $(this).prop('href'),
            dataType: 'json',
            beforeSend: function() {
                Loader
                    .addClass('loading')
            },
            success: function(response) {
                if (response === null || response.data === null) {
                    dataLength = 0;
                } else {
                    dataLength = response.data.length;
                }
                $('.title').html(response.title);
                thead = $('thead', Container);
                tbody = $('tbody', Container);
                LastCount = dataLength;
                Loader.removeClass('loading')
                    .fogStatusUpdate(_L['SEARCH_RESULTS_FOUND']
                            .replace(/%1/,LastCount)
                            .replace(/%2/,LastCount != 1 ? 's' : '')
                            )
                    .find('i')
                    .removeClass()
                    .addClass('fa fa-exclamation-circle fa-1x fa-fw');
                if (dataLength > 0) {
                    buildHeaderRow(response.headerData, response.attributes, 'th');
                    thead = $('thead', Container);
                    buildRow(response.data, response.templates, response.attributes, 'td');
                }
                TableCheck();
                this.listAJAX = null;
                checkboxToggleSearchListPages();
            }
        });
    });
    /**
     * On any form submission, attempt to trim the input fields automatically.
     */
    $('input[type!="file"], textarea').focusout(function() {
        this.value=$(this).val().trim();
    });
    $('form').children().each(function() {
        this.value=$(this).val().trim();
    });
    if ($.inArray(sub,['list','listhosts','listgroups','storageGroup']) < 0 && screenview == 'list') {
        $('.list').trigger('click');
    }
})(jQuery);
function forceClick(e) {
    $(this).unbind('click').click(function(evt) {evt.preventDefault();});
    if (AJAXTaskForceRequest) AJAXTaskForceRequest.abort();
    AJAXTaskForceRequest = $.ajax({
        type: 'POST',
        url: $(this).attr('href'),
        beforeSend: function() {
            $(this).unbind('click').removeClass().addClass('fa fa-refresh fa-spin fa-fw icon');
        },
        success: function(data) {
            if (typeof(data) == 'undefined' || data === null) return;
            $(this).unbind('click').removeClass().addClass('fa fa-angle-double-right fa-fw icon');
        },
        error: function() {
            $(this).bind('click').removeClass().addClass('fa fa-bolt fa-fw icon');
        }
    });
    e.preventDefault();
}
$.fn.exists = function() {return this.length > 0;}
$.fn.isIE8 = function() {return $.browser.msie && parseInt($.browser.version, 10) <= 8;}
$.fn.fogVariable = function(opts) {
    if (this.length == 0) return this;
    return this.each(function() {
        window[$(this).prop('id').toString()] = $(this).html().toString();
        $(this).remove();
    });
}
$.fn.fogAjaxSearch = function(opts) {
    if (this.length == 0) return this;
    var Defaults = {
        URL: $('#search-wrapper').prop('action'),
        Container: '#search-content,#active-tasks',
        SearchDelay: 400,
        SearchMinLength: 1,
    };
    var SearchAJAX = null;
    var SearchTimer;
    var SearchLastQuery;
    var Options = $.extend({},Defaults,opts || {});
    Container = $(Options.Container);
    if (!Container.length) return this;
    callme = 'hide';
    if ($('tbody > tr', Container).filter('.no-active-tasks').length > 0
        || $.inArray(sub,['list','listhosts','listgroups','storageGroup']) > -1) {
        callme = 'show';
    }
    Container[callme]().fogTableInfo().trigger('updateAll');
    ActionBox[callme]();
    ActionBoxDel[callme]();
    return this.each(function() {
        var searchElement = $(this);
        var SubmitButton = $('#'+searchElement.prop('id')+'-submit');
        SubmitButton.append('<i class="fa fa-play fa-1x icon"></i>');
        searchElement.keyup(function() {
            if (this.SearchTimer) clearTimeout(this.SearchTimer);
            this.SearchTimer = setTimeout(PerformSearch,Options.SearchDelay);
        }).focus(function() {
            var searchElement = $(this).removeClass('placeholder');
            if (searchElement.val() == searchElement.prop('placeholder')) searchElement.val('');
        }).blur(function() {
            var searchElement = $(this);
            if (searchElement.val() == '') {
                searchElement.addClass('placeholder').val(searchElement.prop('placeholder'));
                if (this.SearchAJAX) this.SearchAJAX.abort();
                if (this.SearchTimer) clearTimeout(this.SearchTimer);
                Loader.fogStatusUpdate();
                $('tbody',Container).empty().parents('table').hide();
            }
        }).each(function() {
            var searchElement = $(this);
            if (searchElement.val() != searchElement.prop('placeholder')) searchElement.val('');
        }).parents('form').submit(function(e) {
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
                Loader.hide();
                return this;
            }
            if (this.SearchAJAX) this.SearchAJAX.abort();
            this.SearchAJAX = $.ajax({
                type: $('#search-wrapper').prop('method'),
                cache: false,
                url: $('#search-wrapper').prop('action'),
                dataType: 'json',
                data: {crit: Query},
                beforeSend: function() {
                    Loader.fogStatusUpdate();
                    SubmitButton.addClass('searching').find('i').removeClass().addClass('fa fa-spinner fa-pulse fa-fw');
                },
                success: function(response) {
                    dataLength = response === null || response.data === null ? dataLength = 0 : response.data.length;
                    SubmitButton.removeClass('searching').find('i').removeClass().addClass('fa fa-play');
                    thead = $('thead',Container);
                    tbody = $('tbody',Container);
                    LastCount = dataLength;
                    Loader.removeClass('loading').fogStatusUpdate(_L['SEARCH_RESULTS_FOUND'].replace(/%1/,LastCount).replace(/%2/,LastCount != 1 ? 's' : '')).find('i').removeClass().addClass('fa fa-exclamation-circle fa-1x fa-fw');
                    if (dataLength > 0) {
                        buildHeaderRow(response.headerData,response.attributes,'th');
                        thead = $('thead',Container);
                        buildRow(response.data,response.templates,response.attributes,'td');
                    }
                    TableCheck();
                    this.SearchAJAX = null;
                    checkboxToggleSearchListPages();
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    Loader.fogStatusUpdate(_L['ERROR_SEARCHING']+(errorThrown != '' ? errorThrown : ''));
                    this.SearchAJAX = null;
                    this.SearchLastQuery = null;
                }
            });
        }
    });
}
$.fn.fogMessageBox = function() {
    if (this.length == 0) return this;
    var Messages = new Array;
    this.each(function() {
        var messageBox = $(this);
        Messages[Messages.length] = messageBox.html();
    });
    if (Messages.length > 0) Loader.fogStatusUpdate(Messages.join('</p><p>')).hide().fadeIn();
    return this;
}
$.fn.fogStatusUpdate = function(txt, opts) {
    var Defaults = {
        AutoHide: 0,
        Raw: false,
        Progress: null
    };
    var Options = $.extend({},Defaults,opts || {});
    var Loader = $(this);
    var i = Loader.find('i');
    var p = Loader.find('p');
    var ProgressBar = $('#progress',this);
    if (Options.Progress) {
        ProgressBar.show().progressBar(Options.Progress);
    } else {
        ProgressBar.hide().progressBar(Options.Progress);
    }
    if (!txt) {
        p.remove().end().hide();
    } else {
        i.addClass('fa fa-exclamation-circle fa-1x fa-fw');
        p.remove().end().append((Options.Raw ? txt : '<p>'+txt+'</p>')).show();
    }
    Loader.removeClass();
    if (Options.Class) Loader.addClass(Options.Class);
    if (StatusAutoHideTimer) clearTimeout(StatusAutoHideTimer);
    if (Options.AutoHide) StatusAutoHideTimer = setTimeout(function() {Loader.fadeOut('fast');},Options.AutoHide);
    return this;
}
function showForceButton() {
    $('.icon-forced').addClass('fa fa-angle-double-right fa-1x icon');
    $('.icon-force').addClass('fa fa-bolt fa-fw hand').click(forceClick);
}
function showProgressBar() {
    $('.with-progress').hover(function() {
        var id = this.id.replace(/^progress[-_]/,'');
        var progress = $('#progress-'+id);
        progress.show();
        progress.find('.min').removeClass('min').addClass('no-min').end().find('ul').show();
    }, function() {
        var id = this.id.replace(/^progress[-_]/,'');
        var progress = $('#progress-'+id);
        progress.find('.no-min').removeClass('no-min').addClass('min').end().find('ul').show();
    });
}
function buildHeaderRow(data,attributes,wrapper) {
    if (!Container || typeof(Container) === null || typeof(Container) === 'undefined') {
        Container = $('#search-content,#active-tasks');
    }
    savedFilters = Container.find('.tablesorter-filter').map(function(){
        return this.value || '';
    }).get();
    thead.empty();
    var rows = [];
    $.each(data,function(index,value) {
        var attribs = [];
        $.each(attributes[index],function(ind,val) {
            attribs[attribs.length] = ind+'="'+val+'"';
        });
        var row = '<'+wrapper+(attribs.length ? ' '+attribs.join(' ') : '')+' data-column="'+index+'">'+value+'</'+wrapper+'>';
        rows[rows.length] = row;
    });
    thead.append('<tr class="header hand tablesorter-headerRow" role="row">'+rows.join()+'</tr>');
    thead.hide();
}
function buildRow(data,templates,attributes,wrapper) {
    var colspan = templates.length;
    var rows = [];
    checkedIDs = getChecked();
    tbody.empty();
    $.each(data, function(index, value) {
        var row = '<tr id="'+node+'-'+value.id+'">';
        $.each(templates, function(ind, val) {
            var attribs = [];
            $.each(attributes[ind], function(i, v) {
                attribs[attribs.length] = i+'="'+v+'"';
            });
            row += '<'+wrapper+(attribs.length ? ' '+attribs.join(' ') : '')+'>'+val+'</'+wrapper+'>';
        });
        $.each(value, function(ind, val) {
            row = row.replace(new RegExp('\\$\\{'+ind+'\\}','g'), $.trim(val));
        });
        rows[rows.length] = row+'</tr>';
    });
    tbody.append(rows.join());
    rows = [];
    if (node == 'task' && (typeof(sub) == 'undefined' || sub == 'active')) {
        $.each(data, function(index, value) {
            $('#progress-'+value.host_id).remove();
            var percentRow = '';
            if (value.percent > 0 && value.percent < 100) {
                percentRow = '<tr id="progress-'+value.host_id;
                percentRow +='" class="tablesorter-childRow with-progress">';
                percentRow += '<td colspan="'+colspan;
                percentRow += '" class="task-progress-td min">';
                percentRow += '<div class="task-progress-fill min" style="width: ';
                percentRow += value.width+'px"></div>';
                percentRow += '<div class="task-progress min"><ul><li>';
                percentRow += value.elapsed+'/'+value.remains+'</li>';
                percentRow += '<li>'+parseInt(value.percent)+'%</li>';
                percentRow += '<li>'+value.copied+' of '+value.total+' (';
                percentRow += value.bpm+'/min)</li></ul></div></td></tr>';
                $('#'+node+'-'+value.id).addClass('with-progress').after(percentRow);
            }
        });
        showForceButton();
        showProgressBar();
    }
    $('.toggle-action:checkbox,.toggle-checkboxAction:checkbox').change(function() {checkedIDs = getChecked();});
    setChecked(checkedIDs);
    HookTooltips();
}
function TableCheck() {
    if (!Container || typeof(Container) === null || typeof(Container) === 'undefined') {
        Container = $('#search-content,#active-tasks');
    }
    callme = 'hide';
    if ($('.not-found').length === 0) Container.after('<p class="c not-found">'+_L['NO_ACTIVE_TASKS']+'</p>');
    if (typeof(LastCount) != 'undefined' && LastCount > 0) {
        if ($('.not-found').length > 0) $('.not-found').remove();
        callme = 'show';
    }
    Container[callme]().fogTableInfo().trigger('updateAll').find('.tablesorter-filter').each(function(i){
        if (typeof savedFilters === null || typeof savedFilters === 'undefined') return;
        if (typeof savedFilters[i] === null || typeof savedFilters === 'undefined') return;
        $(this).val(savedFilters[i]);
    }).trigger('search');
    ActionBox[callme]();
    ActionBoxDel[callme]();
    thead[callme]();
    if (node == 'task' && $.inArray(sub, ['search', 'listhosts', 'listgroups']) < 0) {
        pauseUpdate[callme]();
        cancelTasks[callme]();
    }
    HookTooltips();
}
function setupParserInfo() {
    if (typeof $.tablesorter == 'undefined') return;
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
}
function setupFogTableInfoFunction() {
    if (typeof $.tablesorter == 'undefined') return;
    node = $_GET['node'];
    sub = $_GET['sub'];
    $.fn.fogTableInfo = function() {
        var parser = '';
        switch (node) {
            case 'task':
                if (typeof(sub) == 'undefined' || sub.indexOf('list') > -1) {
                    headParser = {5: {sorter: 'statusParser'}};
                } else {
                    headParser = {5: {sorter: 'statusParser'}};
                }
                break;
            case 'report':
                if (typeof(sub) != 'undefined') {
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
                                2: {sorter: 'dateParser'},
                                3: {sorter: 'dateParser'}
                            };
                            break;
                        default:
                            headParser = {0: {sorter: 'checkboxParser'}};
                            break;
                    }
                }
                break;
            case 'host':
                headParser = {0: {sorter: 'questionParser'},1: {sorter: 'checkboxParser'},2: {sorter: 'iParser'}};
                break;
            case 'printer':
                headParser = {0: {sorter: 'questionParser'},1: {sorter: 'checkboxParser'}};
                break;
            case 'image':
                headParser = 
                {
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
                headExtra = {7: {sorter: 'sizeParser'}};
                if ($('th').length > 7) $.extend(headParser,headExtra);
                break;
            case 'storage':
                headParser = {};
                break;
            case 'user':
            case 'group':
            case 'snapin':
            default:
                headParser = {0: {sorter: 'checkboxParser'}};
                break;
        }
        table = $('table',this);
        if (table.length == 0 || !table.has('thead')) return this;
        table.find('thead > tr').addClass('hand');
        table.tablesorter({
            headers: headParser,
            theme: 'blue',
            widgets: ["zebra","filter"],
            widgetOptions: {
                filter_ignoreCase: true,
                filter_hideFilters: false,
                filter_hideEmpty: true,
                filter_liveSearch: true,
                filter_placeholder: {search: 'Search...'},
                filter_reset: 'button.reset',
            },
        });
        return this;
    }
}
