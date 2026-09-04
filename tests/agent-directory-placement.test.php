<?php
/**
 * FOG only writes to somebody's directory when it has been told to, has
 * something to correct, and knows what it is correcting.
 *
 * Placement (design 0009 section 5) is the first thing FOG has ever done that
 * MODIFIES an object in a customer's Active Directory. Everything else about
 * directory membership reads or records. So the interesting failures are not
 * "it did not move the object" -- they are the ones where it moves something
 * it should not have touched, or hammers a directory it cannot reach:
 *
 * - It must be off unless BOTH the switch and a server are configured. A
 *   feature that starts writing to a directory because somebody upgraded is
 *   not a feature.
 * - A host with no desired OU must be left alone. An admin who never
 *   expressed a preference is not a machine in the wrong place, and moving it
 *   somewhere would be FOG inventing an intention.
 * - An unjoined host must be left alone: there is no object to move, and
 *   creating one is a join, which needs the machine.
 * - A host that reported its own DN and is already in the right container
 *   must cost NOTHING -- no connection, no row written. That comparison is
 *   free, which is what lets it run on every poll and act on an edited OU
 *   immediately.
 * - A host that could NOT report a DN must still be checked, by asking the
 *   directory. This is the one that reads backwards: the drift comparison
 *   deliberately answers "no drift" when the observed container is unknown
 *   (a report full of unactionable rows is a report nobody reads), so using
 *   it as the gate would silently exclude every Linux host from the feature
 *   forever.
 * - A failure must cool down. Without it a directory that has gone away is
 *   dialed once per poll per host, each paying the connection timeout.
 * - And the whole thing must hang off the POLL, not off the fact report --
 *   the report only runs when the MACHINE's membership moved, and the other
 *   source of drift is an admin editing the host's OU, which no machine will
 *   ever report.
 *
 * DB-free and network-free: the failures here are all reached before a socket
 * is opened.
 *
 * Usage: php tests/agent-directory-placement.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('agent-directory-placement');
FogTestHarness::fakeDb();

$t = new FogChecks();

/**
 * Seeds the settings cache so getSetting() answers without a query.
 *
 * @param array $settings key => value
 *
 * @return void
 */
function settings(array $settings)
{
    $cache = [];
    foreach ($settings as $k => $v) {
        $cache[$k] = ['value' => $v, 'ts' => time()];
    }
    FogTestHarness::setStatic('FOGBase', '_settingsCache', $cache);
    // Marks the cache coherent for this request, which is what stops
    // getSetting() from going to the database for anything absent.
    FogTestHarness::setStatic('FOGBase', '_settingsFileChecked', true);
}

/**
 * Placement configured far enough to be ON, with no usable bind account --
 * so connect() fails immediately, in memory, and the test can tell "tried"
 * from "did not try" without a directory or a timeout.
 *
 * @return void
 */
function placementOnButUnreachable()
{
    settings([
        'FOG_DIRECTORY_PLACEMENT_ENABLED' => '1',
        'FOG_DIRECTORY_LDAP_URI' => 'ldaps://dc.example.com',
        'FOG_DIRECTORY_BIND_DN' => '',
        'FOG_DIRECTORY_BIND_PASSWORD' => '',
        'FOG_DIRECTORY_BASE_DN' => 'DC=example,DC=com',
        'FOG_DIRECTORY_CA_CERT' => ''
    ]);
}

/**
 * A host that wants to be in an OU.
 *
 * @param string $ou    the desired OU
 * @param mixed  $useAD the host's useAD flag
 *
 * @return \FOG\Items\Host
 */
function wanting($ou, $useAD = 1)
{
    return (new \FOG\Items\Host())
        ->set('id', 7)
        ->set('name', 'WS-014')
        ->set('useAD', $useAD)
        ->set('ADOU', $ou);
}

/**
 * An observation row.
 *
 * @param array $fields property => value
 *
 * @return \FOG\Items\HostDirectory
 */
