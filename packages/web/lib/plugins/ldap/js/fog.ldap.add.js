(function($) {
    var createForm = $('#ldap-create-form'),
        createFormBtn = $('#send'),
        templateSel = $('#template'),
        userNameAttr = $('#userNameAttr'),
        grpMemberAttr = $('#grpMemberAttr');
    createForm.on('submit', function(e) {
        e.preventDefault();
    });
    createFormBtn.on('click', function() {
        createFormBtn.prop('disabled', true);
        Common.processForm(createForm, function(err) {
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
                break;
            case '1':
                usrAttr = 'cn';
                grpAttr = 'member';
                break;
            case '2':
                usrAttr = 'uid';
                grpAttr = 'uniqueMember';
                break;
            default:
                usrAttr = '';
                grpAttr = '';
                break;
        }
        userNameAttr.val(usrAttr);
        grpMemberAttr.val(grpAttr);
    });
})(jQuery);
