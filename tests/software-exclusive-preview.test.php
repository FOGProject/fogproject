<?php
/**
 * The Exclusive preview: what a host could lose.
 *
 * SoftwareGrid::exclusivePreview() is pure. It takes the host's open
 * hostSoftware rows and what the host gets, and answers the packages its
 * software backends installed that the host does not get. This pins the
 * filter an admin reads before choosing Exclusive: only backend sources,
 * nothing the host gets (case-insensitive), never Chocolatey itself or
 * its extensions, and never App Installer, which holds winget.
 *
 * Usage: php tests/software-exclusive-preview.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('software-exclusive-preview');
FogTestHarness::fakeDb();

use FOG\Assign\SoftwareGrid;

$t = new FogChecks();

/**
 * One hostSoftware fact.
 *
 * @param string $name    the name
 * @param string $source  the source
 * @param string $version the version
 *
 * @return array
 */
function fact($name, $source, $version = '1.0')
{
    return ['name' => $name, 'source' => $source, 'version' => $version];
}

/**
 * The "source:name" labels of the preview, in order.
 *
 * @param array $rows the preview rows
 *
 * @return array
 */
function labels(array $rows)
{
    return array_map(
        function ($r) {
            return $r['source'] . ':' . $r['name'];
        },
        $rows
    );
}

// Backend filter: registry and dpkg rows are not backend packages.
$facts = [
    fact('Some App', 'registry'),
    fact('curl', 'dpkg'),
    fact('git', 'choco'),
    fact('org.videolan.VLC', 'flatpak'),
    fact('core22', 'snap'),
    fact('odd', ''),
];
$t->check(
    'only backend sources are previewed, sorted by source then name',
    ['choco:git', 'flatpak:org.videolan.VLC', 'snap:core22']
        === labels(SoftwareGrid::exclusivePreview($facts, []))
);

// Assigned exclusion is case-insensitive and keyed on the backend.
$got = SoftwareGrid::exclusivePreview(
    [fact('Git', 'choco'), fact('vlc', 'choco'), fact('vlc', 'snap')],
    [['backend' => 'choco', 'package' => 'GIT']]
);
$t->check(
    'a package the host gets is excluded, case-insensitively',
    ['choco:vlc', 'snap:vlc'] === labels($got)
);
$got = SoftwareGrid::exclusivePreview(
    [fact('vlc', 'choco'), fact('vlc', 'snap')],
    [['backend' => 'snap', 'package' => 'vlc']]
);
$t->check(
    'the same name on another backend is not excluded',
    ['choco:vlc'] === labels($got)
);
$got = SoftwareGrid::exclusivePreview(
    [fact('git', 'choco')],
    [['backend' => '', 'package' => 'git']]
);
$t->check(
    'an empty backend means Chocolatey',
    [] === $got
);

// Chocolatey and its extensions are never previewed.
$got = SoftwareGrid::exclusivePreview(
    [
        fact('chocolatey', 'choco'),
        fact('Chocolatey', 'choco'),
        fact('chocolatey-core.extension', 'choco'),
        fact('Chocolatey-Windowsupdate.Extension', 'choco'),
        fact('chocolatey-tool', 'choco'),
        fact('chocolatey', 'snap'),
    ],
    []
);
$t->check(
    'chocolatey and its extensions are excluded, nothing near them',
    ['choco:chocolatey-tool', 'snap:chocolatey'] === labels($got)
);

// App Installer holds winget, so it is never previewed (GH-2158). Another
// unassigned winget package is, and the same name on another source is.
$got = SoftwareGrid::exclusivePreview(
    [
        fact('Microsoft.AppInstaller', 'winget'),
        fact('microsoft.appinstaller', 'winget'),
        fact('Mozilla.Firefox', 'winget'),
        fact('Microsoft.AppInstaller', 'choco'),
    ],
    []
);
$t->check(
    'App Installer from winget is excluded, another winget package is not',
    ['choco:Microsoft.AppInstaller', 'winget:Mozilla.Firefox'] === labels($got)
);

