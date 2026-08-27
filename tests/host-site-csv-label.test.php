<?php
/**
 * A host's site is a CSV association label, and it replaces rather than adds.
 *
 * The Site plugin's addsiteimport.hook.php registered a `site` label on the
 * host associations column. Sites moved into FOG itself and that hook did
 * not come across, so for the whole gap between the two, exporting a host
 * and re-importing it silently dropped which site it was in -- the import
 * reported success, the row count was right, and the only symptom was a
 * scoped user quietly losing sight of a host.
 *
 * Nothing could have caught that by reading the config, because the config
 * was not wrong; the entry was simply absent. So this asserts presence
 * first, and then the two properties that make `site` unlike every other
 * label on that list:
 *
 *   1. It is SINGLE-VALUED. A host belongs to one site, so import replaces
 *      what is there. Every other label adds to a set, and reusing that
 *      shape here would leave a re-imported host in both its old site and
 *      its new one -- visible to two sets of people, which is the failure
 *      mode this whole subsystem exists to prevent.
 *   2. Its `get` and `apply` are callables rather than the name of a field
 *      on Host, because the membership lives in its own table rather than
 *      on the host row. A future edit that "tidies" them into 'site' /
 *      'addSite' would find no such field and silently export nothing.
 *
 * Source-level: the config is built by a static on a class whose file
 * pulls in the whole FOG hierarchy, and the behaviour under test needs a
 * database. Live proof is
 * ~/scripts/background_scripts/verify_host_site_csv.php, which runs the
 * round trip against a real install.
 *
 * Usage: php tests/host-site-csv-label.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
$page = $root . '/packages/web/src/Base/FOGPage.php';
$site = $root . '/packages/web/src/Items/Site.php';

foreach ([$page, $site] as $needed) {
    if (!is_readable($needed)) {
        fwrite(STDERR, "FAIL: cannot read $needed\n");
        exit(1);
    }
}

$failures = [];
$checks = 0;

/**
 * Records one assertion.
 *
 * @param string $what the claim
 * @param bool   $ok   whether it held
 *
 * @return void
 */
function check($what, $ok)
{
    global $failures, $checks;
    $checks++;
    if (!$ok) {
        $failures[] = $what;
    }
}

/**
 * A method body with comments stripped and whitespace flattened.
 *
 * Comments have to go: the prose above each of these methods names every
 * symbol this test searches for, and would satisfy the search on its own.
 *
 * @param string $file   file to read
 * @param string $method method name
 *
 * @return string|null code of the body, or null if not found
 */
function methodSource($file, $method)
{
    $t = token_get_all(file_get_contents($file));
    $n = count($t);
    for ($i = 0; $i < $n; $i++) {
        if (!is_array($t[$i]) || T_FUNCTION !== $t[$i][0]) {
            continue;
        }
        $j = $i + 1;
        while ($j < $n && is_array($t[$j]) && T_WHITESPACE === $t[$j][0]) {
            $j++;
        }
        if ($j >= $n || !is_array($t[$j]) || $t[$j][1] !== $method) {
            continue;
        }
        $depth = 0;
        $src = '';
        $started = false;
        for ($k = $j; $k < $n; $k++) {
            $c = $t[$k];
            if (is_array($c)
                && in_array($c[0], [T_COMMENT, T_DOC_COMMENT], true)
            ) {
                continue;
            }
            if (!is_array($c)) {
                if ('{' === $c) {
                    $depth++;
                    $started = true;
                } elseif ('}' === $c) {
                    if (0 === --$depth && $started) {
                        return preg_replace('#\s+#', '', $src);
                    }
                }
            }
            if ($started) {
                $src .= is_array($c) ? $c[1] : $c;
            }
        }
        return preg_replace('#\s+#', '', $src);
    }
    return null;
}

/*
 * 1. The label exists on Host, and only on Host.
 *
 * getAssociationConfig() is one switch over the exported class, so the
 * whole thing is read at once and the Host arm located by its own case.
 */
$config = methodSource($page, 'getAssociationConfig');
if (null === $config) {
    fwrite(STDERR, "FAIL: cannot find FOGPage::getAssociationConfig()\n");
    exit(1);
}
$hostArm = strpos($config, "case'Host':");
$nextArm = strpos($config, "case'Group':");
if (false === $hostArm || false === $nextArm || $nextArm < $hostArm) {
    fwrite(STDERR, "FAIL: cannot locate the Host arm of the config switch\n");
    exit(1);
}
$host = substr($config, $hostArm, $nextArm - $hostArm);

check(
    "Host's association config registers a `site` label",
    false !== strpos($host, "'site'=>[")
);
check(
    'the `site` label resolves against the Site class by name',
    false !== strpos($host, "'class'=>'Site'")
);
// Without these the export falls back to hydrating a Site per row, which is
// the N+1 the bulk prime exists to remove -- silent, and only visible as a
// slow export on a large estate.
check(
    'the `site` label can be bulk-primed for export',
    false !== strpos($host, "'bulkclass'=>'SiteHostMember'")
    && false !== strpos($host, "'parentkey'=>'hostID'")
    && false !== strpos($host, "'childkey'=>'siteID'")
);
// Callables, not field names. Host has no `site` field: the membership is a
// row in siteHostMembers, so 'get' => 'site' would read a property that
// does not exist and export an empty column, successfully.
check(
    'the `site` label reads through a callable, not a Host field',
    false !== strpos($host, "'get'=>[Site::class,'hostSiteNames']")
);
check(
    'the `site` label writes through a callable, not a Host method',
    false !== strpos($host, "'apply'=>[Site::class,'applyHostSite']")
);

/*
 * 2. Import replaces, and the helpers it needs exist.
 */
$apply = methodSource($site, 'applyHostSite');
$names = methodSource($site, 'hostSiteNames');
$ids = methodSource($site, 'hostSiteIDs');
foreach (
    [
        'Site::applyHostSite' => $apply,
        'Site::hostSiteNames' => $names,
        'Site::hostSiteIDs' => $ids
    ] as $label => $src
) {
    check("$label exists", null !== $src);
}
if (null === $apply) {
    fwrite(STDERR, "FAIL: Site::applyHostSite() is missing\n");
    exit(1);
}
// The single-valued half. Removing the existing membership is what makes
// this a set rather than an add, and taking [0] is what makes a malformed
// two-site row land in one site rather than two.
check(
    'applyHostSite removes the existing membership',
    false !== strpos($apply, 'removeHost([$hostID])')
);
check(
    'applyHostSite adds exactly one site',
    false !== strpos($apply, 'addHost([$hostID])')
);
check(
    'applyHostSite uses only the first id offered',
    false !== strpos($apply, '$wanted=$ids[0]')
);
// Removing then re-adding the same site would churn history rows and fire
// the association hooks on every re-import of an unchanged CSV.
check(
    'applyHostSite short circuits when the site is unchanged',
    false !== strpos($apply, 'if($current===$wanted){return;}')
);
// Both must go through the Site entity rather than inserting rows, so they
// share the deduplication and the cascade the Site page goes through.
check(
    'applyHostSite writes through the Site entity',
    false !== strpos($apply, "self::getClass('Site',")
);
check(
    'hostSiteNames returns an array, not a bare string',
    null !== $names && false !== strpos($names, '$names=[]')
);

if (count($failures)) {
    fwrite(STDERR, 'FAIL (' . count($failures) . " of $checks):\n");
    foreach ($failures as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}

echo "ok  $checks checks passed\n";
exit(0);
