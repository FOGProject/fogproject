<?php
/**
 * Presents the FOG Kernels version that the clients will use.
 *
 * PHP version 5
 *
 * @category KernelVersion
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Presents the FOG Kernels version that the clients will use.
 *
 * @category KernelVersion
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
require '../commons/base.inc.php';
session_write_close();
ignore_user_abort(true);
set_time_limit(0);
header('Content-Type: text/event-stream');

if (isset($_POST['url'])) {

    // Prevent an unauthenticated user from making arbitrary requests.
    FOGCore::is_authorized();

    $res = $FOGURLRequests
        ->process(filter_input(INPUT_POST, 'url'));
    foreach ((array) $res as &$response) {
        echo $response;
        unset($response);
    }
    
    exit;
}

$kernelvers = function ($kernel) {
    $currpath = sprintf(
        '%s%sservice%sipxe%s%s',
        BASEPATH,
        DS,
        DS,
        DS,
        $kernel
    );
    $basepath = escapeshellarg($currpath);
    $findstr = sprintf(
        'strings %s | grep -m 1 -oP "\d+\.\d+\.\d+(?=.*\([0-9a-zA-Z]*@)"',
        $basepath
    );
    return shell_exec($findstr);
};
printf(
    "%s\n",
    FOG_VERSION
);
printf(
    "bzImage Version: %s\n",
    $kernelvers('bzImage')
);
printf(
    "bzImage32 Version: %s",
    $kernelvers('bzImage32')
);
if ($kernelvers('arm_Image') == null) {
    printf(
        "arm_Image Version: Not installed"
    );
} else {
    printf(
        "arm_Image Version: %s",
        $kernelvers('arm_Image')
    );
}
