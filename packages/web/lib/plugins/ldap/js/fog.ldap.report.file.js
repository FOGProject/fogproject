(function($) {
  var reportString = window.atob(Common.f);

  // Every plugin's report JS loads on any Reports page, so guard on the
  // report name and only act on this plugin's own report.
  switch (reportString) {
    case 'ldap report':
      $('#ldap-report-table').registerReportTable([
        {data: 'name'},
        {data: 'description', visible: false},
        {data: 'createdBy', visible: false},
        {data: 'createdTime', visible: false},
        {data: 'address'},
        {data: 'port', visible: false},
        {data: 'searchDN', visible: false},
        {data: 'userNamAttr', visible: false},
        {data: 'grpNamAttr'},
        {data: 'grpMemberAttr', visible: false},
        {data: 'adminGroup'},
        {data: 'userGroup', visible: false},
        {data: 'searchScope', visible: false},
        {data: 'bindDN', visible: false},
        {data: 'grpSearchDN', visible: false},
        {data: 'useGroupMatch', visible: false},
        {data: 'displayNameOn', visible: false},
        {data: 'displayNameAttr', visible: false}
      ]);
      break;
  }
})(jQuery);
