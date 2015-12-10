var sub = $_GET['sub'],
    ActiveTasksContainer = $('#active-tasks'),
    ActiveTasksLastCount,
    URL;
$(function() {
    $('.toggle-checkboxAction').click(function() {
        $('input.toggle-action[type="checkbox"]').
        not(':hidden').
        prop('checked',$(this).is(':checked'));
    });
    $('#action-box,#action-boxdel').submit(function() {
        taskIDArray = new Array();
        $('input.toggle-action:checked').each(function() {
            taskIDArray[taskIDArray.length] = $(this).val();
        });
        $('input[name="taskIDArray"]').val(taskIDArray.join(','));
    });
    if (ActiveTasksContainer.find('tbody > tr').size() > 0) ActiveTasksContainer.show();
});
