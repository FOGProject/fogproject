$(function() {
    checkboxToggleSearchListPages();
    $('#iFile').on('change keyup',function(e) {
        var start = this.selectionStart,
            end = this.selectionEnd;
        this.value = this.value.replace(/[^\w+]/g,'');
        this.setSelectionRange(start,end);
        e.preventDefault();
    });
    $('#iName').on('change keyup',function(e) {
        var start = this.selectionStart,
            end = this.selectionEnd;
        $('#iFile').val(this.value.replace(/[^\w+]/g,''));
        this.setSelectionRange(start,end);
        e.preventDefault();
    }).blur(function(e) {
        $('#iFile').val(this.value.replace(/[^\w+]/g,''));
        e.preventDefault();
    });
});
