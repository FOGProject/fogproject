(function($) {
    var createForm = $('#location-create-form'),
        createFormBtn = $('#send'),
        groupSelector = $('#storagegroup'),
        nodeSelector = $('#storagenode');
    createForm.on('submit', function(e) {
        e.preventDefault();
    });
    createFormBtn.on('click', function() {
        createFormBtn.prop('disabled', true);
        Common.processForm(createForm, function(err) {
            createFormBtn.prop('disabled', false);
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
})(jQuery);
