/****************************************************
 * FOG Dashboard JS
 *	Author:		Blackout
 *	Created:	10:05 AM 16/04/2011
 *	Revision:	$Revision: 2430 $
 *	Last Update:	$LastChangedDate: 2014-10-16 11:55:06 -0400 (Thu, 16 Oct 2014) $
 ***/
var $_GET = getQueryParams(document.location.search),
    node = $_GET['node'],
    sub = $_GET['sub'],
    tab = $_GET['tab'],
    wrapper = 'td',
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
    $('.toggle-action:checkbox:checked').not(':hidden').each(function(i) {
        val[i] = this.value;
    });
    return val;
}
function setTipsyStuff() {
    $('.icon,.icon-ping-up,.icon-ping-down,#logo > h1 > a > img').tipsy({
        gravity: $.fn.tipsy.autoNS
    });
}
function setEditFocus() {
    $('input,select,textarea').not('[type="checkbox"],[name="storagesel"],[name="ulang"]').change(function(e) {
        e.preventDefault();
        field = $(this);
        field.not(':focus') ? field.next('i').hide() : field.append('<i class="fa fa-pencil fa-fw"></i>');
    });
}
function setChecked(ids) {
    $('.toggle-action:checkbox').not(':hidden').not(':checked').each(function(i) {
        if ($.inArray(this.value,ids) < 0) return;
        this.checked = true;
    });
}
function getQueryParams(qs) {
    qs = qs.split("+").join(" ");
    var params = {},tokens,re = /[?&]?([^=]+)=([^&]*)/g;
    while (tokens = re.exec(qs)) params[decodeURIComponent(tokens[1])] = decodeURIComponent(tokens[2]);
    return params;
}
(function($) {
    setTipsyStuff();
    setEditFocus();
    Content = $('#content');
    Loader = $('#loader');
    Loader.append('&nbsp;<i></i>&nbsp;');
    i = Loader.find('i');
    ActionBox = $('#action-box');
    ActionBoxDel = $('#action-boxdel');
    var callme = 'hide';
    if ((typeof(sub) == 'undefined' || $.inArray(sub,['list','search']) > -1) && $('.no-active-tasks').length < 1) callme = 'show';
    ActionBox[callme]();
    ActionBoxDel[callme]();
    setupParserInfo();
    setupFogTableInfoFunction();
    $.fn.fogAjaxSearch = function(opts) {
        if (this.length == 0) return this;
        var Defaults = {
            URL: $('#search-wrapper').prop('action'),
            Container: '#search-content,#active-tasks',
            SearchDelay: 300,
            SearchMinLength: 1,
        };
        var SearchAJAX = null;
        var SearchTimer;
        var SearchLastQuery;
        var Options = $.extend({},Defaults,opts || {});
        Container = $(Options.Container);
        if (!Container.length) {
            alert('No Container element found: '+Options.Container);
            return this;
        }
        callme = 'hide';
        if ($('tbody > tr',Container).filter('.no-active-tasks').length > 0) callme = 'show';
        Container[callme]().fogTableInfo().trigger('update');
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
                        Loader.removeClass('loading').fogStatusUpdate(_L['SEARCH_RESULTS_FOUND'].replace(/%1/,LastCount).replace(/%2/,LastCount != 1 ? 's' : '')).find('i').removeClass().addClass('fa fa-exclamation-circle');
                        if (dataLength > 0) buildRow(response.data,response.templates,response.attributes);
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
        if (Options.Progress) ProgressBar.show().progressBar(Options.Progress);
        else ProgressBar.hide().progressBar(0);
        if (!txt) p.remove().end().hide();
        else {
            i.addClass('fa fa-exclamation-circle fw');
            p.remove().end().append((Options.Raw ? txt : '<p>'+txt+'</p>')).show();
        }
        Loader.removeClass();
        if (Options.Class) Loader.addClass(Options.Class);
        if (StatusAutoHideTimer) clearTimeout(StatusAutoHideTimer);
        if (Options.AutoHide) StatusAutoHideTimer = setTimeout(function() {Loader.fadeOut('fast');},Options.AutoHide);
        return this;
    }
    $.fn.fogVariable = function(opts) {
        if (this.length == 0) return this;
        var Defaults = {Debug: false};
        var Options = $.extend({}, Defaults, opts || {});
        var Variables = {};
        return this.each(function() {
            var variableElement = $(this);
            window[variableElement.attr('id').toString()] = variableElement.html().toString();
            if (Options.Debug) alert(variableElement.attr('id').toString()+' = '+variableElement.html().toString());
            variableElement.remove();
        });
    }
    jQuery.fn.exists = function() {return this.length > 0;}
    jQuery.fn.isIE8 = function() {return $.browser.msie && parseInt($.browser.version, 10) <= 8;}
})(jQuery);
function forceClick(e) {
    $(this).unbind('click').click(function(evt) {evt.preventDefault();});
    if (AJAXTaskForceRequest) AJAXTaskForceRequest.abort();
    AJAXTaskForceRequest = $.ajax({
        type: 'POST',
        url: this.href,
        beforeSend: function() {$(this).removeClass().addClass('fa fa-refresh fa-spin fa-fw icon');},
        success: function(data) {
            if (typeof(data) == 'undefined' || data === null) return;
            $(this).removeClass().addClass('fa fa-angle-double-right fa-fw icon');
        },
        error: function() {$(this).removeClass().addClass('fa fa-bolt fa-fw icon');}
    });
    e.preventDefault();
}
function showForceButton() {
    $('.icon-forced').addClass('fa fa-angle-double-right fa-1x icon');
    $('.icon-force').addClass('fa fa-bolt fa-fw hand');
    $('.icon-force').unbind('click').click(forceClick);
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
        progress.find('.no-min').removeClass('no-min').addClass('min').end().find('ul').hide();
    });
}
function buildRow(data,templates,attributes) {
    var colspan = templates.length;
    var rows = [];
    checkedIDs = getChecked();
    tbody.empty();
    for (var h in data) {
        var row = '<tr id="'+node+'-'+data[h].id+'">';
        for (var i in templates) {
            var attributes = [];
            for (var j in attributes) attributes[attributes.length] = j+'="'+attributes[i][j]+'"';
            row += '<'+wrapper+(attributes.length ? ' '+attributes.join(' ') : '')+'>'+templates[i]+'</'+wrapper+'>';
        }
        for (var k in data[h]) row = row.replace(new RegExp('\\$\\{'+k+'\\}','g'),$.trim(data[h][k]));
        rows[rows.length] = row+'</tr>';
    }
    tbody.append(rows.join());
    rows = [];
    if (node == 'task' && (typeof(sub) == 'undefined' || sub == 'active')) {
        for (var h in data) {
            var percentRow = '';
            if (data[h].percent > 0 && data[h].percent < 100) {
                percentRow = '<tr id="progress-'+data[h].host_id+'" class="with-progress"><td colspan="'+colspan+'" class="task-progress-td min"><div class="task-progress-fill min" style="width: '+data[h].width+'px"></div><div class="task-progress min"><ul><li>'+data[h].elapsed+'/'+data[h].remains+'</li><li>'+parseInt(data[h].percent)+'%</li><li>'+data[h].copied+' of '+data[h].total+' ('+data[h].bpm+'/min)</li></ul></div></td></tr>';
                $('#'+node+'-'+data[h].id).addClass('with-progress').after(percentRow);
            }
        }
        showForceButton();
        showProgressBar();
    }
    $('.toggle-action:checkbox,.toggle-checkboxAction:checkbox').change(function() {checkedIDs = getChecked();});
    setChecked(checkedIDs);
    HookTooltips();
}
function TableCheck() {
    var callme = 'hide';
    if ($('.not-found').length === 0) Container.after('<p class="c not-found">'+_L['NO_ACTIVE_TASKS']+'</p>');
    if (LastCount > 0) {
        if ($('.not-found').length > 0) $('.not-found').remove();
        callme = 'show';
    }
    Container[callme]().fogTableInfo().trigger('update');
    ActionBox[callme]();
    ActionBoxDel[callme]();
    thead[callme]();
    if (node == 'task' && sub != 'search') {
        pauseUpdate[callme]();
        cancelTasks[callme]();
    }
    HookTooltips();
}
function setupParserInfo() {
    if (typeof $.tablesorter == 'undefined') return;
    $.tablesorter.addParser({
        id: 'checkboxParser',
        is: function(s) {
            return false;
        },
        format: function (s, table, cell, cellIndex) {
            checkbox = $(cell).find('input:checkbox');
            if (checkbox.length > -1) return checkbox.prop('value');
        },
        type: 'text'
    });
    $.tablesorter.addParser({
        id: 'questionParser',
        is: function(s) {
            return false;
        },
        format: function(s, table, cell, cellIndex) {
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
            if (i.length > -1) return i.prop('original-title');
        },
        type: 'text'
    });
    $.tablesorter.addParser({
        id: 'sizeParser',
        is: function(s) {
            return s.match(new RegExp(/[0-9]+(\.[0-9]+)?\ (iB|KiB|MiB|GiB|TiB|EiB|ZiB|YiB)/));
        },
        format: function(s) {
            var suf = s.match(new RegExp(/(iB|KiB|MiB|GiB|TiB|EiB|ZiB|YiB)$/))[1];
            var num = parseFloat(s.match(new RegExp(/^[0-9]+(\.[0-9]+)?/))[0]);
            switch(suf) {
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
            case 'user':
            case 'group':
            case 'snapin':
            default:
                headParser = {0: {sorter: 'checkboxParser'}};
                break;
            case 'host':
                headParser = {0: {sorter: 'questionParser'},1: {sorter: 'checkboxParser'},2: {sorter: 'iParser'}};
                break;
            case 'printer':
                headParser = {0: {sorter: 'questionParser'},1: {sorter: 'checkboxParser'}};
                break;
            case 'image':
                headParser = {0: {sorter: 'iParser'},1: {sorter: 'checkboxParser'},3: {sorter: 'sizeParser'}};
                headExtra = {4: {sorter: 'sizeParser'}};
                if ($('th').length > 7) $.extend(headParser,headExtra);
                console.log(headParser);
                break;
            case 'storage':
                headParser = {};
                break;
        }
        table = $('table',this)
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