function row(array $fields)
{
    $d = (new \FOG\Items\HostDirectory())->set('hostID', 7);
    foreach ($fields as $k => $v) {
        $d->set($k, $v);
    }
    return $d;
}

/**
 * Whether placement consulted the directory about this host.
 *
 * Every attempt is stamped, successful or not, so the stamp is the signal --
 * and it is exactly what the cooldown reads.
 *
 * @param \FOG\Items\HostDirectory $d the row, after ensure()
 *
 * @return bool
 */
function consulted(\FOG\Items\HostDirectory $d)
{
    return '' !== trim((string)$d->get('placementAt'));
}

// ----------------------------------------------------------- the off switch

settings([
    'FOG_DIRECTORY_PLACEMENT_ENABLED' => '0',
    'FOG_DIRECTORY_LDAP_URI' => 'ldaps://dc.example.com'
]);
$t->check(
    'placement is off when the switch is off, however well configured -- FOG'
        . ' must never begin writing to a directory because someone upgraded',
    !\FOG\Agent\DirectoryPlacement::enabled()
);

settings([
    'FOG_DIRECTORY_PLACEMENT_ENABLED' => '1',
    'FOG_DIRECTORY_LDAP_URI' => ''
]);
$t->check(
    'placement is off with the switch on and no server named',
    !\FOG\Agent\DirectoryPlacement::enabled()
);

settings([
    'FOG_DIRECTORY_PLACEMENT_ENABLED' => '1',
    'FOG_DIRECTORY_LDAP_URI' => 'ldaps://dc.example.com'
]);
$t->check(
    'placement is on when both are set',
    \FOG\Agent\DirectoryPlacement::enabled()
);

// ------------------------------------------------- hosts that are left alone

placementOnButUnreachable();

$d = row([
    'joined' => 1,
    'machineAccount' => 'WS-014$',
    // Deliberately UNKNOWN, so nothing but the guard under test can stop
    // this: a row whose container is already right would return one check
    // earlier and pass whether the guard existed or not.
    'computerDN' => ''
]);
\FOG\Agent\DirectoryPlacement::ensure(wanting(''), $d);
$t->check(
    'a host with no desired OU is not consulted about -- an admin who never'
        . ' expressed a preference is not a machine in the wrong place',
    !consulted($d)
);

$d = row([
    'joined' => 0,
    'machineAccount' => 'WS-014$',
    'computerDN' => ''
]);
\FOG\Agent\DirectoryPlacement::ensure(
    wanting('OU=Sales,DC=example,DC=com'),
    $d
);
$t->check(
    'an unjoined host is not consulted about: there is no object to move, and'
        . ' creating one is a join, which needs the machine',
    !consulted($d)
);

$d = row([
    'joined' => 1,
    'machineAccount' => 'WS-014$',
    'computerDN' => ''
]);
\FOG\Agent\DirectoryPlacement::ensure(
    wanting('OU=Sales,DC=example,DC=com', 0),
    $d
);
$t->check(
    'a host not set to use AD is not consulted about, whatever its row says',
    !consulted($d)
);

$d = row([
    'joined' => 1,
    'computerDN' => 'CN=WS-014,OU=Sales, DC=example, DC=com'
]);
\FOG\Agent\DirectoryPlacement::ensure(
    wanting('ou=sales,DC=example,DC=com'),
    $d
);
$t->check(
    'a host that reported its DN and is already in the right container costs'
        . ' NOTHING -- no connection and no row written. That is what lets the'
        . ' check run on every poll, which is how an edited OU takes effect',
    !consulted($d)
);

// ------------------------------- the host that cannot report where it is

$d = row([
    'joined' => 1,
    'domain' => 'example.com',
    'machineAccount' => 'WS-014$',
    'computerDN' => ''
]);
\FOG\Agent\DirectoryPlacement::ensure(
    wanting('OU=Sales,DC=example,DC=com'),
    $d
);
$t->check(
    'a joined host that could not report a DN IS consulted about -- no Linux'
        . ' join tool exposes one, and the directory is the authority on where'
        . ' its own objects live. Gating this on ouDrifted() instead would'
        . ' silently exclude every Linux host from the feature forever, and'
        . ' look like the feature simply not working',
    consulted($d)
);

