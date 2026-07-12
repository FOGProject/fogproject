/**
 * Hello World edit page (sub=edit).
 *
 * Handles the General tab: update (processForm -> editPost) and delete
 * (confirm modal -> $.apiCall to sub=delete), then redirect back to the list.
 */
$(function() {
    $.registerGeneralTab({
        nameInputSel: '#name',
        formSel: '#helloworld-general-form'
    });
});
