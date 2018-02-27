$(function() {
    // ---------------------------------------------------------------
    // GENERAL TAB
    var originalName = $('#location').val(),
        updateName = function(newName) {
            var e = $('#pageTitle'),
                text = e.text();
            text = text.replace(': ' + originalName, ': ' + newName);
            document.title = text;
            e.text(text);
        };

    var generalForm = $('#location-general-form'),
        generalFormBtn = $('#general-send'),
        generalDeleteBtn = $('#general-delete');

    generalForm.on('submit',function(e) {
        e.preventDefault();
    });
    generalFormBtn.on('click',function() {
        generalFormBtn.prop('disabled', true);
        generalDeleteBtn.prop('disabled', true);
        Common.processForm(generalForm, function(err) {
            generalFormBtn.prop('disabled', false);
            generalDeleteBtn.prop('disabled', false);
            if (err) {
                return;
            }
            updateName($('#location').val());
            originalName = $('#location').val();
        });
    });
    generalDeleteBtn.on('cilck', function() {
        generalFormBtn.prop('disabled', true);
        generalDeleteBtn.prop('disabled', true);
        Common.massDelete(null, function(err) {
            if (err) {
                generalFormBtn.prop('disabled', false);
                generalDeleteBtn.prop('disabled', false);
                return;
            }
            window.location = '../management/index.php?node='
            + Common.node
            + '&sub=list';
        });
    });
    // Sets the group selector for the selected node.
    nodeSelector.on('change focus focusout', function(e) {
        e.preventDefault();
        var nodeID = this.value;
        Pace.ignore(function() {
            $.get('../fog/storagenode/'+nodeID, function(data) {
                groupSelector.val(data.storagegroupID).select2({
                    width: '100%'
                });
            }, 'json');
        });
    });
    // Resets the node selector of the selected group is not
    // the selected nodes storage group.
    groupSelector.on('change focus focusout', function(e) {
        e.preventDefault();
        var nodeID = nodeSelector.val(),
            groupID = this.value;
        Pace.ignore(function() {
            $.get('../fog/storagegroup/'+groupID, function(data) {
                if ($.inArray(nodeID, data.allnodes) != -1) {
                    return;
                }
                nodeSelector.val('').select2({
                    width: '100%'
                });
            }, 'json');
        });
    });
    // ---------------------------------------------------------------
    // STORAGE GROUP ASSOCIATION TAB
    // TODO: Make Functional

    // ---------------------------------------------------------------
    // STORAGE NODE ASSOCIATION TAB
    // TODO: Make Functional

    // ---------------------------------------------------------------
    // HOST ASSOCIATION TAB
    // TODO: Make Functional
});
