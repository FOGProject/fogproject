(function($) {
  var reportString = window.atob(Common.f);

  // Every plugin's report JS loads on any Reports page, so guard on the
  // report name and only act on this plugin's own report.
  switch (reportString) {
    case 'site report':
      $('#site-report-table').registerReportTable([
        {data: 'name'},
        {data: 'description'}
      ]);
      break;
  }
})(jQuery);
