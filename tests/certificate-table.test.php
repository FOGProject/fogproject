<?php
/**
 * The Certificates page is a table of certificates, not a card per fact.
 *
 * Two things are pinned, and they fail in different ways.
 *
 * THE EXPIRY BADGE is the only thing on the page that reads a certificate
 * and reaches a conclusion, so it is the only thing that can be silently
 * wrong. openssl's notAfter is GMT and the cell renders it with gmdate();
 * swapping that for date() would relabel every row into FOG_TZ_INFO's zone
 * and, within a few hours of midnight, name the wrong day -- which is the
 * same class of error that has already gone wrong elsewhere with that
 * setting. The zone check below runs from a zone BEHIND UTC so the two
 * answers land on different calendar days and a swap cannot pass.
 *
 * THE TABLE ITSELF is pinned by rendering it, not by grepping for a <table>.
 * The page previously showed the root's subject and fingerprint as prose in
 * one card, the imported root's in a second, and the other six slots in a
 * table carrying neither -- so "every present slot is one row, and every row
 * carries the fingerprint" is exactly the property that was missing and the
 * one worth holding.
 *
 * Usage: php tests/certificate-table.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('certificate-table');

$t = new FogChecks();

$expiry = new \ReflectionMethod(
    'FOG\\Pages\\FOGConfigurationPage',
    '_certExpiry'
);
$expiry->setAccessible(true);

/*
 * 1. The badge, at each of the three thresholds plus the unparseable case.
 *    30 days is the window every ACME client renews inside.
 */
$far = $expiry->invoke(null, ['not_after' => gmdate('M j H:i:s Y', time() + 400 * 86400) . ' GMT']);
$t->check(
    'a certificate a year out carries no badge',
    false === strpos($far, 'badge')
);

$soon = $expiry->invoke(null, ['not_after' => gmdate('M j H:i:s Y', time() + 10 * 86400) . ' GMT']);
$t->check(
    'one inside 30 days is amber',
    false !== strpos($soon, 'badge bg-warning')
);
$t->check(
    'and says how many days are left',
    false !== strpos($soon, '10 days left')
);

$gone = $expiry->invoke(null, ['not_after' => gmdate('M j H:i:s Y', time() - 86400) . ' GMT']);
$t->check(
    'an expired one is red',
    false !== strpos($gone, 'badge bg-danger')
);
$t->check(
    'and says so rather than showing a negative count',
    false !== strpos($gone, 'Expired')
        && false === strpos($gone, 'days left')
);

$junk = $expiry->invoke(null, ['not_after' => 'not a date at all']);
$t->check(
    'a date strtotime cannot read is shown verbatim, not dropped',
    false !== strpos($junk, 'not a date at all')
        && false === strpos($junk, 'badge')
);

/*
 * 2. The date is UTC. Run from a zone eight hours behind it against a
 *    notAfter half an hour after midnight, so date() and gmdate() disagree
 *    about the calendar day and only one of them can pass.
 */
$tz = date_default_timezone_get();
date_default_timezone_set('America/Los_Angeles');
$boundary = $expiry->invoke(null, ['not_after' => 'Jan  1 00:30:00 2030 GMT']);
date_default_timezone_set($tz);
$t->check(
    'the expiry date is rendered in UTC, not the server\'s display zone',
    false !== strpos($boundary, '2030-01-01')
        && false === strpos($boundary, '2029-12-31')
);

/*
 * 3. The table. A fixture in the helper's own shape: six present slots, one
 *    absent, one self-signed.
 */
$cert = function ($slot, $selfSigned = false, $present = true) {
    return [
        'slot' => $slot,
        'present' => $present,
        'subject' => 'CN = subject-' . $slot,
        'issuer' => $selfSigned ? 'CN = subject-' . $slot : 'CN = issuer-' . $slot,
        'not_after' => 'Jul 30 09:12:44 2035 GMT',
        'sha256' => strtoupper(substr(md5($slot), 0, 2)) . ':FINGERPRINT:' . strtoupper($slot),
        'self_signed' => $selfSigned,
        'count' => 1
    ];
};
$status = [
    'certificates' => [
        $cert('root', true),
        $cert('webca'),
        $cert('webchain'),
        $cert('anchor', true),
        $cert('vhost'),
        $cert('commleaf'),
        $cert('sbca', false, false),
        $cert('externalroot', true)
    ]
];

// FOGPage's constructor fires PAGES_WITH_OBJECTS, which reaches
// Route::getIds() and dereferences FOGBase::$DB. Nothing below queries
// anything -- the fake exists only so the page can be constructed.
FogTestHarness::fakeDb();
$page = new \FOG\Pages\FOGConfigurationPage();
$chain = new \ReflectionMethod(
    'FOG\\Pages\\FOGConfigurationPage',
    '_certificateChain'
);
$chain->setAccessible(true);
ob_start();
$chain->invoke($page, $status);
$html = (string) ob_get_clean();

$t->check(
    'the present slots render as one table',
    1 === substr_count($html, '<table')
);
$t->check(
    'one download per present slot, and none for the absent one',
    7 === substr_count($html, 'sub=certificatedownload')
);
$t->check(
    'the absent slot is not a row',
    false === strpos($html, 'slot=sbca')
);
foreach (['root', 'webca', 'webchain', 'anchor', 'vhost', 'commleaf', 'externalroot'] as $slot) {
    $t->check(
        'the ' . $slot . ' row carries its fingerprint',
        false !== strpos($html, ':FINGERPRINT:' . strtoupper($slot))
    );
}
$t->check(
    'a self-signed certificate says so instead of naming its own issuer',
    false !== strpos($html, 'Self-signed')
);
$t->check(
    'an issued one names what signed it',
    false !== strpos($html, 'issuer-webca')
);

/*
 * 4. The reference card arrives shut. AdminLTE hides a .collapsed-card's
 *    body in CSS, so the class in the markup is the whole mechanism -- there
 *    is no JavaScript to fall back on if _box stops emitting it.
 */
$ownPki = new \ReflectionMethod(
    'FOG\\Pages\\FOGConfigurationPage',
    '_certificateOwnPki'
);
$ownPki->setAccessible(true);
ob_start();
$ownPki->invoke($page, null);
$pki = (string) ob_get_clean();
$t->check(
    '"Using your own PKI" is collapsed on arrival',
    false !== strpos($pki, 'collapsed-card')
);
$t->check(
    'and its toggle offers to expand rather than to collapse again',
    false !== strpos($pki, 'data-lte-icon="expand"')
        && false !== strpos($pki, 'data-lte-icon="collapse"')
);

$t->finish();