// Row shape and order within one source.
$got = SoftwareGrid::exclusivePreview(
    [fact('b', 'choco', '2'), fact('a', 'choco', '9')],
    []
);
$t->check(
    'rows carry source, name and version, ordered by name',
    [
        ['source' => 'choco', 'name' => 'a', 'version' => '9'],
        ['source' => 'choco', 'name' => 'b', 'version' => '2']
    ] === $got
);
// A disabled entry's package is retained by the agent.
$got = SoftwareGrid::exclusivePreview(
    [fact('Vlc', 'snap'), fact('git', 'choco')],
    [],
    [['backend' => 'snap', 'package' => 'vlc']]
);
$t->check(
    'a retained package is excluded, case-insensitively',
    ['choco:git'] === labels($got)
);
$t->check('no facts, no preview', [] === SoftwareGrid::exclusivePreview([], []));

// ---------------------------------------------------------------------------
// The level helpers and the two write gates. DB-free.
// ---------------------------------------------------------------------------
use FOG\Agent\SoftwareSet;

$t->check('parseLevel: empty (Assigned)', '' === SoftwareSet::parseLevel(''));
$t->check('parseLevel: exclusive', 'exclusive' === SoftwareSet::parseLevel('exclusive'));
$t->check('parseLevel: trims', 'exclusive' === SoftwareSet::parseLevel(' exclusive '));
$t->check('parseLevel: an unknown value', null === SoftwareSet::parseLevel('everything'));
$t->check('parseLevel: a different case', null === SoftwareSet::parseLevel('Exclusive'));
$t->check('parseLevel: a missing value (null)', null === SoftwareSet::parseLevel(null));
$t->check('parseLevel: a missing value (false)', null === SoftwareSet::parseLevel(false));

// splitResolved: the one gets/retain rule desired() and the preview share.
$links = [['id' => 1], ['id' => 2], ['id' => 3], ['id' => 4]];
$sw = [
    1 => ['backend' => 'choco', 'package' => 'git', 'enabled' => true],
    2 => ['backend' => 'choco', 'package' => 'off', 'enabled' => false],
    3 => ['backend' => 'snap', 'package' => 'vlc', 'enabled' => true],
];
list($gets, $retain) = SoftwareSet::splitResolved($links, $sw, false);
$t->check(
    'splitResolved: an old agent keeps a snap entry and a disabled one in retain',
    [1] === array_column($gets, 'id') && [2, 3] === array_column($retain, 'id')
);
list($gets, $retain) = SoftwareSet::splitResolved($links, $sw, true);
$t->check(
    'splitResolved: with the feature only the disabled entry is retained',
    [1, 3] === array_column($gets, 'id') && [2] === array_column($retain, 'id')
);

// Write gate 1: the mass-edit normalizer must refuse a bad level.
$class = \FOG\Pages\HostManagement::class;
$page = (new \ReflectionClass($class))->newInstanceWithoutConstructor();
$m = new \ReflectionMethod($class, 'massEditCoreFields');
$m->setAccessible(true);
$normalize = $m->invoke($page)['softwareLevel']['normalize'] ?? null;
$t->check('mass edit: softwareLevel has a normalizer', is_callable($normalize));
$refused = false;
try {
    $normalize('bogus');
} catch (\Exception $e) {
    $refused = true;
}
$t->check('mass edit: the normalizer rejects bogus', $refused);
$t->check(
    'mass edit: the normalizer accepts exclusive',
    'exclusive' === $normalize('exclusive')
);

// Write gate 2: requireLevel() refuses a bad level. The mass-edit normalizer
// above reaches it end to end; the Software tab POST calls it too, but reads
// filter_input(), which a CLI run cannot feed, so that call is not driven here.
foreach (['bogus', 'Exclusive', null, false] as $bad) {
    $refused = false;
    try {
        SoftwareSet::requireLevel($bad);
    } catch (\Exception $e) {
        $refused = true;
    }
    $t->check('requireLevel: refuses ' . var_export($bad, true), $refused);
}
$t->check('requireLevel: accepts exclusive', 'exclusive' === SoftwareSet::requireLevel(' exclusive '));
$t->check('requireLevel: accepts empty (Assigned)', '' === SoftwareSet::requireLevel(''));

$t->finish();
