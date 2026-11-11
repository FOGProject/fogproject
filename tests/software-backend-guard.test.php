<?php
/**
 * Software::save() enforces the backend rules for every writer.
 *
 * The page validates a posted form, but the REST API sets fields directly
 * and saves. So the model refuses two things itself: a backend the server
 * does not know, and an exact version on a backend that cannot hold one
 * (Flatpak pins by commit, Snap by revision; fog-agent design 0018).
 *
 * DB-free: the harness's fake connection takes the write.
 *
 * Usage: php tests/software-backend-guard.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('software-backend-guard');
FogTestHarness::fakeDb();

$t = new FogChecks();

/**
 * The refusal message from saving an entry, or null when save() did not
 * refuse.
 *
 * @param string $backend the backend
 * @param string $version the version policy as stored
 *
 * @return string|null
 */
function refusal($backend, $version)
{
    $Software = new \FOG\Items\Software();
    $Software
        ->set('name', 'guard-' . $backend . '-' . $version)
        ->set('package', 'org.example.App')
        ->set('backend', $backend)
        ->set('version', $version);
    try {
        $Software->save();
    } catch (\Exception $e) {
        return $e->getMessage();
    }
    return null;
}

$pin = \FOG\Items\Software::pinRefusal();

$t->check('a pinned snap entry is refused', $pin === refusal('snap', '1.2.3'));
$t->check('a pinned flatpak entry is refused', $pin === refusal('flatpak', '1.0'));
$t->check(
    'an unknown backend is refused',
    'Please select a valid backend.' === refusal('brew', '')
);
$t->check('snap with any version saves', null === refusal('snap', ''));
$t->check('flatpak tracking latest saves', null === refusal('flatpak', 'latest'));
$t->check('a pinned choco entry still saves', null === refusal('choco', '1.2.3'));
$t->check('an empty backend takes the column default', null === refusal('', '1.2.3'));

$t->finish();
