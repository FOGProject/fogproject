/****************************************************
 * * FOG Host Management - Edit - JavaScript
 *	Author:		Blackout
 *	Created:	9:34 AM 1/01/2012
 *	Revision:	$Revision$
 *	Last Update:	$LastChangedDate$
 ***/
var $_GET = getQueryParams(document.location.search);
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
var LoginHistory = $('#login-history');
var LoginHistoryDate = $('#loghist-date');
var LoginHistoryData = new Array();
var Labels = new Array();
var LabelData = new Array();
var LoginData = new Array();
var LoginDateMin = new Array();
var LoginDateMax = new Array();
function UpdateLoginGraph()
{	
	$.ajax({
		url: location.href.replace('edit','hostlogins'),
		cache: false,
		type: 'GET',
		data: {
			dte: LoginHistoryDate.val()
		},
		dataType: 'json',
		success: UpdateLoginGraphPlot
	});
}
function UpdateLoginGraphPlot(data) {
	// If nothing is available, nothing is returned
	if (data == null) return;
	// Initiate counter
	j =0;
	// Loop through the data
	TimezoneOffset = new Date().getTimezoneOffset() * 60000;
	for (i in data) {
		// Set the time intervals as they're only used for this iteration.
		LoginTime = data[i]['login']*1000 - TimezoneOffset;
		LogoutTime = data[i]['logout']*1000 - TimezoneOffset;
		LoginDateMin = data[i]['min']*1000 - TimezoneOffset;
		LoginDateMax = data[i]['max']*1000 - TimezoneOffset;
		// Prepare the new items as necessary
		if (typeof(Labels) == 'undefined') {
			Labels = new Array();
			LabelData[i] = new Array();
			LoginData[i] = new Array();
		}
		// Does data exist for this item, if so place the data on the same line.
		if ($.inArray(data[i]['user'],Labels) > -1) {
			LoginData[i] = [LoginTime,$.inArray(data[i]['user'],Labels)+1,LogoutTime,data[i]['user']];
		// Otherwise create a new entry
		} else {
			Labels.push(data[i]['user']);
			LabelData[i] = [j+1,data[i]['user']];
			LoginData[i] = [LoginTime,++j,LogoutTime,data[i]['user']];
		}
	}
	LoginHistoryData = [{label: 'Logged In Time',data:LoginData}];
	var LoginHistoryOpts = {
		colors: ['rgb(0,120,0)'],
		series: {
			gantt: {
				active:true,
				show:true,
				barHeight:.2
			}
		},
		xaxis: {
			min: LoginDateMin,
			max: LoginDateMax,
			mode: 'time'
		},
		yaxis: {
			min: 0,
			max: LabelData.length + 1,
			ticks: LabelData,
		},
		grid: {
			hoverable: true,
			clickable: true,
		},
		legend: {
			position: "nw"
		}
	};
	$.plot(LoginHistory, LoginHistoryData, LoginHistoryOpts);
}
$(function() {
	UpdateLoginGraph();
	// Uncheck default printer boxes.
	$('input:checkbox[name="default"]').click(function() {
		var ischecked = $(this).attr('checked');
		$('input:checkbox').attr('checked',false);
		$(this).attr('checked', ischecked);
	});
	
	// Fetch MAC Manufactors
	$('.mac-manufactor').each(function()
	{
		var $this = $(this);
		var input = $this.parent().find('input');
		var mac = (input.size() ? input.val() : $this.parent().find('.mac').html());
		$this.load('../management/index.php?sub=getmacman&prefix=' + mac);
	});
	
	// Remove MAC Buttons
	$('.remove-mac').click(function()
	{
		//$(this).parent().remove();
		//$('.tipsy').remove();
		
		if ($('#additionalMACsCell').find('.additionalMAC').size() == 0)
		{
			$('#additionalMACsRow').hide();
		}
		//$(this).attr('checked', ischecked);
	});
	
	// Add MAC Buttons - TODO: Rewrite OLD CODE
	$('.add-mac').click(function()
	{
		$('#additionalMACsRow').show();
		$('#additionalMACsCell').append('<div><input class="addMac" type="text" name="additionalMACs[]" /> <span class="icon icon-remove remove-mac hand" title="Remove MAC"></span> <span class="mac-manufactor"></span></div>');
		
		HookTooltips();
		
		return false;
	});
	
	if ($('.additionalMAC').size())
	{
		$('#additionalMACsRow').show();
	}
	
	// Host Tasks - show advanced tasks on click
	$('.advanced-tasks-link').click(function(event)
	{
		$(this).parents('tr').toggle('slow', function()
		{
			$('#advanced-tasks').toggle('slow');
		});
		$(this).parents('tr').toggle('fast');
	
		event.preventDefault();
	});

	// Show hide based on checked state.
	$('#hostGroupShow').is(':checked') ? $('#hostGroupDisplay').show() : $('#hostGroupDisplay').hide();
	$('#printerNotInHost').is(':checked') ? $('#printerNotInHost').show() : $('#printerNotInHost').hide();
	$('#SnapinNotInHost').is(':checked') ? $('#snapinNotInHost').show() : $('#snapinNotInHost').hide();
	$('#hostGroupShow').click(function() { 
		$('#hostGroupDisplay').toggle();
	});
	$('#hostPrinterShow').click(function() {
		$('#printerNotInHost').toggle();
	});
	$('#hostSnapinShow').click(function() {
		$('#snapinNotInHost').toggle();
	});
	$('.toggle-checkboxprint').click(function() {
		$('input.toggle-print:checkbox').attr('checked', ($(this).attr('checked') ? 'checked' : false));
	});
	$('.toggle-checkboxsnapin').click(function() {
		$('input.toggle-snapin:checkbox').attr('checked', ($(this).attr('checked') ? 'checked' : false));
	});
});
