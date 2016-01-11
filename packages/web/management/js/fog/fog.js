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
    $('.toggle-action:checked').each(function(i) {
        val[i] = $(this).val();
    });
    return val;
}
function setChecked(ids) {
    $('.toggle-action').each(function(i) {
        if ($.inArray($(this).val(),ids) != -1 && $(this).not(':checked')) $(this).prop('checked',true);
    });
}
function getQueryParams(qs) {
    qs = qs.split("+").join(" ");
    var params = {},
    tokens,
        re = /[?&]?([^=]+)=([^&]*)/g
            while (tokens = re.exec(qs)) {
                params[decodeURIComponent(tokens[1])] = decodeURIComponent(tokens[2]);
            }
    return params;
}
(function($) {
    $('.icon,.icon-ping-up,.icon-ping-down').tipsy({gravity: $.fn.tipsy.autoNS});
    $('#logo > h1 > a > img').tipsy({gravity: $.fn.tipsy.autoNS});
    Content = $('#content');
    Loader = $('#loader');
    Loader.append('&nbsp;<i class="fa fa-1x"></i>&nbsp;');
    i = Loader.find('i');
    ActionBox = $('#action-box');
    ActionBoxDel = $('#action-boxdel');
    ActionBox.hide();
    ActionBoxDel.hide();
    if ((typeof(sub) == 'undefined' || $.inArray(sub,['list','search']) > -1) && $('.no-active-tasks').length < 1) {
        ActionBox.show();
        ActionBoxDel.show();
    }
    $.fn.fogAjaxSearch = function(opts) {
        if (this.length == 0) return this;
        var Defaults = {
            URL: $('#search-wrapper').prop('action'),
            Container: '#search-content',
            SearchDelay: 300,
            SearchMinLength: 1,
            Template: function(data,i) {
                return '<tr><td>'+data['host_name']+'</td></tr>';
            },
        };
        var SearchAJAX = null;
        var SearchTimer;
        var SearchLastQuery;
        var Options = $.extend({},Defaults,opts || {});
        Container = $(Options.Container);
        if (!Container.length) {
            alert('No Container element found: ' + Options.Container);
            return this;
        }
        if ($('tbody > tr', Container).filter('.no-active-tasks').length > 0) {
            Container.show();
            ActionBox.show();
            ActionBoxDel.show();
        } else {
            Container.hide();
            ActionBox.hide();
            ActionBoxDel.hide();
        }
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
                if (searchElement.val() != searchElement.prop('placeholder')) iterateElement.val('');
            }).parents('form').submit(function(e) {
                e.preventDefault();
            });
            function PerformSearch() {
                var Query = searchElement.val();
                if (Query == this.SearchLastQuery) return;
                this.SearchLastQuery = Query;
                if (Query.length < Options.SearchMinLength) return;
                if (this.SearchAJAX) this.SearchAJAX.abort();
                this.SearchAJAX = $.ajax({
                    type: $('#search-wrapper').prop('method'),
                    cache: false,
                    url: $('#search-wrapper').prop('action'),
                    dataType: 'json',
                    data: {
                        crit: Query
                    },
                    beforeSend: function() {
                        Loader.fogStatusUpdate();
                        SubmitButton.find('i').removeClass('fa-play').addClass('fa-spinner fa-pulse fa-fw');
                    },
                    success: function(response) {
                        dataLength = response === null || response.data === null ? dataLength = 0 : response.data.length;
                        SubmitButton.removeClass('searching').find('i').removeClass('fa-spinner fa-pulse fa-fw').addClass('fa-play');
                        thead = $('thead',Container);
                        tbody = $('tbody',Container);
                        LastCount = dataLength;
                        Loader.removeClass('loading').fogStatusUpdate(_L['SEARCH_RESULTS_FOUND'].replace(/%1/,LastCount).replace(/%2/,LastCount != 1 ? 's' : '')).find('i').removeClass('fa-refresh fa-spin fa-fw').addClass('fa-exclamation-circle');
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
    $.fn.fogTableInfo = function() {
        $('table:has(thead)').tablesorter({
            theme: 'blue',
            widgets: ["zebra","filter"],
            widgetOptions: {
                filter_ignoreCase: true,
                filter_hideFilters: false,
                filter_hideEmpty: true,
                filter_liveSearch: true,
                filter_placeholder: { search: 'Search...'},
                filter_reset: 'button.reset',
            },
        });
        $('table:has(thead) > thead > tr > td').addClass('hand');
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
            i.addClass('fa-exclamation-circle');
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
        var Defaults = {
            Debug: false
        };
        var Options = $.extend({}, Defaults, opts || {});
        var Variables = {};
        return this.each(function() {
            var variableElement = $(this);
            window[variableElement.attr('id').toString()] = variableElement.html().toString();
            if (Options.Debug) alert(variableElement.attr('id').toString()+' = '+variableElement.html().toString());
            variableElement.remove();
        });
    }
    jQuery.fn.exists = function() {
        return this.length > 0;
    }
    jQuery.fn.isIE8 = function() {
        return $.browser.msie && parseInt($.browser.version, 10) <= 8;
    }
})(jQuery);
function forceClick(e) {
    $(this).unbind('click').click(function(evt) {
        evt.preventDefault();
    });
    if (AJAXTaskForceRequest) AJAXTaskForceRequest.abort();
    AJAXTaskForceRequest = $.ajax({
        type: 'POST',
        url: $(this).attr('href'),
        beforeSend: function() {
            $(this).removeClass().addClass('fa fa-refresh fa-spin fa-fw icon');
        },
        success: function(data) {
            if (typeof(data) == 'undefined' || data === null) return;
            $(this).removeClass().addClass('fa fa-angle-double-right fa-fw icon');
        },
        error: function() {
            $(this).removeClass().addClass('fa fa-bolt fa-fw icon');
        }
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
        var id = $(this).prop('id').replace(/^progress[-_]/,'');
        var progress = $('#progress-'+id);
        progress.show();
        progress.find('.min').removeClass('min').addClass('no-min').end().find('ul').show();
    }, function() {
        var id = $(this).prop('id').replace(/^progress[-_]/,'');
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
        console.log(h);
        var row = '<tr id="'+node+'-'+data[h].id+'">';
        for (var i in templates) {
            var attributes = [];
            for (var j in attributes) {
                attributes[attributes.length] = j+'="'+attributes[i][j]+'"';
            }
            row += '<'+wrapper+(attributes.length ? ' '+attributes.join(' ') : '')+'>'+templates[i]+'</'+wrapper+'>';
        }
        for (var k in data[h]) {
            row = row.replace(new RegExp('\\$\\{'+k+'\\}','g'),data[h][k]);
        }
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
    $('.toggle-action').change(function() {
        checkedIDs = getChecked();
    });
    setChecked(checkedIDs);
    HookTooltips();
}
function TableCheck() {
    if (LastCount > 0) {
        if ($('.not-found').length > 0) $('.not-found').remove();
        $('#content-inner').fogTableInfo();
        Container.show();
        ActionBox.show();
        ActionBoxDel.show();
        thead.show();
        if (node == 'task') {
            pauseUpdate.show();
            cancelTasks.show();
        }
        HookTooltips();
    } else {
        if ($('.not-found').length === 0) Container.after('<p class="c not-found">'+_L['NO_ACTIVE_TASKS']+'</p>');
        $('#content-inner').fogTableInfo();
        Container.hide();
        ActionBox.hide();
        ActionBoxDel.hide();
        thead.hide();
        if (node == 'task') {
            pauseUpdate.hide();
            cancelTasks.hide();
        }
        HookTooltips();
    }
    Container.trigger('update');
}
