(function($) {
    var templateSel = $('#template'),
        userNameAttr = $('#userNameAttr'),
        groupNameAttr = $('#groupNameAttr'),
        grpMemberAttr = $('#grpMemberAttr');
    $('#ldap-create-form').wireCreateForm();
    templateSel.on('change blur focus focusout', function(e) {
        e.preventDefault();
        selected = this.value;
        switch (selected) {
            case '0':
                usrAttr = 'samAccountName';
                grpAttr = 'member';
                grpNam = 'name';
                break;
            case '1':
                usrAttr = 'cn';
                grpAttr = 'member';
                grpNam = 'name';
                break;
            case '2':
                usrAttr = 'uid';
                grpAttr = 'uniqueMember';
                grpNam = 'name'
                break;
            case '3':
                usrAttr = 'uid';
                grpAttr = 'member';
                grpNam = 'cn';
            default:
                usrAttr = '';
                grpAttr = '';
                grpNam = '';
                break;
        }
        userNameAttr.val(usrAttr);
        groupNameAttr.val(grpNam);
        grpMemberAttr.val(grpAttr);
    });
})(jQuery);
