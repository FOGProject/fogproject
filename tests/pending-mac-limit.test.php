<?php
/**
 * FOG_QUICKREG_MAX_PENDING_MACS caps how many pending MACs a host stores,
 * and 0 turns pending MACs off.
 *
 * RegisterClient::json() used to test `$pendingMACcount > $maxPending`
 * before it added the batch. A host at the limit (0 of 0, or 4 of 4) passed
 * that test and stored one more batch of any size, so 0 never disabled
 * anything. json() now asks pendingLimitExceeded() with the batch size.
 *
 * Usage: php tests/pending-mac-limit.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('pending-mac-limit');

$t = new FogChecks();
$limit = [\FOG\Client\RegisterClient::class, 'pendingLimitExceeded'];

$t->check('max 0 refuses the first pending MAC', $limit(0, 1, '0'));
$t->check('max 4 refuses a fifth MAC', $limit(4, 1, '4'));
$t->check('max 4 refuses a batch that overshoots', $limit(2, 3, '4'));
$t->check('max 4 accepts a batch that fills it', !$limit(2, 2, '4'));
$t->check('an unset max stores none', $limit(0, 1, null));

$t->finish();
