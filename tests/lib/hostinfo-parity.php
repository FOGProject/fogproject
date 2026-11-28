<?php
/**
 * Renders one boot-menu task scenario twice: the kernel line, and the reply
 * fields service/hostinfo.php builds for the same fixtures (ADR 0023).
 *
 * Both come from one child process of tests/lib/bootmenu-render.php. The
 * script has no function to call, so that process runs hostinfo.php's own
 * merge block, cut out by its anchors, when a scenario sets
 * printHostinfoFields.
 *
 * PHP version 7.4+
 *
 * @category Tests
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

// The task types the parity scenarios use.
const PARITY_DEPLOY = [
    'id' => 1, 'imaging' => true, 'initneeded' => true, 'kernelArgs' => 'type=down',
];
const PARITY_CAPTURE = [
    'id' => 2, 'imaging' => true, 'initneeded' => true, 'capture' => true,
    'kernelArgs' => 'type=up',
];
// The stock Hardware Inventory type (10): it boots FOS and images nothing, and
// its kernelArgs is empty in the schema.
const PARITY_NON_IMAGING = [
    'id' => 10, 'imaging' => false, 'initneeded' => true, 'kernelArgs' => '',
];
// The stock Fast Wipe (18) and Password Reset (11) types: empty kernelArgs.
const PARITY_WIPE = [
    'id' => 18, 'imaging' => false, 'initneeded' => true, 'kernelArgs' => '',
];
const PARITY_WINPASS = [
    'id' => 11, 'imaging' => false, 'initneeded' => true, 'kernelArgs' => '',
];
const PARITY_MULTICAST = [
    'id' => 8, 'imaging' => true, 'initneeded' => true, 'multicast' => true,
    'kernelArgs' => 'type=down mc=yes',
];
// A task type that needs no init, so its task has no image data.
const PARITY_NO_INIT = [
    'id' => 13, 'imaging' => false, 'initneeded' => false, 'kernelArgs' => 'mode=other',
];

/**
 * The base image of a parity scenario, with fields replaced.
 *
 * @param array $fields fields that replace the base ones
 *
 * @return array
 */
function parityImage(array $fields = [])
{
    return array_merge(
        [
            'id' => 1,
            'osID' => 50,
            'path' => 'testimage',
            'format' => 5,
            'imagetype' => 'mps',
            'partitiontype' => 'all',
            'compress' => '3',
        ],
        $fields
    );
}

/**
 * Builds a scenario for a task of one type.
 *
 * @param array $taskType the task type fields
 * @param array $host     host fields added to the base host
 * @param array $task     task fields added to the base task
 * @param array $extra    scenario keys added
 *
 * @return array
 */
function parityScenario(array $taskType, array $host = [], array $task = [], array $extra = [])
{
    return array_merge(
        [
            'request' => ['arch' => 'x86_64', 'platform' => 'bios'],
            // The request's MACs differ from the host's, as at registration.
            'requestMacs' => ['aa:bb:cc:dd:ee:ff', '11:22:33:44:55:66'],
            'printHostinfoFields' => true,
            'host' => array_merge(
                [
                    'id' => 1,
                    'name' => 'testhost',
                    'task' => array_merge(
                        [
                            'id' => 1,
                            'typeID' => $taskType['id'],
                            'tasktype' => $taskType,
                            'image' => parityImage(),
                        ],
                        $task
                    ),
                ],
                $host
            ),
        ],
        $extra
    );
}

/**
 * Renders a scenario and returns the kernel line and the hostinfo fields.
 *
 * @param array $scenario the scenario
 *
 * @return array [kernel line, fields, raw output]
 */
function renderParity(array $scenario)
{
    $cmd = sprintf(
        '%s %s %s %s 2>&1',
        escapeshellarg(PHP_BINARY),
        escapeshellarg(__DIR__ . '/bootmenu-render.php'),
        escapeshellarg((string)json_encode($scenario)),
        escapeshellarg(dirname(__DIR__, 2) . '/packages/web/src/Boot/IpxeBootMenu.php')
    );
    $out = (string)shell_exec($cmd);
    $kernel = '';
    if (preg_match('/^kernel .*$/m', $out, $m)) {
        $kernel = $m[0];
    }
    $fields = null;
    if (preg_match('/^#hostinfo-fields (.*)$/m', $out, $m)) {
        $fields = json_decode($m[1], true);
    }
    return [$kernel, $fields, $out];
}

/**
 * BootMenuBase::TASK_KEYS, as the render process printed it.
 *
 * @param string $out the raw render output
 *
 * @return array
 */
function taskKeysFrom($out)
{
    if (preg_match('/^#task-keys (.*)$/m', $out, $m)) {
        return (array)json_decode($m[1], true);
    }
    return [];
}

/**
 * The task variables of a kernel line, the last token of a name winning.
 *
 * They start at the first mac= token: everything before it is the boot
 * loader's own arguments, which hostinfo.php never sends.
 *
 * @param string $kernel the kernel line
 *
 * @return array
 */
function kernelVars($kernel)
{
    $vars = [];
    $on = false;
    foreach (preg_split('/\s+/', trim($kernel)) as $token) {
        if (0 === strpos($token, 'mac=')) {
            $on = true;
        }
        if (!$on || false === strpos($token, '=')) {
            continue;
        }
        list($name, $value) = explode('=', $token, 2);
        $vars[$name] = $value;
    }
    return $vars;
}

/**
 * The hostinfo fields of one scenario.
 *
 * @param array $scenario the scenario
 *
 * @return array
 */
function hostinfoFields(array $scenario)
{
    list(, $fields) = renderParity($scenario);
    return is_array($fields) ? $fields : [];
}