$t->check(
    'and the reason it could not finish is recorded against the host, not'
        . ' swallowed -- a silent failure reads like drift that never clears',
    '' !== trim((string)$d->get('placementError'))
);

// --------------------------------------------------------------- cooling off

$d = row([
    'joined' => 1,
    'machineAccount' => 'WS-014$',
    'computerDN' => '',
    // Minutes ago, not seconds: two stamps written in the same second are
    // the same string, and an assertion that cannot see the difference is
    // not a gate. Well inside the cooldown either way.
    'placementAt' => (new \DateTime('now', new \DateTimeZone('UTC')))
        ->modify('-120 seconds')
        ->format('Y-m-d H:i:s'),
    'placementError' => 'bind failed: Invalid credentials'
]);
$before = (string)$d->get('placementAt');
\FOG\Agent\DirectoryPlacement::ensure(
    wanting('OU=Sales,DC=example,DC=com'),
    $d
);
$t->check(
    'a host consulted about a moment ago is left alone: without this, a'
        . ' directory that has gone away is dialed once per poll per host,'
        . ' every one of them paying the connection timeout',
    $before === (string)$d->get('placementAt')
);

$d = row([
    'joined' => 1,
    'machineAccount' => 'WS-014$',
    'computerDN' => '',
    'placementAt' => (new \DateTime('now', new \DateTimeZone('UTC')))
        ->modify('-' . (\FOG\Agent\DirectoryPlacement::RETRY_AFTER + 60)
            . ' seconds')
        ->format('Y-m-d H:i:s'),
    'placementError' => 'bind failed: Invalid credentials'
]);
$before = (string)$d->get('placementAt');
\FOG\Agent\DirectoryPlacement::ensure(
    wanting('OU=Sales,DC=example,DC=com'),
    $d
);
$t->check(
    'and one consulted about longer ago than the cooldown is tried again',
    $before !== (string)$d->get('placementAt')
);

// --------------------------------------------------------------- DN handling

$t->check(
    'the RDN is the first component',
    'CN=WS-014' === \FOG\Net\FOGLdap::rdn('CN=WS-014,OU=Sales,DC=example,DC=com')
);

$t->check(
    'an escaped comma does not end the RDN -- a plain explode would rename'
        . ' the object to half of its own name',
    'CN=Smith\\, John'
        === \FOG\Net\FOGLdap::rdn('CN=Smith\\, John,OU=Sales,DC=example,DC=com')
);

$t->check(
    'a DN with no comma is all RDN',
    'CN=WS-014' === \FOG\Net\FOGLdap::rdn('CN=WS-014')
);

$t->check(
    'the parent is everything after the RDN',
    'OU=Sales,DC=example,DC=com'
        === \FOG\Net\FOGLdap::parentDn('CN=WS-014,OU=Sales,DC=example,DC=com')
);

$t->check(
    'an escaped comma does not end the RDN when reading the parent either',
    'OU=Sales,DC=example,DC=com' === \FOG\Net\FOGLdap::parentDn(
        'CN=Smith\\, John,OU=Sales,DC=example,DC=com'
    )
);

$t->check(
    'a DN with no comma has no parent, rather than being its own',
    '' === \FOG\Net\FOGLdap::parentDn('CN=WS-014')
);

// ------------------------------------------------------------- the lookup

$m = new \ReflectionMethod(\FOG\Agent\DirectoryFacts::class, 'row');
$m->setAccessible(true);
$looked = null;
$lookupError = '';
try {
    $looked = $m->invoke(null, 4242);
} catch (\Throwable $e) {
    $lookupError = get_class($e) . ': ' . $e->getMessage();
}

