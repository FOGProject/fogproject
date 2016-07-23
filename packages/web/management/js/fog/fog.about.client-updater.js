$(function() {
    $("input[name='delcu']").click(function(e) {
        e.preventDefault();
        this.form.submit();
        this.remove();
    });
});
