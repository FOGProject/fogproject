// 30 day
var Graph30Day,
    Graph30DayOpts = {
        colors: ['#7386ad'],
        grid: {
            hoverable: true,
            borderColor: '#f3f3f3',
            borderWidth: 1
        },
        xaxis: {
            mode: 'time',
            tickLength: 0,
            show: true
        },
        yaxis: {
            tickFormatter: function(v) {
                return '<div class="tick r">'
                    + v
                    + '</div>';
            },
            min: 0,
            minTickSize: 1,
            tickLength: 0,
            show: true
        },
        lines: {
            fill: false
        },
        series: {
            shadowSize: 0,
            lines: {
                show: true,
            },
            points: {
                show: true
            }
        },
        legend: {
            show: false
        }
    },
    // Bandwidth
    GraphBandwidthMaxDataPoints,
    realtime = 'on',
    // Client Count
    updateClientCountData = [[0,0]],
    updateClientCountOpts = {
        colors: ['#45a73c','#7386ad','#cb4b4b'],
        series: {
            pie: {
                show: true,
                radius: 1,
                innerRadius: 0.5,
                label: {
                    show: true,
                    radius: 2/3,
                    formatter: function(label, series) {
                        return '<div style="color: #f3f3f3">'
                            + series.percent
                            + '%</div>';
                    },
                    threshold: 0.1
                }
            }
        },
        legend: {
            show: true,
            align: 'right',
            position: 'se',
            labelColor: '#666666',
            labelFormatter: function(label, series) {
                return '<div class="graph-legend">'
                    + label
                    + ': '
                    + series.datapoints.points[1]
                    + '</div>';
            }
        }
    },
    clientinterval,
    // Disk Usage
    GraphDiskUsageOpts = {
        colors: ['#45a73c', '#cb4b4b'],
        series: {
            pie: {
                show: true,
                radius: 1,
                innerRadius: 0.5,
                label: {
                    show: true,
                    radius: 3/4,
                    formatter: function(label, series) {
                        return '<div style="color: #f3f3f3">'
                            + Math.round(series.percent)
                            + '%</div>';
                    },
                    threshold: 0.1
                }
            }
        },
        legend: {
            show: true,
            align: 'right',
            position: 'se',
            labelColor: '#666666',
            labelFormatter: function(label, series) {
                units = [
                    ' iB',
                    ' KiB',
                    ' MiB',
                    ' GiB',
                    ' TiB',
                    ' PiB',
                    ' EiB',
                    ' ZiB',
                    ' YiB'
                ];
                for (var i = 0; series.data[0][1] >= 1024 && i < units.length - 1; i++) {
                    series.data[0][1] /= 1024;
                }
                return '<div class="graph-legend">'
                    + label
                    + ': '
                    + series.data[0][1].toFixed(2)
                    + units[i]
                    + '</div>';
            }
        }
    },
    GraphDiskUsageData = [],
    diskusageinterval,
    startTime = new Date().getTime(),
    loadings = {
        activity: true,
        diskusage: true,
        imagehistory: true,
        bandwidth: true
    };

(function($) {
    Graph30Day = $('#graph-30day');

    setupOverlays();

    setupOverview();
    setupActivity();
    setupDiskUsage();
    setupImagingHistory();
    setupBandwidth();
})(jQuery);

function setupOverlays() {
    var activity = $("#graph-activity");
    var diskUsage = $("#diskusage-selector");
    var bandwidth = $("#realtime");
    makeParentBoxLoad(activity, true);
    makeParentBoxLoad(diskUsage, true);
    makeParentBoxLoad(Graph30Day, true);
    makeParentBoxLoad(bandwidth, true);
}


function makeParentBoxLoad(child, loading) {
    var parent = child.closest('.box');
    //Common.setLoading(parent, loading);
}

function setupOverview() {

}

