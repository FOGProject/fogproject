/****************************************************
 * FOG JavaScript: Active Tasks
 *	Author:		Blackout
 *	Created:	1:48 PM 23/02/2011
 *	Revision:	$Revision: 835 $
 *	Last Update:	$LastChangedDate: 2012-01-12 11:57:46 +1000 (Thu, 12 Jan 2012) $
 ***/
// TODO: Merge this with $.fn.fogAjaxSearch()
var ActiveTasksContainer;
var ActiveTasksLastCount;
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
    // Show Task Container if we have items
    ActiveTasksContainer = $('#active-tasks');
    if (ActiveTasksContainer.find('tbody > tr').size() > 0) ActiveTasksContainer.show();
    ActiveTasksTableCheck();
    var URL;
    switch($_GET['sub']) {
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
        $('input[name="Cancel"]').remove();
        break;
    }
    $('input[name="Cancel"]').click(function() {
        checkedIDs = getChecked();
        sub = $_GET['sub'];
        if (checkedIDs.length > 0) {
            $('#canceltasks').html('Are you sure you wish to cancel these tasks?');
            $('#canceltasks').dialog({
                resizable: false,
                modal: true,
                title: 'Cancel tasks',
                buttons: {
                    'Yes': function() {
                        $.ajax({
                            type: 'POST',
                            url: URL,
                            data: {
                                task: checkedIDs
                            },
                            success: function(data) {
                                clearTimeout(ActiveTasksUpdateTimer);
                                if ($_GET['sub'] == 'active') {
                                    ActiveTasksUpdate();
                                    ActiveTasksTableCheck();
                                    ActiveTasksUpdateTimerStart();
                                } else {
                                    location.reload();
                                }
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
    // Hook buttons
    ActiveTasksButtonHook();
    // Update timer
    ActiveTasksUpdateTimerStart();
    // Add Pause/Continue button text.
    $('#taskpause').val('Pause auto update').addClass('active');
    ActiveTasksUpdateTimerStart();
    $('#taskpause').click(function(e) {
        e.preventDefault();
        if (!$(this).hasClass('active')) {
			$(this).addClass('active').val('Pause auto update');
			ActiveTasksUpdateTimerStart();
		} else {
			$(this).removeClass('active').val('Continue auto update');
			clearTimeout(ActiveTasksUpdateTimer);
		}
	});
});
function ActiveTasksUpdateTimerStart() {
	if (typeof($_GET['sub']) == 'undefined' || $_GET['sub'] == 'active') {
		ActiveTasksUpdateTimer = setTimeout(function() {
            if (!ActiveTasksRequests.length && $('#taskpause').hasClass('active')) ActiveTasksUpdate();
        },ActiveTasksUpdateInterval);
	}
}
function ActiveTasksUpdate() {
	if (ActiveTasksAJAX) return;
	ActiveTasksAJAX = $.ajax({
        type: 'POST',
        url: '?node=task&sub=active',
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
            // Loader
            Loader
            .removeClass('loading')
            .fogStatusUpdate(_L['ACTIVE_TASKS_FOUND']
                .replace(/%1/, dataLength)
                .replace(/%2/, dataLength != 1 ? 's' : '')
            );
            i = Loader.find('i');
            i
            .removeClass('fa-refresh fa-spin fa-fw')
            .addClass('fa-exclamation-circle');
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
                    // Create row
                    head += '<th'+(headatts.length?' '+headatts.join(' '):'')+'>'+response['headerData'][i]+'</th>';
                }
                head += '</tr>';
                tbody.before('<thead>'+head+'</thead>');
            }
            if (dataLength > 0) {
                var rows = '';
                for (var i in response['data']) {
                    var row = '<tr id="task-'+response['data'][i]['id']+'" class="'+(i % 2 ? 'alt2' : 'alt1')+(response['data'][i]['percent'] ? ' with-progress' : '')+'">';
                    for (var j in response['templates']) {
                        var attributes = [];
                        for (var k in response['attributes'][j]) {
                            attributes[attributes.length] = k+'="' + response['attributes'][j][k]+'"';
                        }
                        // Add
                        row += '<td'+(attributes.length ? ' '+attributes.join(' ') : '')+'>'+response['templates'][j]+'</td>';
                    }
                    // Replace variable data
                    if (response['data'][i]['percent'] > 0 && response['data'][i]['percent'] < 100) {
                        row += '<tr id="progress-${host_id}" class="${class}"><td colspan="6" class="task-progress-td min"><div class="task-progress-fill min" style="width: ${width}px"></div><div class="task-progress min"><ul><li>${elapsed}/${remains}</li><li>${percentText}%</li><li>${copied} of ${total} (${bpm}/min)</li></ul></div></td></tr>';
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
	// Hook: Click: Kill Button - Legacy GET call still works if AJAX fails
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
                // Unhook this button - multiple clicks now do nothing
                a.unbind('click').click(function() {
                    return false;
                });
            },
            success: function(data) {
                // Fade row out
                tr.fadeOut('fast', function() {
                    // Remove tr element
                    tr.remove();
                    // Remove progress bar
                    ProgressBar.remove();
                    // Adjust row colours / check for empty table
                    ActiveTasksTableCheck();
                    // Update tooltips
                    HookTooltips();
                });
                // Remove this request from our AJAX request tracking
                ActiveTasksRequests.splice(0, 1);
            },
            error: function() {
                // Re-hook buttons
                ActiveTasksButtonHook();
                // Remove this request from our AJAX request tracking
                ActiveTasksRequests.splice(0, 1);
            }
        });
        // Stop default action
        return false;
    });
    // Hook: Click: Force Button - Legacy GET call still works if AJAX fails
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
                // Loader
                i.removeClass().addClass(waiting);
            },
            success: function(data) {
                if (typeof(data) != 'undefined') {
                    obj = jQuery.parseJSON(data);
                    if (typeof(obj) != 'undefined' && typeof(obj.success) != 'undefined' && obj.success === true) {
                        // Indicate job has been forced
                        i.removeClass().addClass('fa fa-angle-double-right fa-1x icon');
                        // Remove this request from our AJAX request tracking
                        ActiveTasksRequests.splice(0,1);
                    }
                }
            },
            error: function() {
                i.removeClass().addClass('fa fa-bolt fa-1x icon');
                // Remove this request from our AJAX request tracking
                ActiveTasksRequests.splice(0, 1);
            }
        });
        // Stop Default action
        return false;
    });
// Hook: Hover: Show Progress Bar on Active Task
$('.with-progress').hover(function() {
		var id = $(this).attr('id').replace(/^host-/, '');
		var progress = $('#progress-' + id);
		progress.show();
		progress.find('.min').addClass('no-min').removeClass('min').end().find('ul').show();
		}, function() {
		var id = $(this).attr('id').replace(/^host-/, '');
		var progress = $('#progress-' + id);
		progress.find('.no-min').addClass('min').removeClass('no-min').end().find('ul').hide();
		});
// Hook: Hover: Show Progress Bar on Progress Bar
$('tr[id^="progress-"]').hover(function() {
		$(this).find('.min').addClass('no-min').removeClass('min').end().find('ul').show();
		}, function() {
		$(this).find('.no-min').addClass('min').removeClass('no-min').end().find('ul').hide();
		});
}
function ActiveTasksTableCheck() {
    // Variables
    var table = $('table', ActiveTasksContainer);
    var tbody = $('tbody', ActiveTasksContainer);
    var thead = $('thead', ActiveTasksContainer);
    var tbodyRows = tbody.find('tr');
    var tbodyCols = thead.find('th');
    // If we have rows in the table
    if (tbodyRows.size() > 0) {
        // Adjust alt colours
        var i = 0;
        tbodyRows.each(function() {
            $(this).removeClass().addClass('alt' + (i++ % 2 ? '2' : '1'));
        });
    } else {
        $('table').removeClass('tablesorter-blue');
        thead.remove();
        tbody.html('<tr><td colspan="7" class="no-active-tasks">' + _L['NO_ACTIVE_TASKS'] + '</td></tr>');
    }
    if ($('.no-active-tasks').size() == 0) ActiveTasksContainer.after('<center><div id="canceltasks"><input type="button" name="Cancel" value="Cancel selected tasks?"/></div></center>');
    else $('#canceltasks').hide();
}
