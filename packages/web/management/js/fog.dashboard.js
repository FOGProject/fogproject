/****************************************************
 * FOG Dashboard JS
 *	Author:		Blackout
 *	Created:	3:04 PM 20/04/2011
 *	Revision:	$Revision$
 *	Last Update:	$LastChangedDate$
 ***/

var GraphDiskUsage;
var GraphDiskUsageAJAX;
var GraphDiskUsageNode;

var GraphBandwidthDebug = false;
var GraphBandwidth;
var GraphBandwidthPlot;
var GraphBandwidthData; 

var GraphBandwidthMaxDataPoints = 120;
var GraphBandwidthFilterTransmit;
var GraphBandwidthFilterTransmitActive;

var GraphData = new Array();
var SeriesData, Now, Delta, Offset;

var JSONParseFunction;


$(function()
{
	// Determine which function to use for parsing JSON data
	JSONParseFunction = (typeof(JSON) != 'undefined' ? JSON.parse : eval)

	// Graph objects
	GraphDiskUsage = $('#graph-diskusage', '#content-inner');
	GraphDiskUsageNode = $('#diskusage-selector select', '#content-inner');
	GraphBandwidth = $('#graph-bandwidth', '#content-inner');
	GraphBandwidthFilterTransmit = $('#graph-bandwidth-filters-transmit', '#graph-bandwidth-filters');
	GraphBandwidthFilterTransmitActive = GraphBandwidthFilterTransmit.hasClass('active');
	
	// Bandwidth Graph - init plot
	GraphBandwidthPlot = $.plot(GraphBandwidth, [[0,0]],
	{
		'colors': ['#7386AD', '#91a73c'],
		'xaxis':
		{
			'mode':		'time'
		},
		'yaxis':
		{
			'min':		'0',
			'tickFormatter': function (v)
			{
				return v + ' MB/s';
			}
		},
		'series':
		{
			'lines':
			{
				'show': true
			}
		},
		'legend':
		{
			show: 		false,
		}
	});
	
	// Bandwidth Graph - TX/RX Filter
	$('#graph-bandwidth-filters-transmit, #graph-bandwidth-filters-receive', '#graph-bandwidth-filters').click(function()
	{
		// Blur -> add active class -> remove active class from old active item
		$(this).blur().addClass('active').siblings('a').removeClass('active');
		
		// Update title
		$('#graph-bandwidth-title > span').eq(0).html($(this).html());
		
		// Set variable
		GraphBandwidthFilterTransmitActive = (GraphBandwidthFilterTransmit.hasClass('active') ? true : false)
		
		// Update graph
		UpdateBandwidthGraph();
		
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
		UpdateBandwidthGraph();

		// Prevent default action
		return false;
	});
	
	// Bandwidth Graph - start thread
	setTimeout(function()
	{
		UpdateBandwidth();
	}, (200));

	
	// 30 Day History Graph
	if (typeof(Graph30dayData) != 'undefined')
	{
		$.plot($('#graph-30day', '#content-inner'),
		[
			{
				'label': 'Computers Imaged',
				'data': JSONParseFunction(Graph30dayData)
			}
		], {
			'colors': ['#7386AD'],
			'xaxis':
			{
				'mode':		'time'
			},
			'yaxis':
			{
				'tickFormatter': function (v) { return '<div style="width: 35px; text-align: right; padding-right: 7px;">' + v + '</div>'; },
				'min':		0
			},
			'series':
			{
				'lines': { 'show': true, 'fill': true },
				'points': { 'show': true }
			},
			'legend':
			{
				'position':	'nw'
			}
		});
	}
	
	// Diskusage Graph - load default Storage Node on page load
	UpdateDiskUsage();
	
	// Diskusage Graph - Node select - Hook select box to load new data via AJAX
	$('#diskusage-selector select').change(function()
	{
		UpdateDiskUsage();
		return false;
	});
	
	// System Activity Graph
	$.plot($('#graph-activity', '#content-inner'),
	[
		{ 'label': 'Active', 'data': parseInt(ActivityActive) },
		{ 'label': 'Queued', 'data': parseInt(ActivityQueued) },
		{ 'label': 'Free', 'data': parseInt(ActivitySlots) }
	], {
		'colors': [ '#CB4B4B','#7386AD', '#45A73C'],
		'series':
		{
			'pie':
			{
				'show':		true,
				'radius':	1,
				'label':
				{
					'radius':	.75,
					'formatter':	function(label, series)
					{
						return '<div style="font-size:8pt;text-align:center;padding:2px;color:white;">'+label+'<br/>'+Math.round(series.percent)+'%</div>';
					},
					'background':	{ opacity: 0.5 }
				}
			}
		},
		'legend':
		{
			'show': 	true,
			'align':	'left',
			'labelColor':	'#666',
			'labelFormatter':	function(label, series)
			{
				return '<div style="font-size:8pt;padding:2px">'+label+': '+series.datapoints.points[1]+'</div>';
			}
		}
	});
	
	// Remove loading spinners
	$('.graph').not(GraphDiskUsage).addClass('loaded');
});

