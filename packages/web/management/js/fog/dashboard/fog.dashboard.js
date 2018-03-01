// 30 day
var Graph30Day = $('#graph-30day'),
    Graph30DayOpts = {
        colors: ['#3c8dbc', '#0073b7'],
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
    // Client Count
    updateClientCountData = [[0,0]],
    updateClientCountOpts = {
        colors: ['#00c0ef', '#3c8dbc', '#0073b7'],
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
        colors: ['#00c0ef', '#3c8dbc'],
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
    startTime = new Date().getTime();
(function($) {
    // 30 day chart, updates every 5 minutes
    var update30day = function() {
        Pace.ignore(function() {
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
                }
            });
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

    // Bandwidth chart
    function updateBandwidth() {
        var GraphBandwidthOpts = {
            colors: ['#3c8dbc', '#0073b7'],
            grid: {
                borderColor: '#f3f3f3',
                borderWidth: 1,
                tickColor: '#f3f3f3'
            },
            series: {
                shadowSize: 0,
                colors: ['#3c8dbc', '#0073b7'],
                lines: {
                    show: true
                }
            },
            lines: {
                fill: false,
                colors: ['#3c8dbc', '#0073b7']
            },
            xaxis: {
                mode: 'time',
                show: true
            },
            yaxis: {
                min: 0,
                tickFormatter: function(v) {
                    return v
                        + 'Mbps';
                },
                show: true
            },
            legend: {
                show: true,
                noColumns: 5,
                labelFormatter: function(label, series) {
                    return label.replace('()', '');
                },
                position: 'nw'
            }
        };

        var GraphData = [],
            GraphBandwidthData = [],
            GraphBandwidth = $('#graph-bandwidth'),
            bandwidthinterval,
            bandwidthajax;

        $.plot(GraphBandwidth, GraphData, GraphBandwidthOpts);

        function fetchData() {

            function onDataReceived(series) {
                var d = new Date(),
                    n = d.getTime() - (d.getTimezoneOffset() * 60000);
                $.each(series, function(index, value) {
                    if (typeof GraphBandwidthData[index] == 'undefined') {
                        GraphBandwidthData[index] = {};
                        GraphBandwidthData[index].tx = [];
                        GraphBandwidthData[index].rx = [];
                        GraphBandwidthData[index].dev = value.dev;
                        GraphBandwidthData[index].name = value.name;
                    }
                    GraphBandwidthData[index].tx.push([n, value.tx]);
                    GraphBandwidthData[index].rx.push([n, value.rx]);
                    while (n - GraphBandwidthData[index].tx[0][0] > GraphBandwidthMaxDataPoints * 1000) {
                        GraphBandwidthData[index].tx.shift();
                        GraphBandwidthData[index].rx.shift();
                    }

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
                $.plot(GraphBandwidth, GraphData, GraphBandwidthOpts);
                bandwidthinterval = setTimeout(fetchData, 1000);
            }

            bandwidthajax = $.ajax({
                url: '../management/index.php?node=home&sub=bandwidth',
                type: 'post',
                data: {
                    url: $('#bandwidthUrls').val().split(','),
                    names: $('#nodeNames').val().split(',')
                },
                dataType: 'json',
                beforeSend: function() {
                    if (bandwidthajax) {
                        bandwidthajax.abort();
                    }
                    if (bandwidthinterval) {
                        clearTimeout(bandwidthinterval);
                    }
                },
                success: onDataReceived
            });
        }

        fetchData();

        $('#realtime .btn').on('click', function(e) {
            if ($(this).data('toggle') === 'on') {
                var realtime = 'on';
                fetchData();
            } else {
                clearTimeout(bandwidthinterval);
                var realtime = 'off';
            }
        });
    }

    $('.type-filters').on('click', function(e) {
        $('#graph-bandwidth-title > span').text($(this).text());
        $(this).blur().addClass('active').siblings('a').removeClass('active');
        e.preventDefault();
    });

    $('.time-filters').on('click', function(e) {
        $('#graph-bandwidth-time-title > span').text($(this).text());
        $(this).blur().addClass('active').siblings('a').removeClass('active');
        GraphBandwidthMaxDataPoints = $(this).prop('rel');
        console.log(GraphBandwidthMaxDataPoints);
        e.preventDefault();
    });
    GraphBandwidthMaxDataPoints = $('.time-filters.active').prop('rel');
    console.log(GraphBandwidthMaxDataPoints);

    $('#graph-bandwidth').css({
        height: '150px'
    });

    updateBandwidth();

    // Client Count
    $('#graph-activity').css({
        height: '150px',
        color: '#f3f3f3',
        'text-decoration': 'none',
        'font-weight': 'bold'
    });
    var updateClientCount = function() {
        sgID = $('.activity-count').val();
        Pace.ignore(function() {
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
                }
            });
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
        $.plot('#graph-activity', updateClientCountData, updateClientCountOpts);
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
    $('.activity-count').on('change', function(e) {
        if (clientinterval) {
            clearTimeout(clientinterval);
        }
        updateClientCount();
        e.preventDefault();
    });

    // Disk Usage
    $('#graph-diskusage').css({
        height: '150px',
        color: '#f3f3f3',
        'text-decoration': 'none',
        'font-weight': 'bold'
    });
    var updateDiskUsage = function() {
        var now = new Date().getTime(),
            nodeid = $('.nodeid').val();
        Pace.ignore(function() {
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
                }
            });
        });
    };
    var updateDiskUsageGraph = function(data) {
        if (data.error) {
            $('#graph-diskusage').html(data.error ? data.error : 'No data returned');
        }
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
        $.plot($('#graph-diskusage'), GraphDiskUsageData, GraphDiskUsageOpts);
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
    nodeid = $('.nodeid').val();
    $('#hwinfolink').attr('href', '../management/index.php?node=hwinfo&id=' + nodeid);
    updateDiskUsage();
    $('.nodeid').on('change', function(e) {
        nodeid = $(this).val();
        $('#hwinfolink').attr('href', '../management/index.php?node=hwinfo&id=' + nodeid);
        if (diskusageinterval) {
            clearTimeout(diskusageinterval);
        }
        updateDiskUsage();
        e.preventDefault();
    });
})(jQuery);
