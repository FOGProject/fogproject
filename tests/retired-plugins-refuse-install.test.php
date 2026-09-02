<?php
/**
 * A retired plugin cannot be uploaded, installed or activated.
 *
 * ADR 0038 decision 14 retired persistentgroups by dropping its trigger and
 * its row (schema step 404). The ADR also named the one hole the drop alone
 * leaves: the external plugin root is never touched by the installer, so an
 * admin who copied the plugin there keeps its code across the upgrade, and
 * its install() re-creates the trigger on demand. One click undoes the
 * retirement, silently, and the trigger goes back to copying a domain
 * password between hosts (decision 15). "Either refuse it or say so in the
 * release notes; silently doing neither is not defensible." This is the
 * refusal.
 *
 * WHAT THIS DRIVES, and why each half is executed rather than grepped:
 *
 *   1. activationBlockers() names a retired plugin as blocked, with the
 *      retirement as the reason -- and does so BEFORE the "no code on disk"
 *      check, because the case that matters is a retired plugin whose code
 *      IS present. The control is a non-retired row with no code, which must
 *      still be refused for the old reason: that proves the fake reached the
 *      loop and the retirement branch did not swallow everything.
 *   2. Both install and activate route through the same refusal. A retirement
 *      that only one of them honors is a retirement with a second door.
 *   3. stageArchive() refuses an upload by NAME, before extraction, so the
 *      code never lands in the external root and no install button ever
 *      appears for it. Built against a real .tar.gz, because the name is
 *      read off the archive's own top-level directory.
 *   4. The lookup is case-insensitive, matching how schema step 404 found the
 *      row it deleted and how every other identity check in Plugin works.
 *
 * Usage: php tests/retired-plugins-refuse-install.test.php
 * Exit status 0 = pass, 1 = fail.
 *
 * PHP version 7.4+
 *
 * @category Tests
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

use FOG\Items\Plugin;

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('retired-plugins-refuse-install');
$db = FogTestHarness::fakeDb();

$t = new FogChecks();
$web = dirname(__DIR__) . '/packages/web';

// -------------------------------------------------------------------------
// 4. The lookup itself.
// -------------------------------------------------------------------------
$reason = Plugin::retirementReason('persistentgroups');
$t->check('persistentgroups is retired', '' !== $reason);
$t->check(
    'the reason says what replaced it, not only that it is gone',
    false !== stripos($reason, 'grant')
    && false !== stripos($reason, 'trigger')
);
$t->check(
    'the lookup is case-insensitive',
    $reason === Plugin::retirementReason('PersistentGroups')
    && $reason === Plugin::retirementReason(' persistentgroups ')
);
$t->check(
    'a plugin that is not retired has no reason',
    '' === Plugin::retirementReason('location')
    && '' === Plugin::retirementReason('')
);

// -------------------------------------------------------------------------
// 1. activationBlockers(), against two rows: one retired, one merely missing.
// -------------------------------------------------------------------------
$db->pdo->rowCount = 2;
$db->pdo->rowFactory = function (array $columns, $n) {
    $row = FogFakePdo::defaultRow($columns, $n);
    $row['pID'] = $n;
    $row['pName'] = 1 === $n ? 'PersistentGroups' : 'helloworld';
    $row['pState'] = 0;
    $row['pInstalled'] = 0;
    // Neither has code on disk. For the retired one that must not be the
    // reason given -- retirement is checked first, because the real case
    // is code that IS on disk in the external root.
    $row['pLocation'] = '/nonexistent/' . strtolower($row['pName']);
    return $row;
};
$blockers = Plugin::activationBlockers([1, 2]);
$db->pdo->rowFactory = null;

$t->check(
    'the retired plugin is blocked',
    isset($blockers['persistentgroups'])
);
$t->check(
    'and the reason is the retirement, not the missing code',
    isset($blockers['persistentgroups'])
    && $blockers['persistentgroups'] === $reason
);
$t->check(
    'a non-retired plugin with no code is still refused for that reason (control)',
    isset($blockers['helloworld'])
    && 'has no code on disk' === $blockers['helloworld']
);

// -------------------------------------------------------------------------
// 2. Install and activate both go through the refusal.
// -------------------------------------------------------------------------
$page = (string)file_get_contents($web . '/src/Pages/PluginManagement.php');
foreach (['installPost', 'activatePost'] as $method) {
    $at = strpos($page, 'public function ' . $method . '()');
    $body = '';
    if (false !== $at) {
        $open = strpos($page, '{', $at);
        $depth = 0;
        for ($i = $open; $i < strlen($page); $i++) {
            if ('{' === $page[$i]) {
                $depth++;
            } elseif ('}' === $page[$i]) {
                if (0 === --$depth) {
                    $body = substr($page, $open, $i - $open + 1);
                    break;
                }
            }
        }
    }
    $t->check(
        "$method refuses blocked plugins before touching the database",
        '' !== $body
        && false !== strpos($body, '$this->_refuseBlocked($plugins);')
        && strpos($body, '_refuseBlocked') < strpos($body, 'PluginManager')
    );
}
$t->check(
    '_refuseBlocked asks activationBlockers, which is where retirement lives',
    (bool)preg_match(
        '#function _refuseBlocked\(\$plugins\)\s*\{\s*\$blockers = Plugin::activationBlockers#s',
        $page
    )
);

// -------------------------------------------------------------------------
// 3. The upload path, against a real archive.
// -------------------------------------------------------------------------
/**
 * Builds a minimal plugin archive with the given top-level directory name.
 *
 * @param string $dir  scratch directory to build in
 * @param string $name the plugin (top-level directory) name
 *
 * @return string path to the .tar.gz
 */
function buildArchive($dir, $name)
{
    $tree = $dir . '/' . $name . '/config';
    @mkdir($tree, 0700, true);
    file_put_contents(
        $tree . '/plugin.config.php',
        "<?php\nreturn ['name' => '$name', 'fog_min' => '1.6.0'];\n"
    );
    $tar = $dir . '/' . $name . '.tar';
    @unlink($tar);
    @unlink($tar . '.gz');
    $phar = new \PharData($tar);
    $phar->buildFromDirectory($dir . '/' . $name . '/../', '#/' . $name . '/#');
    $phar->compress(\Phar::GZ);
    unset($phar);
    return $tar . '.gz';
}

$scratch = sys_get_temp_dir() . '/fog-retired-' . bin2hex(random_bytes(4));
@mkdir($scratch, 0700, true);
$archive = buildArchive($scratch, 'persistentgroups');
$result = Plugin::stageArchive($archive, 'persistentgroups.tar.gz');
$t->check(
    'uploading the retired plugin is refused',
    isset($result['error'])
    && false !== strpos($result['error'], 'persistentgroups')
    && false !== stripos($result['error'], 'retired')
);
// The control: the same archive under a non-retired name must get PAST the
// retirement check. Whatever it fails on next, it must not be this.
$control = Plugin::stageArchive(
    buildArchive($scratch, 'zzretireprobe'),
    'zzretireprobe.tar.gz'
);
$t->check(
    'a non-retired archive is not refused as retired (control)',
    !isset($control['error'])
    || false === stripos($control['error'], 'retired')
);
Plugin::purgeStaging();
// Remove what this test built. sys_get_temp_dir() is used because the
// archive has to be readable by PharData under a real path; nothing large.
$rm = function ($p) use (&$rm) {
    foreach (glob($p . '/*') ?: [] as $f) {
        is_dir($f) ? $rm($f) : @unlink($f);
    }
    @rmdir($p);
};
$rm($scratch);

$t->finish();
