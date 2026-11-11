<?php
/**
 * A failed "Create new tasking" form fetch says why inside the modal.
 *
 * Forum topic 18314: Capture on a host with no image left the modal on
 * "Loading, please wait..." for good. ?sub=deploy answered 400 "Assigned
 * image is invalid", and the error handler called modal('hide'). Bootstrap 5
 * drops a hide() that lands inside the show transition, and the 400 came
 * back faster than the fade. The group page had no hide at all.
 *
 * Three pages open this modal: the host Tasks tab, the group Tasks tab and
 * the task list's Recent re-deploy. Each error handler must hand the
 * failure to $.taskFormFailed and must not hide the modal. The helper must
 * end the loading state and write the escaped reason into the form holder.
 *
 * Pinned by source: the handlers are browser callbacks. Each slice is
 * anchored on the page's own fetch, so a hide() in a success path nearby
 * cannot satisfy or fail the check.
 *
 * Usage: php tests/task-form-error-stays-in-modal.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

$t = new FogChecks();
$js = dirname(__DIR__) . '/packages/web/management/js/fog';

/**
 * The text of one file, or '' when it cannot be read.
 *
 * @param string $path the file
 *
 * @return string
 */
function taskJs($path)
{
    return (string)@file_get_contents($path);
}

/**
 * The first error callback after $anchor, up to its closing brace.
 *
 * @param string $src    the file
 * @param string $anchor a string inside the $.ajax() block
 *
 * @return string
 */
function taskErrorHandler($src, $anchor)
{
    $from = strpos($src, $anchor);
    if (false === $from) {
        return '';
    }
    $at = strpos($src, 'error: function(jqXHR, textStatus, errorThrown) {', $from);
    if (false === $at) {
        return '';
    }
    // The handler ends at the first brace back at its own indentation.
    $lineStart = strrpos(substr($src, 0, $at), "\n");
    $indent = substr($src, $lineStart + 1, $at - $lineStart - 1);
    $end = strpos($src, "\n" . $indent . '}', $at);
    return substr($src, $at, (false === $end ? strlen($src) : $end) - $at);
}

// The anchor is the line that fills the holder on success, which sits in
// the same $.ajax() block just before the error handler.
$pages = [
    'host Tasks tab' => $js . '/host/fog.host.edit.js',
    'group Tasks tab' => $js . '/group/fog.group.edit.js',
    'task list re-deploy' => $js . '/task/fog.task.list.js',
];
foreach ($pages as $what => $file) {
    $body = taskErrorHandler(
        taskJs($file),
        "\$('#task-form-holder').html(\$.parseHTML(data.msg));"
    );
    $t->check("the $what fetch has an error handler", '' !== $body);
    $t->check(
        "the $what error handler hands the failure to \$.taskFormFailed",
        false !== strpos($body, '$.taskFormFailed(jqXHR, taskName);')
    );
    $t->check(
        "the $what error handler does not hide the modal,"
            . ' which Bootstrap drops mid-transition',
        false === strpos($body, "modal('hide')")
    );
}

$common = taskJs($js . '/fog.common.js');
$from = strpos($common, '$.taskFormFailed = function(jqXHR, taskName) {');
$helper = false === $from
    ? ''
    : substr($common, $from, strpos($common, "\n};", $from) - $from);
$t->check('$.taskFormFailed exists in fog.common.js', '' !== $helper);
$t->check(
    'it ends the loading state',
    false !== strpos($helper, "\$('#task-modal .modal-dialog').setLoading(false);")
);
$t->check(
    "it reads the server's error field",
    false !== strpos($helper, 'jqXHR.responseJSON.error')
);
$t->check(
    'it writes the reason into the form holder, escaped',
    1 === preg_match(
        "/\\\$\('#task-form-holder'\)\.html\([^;]*\\\$\.escapeHtml\(/s",
        $helper
    )
);
$t->check(
    'it does not hide the modal',
    false === strpos($helper, "modal('hide')")
);

$t->finish();
