(function($) {
    var importForm = $('#import-form'),
        exportForm = $('#export-form'),
        importBtn = $('#importdb'),
        exportBtn = $('#exportdb');

    function disableButtons(disable) {
        importBtn.prop('disabled', disable);
        exportBtn.prop('disabled', disable);
    }

    importForm.on('submit', function(e) {
        e.preventDefault();
    });
    exportForm.on('submit', function(e) {
        e.preventDefault();
    });

    importBtn.on('click', function(e) {
        e.preventDefault();
        disableButtons(true);
        Common.processForm(importForm, function(err, data) {
            disableButtons(false);
            if (err) {
                return;
            }
        });
    });
    exportBtn.on('click', function(e) {
        e.preventDefault();
        disableButtons(true);
        var method = importForm.attr('method'),
            action = importForm.attr('action'),
            opts = {
                toExport: 1
            };
        Common.apiCall(method, action, opts, function(err, data) {
            disableButtons(false);
            if (err) {
                return;
            }
            var element = document.createElement('a');
            element.setAttribute('href', 'data:text/plain;charset=utf-8,' + encodeURIComponent(data._content));
            element.setAttribute('download', data._filename);
            element.style.display = 'none';
            document.body.appendChild(element);

            element.click();

            document.body.removeChild(element);
        });
    });
})(jQuery);
