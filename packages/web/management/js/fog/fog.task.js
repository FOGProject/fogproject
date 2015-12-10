var ActiveTasksContainer;
var ActiveTasksLastCount;
var sub = $_GET['sub'];
$(function() {
    $('.toggle-checkboxAction').click(function() {
        $('input.toggle-action[type="checkbox"]')
            .not(':hidden')
            .prop('checked', $(this).is(':checked'));
    });
    $('#action-box,#action-boxdel').submit(function() {
        var checked = $('input.toggle-action:checked');
        var taskIDArray = new Array();
        for (var i = 0,len = checked.size();i < len;i++) taskIDArray[taskIDArray.length] = checked.eq(i).prop('value');
        $('input[name="taskIDArray"]').val(taskIDArray.join(','));
    });
    ActiveTasksContainer = $('#active-tasks');
    if (ActiveTasksContainer.find('tbody > tr').size() > 0) ActiveTasksContainer.show();
    ActiveTasksTableCheck();
    var URL;
    switch(sub) {
        case 'active':
            URL = '?node=task&sub=canceltasks';
            break;
        case 'active-snapins':
            URL = '?node=task&sub=active_snapins_post';
            break;
        case 'active-multicast':
            URL = '?node=task&sub=remove_multicast_post';
            break;
        case 'scheduled':
            URL = '?node=task&sub=cancelscheduled';
            break;
        default:
            $('input[name="Cancel"]').hide();
            break;
    }
    $('input[name="Cancel"]').click(function() {
        checkedIDs = getChecked();
        if (checkedIDs.length > 0) {
            $('#canceltasks').html('Are you sure you wish to cancel these tasks?');
            $('#canceltasks').dialog({
                resizable: false,
                modal: true,
                title: 'Cancel tasks',
                buttons: {
                    'Yes': function() {
                        $.post(URL,{task: checkedIDs}, function(data) {
                            clearTimeout(ActiveTasksUpdateTimer);
                            if (typeof(sub) == 'undefined' || sub.indexOf('active') != -1) {
                                ActiveTasksUpdate(window.location.href);
                                ActiveTasksTableCheck();
                                ActiveTasksUpdateTimerStart();
                            }
                        });
                        $(this).dialog('close');
                    },
                    'No': function() {
                        ActiveTasksTableCheck();
                        $(this).dialog('close');
                    }
                }
            });
        }
    });
    if (typeof(sub) == 'undefined' || sub.indexOf('active') != -1) {
        ActiveTasksUpdate(window.location.href);
        ActiveTasksButtonHook();
        ActiveTasksUpdateTimerStart();
        $('#taskpause').val('Pause auto update').addClass('active');
        ActiveTasksUpdateTimerStart();
        $('#taskpause').click(function(e) {
            e.preventDefault();
            if (!$(this).hasClass('active')) {
                $(this).addClass('active').val('Pause auto update');
                ActiveTasksUpdate(window.location.href);
                ActiveTasksButtonHook();
                ActiveTasksUpdateTimerStart();
            } else {
                $(this).removeClass('active').val('Continue auto update');
                clearTimeout(ActiveTasksUpdateTimer);
            }
        });
    }
});
function ActiveTasksUpdateTimerStart() {
    if (typeof(sub) == 'undefined' || sub.indexOf('active') != -1) {
        ActiveTasksUpdateTimer = setTimeout(function() {
            if (!ActiveTasksRequests.length && $('#taskpause').hasClass('active')) ActiveTasksUpdate(window.location.href);
        },ActiveTasksUpdateInterval);
    }
}
function ActiveTasksUpdate(URL) {
    if (ActiveTasksAJAX) return;
    console.log(URL);
    ActiveTasksAJAX = $.ajax({
        type: 'POST',
        url: URL,
        cache: false,
        dataType: 'json',
        beforeSend:	function() {
            Loader
                .addClass('loading');
            if (ActiveTasksLastCount) {
                Loader
                    .fogStatusUpdate(_L['ACTIVE_TASKS_FOUND']
                            .replace(/%1/, ActiveTasksLastCount)
                            .replace(/%2/, ActiveTasksLastCount != 1 ? 's' : '')
                            );
                i
                    .removeClass('fa-refresh fa-spin fa-fw')
                    .addClass('fa-exclamation-circle');
            } else {
                Loader
                    .fogStatusUpdate(_L['ACTIVE_TASKS_LOADING']);
                i
                    .removeClass('fa-exclamation-circle')
                    .addClass('fa-refresh fa-spin fa-fw');
            }
        },
        success: function(response)	{
            checkedIDs = getChecked();
            dataLength = response.data !== null ? response.data.length : 0;
            Loader
                .removeClass('loading')
                .fogStatusUpdate(_L['ACTIVE_TASKS_FOUND']
                        .replace(/%1/, dataLength)
                        .replace(/%2/, dataLength != 1 ? 's' : '')
                        );
            i = Loader.find('i');
            i.removeClass('fa-refresh fa-spin fa-fw').addClass('fa-exclamation-circle');
            ActiveTasksAJAX = null;
            var tbody = $('tbody',ActiveTasksContainer);
            var thead = $('thead',ActiveTasksContainer);
            ActiveTasksLastCount = dataLength;
            tbody.empty();
            if (thead.length == 0) {
                var head = '<tr class="header">';
                for (var i in response['headerData']) {
                    var headatts = [];
                    for (var j in response['attributes'][i]) {
                        headatts[headatts.length] = j+'="'+response['attributes'][i][j]+'"';
                    }
                    head += '<th'+(headatts.length?' '+headatts.join(' '):'')+'>'+response['headerData'][i]+'</th>';
                }
                head += '</tr>';
                tbody.before('<thead>'+head+'</thead>');
            }
            if (dataLength > 0) {
                var rows = '';
                for (var i in response['data']) {
                    var row = '<tr id="task-'+response['data'][i]['id']+'" '+(response['data'][i]['percent'] ? 'class="with-progress"' : '')+'>';
                    for (var j in response['templates']) {
                        var attributes = [];
                        for (var k in response['attributes'][j]) {
                            attributes[attributes.length] = k+'="' + response['attributes'][j][k]+'"';
                        }
                        row += '<td'+(attributes.length ? ' '+attributes.join(' ') : '')+'>'+response['templates'][j]+'</td>';
                    }
                    if (response['data'][i]['percent'] > 0 && response['data'][i]['percent'] < 100) {
                        numRows = $('#active-tasks tr td').length;
                        row += '<tr id="progress-${host_id}" class="${class}"><td colspan="'+numRows+'" class="task-progress-td min"><div class="task-progress-fill min" style="width: ${width}px"></div><div class="task-progress min"><ul><li>${elapsed}/${remains}</li><li>${percentText}%</li><li>${copied} of ${total} (${bpm}/min)</li></ul></div></td></tr>';
                    }
                    for (var k in response['data'][i]) {
                        row = row.replace(new RegExp('\\$\\{' + k + '\\}', 'g'), response['data'][i][k]);
                    }
                    row = row.replace(new RegExp('\\$\\{\w+\\}','g'),'');
                    rows += row+'</tr>';
                    if (response['data'][i]['percent']) {
                        rows += response['data'][i]['percent'];
                    }
                }
                tbody.append(rows);
                var tr = $('tr',tbody);
                for (i in response['data']) tr.eq(i).data({
                    host_id: response['data'][i]['host_id'],
                    host_name: response['data'][i]['host_name']
                });
                HookTooltips();
                ActiveTasksButtonHook();
                ActiveTasksContainer.show();
            } else {
                ActiveTasksTableCheck();
            }
            setChecked(checkedIDs);
            ActiveTasksUpdateTimerStart();
            $('table').trigger('update');
        },
        error: function() {
            ActiveTasksAJAX = null;
            Loader
                .fogStatusUpdate(_L['ACTIVE_TASKS_UPDATE_FAILED'])
                .addClass('error');
            i = Loader.find('i');
            i.css({color: 'red'});
        }
    });
}
function ActiveTasksButtonHook() {
    var waiting = 'fa fa-refrest fa-1x fa-spin icon';
    $('.icon-kill').find('i').addClass('fa fa-minus-circle fa-1x icon');
    $('.icon-kill').unbind('click').click(function() {
        var a = $(this);
        var i = a.find('i');
        var url = a.prop('href');
        var tr = a.parents('tr');
        var ID = tr.prop('id').replace(/^host-/,'');
        var ProgressBar = $('#progress-'+ID,ActiveTasksContainer);
        ActiveTasksRequests[ActiveTasksRequests.length] = $.ajax({
            type: 'POST',
            url: url,
            beforeSend: function() {
                i.removeClass().addClass(waiting);
                a.unbind('click').click(function() {
                    return false;
                });
            },
            success: function(data) {
                tr.fadeOut('fast', function() {
                    tr.remove();
                    ProgressBar.remove();
                    ActiveTasksTableCheck();
                    HookTooltips();
                });
                ActiveTasksRequests.splice(0, 1);
            },
            error: function() {
                ActiveTasksButtonHook();
                ActiveTasksRequests.splice(0, 1);
            }
        });
        return false;
    });
    $('.icon-forced').addClass('fa fa-angle-double-right fa-1x icon');
    $('.icon-force').find('i').addClass('fa fa-bolt fa-1x icon');
    $('.icon-force').unbind('click').click(function() {
        var a = $(this);
        var url = a.prop('href');
        var i = a.find('i');
        a.unbind('click').click(function() {
            return false;
        });
        ActiveTasksRequests[ActiveTasksRequests.length] = $.ajax({
            type: 'POST',
            url: url,
            beforeSend: function() {
                i.removeClass().addClass(waiting);
            },
            success: function(data) {
                if (typeof(data) != 'undefined') {
                    obj = jQuery.parseJSON(data);
                    if (typeof(obj) != 'undefined' && typeof(obj.success) != 'undefined' && obj.success === true) {
                        i.removeClass().addClass('fa fa-angle-double-right fa-1x icon');
                        ActiveTasksRequests.splice(0,1);
                    }
                }
            },
            error: function() {
                i.removeClass().addClass('fa fa-bolt fa-1x icon');
                ActiveTasksRequests.splice(0, 1);
            }
        });
        return false;
    });
    $('.with-progress').hover(function() {
        var id = $(this).attr('id').replace(/^host-/, '');
        var progress = $('#progress-' + id);
        progress.show();
        progress.find('.min').removeClass('min').addClass('no-min').end().find('ul').show();
    }, function() {
        var id = $(this).attr('id').replace(/^host-/, '');
        var progress = $('#progress-' + id);
        progress.find('.no-min').removeClass('no-min').addClass('min').end().find('ul').hide();
    });
    $('tr[id^="progress-"]').hover(function() {
        $(this).find('.min').removeClass('min').addClass('no-min').end().find('ul').show();
    }, function() {
        $(this).find('.no-min').removeClass('no-min').addClass('min').end().find('ul').hide();
    });
}
function ActiveTasksTableCheck() {
    var table = $('table', ActiveTasksContainer);
    var tbody = $('tbody', ActiveTasksContainer);
    var thead = $('thead', ActiveTasksContainer);
    var tbodyRows = tbody.find('tr');
    var tbodyCols = thead.find('th');
    if (tbodyRows.size() > 0) {
        $('input[name="Cancel"]').show();
    } else {
        $('table').removeClass('tablesorter-blue');
        thead.remove();
        tbody.html('<tr><td colspan="'+tbodyCols.length+'" class="no-active-tasks">' + _L['NO_ACTIVE_TASKS'] + '</td></tr>');
    }
    if ($('.no-active-tasks').size() == 0) ActiveTasksContainer.after('<div id="canceltasks" class="c"><input type="button" name="Cancel" value="Cancel selected tasks?"/></div>');
    else $('#canceltasks').hide();
}
