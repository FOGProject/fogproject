<?php
/**
 * fog-agent update resolution: modes, rings, latest, the minimum version,
 * and which release files a sync keeps (schema 438, design 0015 section 7).
 *
 * Every decision lives in a pure function, so this file pins them without a
 * database. Each check names the failure it exists to catch.
 *
 * Usage: php tests/agent-update-rings.test.php
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

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('agent-update-rings');
FogTestHarness::fakeDb();

$t = new FogChecks();

/**
 * Equality with the actual value in the label.
 *
 * @param string $label what is being asserted
 * @param mixed  $want  the expected value
 * @param mixed  $got   the value under test
 *
 * @return bool
 */
$eq = function ($label, $want, $got) use ($t) {
    return $t->check(
        $label . ' [want ' . var_export($want, true)
            . ', got ' . var_export($got, true) . ']',
        $want === $got
    );
};

use FOG\Agent\Releases;
use FOG\Agent\Update;

$day = 86400;
$now = 1757600000;

// ------------------------------------------------------------ ring delays

$eq('rings parse', [0, 3, 7], Releases::ringDelays('0,3,7'));
$eq('spaces around delays are allowed', [0, 2], Releases::ringDelays(' 0, 2 '));
$eq(
    'a broken list falls back to the default, never to no delay',
    Releases::DEFAULT_RINGS,
    Releases::ringDelays('0,x,7')
);
$eq('an empty list falls back to the default', Releases::DEFAULT_RINGS, Releases::ringDelays(''));
$eq('a negative delay is refused', null, Releases::parseRings('-1,3'));
$eq('a delay over a year is refused', null, Releases::parseRings('0,400'));

$eq('ring 0', 0, Releases::delayFor('0', [0, 3, 7]));
$eq('ring 1', 3, Releases::delayFor('1', [0, 3, 7]));
$eq(
    'no ring is the LAST ring, so a new host is never among the first',
    7,
    Releases::delayFor('', [0, 3, 7])
);
$eq('a ring past the end of the list is the last ring', 7, Releases::delayFor('9', [0, 3, 7]));
$eq('a ring that is not a number is the last ring', 7, Releases::delayFor('x', [0, 3, 7]));

// ----------------------------------------------------------------- latest

$seen = [
    '0.1.6' => $now - 30 * $day,
    '0.1.7' => $now - 5 * $day,
    '0.1.8' => $now - 1 * $day
];
$eq('ring 0 gets the newest release at once', '0.1.8', Releases::latest($seen, 0, '0.1.6', $now));
$eq(
    'a 3-day ring waits for 0.1.8 and takes 0.1.7',
    '0.1.7',
    Releases::latest($seen, 3, '0.1.6', $now)
);
$eq(
    'version order is semantic, not string order',
    '0.1.10',
    Releases::latest(['0.1.9' => $now - 9 * $day, '0.1.10' => $now - 9 * $day], 0, '', $now)
);
$eq(
    'never moves a host below the version it runs',
    '0.1.8',
    Releases::latest($seen, 7, '0.1.8', $now)
);
$eq(
    'a leading v on the running version is the same version',
    '0.1.8',
    Releases::latest($seen, 7, 'v0.1.8', $now)
);
$eq(
    'a running version withdrawn from the manifest does not hold the host',
    '0.1.6',
    Releases::latest(['0.1.6' => $now - 30 * $day], 7, '0.1.9', $now)
);
$eq(
    'nothing old enough: the host stays on the version it runs',
    '0.1.7',
    Releases::latest(['0.1.7' => $now - $day, '0.1.8' => $now - $day], 7, '0.1.7', $now)
);
$eq(
    'nothing old enough and a lab build running: no version at all',
    '',
    Releases::latest(['0.1.8' => $now - $day], 7, 'da11e37', $now)
);

// ------------------------------------------------------------------ floor

$eq('no minimum changes nothing', '0.1.6', Releases::floor('0.1.6', '0.1.6', ''));
$eq('a version named below the minimum is raised', '0.1.8', Releases::floor('0.1.6', '0.1.5', '0.1.8'));
$eq('a version at or above the minimum stands', '0.1.9', Releases::floor('0.1.9', '0.1.5', '0.1.8'));
$eq('Off: a host running below the minimum is raised', '0.1.8', Releases::floor('', '0.1.6', '0.1.8'));
$eq('Off: a host at the minimum is left alone', '', Releases::floor('', '0.1.8', '0.1.8'));
$eq('Off: a lab build is not a release and is left alone', '', Releases::floor('', 'da11e37', '0.1.8'));
$eq(
    'Off: a git describe build is left alone, because PHP and the agent order it differently',
    '',
    Releases::floor('', '0.1.7-2-gf344ed3', '0.1.9')
);
$eq(
    'a named git describe build is not raised either',
    '0.1.7-2-gf344ed3',
    Releases::floor('0.1.7-2-gf344ed3', '0.1.6', '0.1.9')
);
$eq('a minimum that is not a version is ignored', '0.1.6', Releases::floor('0.1.6', '0.1.6', 'latest'));

// ---------------------------------------------------------------- resolve

