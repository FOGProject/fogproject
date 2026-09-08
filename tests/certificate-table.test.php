<?php
/**
 * The Certificates page is one card with tabs, and its table is a table.
 *
 * Three things are pinned, and they fail in three different ways.
 *
 * NOTHING SHIPS BEHIND A COLLAPSE. This page briefly rendered its longest
 * section as an AdminLTE `.collapsed-card`, and the content was unreachable:
 * AdminLTE binds the collapse toggle with
 * `document.querySelectorAll(...).forEach(el => el.addEventListener(...))`
 * once at DOMContentLoaded, it is NOT delegated, and FOG replaces the content
 * by AJAX on every sidebar click -- so a card arriving by navigation has no
 * handler on its toggle at all. It works only on a hard reload, which is
 * exactly what a static snapshot of the page is, which is how it passed
 * review. Bootstrap's own tab toggle IS delegated, which is why the house
 * pattern works where that did not. This asserts on the rendered page rather
 * than on a call site, so re-introducing it any other way still fails.
 *
 * THE EXPIRY BADGE is the only thing on the page that reads a certificate and
 * reaches a conclusion, so it is the only thing that can be silently wrong.
 * openssl's notAfter is GMT and the cell renders it with gmdate(); swapping
 * that for date() would relabel every row into FOG_TZ_INFO's zone -- the zone
 * rows are STORED in -- and, within a few hours of midnight, name the wrong
 * day. The zone check below runs from a zone BEHIND UTC so the two answers
 * land on different calendar days and a swap cannot pass.
 *
 * THE TABLE is pinned by rendering it, not by grepping for a <table>. The
 * page used to show the root's subject and fingerprint as prose in one card,
 * the imported root's in a second, and the other six slots in a table
 * carrying neither -- so "every present slot is one row, and every row
 * carries the fingerprint" is exactly the property that was missing.
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
    false !== strpos($soon, 'badge text-bg-warning')
);
$t->check(
    'and says how many days are left',
    false !== strpos($soon, '10 days left')
);

$gone = $expiry->invoke(null, ['not_after' => gmdate('M j H:i:s Y', time() - 86400) . ' GMT']);
$t->check(
    'an expired one is red',
    false !== strpos($gone, 'badge text-bg-danger')
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
 * 3. The table. A fixture in the helper's own shape: seven present slots,
 *    one absent, three self-signed.
 */