function setupActivity() {
    // Client Count
    $('#graph-activity').css({
        height: '150px',
        color: '#f3f3f3',
        'text-decoration': 'none',
        'font-weight': 'bold'
    });
    var updateClientCount = function() {
        sgID = $('#graph-activity-selector select').val();
        if (!loadings.activity) {
            makeParentBoxLoad($('#graph-activity'), true);
            loadings.activity = true;
        }
        $.ajax({
            url: '../management/index.php?node=home&sub=clientcount',
            type: 'post',
            data: {
                id: sgID
            },
            dataType: 'json',
            success: updateClientCountGraph,
            error: function(jqXHR, textStatus, errorThrown) {
            },
            complete: function() {
                $('#graph-activity').addClass('loaded');
                if (loadings.activity) {
                    makeParentBoxLoad($('#graph-activity'), false);
                    loadings.activity = false;
                }
            }
        });
    };
    var updateClientCountGraph = function(data) {
        updateClientCountData = [
            {
                label: data._labels[0],
                data: parseInt(data.ActivitySlots)
            },
            {
                label: data._labels[1],
                data: parseInt(data.ActivityQueued)
            },
            {
                label: data._labels[2],
                data: parseInt(data.ActivityActive)
            }
        ];
        if (data.error) {
            $('#graph-activity').html(
                '<div class="alert alert-warning">'
                + '<h4>'
                + data.title
                + '</h4>'
                + data.error
                + '</div>'
            );
        } else {
            $('#graph-activity').html('');
            $.plot('#graph-activity', updateClientCountData, updateClientCountOpts);
        }
        if (clientinterval) {
            clearTimeout(clientinterval);
        }
        clientinterval = setTimeout(
            updateClientCount,
            300000 - (
                (new Date().getTime() - startTime)
                % 300000
            )
        );
    };
    updateClientCount();
    $('#graph-activity-selector select').on('change', function(e) {
        if (clientinterval) {
            clearTimeout(clientinterval);
        }
        updateClientCount();
        e.preventDefault();
    });
}

function setupDiskUsage() {
    // Disk Usage
    $('#graph-diskusage').css({
        height: '150px',
        color: '#f3f3f3',
        'text-decoration': 'none',
        'font-weight': 'bold'
    });
    var updateDiskUsage = function() {
        var now = new Date().getTime(),
            nodeid = $('#diskusage-selector select').val();
        if (!loadings.diskusage) {
            makeParentBoxLoad($('#graph-diskusage'), true);
            loadings.diskusage = true;
        }
        $.ajax({
            url: '../management/index.php?node=home&sub=diskusage',
            data: {
                id: nodeid
            },
            dataType: 'json',
            success: updateDiskUsageGraph,
            error: function(jqXHR, textStatus, errorThrown) {
            },
            complete: function() {
                if (loadings.diskusage) {
                    makeParentBoxLoad($('#graph-diskusage'), false);
                    loadings.diskusage = false;
                }
            }
        });
    };
    var updateDiskUsageGraph = function(data) {
        GraphDiskUsageData = [
            {
                label: data._labels[0],
                data: parseInt(data.free, 10)
            },
            {
                label: data._labels[1],
                data: parseInt(data.used, 10)
            }
        ];
        if (data.error) {
            $('#graph-diskusage').html(
                '<div class="alert alert-warning">'
                + '<h4>'
                + data.title
                + '</h4>'
                + data.error
                + '</div>'
            );
        } else {
            $.plot($('#graph-diskusage'), GraphDiskUsageData, GraphDiskUsageOpts);
        }
        if (diskusageinterval) {
            clearTimeout(diskusageinterval);
        }
        diskusageinterval = setTimeout(
            updateDiskUsage,
            300000 - (
                (
                    new Date().getTime() - startTime
                ) % 300000
            )
        );
    };
    nodeid = $('#diskusage-selector select').val();
    $('#graph-diskusage').parents('a').attr('href', '../management/index.php?node=hwinfo&id=' + nodeid);
    updateDiskUsage();
    $('#diskusage-selector select').on('change', function(e) {
        nodeid = $(this).val();
        $('#graph-diskusage').parents('a').attr('href', '../management/index.php?node=hwinfo&id=' + nodeid);
        if (diskusageinterval) {
            clearTimeout(diskusageinterval);
        }
        updateDiskUsage();
        e.preventDefault();
    });
}

