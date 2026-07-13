(function($) {
  var reportString = window.atob(Common.f);

  // Every plugin's report JS loads on any Reports page, so guard on the
  // report name and only act on this plugin's own report.
  switch (reportString) {
    case 'location report':
      $('#location-report-table').registerReportTable([
        {data: 'name'},
        {data: 'description'},
        {data: 'createdBy', visible: false},
        {data: 'createdTime', visible: false},
        {data: 'storagegroupID', visible: false},
        {data: 'storagenodeID', visible: false},
        {data: 'tftp', visible: false}
      ]);
      break;
  }
})(jQuery);