function UpdateDiskUsage()
{
	if (GraphDiskUsageAJAX) GraphDiskUsageAJAX.abort();
	
	var NodeID = GraphDiskUsageNode.val();
	
	GraphDiskUsageAJAX = $.ajax(
	{
		'url':		'../status/freespace.php',
		'cache':	false,
		'type':		'GET',
		'data':		{ 'id': NodeID },
		'dataType':	'json',
		'beforeSend':	function()
		{
			GraphDiskUsage.html('').removeClass('loaded').parents('a').attr('href', '?node=hwinfo&id=' + NodeID);
		},
		'success':	function(data)
		{
			GraphDiskUsage.addClass('loaded');
			
			if (data['error'] || (!data['free'] && !data['used']))
			{
				// Error was returned/incomplete data - show error
				GraphDiskUsage.html((data['error'] ? data['error'] : 'No error, but no data was return')).addClass('loaded');
			}
			else
			{
				// Everything was fine - build Disk Usage Graph
				$.plot(GraphDiskUsage,
				[
					{ 'label': 'Free', 'data': parseInt(data['free']) + parseInt(data['used']) },
					{ 'label': 'Used', 'data': parseInt(data['used']) }
				], {
					'colors': ['#45A73C', '#CB4B4B'],
					'series':
					{
						'pie':
						{
							'show':		true,
							'radius':	1
						}
					},
					'legend':
					{
						'show': 	true,
						'align':	'right',
						'position':	'se',
						'labelColor':	'#666',
						'labelFormatter':	function(label, series)
						{   var kb = 1;
                            var mb = kb * 1024;
                            var gb = mb * mb;
                            var tb = gb * mb;
                            var pb = tb * mb;
                            var eb = pb * mb;
                            if (series.data[0][1] >= eb){
                              series.data[0][1] = (Math.round(series.data[0][1]/eb *100)/100).toFixed(2);
                              return '<div style="font-size:8pt;padding:2px; margin-top: 16px;">'+label+': '+Math.round(series.percent)+'% <br />' + series.data[0][1]+' EiB</div>';
                            }
                            else if (series.data[0][1] >= pb && series.data[0][1] < eb){
                              series.data[0][1] = (Math.round(series.data[0][1]/pb * 100)/100).toFixed(2);
                              return '<div style="font-size:8pt;padding:2px; margin-top: 16px;">'+label+': '+Math.round(series.percent)+'% <br />' + series.data[0][1]+' PiB</div>';
                            }
                            else if (series.data[0][1] >= tb && series.data[0][1] < pb){
                              series.data[0][1] = (Math.round(series.data[0][1]/tb * 100)/100).toFixed(2);
                              return '<div style="font-size:8pt;padding:2px; margin-top: 16px;">'+label+': '+Math.round(series.percent)+'% <br />' + series.data[0][1]+' TiB</div>';
                            }
                            else if (series.data[0][1] >= gb && series.data[0][1] < tb){
                              series.data[0][1] = (Math.round(series.data[0][1]/gb * 100)/100).toFixed(2);
                              return '<div style="font-size:8pt;padding:2px; margin-top: 16px;">'+label+': '+Math.round(series.percent)+'% <br />' + series.data[0][1]+' GiB</div>';
                            }
                            else if (series.data[0][1] >=mb && series.data[0][1] < gb){
                              series.data[0][1] = (Math.round(series.data[0][1]/mb * 100)/100).toFixed(2);
							  return '<div style="font-size:8pt;padding:2px; margin-top: 16px;">'+label+': '+Math.round(series.percent)+'% <br />' + series.data[0][1]+' MiB</div>';
                            }
                            else if (series.data[0][1] < mb){
                              series.data[0][1] = (Math.round(series.data[0][1] * 100)/100).toFixed(2);
                              return '<div style="font-size:8pt;padding:2px; margin-top: 16px;">'+label+': '+Math.round(series.percent)+'% <br />' + series.data[0][1]+' KiB</div>';
                            }
						}
					}
				});
			}
		},
		'error':	function()
		{
			GraphDiskUsage.addClass('loaded');
		}
	});
}

function UpdateBandwidth()
{
	$.ajax(
	{
		'url':		'../status/bandwidth.php',
		'data':		{ 'sub': 'bandwidth' },
		'cache':	false,
		'type':		'GET',
		'dataType':	'json',
		'success':	function(data)
		{
			// Catch failures
			if (data.length == 0) return;

			// Call graph updater
			UpdateBandwidthGraph(data);
			
			// Schedule another update
			setTimeout(function()
			{
				UpdateBandwidth();
			}, 20);
		}
	});
}

function UpdateBandwidthGraph(data)
{
	if (GraphBandwidthDebug && window.console) console.profile();

	// Parse new data coming in -> add to data array
	if (typeof(data) != 'undefined')
	{
		// Create date object
		var d = new Date();
		
		// Convert to msec -> subtract local time zone offset -> get UTC time in msec
		Now = new Date().getTime() - (d.getTimezoneOffset() * 60000);

		if (typeof(GraphBandwidthData) == 'undefined')
		{
			GraphBandwidthData = new Array();
			GraphBandwidthData['tx'] = new Array();
			GraphBandwidthData['rx'] = new Array();
		}


		GraphBandwidthData['tx'].push([Now, Math.round(data['tx'] / 1024) ]);
		GraphBandwidthData['rx'].push([Now, Math.round(data['rx'] / 1024) ]);
		
		if (GraphBandwidthData['tx'].length >= GraphBandwidthMaxDataPoints)			// Without time filter
		{
			GraphBandwidthData['tx'].shift();
			GraphBandwidthData['rx'].shift();
		}
	}

	// Build graph data from GraphBandwidthData
	GraphData = new Array({ 'label': 'default', 'data': (GraphBandwidthFilterTransmitActive ? GraphBandwidthData['tx'] : GraphBandwidthData['rx']) });
	
	// Build graph with new data
	GraphBandwidthPlot.setData(GraphData);
	GraphBandwidthPlot.setupGrid();
	GraphBandwidthPlot.draw();
	
	if (GraphBandwidthDebug && window.console) console.profileEnd();
}
