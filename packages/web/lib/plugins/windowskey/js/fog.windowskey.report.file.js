(function($) {
  var reportString = window.atob(Common.f);

  // Every plugin's report JS loads on any Reports page, so guard on the
  // report name and only act on this plugin's own report.
  switch (reportString) {
    case 'windowskey report':
      $('#windowskey-report-table').registerReportTable([
        {data: 'name'},
        {data: 'description', visible: false},
        {data: 'createdBy', visible: false},
        {data: 'createdTime', visible: false},
        {data: 'key'}
      ]);
      break;
  }
})(jQuery);
