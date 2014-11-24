var JSONParseFunction = (typeof(JSON) != 'undefined' ? JSON.parse : eval)
// Disk Usage Graph Stuff
var GraphDiskUsage = $('#graph-diskusage','#content-inner');
var GraphDiskUsageAJAX;
var GraphDiskUsageNode = $('#diskusage-selector select','#content-inner');
var NodeID;
var GraphDiskUsageData = [
	{label: 'Free',data:0},
	{label: 'Used',data:0}
];
var bytes, units;
var GraphDiskUsageOpts = {
	colors: ['#45a73c','#cb4b4b'],
	series: {
		pie: {
			show: true,
			radius: 1
		}
	},
	legend: {
		show: true,
		align: 'right',
		position: 'se',
		labelColor: '#666',
		labelFormatter: function(label, series) {
			units = [' iB',' KiB',' MiB',' GiB',' TiB',' PiB',' EiB',' ZiB',' YiB'];
			for (i =0; series.data[0][1] >= 1024 && i < units.length -1; i++) {
				series.data[0][1] /= 1024;
			}
			return '<div style="font-size:8pt;padding2px;margin-top:16px;">'+label+': '+Math.round(series.percent)+'% <br />'+series.data[0][1].toFixed(2)+units[i]+'</div>';
		}
	}
};
// Bandwidth Variable/Option settings.
var GraphData = new Array();
var GraphBandwidth = $('#graph-bandwidth', '#content-inner');
var GraphBandwidthFilterTransmit = $('#graph-bandwidth-filters-transmit', '#graph-bandwidth-filters');
var GraphBandwidthFilterTransmitActive = GraphBandwidthFilterTransmit.hasClass('active');
var GraphBandwidthData = new Array();
var GraphBandwidthdata = [];
var GraphBandwidthMaxDataPoints = 120;
var GraphBandwidthOpts = {
	colors: ['#7386AD','#91a73c'],
	xaxis: {
		mode: 'time'
	},
	yaxis: {
		min: 0,
		tickFormatter: function (v) { return v+' Mbps';}
	},
	series: {
		lines: {show: true}
	},
	legend: {
		show: true,
		position: 'nw',
		noColumns: 5,
		labelFormatter: function(label, series) { return label; }
	},
};
var GraphBandwidthFilters = $('#graph-bandwidth-filters-transmit, #graph-bandwidth-filters-receive', '#graph-bandwidth-filters');
var GraphBandwidthAJAX;
// 30 Day Data
var Graph30Day = $('#graph-30day', '#content-inner');
var Graph30DayData;
var Graph30DayOpts = {
	colors: ['#7386ad'],
	xaxis: {
		mode: 'time',
	},
	yaxis: {
		tickFormatter: function (v) {
			return '<div style="width: 35px; text-align: right; padding-right: 7px;">'+v+'</div>';
		},
		min: 0,
	},
	series: {
		lines: {
			show: true,
			fill: true,
		},
		points: {
			show: true,
		}
	},
	legend: {
		position: 'nw'
	}
};
// Client Count variables
var GraphClient = $('#graph-activity','#content-inner');
var UpdateClientCountData = [[0,0]];
var UpdateClientCountOpts = {
	colors: ['#cb4b4b','#7386ad','#45a73c'],
	series: {
		pie: {
			show: true,
			radius: 1,
			label: {
				radius: .75,
				formatter: function(label, series) {
					return '<div style="font-size:8pt;text-align:center;padding:2px;color:white;">'+label+'<br/>'+Math.round(series.percent)+'%</div>';
				},
				background: {opacity: 0.5}
			}
		},
	},
	legend: {
		show: true,
		align: 'left',
		labelColor: '#666',
		labelFormatter: function(label, series) {
			return '<div style="font-size:8pt;padding:2px">'+label+': '+series.datapoints.points[1]+'</div>';
		}
	}
};
$(function()
{
	// Diskusage Graph - Node select - Hook select box to load new data via AJAX
	GraphDiskUsageUpdate();
	UpdateClientCount();
	$('#diskusage-selector select').change(function()
	{
		GraphDiskUsageUpdate();
		UpdateClientCount();
		return false;
	});
	// Client Count starter.
	// Only start bandwidth once the page is fully loaded.
	// 30 Day History Graph
	if (typeof(Graph30dayData) != 'undefined') {
		Graph30DayData = [
			{label: 'Computers Imaged',data: JSONParseFunction(Graph30dayData)}
		];
	}
	$.plot(Graph30Day,Graph30DayData,Graph30DayOpts);
	// Start counters
	UpdateBandwidth();
	// Bandwidth Graph - TX/RX Filter
	GraphBandwidthFilters.click(function()
	{
		// Blur -> add active class -> remove active class from old active item
		$(this).blur().addClass('active').siblings('a').removeClass('active');
		// Update title
		$('#graph-bandwidth-title > span').eq(0).html($(this).html());
		GraphBandwidthFilterTransmitActive = (GraphBandwidthFilterTransmit.hasClass('active') ? true : false);
		// Update graph
		UpdateBandwidth();
		// Prevent default action
		return false;
	});
	// Bandwidth Graph - Time Filter
	$('#graph-bandwidth-filters div:eq(2) a').click(function()
	{
		// Blur -> add active class -> remove active class from old active item
		$(this).blur().addClass('active').siblings('a').removeClass('active');
		// Update title
		$('#graph-bandwidth-title > span').eq(1).html($(this).html());
		// Update max data points variable
		GraphBandwidthMaxDataPoints = $(this).attr('rel');
		// Update graph
		UpdateBandwidth();
		// Prevent default action
		return false;
	});
	// Remove loading spinners
	$('.graph').not(GraphDiskUsage,GraphBandwidth).addClass('loaded');
	// Set the intervals.
});
// Disk Usage Functions
function GraphDiskUsageUpdate() {
	if (GraphDiskUsageAJAX) GraphDiskUsageAJAX.abort();
	NodeID = GraphDiskUsageNode.val();
	GraphDiskUsageAJAX = $.ajax({
		url: '../status/freespace.php',
		cache: false,
		type: 'GET',
		data: {
			id:NodeID
		},
		dataType: 'json',
		beforeSend: function() {
			GraphDiskUsage.html('').removeClass('loaded').parents('a').attr('href','?node=hwinfo&id='+NodeID);
		},
		success: function(data) {
			if (data.length == 0) return;
			GraphDiskUsagePlots(data);
			setTimeout('GraphDiskUsageUpdate()',120000);
		}
	});
}
function GraphDiskUsagePlots(data) {
	if (typeof(data['error']) != 'undefined') {
		GraphDiskUsage.html((data['error'] ? data['error'] : 'No error, but no data was returned')).addClass('loaded');
		return;
	};
	GraphDiskUsageData = [ 
		{label: 'Free',data: parseInt(data['free'])},
		{label: 'Used',data: parseInt(data['used'])}
	];
	$.plot(GraphDiskUsage,GraphDiskUsageData,GraphDiskUsageOpts);
	GraphDiskUsage.addClass('loaded');
}
// Bandwidth Functions
function UpdateBandwidth() {
	$.ajax({
		url: '../management/index.php?node=home',
		cache: false,
		type: 'GET',
		data: {sub: 'bandwidth'},
		dataType: 'json',
		success: function(data) {
			if (data.length == 0) return;
			UpdateBandwidthGraph(data);
			setTimeout('UpdateBandwidth()',1000);
		}
	});
}
function UpdateBandwidthGraph(data) {
	var d = new Date();
	Now = new Date().getTime() - (d.getTimezoneOffset() * 60000);
	for (i in data) {
		// Setup all the values we may need.
		if (typeof(GraphBandwidthData[i]) == 'undefined') {
			GraphBandwidthData[i] = new Array();
			GraphBandwidthData[i]['tx_old'] = new Array();
			GraphBandwidthData[i]['rx_old'] = new Array();
			GraphBandwidthData[i]['tx'] = new Array();
			GraphBandwidthData[i]['rx'] = new Array();
		}
		// If the old is set, setup the new, compare and set the tbps/rbps values.
		if (GraphBandwidthData[i]['tx_old'].length == 1) {
			GraphBandwidthData[i]['tx'].push([Now,Math.round((Math.round((data[i]['tx'] / 1024), 2) - GraphBandwidthData[i]['tx_old']) * 8 / 1000,2)]);
			GraphBandwidthData[i]['rx'].push([Now,Math.round((Math.round((data[i]['rx'] / 1024), 2) - GraphBandwidthData[i]['rx_old']) * 8 / 1000,2)]);
			// Reset the old and new values for the next iteration.
			GraphBandwidthData[i]['tx_old'] = new Array();
			GraphBandwidthData[i]['rx_old'] = new Array();
		} else if (GraphBandwidthData[i]['tx_old'].length == 0) {
			// Set the old values and wait one second.
			GraphBandwidthData[i]['tx_old'].push([Math.round((data[i]['tx'] / 1024), 2)]);
			GraphBandwidthData[i]['rx_old'].push([Math.round((data[i]['rx'] / 1024), 2)]);
		}
		// If the rx/tx are at their max datapoints, shift off the last bit's of data.
		while (GraphBandwidthData[i]['tx'].length >= GraphBandwidthMaxDataPoints) {
			GraphBandwidthData[i]['tx'].shift();
			GraphBandwidthData[i]['rx'].shift();
		}
	}
	GraphData = new Array();
	for (i in GraphBandwidthData) {
		GraphData.push({label: i, data: (GraphBandwidthFilterTransmitActive ? GraphBandwidthData[i]['tx'] : GraphBandwidthData[i]['rx'])});
	}
	$.plot(GraphBandwidth,GraphData,GraphBandwidthOpts);
}
// Client Count Functions.
function UpdateClientCount() {
	NodeID = GraphDiskUsageNode.val();
	$.ajax({
		url: '../status/clientcount.php',
		cache: false,
		type: 'GET',
		data: {
			id: NodeID
		},
		dataType: 'json',
		success: function(data) {
			if (data.length == 0) return;
			UpdateClientCountPlot(data);
			setTimeout('UpdateClientCount()',1000);
		}
	});
}
function UpdateClientCountPlot(data) {
	UpdateClientCountData = [
		{label:'Active',data:parseInt(data['ActivityActive'])},
		{label:'Queued',data:parseInt(data['ActivityQueued'])},
		{label:'Free',data:parseInt(data['ActivitySlots'])}
	];
	$.plot(GraphClient,UpdateClientCountData,UpdateClientCountOpts);
	$('#ActivityActive').html(data['ActivityActive']);
	$('#ActivityQueued').html(data['ActivityQueued']);
	$('#ActivitySlots').html(data['ActivitySlots']);
}
