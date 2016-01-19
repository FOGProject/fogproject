$(function() {
    checkboxToggleSearchListPages();
    var iFileVal = $('#iFile').val();
    $('#iFile').on('change keyup',function(e) {
        var start = this.selectionStart,
            end = this.selectionEnd;
        this.value = this.value.replace(/[^\w+]/g,'');
        this.setSelectionRange(start,end);
        iFileVal = this.value;
        e.preventDefault();
    });
    $('#iName').on('change keyup',function(e) {
        var start = this.selectionStart,
            end = this.selectionEnd;
        if (iFileVal.length == 0) $('#iFile').val(this.value.replace(/[^\w+]/g,''));
        this.setSelectionRange(start,end);
        e.preventDefault();
    }).blur(function(e) {
        if (iFileVal.length == 0) $('#iFile').val(this.value.replace(/[^\w+]/g,''));
        iFileVal = $('#iFile').val();
        e.preventDefault();
    });
});
