$(function() {
    var activeSystemClass = $('.list-group-item.active');
    $('.system-search').keyup( function() {
        var that = this;
        var div = $(that).parents('.row');
        var tableBody = $('.table-list-search tbody', div);
        var tableRowsClass = $('.table-list-search tbody tr', div);
        $('.search-sf').remove();
        tableRowsClass.each( function(i, val) {
            var rowText = $(val).text().toLowerCase();
            var inputText = $(that).val().toLowerCase();
            if (inputText != '') {
                $('.search-query-sf').remove();
                tableBody.prepend(
                    '<tr class="search-query-sf"><td colspan="6">'
                    + '<strong>Searching for: "'
                    + $(that).val()
                    + '"</strong></td></tr>'
                );
            } else {
                $('.search-query-sf').remove();
            }
            if (rowText.indexOf(inputText) == -1) {
                tableRowsClass.eq(i).hide();
            } else {
                $('.search-sf').remove();
                tableRowsClass.eq(i).show();
            }
        });
        if(tableRowsClass.children(':visible').length == 0) {
            tableBody.append(
                '<tr class="search-sf"><td class="text-muted" colspan="6">'
                + 'No entries found.</td></tr>'
            );
        }
    });
});