function setupImagingHistory() {
    // 30 day chart, updates every 5 minutes
    var update30day = function() {
        if (!loadings.imaginghistory) {
            makeParentBoxLoad($('#graph-30day'), true);
            loadings.imaginghistory = true;
        }
        $.ajax({
            url: '../management/index.php?node=home',
            type: 'post',
            data: {
                sub: 'get30day'
            },
            dataType: 'json',
            success: function(data) {
                var Graph30DayData = [
                    {
                        label: 'Computers Imaged',
                        data: data
                    }
                ];
                $.plot(
                    Graph30Day,
                    Graph30DayData,
                    Graph30DayOpts
                );
                setTimeout(
                    update30day,
                    300000 - (
                        (
                            new Date().getTime() - startTime
                        ) % 300000
                    )
                );
            },
            complete: function() {
                if (loadings.imaginghistory) {
                    makeParentBoxLoad($('#graph-30day'), false);
                    loadings.imaginghistory = false;
                }
            }
        });
    };
    $('<div class="tooltip-inner" id="graph-30day-tooltip"></div>').css({
        position: 'absolute',
        display: 'none',
        opacity: 0.8
    }).appendTo('body');
    Graph30Day.css({
        height: '150px'
    }).bind('plothover', function(event, pos, item) {
        if (item) {
            var x = item.datapoint[0],
                y = item.datapoint[1],
                date = new Date(x).toDateString();
            $('#graph-30day-tooltip').html(
                item.series.label + ': ' + y + ' on ' + date
            ).css({
                top: item.pageY + 5,
                left: item.pageX + 5
            }).fadeIn(200);
        } else {
            $('#graph-30day-tooltip').hide();
        }
    });
    update30day();
}