$t->check(
    'the row lookup runs at all. It shipped calling'
        . ' HostDirectoryManager::find(), which does not exist --'
        . ' FOGManagerController is the read side of the route layer, not a'
        . ' repository -- and because report() is reached only when a host'
        . ' sends a directory block, and no agent had ever sent one, the'
        . ' fatal sat there undetected through a full test suite, a deploy'
        . ' and a review. Anything that CALLS it catches that'
        . ('' === $lookupError ? '' : '; got ' . $lookupError),
    $looked instanceof \FOG\Items\HostDirectory
);

$t->check(
    'and an unknown host gets a new row carrying its id, not a null',
    $looked instanceof \FOG\Items\HostDirectory
        && 4242 === (int)$looked->get('hostID')
);

// ------------------------------------------------------- the bind password

/**
 * The bind password placement would actually use.
 *
 * @param string $stored what sits in globalSettings
 *
 * @return string
 */
function bindPasswordFor($stored)
{
    settings(['FOG_DIRECTORY_BIND_PASSWORD' => $stored]);
    $m = new \ReflectionMethod(
        \FOG\Agent\DirectoryPlacement::class,
        '_bindPassword'
    );
    $m->setAccessible(true);
    return (string)$m->invoke(null);
}

$t->check(
    'a password typed into the configuration page comes back as typed. This'
        . ' is the one the LDAP plugin gets WRONG: its probe asks'
        . ' `if ($x = base64_decode($test))`, and non-strict base64_decode'
        . ' does not fail on a non-base64 string -- it drops the characters'
        . ' outside the alphabet and decodes the rest. WkwdOXZuKFK is an'
        . ' ordinary alphanumeric password, and that probe turns it into'
        . ' eight bytes of binary and binds with those. Round-tripping the'
        . ' encode is the only test that tells the two shapes apart',
    'WkwdOXZuKFK' === bindPasswordFor('WkwdOXZuKFK')
);

$t->check(
    'and a base64-stored one is decoded, because a script that writes the'
        . ' setting has no page to type into',
    'WkwdOXZuKFK' === bindPasswordFor(base64_encode('WkwdOXZuKFK'))
);

$t->check(
    'an empty setting is an empty password, not a decode of nothing',
    '' === bindPasswordFor('')
);

$t->check(
    'a password that IS valid base64 by accident survives -- the round trip'
        . ' would accept it as encoded, so the decoded value has to be the'
        . ' one that reads as text',
    'passwordZ' === bindPasswordFor('passwordZ')
);

// ------------------------------------------------------------ where it hangs

$facts = file_get_contents(
    dirname(__DIR__) . '/packages/web/src/Agent/DirectoryFacts.php'
);
$state = file_get_contents(
    dirname(__DIR__) . '/packages/web/src/Agent/State.php'
);

/**
 * A file's code with its comments removed.
 *
 * A guard that greps the raw file passes on its own prose: this suite has
 * already had one drift check stay green because the docblock explaining it
 * contained the very string it was searching for.
 *
 * @param string $src the PHP source
 *
 * @return string
 */
function codeOf($src)
{
    $out = '';
    foreach (token_get_all($src) as $token) {
        if (is_array($token)) {
            if (T_COMMENT === $token[0] || T_DOC_COMMENT === $token[0]) {
                continue;
            }
            $out .= $token[1];
            continue;
        }
        $out .= $token;
    }
    return $out;
}

$t->check(
    'placement runs from the poll, so an admin editing a host OU takes effect'
        . ' -- hanging it off the fact report instead would mean the move'
        . ' never happens until the MACHINE changes domains, which is design'
        . ' 0009 arrived at from the other direction',
    false !== strpos(codeOf($state), 'DirectoryFacts::place($Host)')
);

$t->check(
    'and DirectoryFacts::report() does not call it, or it would fire twice on'
        . ' the poll that carries a changed block',
    false === strpos(
        codeOf(substr($facts, 0, (int)strpos($facts, 'public static function place'))),
        'DirectoryPlacement'
    )
);

$t->finish();
