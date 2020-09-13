<?php
/**
 * Gets version information
 *
 * PHP version 5
 *
 * @category Mainversion
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Gets version information
 *
 * @category Mainversion
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
require '../commons/base.inc.php';
session_write_close();
ignore_user_abort(true);
set_time_limit(0);

$curversion = FOG_VERSION;
$urls = array(
    'https://api.github.com/repos/fogproject/fogproject/tags',
    'https://raw.githubusercontent.com/FOGProject/fogproject/dev-branch/packages/web/lib/fog/system.class.php',
    'https://raw.githubusercontent.com/FOGProject/fogproject/working-1.6/packages/web/lib/fog/system.class.php'
);
$resp = $FOGURLRequests->process($urls);

$tags = json_decode(array_shift($resp));
foreach ($tags as $tag) {
    if (preg_match('/^[0-9]\.[0-9]\.[0-9]$/', $tag->name)) {
        $stableversion = $tag->name;
        break;
    }
}
$systemclass = array_shift($resp);
if (preg_match("/FOG_VERSION', '([0-9.RCalphbet-]*)'/", $systemclass, $fogver)) {
    $devversion = $fogver[1];
}
$systemclass = array_shift($resp);
if (preg_match("/FOG_VERSION', '([0-9.RCalphbet-]*)'/", $systemclass, $fogver)) {
    $alphaversion = $fogver[1];
}

$stablecheck = version_compare($curversion, $stableversion, '=');
$devcheck = version_compare($curversion, $devversion, '=');
$alphacheck = version_compare($curversion, $alphaversion, '=');

if (!$stablecheck && !$devcheck && !$alphacheck) {
    $result = '<font face="arial" color="red" size="4"><b>You are not running the most current version of FOG!</b></font>'
    . "<p>You are currently running version: $curversion</p>"
    . "<p>Latest stable version is " . $stableversion . "</p>"
    . "<p>Latest dev-branch version is $devversion</p>"
    . "<p>Latest alpha-branch version is $alphaversion</p>";
} else {
    $result = "<b>Your version of FOG is up to date.</b><br/>";
    if ($stablecheck) {
        $result .= "You're running the latest stable version: " . $stableversion;
    } elseif ($devcheck) {
        $result .= "You're running the latest dev-branch version: " . $devversion;
    } else {
        $result .= "You're running the latest alpha-branch version: " . $alphaversion;
    }
}


echo json_encode($result);
exit;
