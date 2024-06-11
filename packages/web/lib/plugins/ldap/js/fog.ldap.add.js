(function($) {
    var createForm = $('#ldap-create-form'),
        createFormBtn = $('#send'),
        templateSel = $('#template'),
        userNameAttr = $('#userNameAttr'),
        groupNameAttr = $('#groupNameAttr'),
        grpMemberAttr = $('#grpMemberAttr');
    createForm.on('submit', function(e) {
        e.preventDefault();
    });
    createFormBtn.on('click', function() {
        createFormBtn.prop('disabled', true);
        createForm.processForm(function(err) {
            createFormBtn.prop('disabled', false);
        });
    });
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