$fleet = function ($mode, array $extra = []) use ($seen, $now) {
    return $extra + [
        'mode' => $mode,
        'pinned' => '0.1.7',
        'min' => '',
        'rings' => [0, 3, 7],
        'firstSeen' => $seen,
        'now' => $now
    ];
};
$eq('Off names nothing', '', Update::resolve('', '', '0.1.6', $fleet(Update::MODE_OFF)));
$eq('Pinned names the global version', '0.1.7', Update::resolve('', '', '0.1.6', $fleet(Update::MODE_PINNED)));
$eq('Latest follows the ring', '0.1.8', Update::resolve('', '0', '0.1.6', $fleet(Update::MODE_LATEST)));
$eq(
    'the host override wins over every mode, even when lower',
    '0.1.6',
    Update::resolve('0.1.6', '0', '0.1.8', $fleet(Update::MODE_LATEST))
);
$eq(
    'the override is still raised to the minimum',
    '0.1.8',
    Update::resolve('0.1.6', '', '0.1.6', $fleet(Update::MODE_PINNED, ['min' => '0.1.8']))
);
$eq(
    'the minimum outranks a ring delay',
    '0.1.8',
    Update::resolve('', '', '0.1.6', $fleet(Update::MODE_LATEST, ['min' => '0.1.8']))
);

// --------------------------------------------------------- manifest shape

$file = [
    'os' => 'windows',
    'arch' => 'amd64',
    'sha256' => str_repeat('a', 64),
    'size' => 100,
    'url' => 'https://github.com/FOGProject/fog-agent/releases/download/v0.1.7/fog-agent-windows-amd64.exe'
];
$rows = Releases::parseManifest(
    json_encode(['sequence' => 7, 'versions' => ['0.1.7' => ['security' => true, 'artifacts' => [$file]]]])
);
$eq('one file row', 1, count($rows));
$eq('the row carries its version', '0.1.7', $rows[0]['version'] ?? null);
$eq('the security flag is kept', 1, $rows[0]['security'] ?? null);
$unsafe = Releases::parseManifest(
    json_encode(
        [
            'versions' => [
                'latest' => ['artifacts' => [$file]],
                '0.1.7' => [
                    'artifacts' => [
                        ['url' => 'http://example.org/fog-agent'] + $file,
                        ['sha256' => 'abc'] + $file,
                        ['os' => '../etc'] + $file,
                        ['size' => 0] + $file,
                        ['url' => 'https://example.org/'] + $file
                    ]
                ]
            ]
        ]
    )
);
$eq(
    'unsafe rows are dropped: a non-version key, http, a short hash, a path in the platform, no size, no file name',
    0,
    count($unsafe)
);
$threw = false;
try {
    Releases::parseManifest('<html>404 Not Found</html>');
} catch (\RuntimeException $e) {
    $threw = true;
}
$t->check('an error page is not a manifest, so the sync keeps what it had', $threw);
$eq(
    'a signature envelope has its shape',
    true,
    Releases::isEnvelope(json_encode(['chain' => ['-----BEGIN CERTIFICATE-----'], 'alg' => 'ecdsa-p256-sha256', 'sig' => 'AAAA']))
);
$eq('an error page is not a signature', false, Releases::isEnvelope('<html>404 Not Found</html>'));
$eq('the stored name is the file name of the address', 'fog-agent-windows-amd64.exe', Releases::fileName($file['url']));

// ----------------------------------------------------------- needed files

$needed = Releases::neededFrom(
    [
        ['override' => '', 'ring' => '', 'running' => '0.1.6', 'os' => 'windows', 'arch' => 'amd64'],
        ['override' => '0.1.5', 'ring' => '', 'running' => '0.1.7', 'os' => 'linux', 'arch' => 'amd64'],
        ['override' => '', 'ring' => '0', 'running' => '0.1.6', 'os' => '', 'arch' => '']
    ],
    $fleet(Update::MODE_LATEST)
);
ksort($needed);
$eq(
    'each host needs its target and the version it runs, for its own platform',
    ['0.1.5|linux|amd64', '0.1.6|windows|amd64', '0.1.7|linux|amd64'],
    array_keys($needed)
);

// --------------------------------------------------------------- pruning

$cached = [
    ['id' => 1, 'version' => '0.1.4', 'os' => 'windows', 'arch' => 'amd64', 'inManifest' => 1],
    ['id' => 2, 'version' => '0.1.5', 'os' => 'windows', 'arch' => 'amd64', 'inManifest' => 1],
    ['id' => 3, 'version' => '0.1.6', 'os' => 'windows', 'arch' => 'amd64', 'inManifest' => 1],
    ['id' => 4, 'version' => '0.1.7', 'os' => 'windows', 'arch' => 'amd64', 'inManifest' => 1],
    ['id' => 5, 'version' => '0.1.3', 'os' => 'windows', 'arch' => 'amd64', 'inManifest' => 1],
    ['id' => 6, 'version' => '0.1.9', 'os' => 'windows', 'arch' => 'amd64', 'inManifest' => 0],
    // One platform withdrawn from a version that is still listed.
    ['id' => 7, 'version' => '0.1.7', 'os' => 'linux', 'arch' => 'arm64', 'inManifest' => 0]
];
$hostNeeds = ['0.1.3|windows|amd64' => true];
$eq(
    'keeps the newest 3 and what hosts need; prunes the rest, a withdrawn version and a withdrawn platform',
    [1, 6, 7],
    Releases::prunable($cached, $hostNeeds, 3)
);
$eq(
    'keep 0 keeps only what hosts need',
    [1, 2, 3, 4, 6, 7],
    Releases::prunable($cached, $hostNeeds, 0)
);

$t->finish();