$cert = function ($slot, $selfSigned = false, $present = true) {
    return [
        'slot' => $slot,
        'present' => $present,
        'subject' => 'CN = subject-' . $slot,
        'issuer' => $selfSigned ? 'CN = subject-' . $slot : 'CN = issuer-' . $slot,
        'not_after' => 'Jul 30 09:12:44 2035 GMT',
        'sha256' => 'AA:FINGERPRINT:' . strtoupper($slot),
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

$invoke = function ($method, array $args) use ($page) {
    $m = new \ReflectionMethod('FOG\\Pages\\FOGConfigurationPage', $method);
    $m->setAccessible(true);
    return (string) $m->invokeArgs($page, $args);
};

$html = $invoke('_certificateChain', [$status]);

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
 * 4. Every section is a tab BODY, not a card it echoes for itself -- that is
 *    what lets certificates() drop a tab whose content is empty rather than
 *    render an empty pane. A storage node has no helper, so on one of those
 *    three of the four sections have nothing to say.
 */
$t->check(
    'the chain builder returns its body rather than echoing it',
    '' !== $html && false !== strpos($html, '<table')
);
foreach (
    [
        '_certificateChain' => [null],
        '_certificateExternalRoot' => [null, true],
        '_certificatePreferences' => [null, true]
    ] as $method => $args
) {
    $t->check(
        $method . ' has nothing to show without the helper',
        '' === $invoke($method, $args)
    );
}
$t->check(
    '_certificateOwnPki still does -- it needs no helper',
    '' !== $invoke('_certificateOwnPki', [null])
);

/*
 * 4b. The alarm. Its whole job is to fire when the web tier can open a key
 *     it must not, and to stay quiet for the two keys that are MEANT to be
 *     readable -- the client communication key, which certDecrypt() opens on
 *     every fog-client handshake, and the vhost key once an ACME renewal
 *     owns it. Flagging those would report a correct install as a breach,
 *     which is the failure that makes an alarm get ignored.
 */
$readable = __FILE__;
$t->check(
    'a readable key the installer should have locked down raises the alarm',
    false !== strpos(
        $invoke('_certificateKeyExposure', [[
            'private_keys' => [
                ['label' => 'Root CA private key', 'path' => $readable]
            ]
        ]]),
        'Private keys are readable by the web server'
    )
);
$t->check(
    'a key that is meant to be readable does not',
    '' === $invoke('_certificateKeyExposure', [[
        'private_keys' => [
            [
                'label' => 'Client communication key',
                'path' => $readable,
                'expect_readable' => true
            ]
        ]
    ]])
);
$t->check(
    'and a key that is not there at all does not',
    '' === $invoke('_certificateKeyExposure', [[
        'private_keys' => [
            ['label' => 'Root CA private key', 'path' => '/nonexistent/ca.key']
        ]
    ]])
);

/*
 * 5. The page itself, in whichever of its two shapes this host is in.
 *
 *    Which one is not a choice: _pkiStatus() returns null where the helper is
 *    absent -- no sudoers rule for whoever runs the suite, which is a bare CI
 *    runner and is also the storage-node shape -- and a status array on a
 *    server that has actually been installed, which is every FOG developer's
 *    own box. This block used to assert the absent shape unconditionally, so
 *    it passed on CI and reported two failures to anyone running the suite on
 *    a working install: no warning to find, and a chain tab that legitimately
 *    has content.
 *
 *    Branching rather than skipping. The card, the tab wiring and the
 *    collapse ban are the same either way and are asserted once; only the
 *    warning and the chain tab differ, and each shape is a real gate in the
 *    environment that produces it.
 */
$pkiStatus = new \ReflectionMethod(
    'FOG\\Pages\\FOGConfigurationPage',
    '_pkiStatus'
);
$pkiStatus->setAccessible(true);
$helperPresent = null !== $pkiStatus->invoke(null);

ob_start();
$page->certificates();
$rendered = (string) ob_get_clean();

$t->check(
    'the page is a card with tabs, like every other management page',
    false !== strpos($rendered, 'nav nav-tabs')
);
$t->check(
    'each section is a tab pane',
    false !== strpos($rendered, 'id="pki-own"')
        && false !== strpos($rendered, 'data-bs-toggle="tab"')
);
if ($helperPresent) {
    $t->check(
        'with the helper present the chain section has content, so it is a tab',
        false !== strpos($rendered, 'id="pki-chain"')
    );
    $t->check(
        'and the missing-helper warning is NOT rendered',
        false === strpos($rendered, 'helper is not installed')
    );
} else {
    $t->check(
        'a section with nothing to show is not an empty tab',
        false === strpos($rendered, 'id="pki-chain"')
    );
    $t->check(
        'the missing-helper warning is rendered',
        false !== strpos($rendered, 'helper is not installed')
    );
    $t->check(
        'and it is ABOVE the tabs, not behind one of them',
        strpos($rendered, 'helper is not installed')
            < strpos($rendered, 'nav nav-tabs')
    );
}
$t->check(
    'nothing on the page ships behind a collapse an AJAX visit cannot open',
    false === strpos($rendered, 'collapsed-card')
);

/*
 * 6. The netboot transport row. Two radios rather than a select, because a
 *    select showed one value and hid the other behind a click -- which on a
 *    row whose point is "this is a choice between two transports" read as a
 *    status badge, not a control.
 *
 *    Pinned on the rendered row, not on a call site, because the failure is
 *    entirely in what the browser receives: a radio group that lost its shared
 *    `name` still renders as two radios and still posts, but both can be
 *    checked at once and the row stops describing a single setting. The
 *    ALREADY-checked `for` target is here for a sharper reason -- pointing the
 *    title at a fixed id would make one click on the setting name silently
 *    force the transport, which is the exact misclick ADR 0036 spent a
 *    rejected alternative refusing to expose.
 */
$prefsOf = function ($proto, $mayEdit) use ($invoke) {
    return $invoke('_certificatePreferences', [
        ['preferences' => ['BOOT_url_proto' => $proto]],
        $mayEdit
    ]);
};

$protoHtml = $prefsOf('http', true);
$t->check(
    'the netboot transport is a radio group, not a select',
    2 === substr_count($protoHtml, 'type="radio"')
        && false === strpos($protoHtml, 'pki-pref-select')
        && false === strpos($protoHtml, '<select')
);
$t->check(
    'both radios share one name, so they are mutually exclusive',
    2 === substr_count($protoHtml, 'name="BOOT_url_proto"')
);
$t->check(
    'exactly one radio is checked',
    1 === substr_count($protoHtml, ' checked')
);
$t->check(
    'and it is the transport the helper reported',
    false !== strpos(
        $protoHtml,
        'id="BOOT_url_proto_http" value="http" data-key="BOOT_url_proto"'
    )
);
$t->check(
    'the setting name is worded as the force it performs',
    false !== strpos($protoHtml, 'Force netboot over HTTP or HTTPS')
);
$t->check(
    'and it says which key records that force',
    false !== strpos($protoHtml, 'BOOT_url_proto_forced')
);
$t->check(
    'the title label points at the radio already checked, so a click on it '
        . 'cannot change the transport',
    false !== strpos($protoHtml, 'for="BOOT_url_proto_http"><strong>')
);

$protoHttps = $prefsOf('https', true);
$t->check(
    'https is what is checked when https is what is set',
    false !== strpos($protoHttps, 'id="BOOT_url_proto_https" value="https"')
        && false !== strpos($protoHttps, 'for="BOOT_url_proto_https"><strong>')
        && 1 === substr_count($protoHttps, ' checked')
);

// Counted on the radio tags themselves. The three switches above carry
// `disabled` under the same condition, so a count over the whole body would
// pass whatever the radios did.
$radiosDisabled = function ($html) {
    return preg_match_all('/type="radio"[^>]*\sdisabled/', $html);
};
$protoRo = $prefsOf('http', false);
$t->check(
    'a caller without system.pki gets both radios disabled',
    2 === $radiosDisabled($protoRo)
);
$t->check(
    'and the writable render disables neither',
    0 === $radiosDisabled($protoHtml)
);

$t->finish();
