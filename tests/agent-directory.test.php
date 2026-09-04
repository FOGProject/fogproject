<?php
/**
 * The directory membership an agent reports stays inside its bounds, and
 * the drift comparison says what it means.
 *
 * Membership rides the poll request (design 0009) onto one `hostDirectory`
 * row per host: what the machine says it is, against the `hosts` table's
 * hostADDomain and hostADOU, which are what an admin asked for. The body
 * comes from a host, so these are the ways it could go wrong quietly:
 *
 * - A host inventing a `kind` would put an uncontrolled string on a page an
 *   admin reads, and would break the report's grouping.
 * - An unjoined machine that still carried a domain would compare EQUAL to
 *   the desired value and hide the drift the row exists to show. The agent
 *   clears those fields; the server must clear them again, so a hand-built
 *   or older block cannot reintroduce the lie.
 * - Drift reported on a missing value is drift nobody can act on. An empty
 *   desired OU is an admin who never expressed one; an empty observed DN is
 *   a platform that cannot report one. Neither is a machine in the wrong
 *   place.
 * - A DN is parsed to find its container, and RFC 4514 escapes a literal
 *   comma in an RDN as `\,` -- a plain explode cuts `CN=Smith\, John` in
 *   half and names a container that does not exist.
 * - The widths map has to name real columns, or a typo silently stops
 *   truncating a field and moves the failure to the database, where it
 *   costs the host its whole poll rather than one field.
 *
 * DB-free: only the pure comparison and normalization are exercised.
 *
 * Usage: php tests/agent-directory.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('agent-directory');
FogTestHarness::fakeDb();

$t = new FogChecks();

/**
 * Builds a HostDirectory carrying one observation.
 *
 * @param array $fields property => value
 *
 * @return \FOG\Items\HostDirectory
 */
function observed(array $fields)
{
    $d = new \FOG\Items\HostDirectory();
    foreach ($fields as $k => $v) {
        $d->set($k, $v);
    }
    return $d;
}

// ------------------------------------------------------- the DN container

$t->check(
    'the container is everything after the first comma',
    'OU=Sales,DC=corp,DC=example,DC=com' === observed([
        'computerDN' => 'CN=WS-014,OU=Sales,DC=corp,DC=example,DC=com'
    ])->containerDN()
);

$t->check(
    'an escaped comma does not end the RDN'
        . ': got ' . observed([
            'computerDN' => 'CN=Smith\\, John,OU=Sales,DC=corp,DC=com'
        ])->containerDN(),
    'OU=Sales,DC=corp,DC=com' === observed([
        'computerDN' => 'CN=Smith\\, John,OU=Sales,DC=corp,DC=com'
    ])->containerDN()
);

$t->check(
    'a DN with no comma has no container, rather than being its own',
    '' === observed(['computerDN' => 'CN=WS-014'])->containerDN()
);

$t->check(
    'nothing reported is no container',
    '' === observed(['computerDN' => ''])->containerDN()
);

// --------------------------------------------------------------- OU drift

$inSales = observed([
    'joined' => 1,
    'domain' => 'corp.example.com',
    'computerDN' => 'CN=WS-014,OU=Sales,DC=corp,DC=example,DC=com'
]);

$t->check(
    'a machine in the OU it should be in has not drifted',
    !$inSales->ouDrifted('OU=Sales,DC=corp,DC=example,DC=com')
);

$t->check(
    'a machine in a different OU has drifted -- the case the legacy client'
        . ' never detects, because it only reads the OU at the first join',
    $inSales->ouDrifted('OU=Engineering,DC=corp,DC=example,DC=com')
);

$t->check(
    'spacing after the commas is not drift',
    !$inSales->ouDrifted('OU=Sales, DC=corp, DC=example, DC=com')
);

$t->check(
    'case is not drift: LDAP naming attributes are case-insensitive',
    !$inSales->ouDrifted('ou=sales,dc=corp,dc=example,dc=com')
);

$t->check(
    'no desired OU is not drift -- an admin who never expressed one',
    !$inSales->ouDrifted('')
);

$t->check(
    'no observed DN is not drift -- no Linux join tool reports one, and a'
        . ' report full of unactionable rows is a report nobody reads',
    !observed(['joined' => 1, 'computerDN' => ''])
        ->ouDrifted('OU=Sales,DC=corp,DC=example,DC=com')
);

// ----------------------------------------------------------- domain drift

$t->check(
    'the DNS name matches the DNS name',
    !$inSales->domainDrifted('corp.example.com')
);

$t->check(
    'a short desired name matches the reported NetBIOS name',
    !observed([
        'joined' => 1, 'domain' => 'corp.example.com', 'netbios' => 'CORP'
    ])->domainDrifted('CORP')
);

$t->check(
    'a short desired name matches the first label when no NetBIOS name was'
        . ' reported -- realmd does not expose one, and calling that drift'
        . ' would be a false alarm on every Linux host',
    !observed(['joined' => 1, 'domain' => 'corp.example.com'])
        ->domainDrifted('CORP')
);

$t->check(
    'a different domain is drift',
    $inSales->domainDrifted('other.example.com')
);

$t->check(
    'a host that should be in a domain and is in none is drift',
    observed(['joined' => 0])->domainDrifted('corp.example.com')
);

$t->check(
    'an UNJOINED row still carrying its old domain is drift, not agreement'
        . ' -- joined is the first question, and comparing the leftover name'
        . ' instead would report a machine that has left the domain as being'
        . ' exactly where it belongs',
    observed([
        'joined' => 0,
        'domain' => 'corp.example.com',
        'netbios' => 'CORP'
    ])->domainDrifted('corp.example.com')
);

$t->check(
    'no desired domain is not drift',
    !$inSales->domainDrifted('')
);

// -------------------------------------------------- the reported vocabulary

$t->check(
    'the kinds the server accepts are the ones the agent can send',
    ['ad', 'entra', 'workgroup', 'none'] === \FOG\Agent\DirectoryFacts::KINDS
);

$prop = (new \ReflectionClass(\FOG\Items\HostDirectory::class))
    ->getProperty('databaseFields');
$prop->setAccessible(true);
$fields = (array)$prop->getValue(new \FOG\Items\HostDirectory());

$t->check(
    'every reported key maps to a real HostDirectory property',
    [] === array_diff(
        array_values(\FOG\Agent\DirectoryFacts::FIELDS),
        array_keys($fields)
    )
);

$t->check(
    'every truncated field is one of those properties',
    [] === array_diff(
        array_keys(\FOG\Agent\DirectoryFacts::WIDTHS),
        array_keys($fields)
    )
);

$t->check(
    'every reported field has a width, or an overlong value would reach the'
        . ' database and cost the host its whole poll',
    [] === array_diff(
        array_values(\FOG\Agent\DirectoryFacts::FIELDS),
        array_keys(\FOG\Agent\DirectoryFacts::WIDTHS)
    )
);

// ------------------------------------------------------------- the wiring

$t->check(
    'directory is a fact report, so it inherits the hash gate and the'
        . ' want_directory answer rather than getting a route of its own',
    (\FOG\Agent\State::FACT_REPORTS['directory'] ?? null)
        === \FOG\Agent\DirectoryFacts::class
);

$t->check(
    'the report is gated on host, not on the shared report node',
    'host' === (\FOG\Auth\Authorization::REPORT_NODES['directory_membership']
        ?? null)
);

$t->finish();