function setupBandwidth() {

    // Get the list of url's and names.
    var nodeurls = $('#bandwidthUrls').val().split(','),
        nodenames = $('#nodeNames').val().split(','),
        urls,
        names;

    function setStuff(data) {
        urls = data.urls;
        names = data.names;
        updateBandwidth();
    }
    // Let's find out which are actually available.
    $.ajax({
        url: '../management/index.php?node=home&sub=testUrls',
        type: 'post',
        data: {
            url: nodeurls,
            names: nodenames
        },
        dataType: 'json',
        success: function(data) {
            console.log('here');
            setStuff(data);
        }
    });

    // Bandwidth chart
    function updateBandwidth() {
        var GraphBandwidthOpts = {
            grid: {
                borderColor: '#f3f3f3',
                borderWidth: 1,
                tickColor: '#f3f3f3'
            },
            series: {
                shadowSize: 0,
                lines: {
                    show: true
                }
            },
            lines: {
                fill: false
            },
            xaxis: {
                mode: 'time',
                show: true
            },
            yaxis: {
                min: 0,
                tickFormatter: function(v) {
                    var f = parseFloat(v);
                    f = f.toFixed(2);
                    return f + ' Mbps';
                },
                show: true
            },
            legend: {
                show: true,
                noColumns: 5,
                labelFormatter: function(label, series) {
                    return label;
                    //return label.replace('()', '');
                },
                position: 'nw'
            }
        };

        var GraphData = [],
            GraphBandwidthData = [],
            GraphBandwidth = $('#graph-bandwidth'),
            bandwidthinterval,
            bandwidthajax;

        // Fetches our data.
        function fetchData() {

            // When ajax recieves data, this updates the graph.
            function onDataReceived(series) {

                // Setup our Time elements.
                var d = new Date(),
                    n = d.getTime() - (d.getTimezoneOffset() * 60000);

                // Because we can monitor multiple servers, we must iterate
                // all of our data.
                $.each(series, function(index, value) {

                    // If the data hasn't been initilized yet, initialize it.
                    if (typeof GraphBandwidthData[index] == 'undefined') {
                        GraphBandwidthData[index] = {};
                        GraphBandwidthData[index].tx = [];
                        GraphBandwidthData[index].rx = [];
                        GraphBandwidthData[index].dev = value.dev;
                        GraphBandwidthData[index].name = value.name;
                    }

                    // Push new data to storage.
                    GraphBandwidthData[index].tx.push([n, value.tx]);
                    GraphBandwidthData[index].rx.push([n, value.rx]);

                    // This is magical. It bases the first tx time as compared
                    // to the current time. As javascript is milliseconds,
                    // it takes our relational point (GraphBandwidthMaxDataPoints)
                    // and multiplies it by 1000. If the time is greater than x
                    // seconds, shift off the array.
                    //
                    // In the past it was just the length of the store, which was
                    // rather inaccurate. As the data recieved might have taken longer
                    // to store, you could end up with a 2 minute time spanning over 4-5
                    // minutes. Imagine how this would play out for 5 minutes, 10 minutes,
                    // 30 minutes, or an hour.
                    while (n - GraphBandwidthData[index].tx[0][0] > GraphBandwidthMaxDataPoints * 1000) {
                        GraphBandwidthData[index].tx.shift();
                        GraphBandwidthData[index].rx.shift();
                    }

                    // This is how our data is presented to the graph.
                    GraphData[index] = {
                        label: GraphBandwidthData[index].name
                        + ' ('
                        + GraphBandwidthData[index].dev
                        + ')',
                        data: (
                            $('#graph-bandwidth-filters-transmit').hasClass('active') ?
                            GraphBandwidthData[index].tx :
                            GraphBandwidthData[index].rx
                        )
                    };
                });

                // Update our graph if we can.
                if (realtime === 'on') {
                    $.plot(GraphBandwidth, GraphData, GraphBandwidthOpts);
                }

                // Start a new iteration.
                bandwidthinterval = setTimeout(fetchData, 1000);
            }


            // This gets our data.
            bandwidthajax = $.ajax({
                // The url
                url: '../management/index.php?node=home&sub=bandwidth',
                // How we're sending the request.
                type: 'post',
                // The data we want to obtain.
                data: {
                    url: urls,
                    names: names
                },
                // The data type.
                dataType: 'json',
                // Before we actually send,
                beforeSend: function() {
                    // If ajax is already running, abort it.
                    if (bandwidthajax) {
                        bandwidthajax.abort();
                    }
                    // If the timeout is still running, abore it.
                    // It shouldn't make it here, but safer is better.
                    if (bandwidthinterval) {
                        clearTimeout(bandwidthinterval);
                    }
                },
                // On Success, update our graph and data.
                success: onDataReceived,
                complete: function() {
                    if (loadings.bandwidth) {
                        makeParentBoxLoad($('#realtime'), false);
                        loadings.bandwidth = false;
                    }
                }
            });
        }

        // Actually fetch our data.
        fetchData();
    }
    // If the user presses the off button, we should stop
    // displaying, notice we still collect data, we just don't
    // display it. When you press on again, it should update
    // with your missed data.
    $('#realtime .btn').on('click', function(e) {
        // Return if on is already toggled.
        // Otherwise set our variable back to 'on' if on is pressed.
        // Otherwise set our variable to 'off' and stop presenting.
        if ($(this).data('toggle') === 'on' && realtime === 'on') {
            return;
        } else if ($(this).data('toggle') === 'on') {
            realtime = 'on';
            $('#btn-off').removeClass('active');
            $('#btn-on').addClass('active');
        } else {
            realtime = 'off';
            $('#btn-on').removeClass('active');
            $('#btn-off').addClass('active');
        }
    });

    $('.type-filters').on('click', function(e) {
        $('#graph-bandwidth-title > span').text($(this).text());
        $(this).blur().addClass('active').siblings('a').removeClass('active');
        $('#btn-on').trigger('click');
        e.preventDefault();
    });

    $('.time-filters').on('click', function(e) {
        $('#graph-bandwidth-time > span').text($(this).text());
        $(this).blur().addClass('active').siblings('a').removeClass('active');
        GraphBandwidthMaxDataPoints = $(this).prop('rel');
        $('#btn-on').trigger('click');
        e.preventDefault();
    });

    GraphBandwidthMaxDataPoints = $('.time-filters.active').prop('rel');

    $('#graph-bandwidth').css({
        height: '150px'
    });
}
