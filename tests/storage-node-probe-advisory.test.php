<?php
/**
 * The storage node availability probe is advisory, and measures something.
 *
 * The probe is a TCP connect to a node's ssh or web port. It is useful for
 * choosing between nodes and cannot tell you whether the image share works,
 * so three properties have to hold or it starts deciding things it is not
 * qualified to decide. Each was broken in 1.6 and each produced the same
 * user-visible symptom -- "No master nodes available" and no capture task,
 * on a server whose storage was fine (forums 18210, 18217).
 *
 *   1. A probe failure must not throw. 1.5 fell back to min($nodes) in both
 *      getMasterStorageNode() and getOptimalStorageNode(); 1.6 removed the
 *      fallback, so an unprobeable node -- a NAS with ssh switched off is the
 *      common one -- blocked task creation outright.
 *   2. The fallback must say so. 1.5's was silent, which traded a hard stop
 *      for a puzzling one.
 *   3. The probe must allow enough time, and try more than one port. A tenth
 *      of a second is inside the noise for a switched network, and FOG talks
 *      to a node over http as well as ssh -- _getData() fetches every file
 *      listing from <proto>://<ip>/fog/status/getfiles.php -- so a node can
 *      be answering FOG on one port while the probe calls it dead on another.
 *
 * Source-level on purpose: reaching these paths for real needs a database,
 * a storage group and an unreachable host to probe.
 *
 * PHP version 7.4+
 *
 * @category Tests
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

$root = dirname(__DIR__);
$web = $root . '/packages/web';
$failures = [];
$checks = 0;

/**
 * Strips comments so a commented-out line cannot satisfy a check.
 *
 * @param string $path the file to read
 *
 * @return string the source with comments removed and whitespace squashed
 */
function probeSource($path)
{
    $clean = '';
    foreach (token_get_all(file_get_contents($path)) as $token) {
        if (is_array($token)
            && ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT)
        ) {
            continue;
        }
        $clean .= is_array($token) ? $token[1] : $token;
    }
    return preg_replace('#\s+#', '', $clean);
}

/**
 * Extracts one method body by brace depth.
 *
 * @param string $src    squashed source
 * @param string $method the method name
 *
 * @return string the body, or '' when the method is absent
 */
function probeMethod($src, $method)
{
    $at = strpos($src, 'function' . $method . '(');
    if ($at === false) {
        return '';
    }
    $open = strpos($src, '{', $at);
    if ($open === false) {
        return '';
    }
    $depth = 0;
    $len = strlen($src);
    for ($i = $open; $i < $len; $i++) {
        if ($src[$i] === '{') {
            $depth++;
        } elseif ($src[$i] === '}') {
            $depth--;
            if ($depth === 0) {
                return substr($src, $open, $i - $open + 1);
            }
        }
    }
    return '';
}

$group = probeSource($web . '/lib/fog/storagegroup.class.php');
$node = probeSource($web . '/lib/fog/storagenode.class.php');
$urls = probeSource($web . '/lib/fog/fogurlrequests.class.php');

// ---------------------------------------------------------------
// 1. Neither selector may throw when the group has nodes.
// ---------------------------------------------------------------
foreach (['getMasterStorageNode', 'getOptimalStorageNode'] as $method) {
    $body = probeMethod($group, $method);
    $checks++;
    if ($body === '') {
        $failures[] = "StorageGroup::$method() not found";
        continue;
    }
    $checks++;
    if (strpos($body, '$this->_fallbackNode(') === false) {
        $failures[] = sprintf(
            '%s() does not fall back when nothing answers the probe, so an '
            . 'unreachable node blocks tasking instead of degrading',
            $method
        );
    }
    // The fallback has to run BEFORE the throw, or it can never be reached.
    $throw = strpos($body, 'throw new\Exception');
    $fall = strpos($body, '$this->_fallbackNode(');
    $checks++;
    if ($throw !== false && $fall !== false && $fall > $throw) {
        $failures[] = "$method() falls back after the throw that it exists to avoid";
    }
}

// ---------------------------------------------------------------
// 2. The fallback is not silent.
// ---------------------------------------------------------------
$fallback = probeMethod($group, '_fallbackNode');
$checks++;
if ($fallback === '') {
    $failures[] = 'StorageGroup::_fallbackNode() not found';
} else {
    $checks++;
    if (strpos($fallback, 'error_log(') === false) {
        $failures[] = '_fallbackNode() no longer logs, so a degraded pick is '
            . 'silent -- which is the 1.5 behaviour this replaced';
    }
    $checks++;
    if (strpos($fallback, 'self::error(') !== false) {
        $failures[] = '_fallbackNode() logs through self::error(), which is '
            . 'gated on a logging setting and prints nothing on an ajax '
            . 'request -- and tasking is an ajax request';
    }
    $checks++;
    if (strpos($fallback, 'min($ids)') === false) {
        $failures[] = '_fallbackNode() no longer picks deterministically';
    }
}

// ---------------------------------------------------------------
// 3. The probe measures something.
// ---------------------------------------------------------------
$online = probeMethod($node, 'loadOnline');
$checks++;
if ($online === '') {
    $failures[] = 'StorageNode::loadOnline() not found';
} else {
    $checks++;
    if (preg_match('#isAvailable\([^)]*,(0\.[0-9]+|0),#', $online)) {
        $failures[] = 'loadOnline() probes with a sub-second timeout again; '
            . 'a tenth of a second reports unhurried hosts as offline';
    }
    $checks++;
    if (substr_count($online, 'isAvailable(') < 2) {
        $failures[] = 'loadOnline() probes only one port, so a node answering '
            . 'FOG over http is reported offline when ssh is shut';
    }
    $checks++;
    if (strpos($online, '$webPort') === false) {
        $failures[] = 'loadOnline() no longer falls back to the web port, '
            . 'which is where _getData() fetches every file listing';
    }
}

$avail = probeMethod($urls, 'isAvailable');
$checks++;
if ($avail === '') {
    $failures[] = 'FOGURLRequests::isAvailable() not found';
} else {
    $checks++;
    if (strpos($avail, '$timeout<1') === false) {
        $failures[] = 'isAvailable() no longer floors a sub-second timeout, '
            . 'so any caller can reintroduce a 100ms probe';
    }
    // The floor has to be applied before the socket that uses it.
    $floor = strpos($avail, '$timeout<1');
    $sock = strpos($avail, 'fsockopen(');
    $checks++;
    if ($floor !== false && $sock !== false && $floor > $sock) {
        $failures[] = 'isAvailable() floors the timeout after opening the socket';
    }
}

if (count($failures) > 0) {
    echo 'FAIL storage-node-probe-advisory (' . count($failures) . " problem(s))\n";
    foreach ($failures as $failure) {
        echo "  - $failure\n";
    }
    exit(1);
}

echo "PASS storage-node-probe-advisory ($checks checks)\n";
exit(0);
