$(function() {
    // ---------------------------------------------------------------
    // GENERAL TAB
    var originalName = $('#ldap').val(),
        updateName = function(newName) {
            var e = $('#pageTitle'),
                text = e.text();
            text = text.replace(': ' + originalName, ': ' + newName);
            document.title = text;
            e.text(text);
        },
        generalForm = $('#ldap-general-form'),
        generalFormBtn = $('#general-send'),
        generalDeleteBtn = $('#general-delete'),
        generalDeleteModal = $('#deleteModal'),
        generalDeleteModalConfirm = $('#confirmDeleteModal'),
        generalDeleteModalCancel = $('#closeDeleteModal'),
        templateSel = $('#template'),
        userNameAttr = $('#userNameAttr'),
        groupNameAttr = $('#groupNameAttr'),
        grpMemberAttr = $('#grpMemberAttr');

    generalForm.on('submit',function(e) {
        e.preventDefault();
    });
    generalFormBtn.on('click',function() {
        generalFormBtn.prop('disabled', true);
        generalDeleteBtn.prop('disabled', true);
        generalForm.processForm(function(err) {
            generalFormBtn.prop('disabled', false);
            generalDeleteBtn.prop('disabled', false);
            if (err) {
                return;
            }
            updateName($('#ldap').val());
            originalName = $('#ldap').val();
        });
    });
    generalDeleteBtn.on('click', function() {
        generalDeleteModal.modal('show');
    });
    generalDeleteModalConfirm.on('click', function() {
        var method = 'post',
            action = '../management/index.php?node='
                + Common.node
                + '&sub=delete&id='
                + Common.id;
        $.apiCall(method, action, null, function(err) {
            if (err) {
                return;
            }
            setTimeout(function() {
                window.location = '../management/index.php?node='
                    + Common.node
                    + '&sub=list';
            }, 2000);
        });
    });
    templateSel.on('change blur focus focusout', function(e) {
        e.preventDefault();
        selected = this.value;
        switch (selected) {
            case '0':
                usrAttr = 'samAccountName';
                grpAttr = 'member';
                grpNam = 'name'
                break;
            case '1':
                usrAttr = 'cn';
                grpAttr = 'member';
                grpNam = 'name';
                break;
            case '2':
                usrAttr = 'uid';
                grpAttr = 'uniqueMember';
                grpNam = 'ou';
                break;
            case '3':
                usrAttr = 'uid';
                grpAttr = 'member';
                grpNam = 'cn';
                break;
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
});
