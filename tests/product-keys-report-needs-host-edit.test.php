<?php
/**
 * The Product Keys report requires host.edit (GHSA-q38j-qx5x-w25v).
 *
 * The report's JSON feed and a server-side export carry every host's full
 * product key; the grid and its CSV button show the mask. It used to
 * resolve to report.view, which the read-only Legacy Restricted role
 * holds. The API never returns a product key, so the report must not hand
 * them to a view-only role either. host.edit is the permission that can
 * already set a key.
 *
 * EXECUTED through resolvePagePermission(), the call the page dispatcher and
 * the sidebar both make.
 *
 * Usage: php tests/product-keys-report-needs-host-edit.test.php
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

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('product-keys-report-needs-host-edit');
FogTestHarness::fakeDb();

use FOG\Auth\Authorization;
use FOG\Base\FOGPageManager;

$results = [];

/**
 * Record one assertion and echo its result.
 *
 * Named uniquely rather than check(): tests/ is analyzed as one program by
 * phpstan-tests.neon, and a global check() collides with other files.
 *
 * @param string $label what is being asserted
 * @param bool   $ok    whether it held
 * @param string $extra detail printed on failure
 *
 * @return bool the value of $ok
 */
function productKeysGateCheck($label, $ok, $extra = '')
{
    $suffix = ('' !== $extra ? " ($extra)" : '');
    echo ($ok ? 'ok   - ' : 'FAIL - ') . $label . ($ok ? '' : $suffix) . "\n";
    return $ok;
}

$f = base64_encode('product keys');
// The sidebar shape, which hides the entry from a user who lacks it.
$sidebar = 'file&f=' . $f;
foreach (['GET' => false, 'POST' => true] as $verb => $isPost) {
    $got = Authorization::resolvePagePermission('report', $sidebar, $isPost);
    $results[] = productKeysGateCheck(
        "Product Keys report requires host.edit ($verb)",
        'host.edit' === $got,
        'got ' . var_export($got, true)
    );
}

// A real request carries `f` in the query string with a bare sub. That
// reaches the same _reportNode() lookup through filter_input(), which reads
// nothing from CLI, so only the sub shape is executable here.

// The neighbors are unchanged: a node value still resolves to view.
$got = Authorization::resolvePagePermission(
    'report',
    'file&f=' . base64_encode('fleet report'),
    false
);
$results[] = productKeysGateCheck(
    'Fleet Report still requires host.view',
    'host.view' === $got,
    'got ' . var_export($got, true)
);

// The page loader must name the same report the gate gates on. Each value
// below once passed the gate as report.view and still loaded a gated report.
// Executed through FOGPageManager::reportClassFromF(), the loader's own call.
$malformed = 'UHJvZHVj.dF9LZXlz';
$results[] = productKeysGateCheck(
    'the malformed sample is the attack: loose decode names Product_Keys',
    'Product_Keys' === base64_decode($malformed)
    && false === base64_decode($malformed, true)
);
$bypasses = [
    // A loose decode skipped the '.'.
    'malformed base64' => $malformed,
    // qualify() passed a namespaced name through unchanged.
    'namespaced name' => base64_encode('FOG\\Reports\\Product_Keys'),
    'leading backslash' => base64_encode('\\FOG\\Reports\\Product_Keys'),
    'lowercase namespaced' => base64_encode('fog\\reports\\product_keys'),
    'namespaced Audit Report' => base64_encode('FOG\\Reports\\Audit_Report'),
];
foreach ($bypasses as $label => $value) {
    $gate = Authorization::resolvePagePermission(
        'report',
        'file&f=' . $value,
        false
    );
    $class = FOGPageManager::reportClassFromF($value);
    $results[] = productKeysGateCheck(
        "$label: the loader loads no report",
        '' === $class,
        "gate '$gate', loader '$class'"
    );
}
// And a real report name still loads its class -- also with the padding
// the gate trims, so the gate and the loader agree on it.
foreach (['product keys', " product keys \n", "Product_Keys\n"] as $name) {
    $value = base64_encode($name);
    $gate = Authorization::resolvePagePermission(
        'report',
        'file&f=' . $value,
        false
    );
    $class = FOGPageManager::reportClassFromF($value);
    $results[] = productKeysGateCheck(
        'gate and loader agree on ' . json_encode($name),
        'host.edit' === $gate && 'FOG\\Reports\\Product_Keys' === $class,
        "gate '$gate', loader '$class'"
    );
}

$failed = count(array_filter($results, static function ($r) {
    return !$r;
}));
printf("\n%d check(s), %d failed\n", count($results), $failed);
exit($failed > 0 ? 1 : 0);
