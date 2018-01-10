(function($) {
    var addToGroup = $("#addSelectedToGroup");
    var deleteSelected = $("#deleteSelected");

    function disableButtons (disable) {
        addToGroup.prop("disabled", disable);
        deleteSelected.prop("disabled", disable);
    }
    function onSelect (selected) {
        var disabled = selected.count() == 0;
        disableButtons(disabled);
    }

    disableButtons(true);
    var table = Common.registerTable($("#dataTable"), onSelect);
    
    deleteSelected.click(function() {
        disableButtons(true);

        Common.massDelete(null, function(err) {
                onSelect(table.rows({selected: true}));
            }, table);
    });

})(jQuery);
