<?php
/**
 * FOGLdap's rejoin lookups must distinguish an absent object from an
 * incomplete or ambiguous LDAP answer, without opening a directory socket.
 *
 * Usage: php tests/ldap-computer-record.test.php
 */

namespace FOG\Net;

const LDAP_ESCAPE_FILTER = 1;

$GLOBALS['ldapFixture'] = [];

class FakeLdapResult
{
    public $entries;
    public $parse;

    public function __construct($entries, $parse)
    {
        $this->entries = $entries;
        $this->parse = $parse;
    }
}

function ldap_escape($value, $ignore, $flags)
{
    return strtr((string)$value, [
        '\\' => '\\5c',
        '*' => '\\2a',
        '(' => '\\28',
        ')' => '\\29',
        "\0" => '\\00'
    ]);
}

function ldap_search($ld, $base, $filter, $attributes)
{
    $GLOBALS['ldapFixture']['search'] = [$base, $filter, $attributes];
    $fixture = $GLOBALS['ldapFixture']['searchResult'] ?? null;
    return $fixture instanceof FakeLdapResult ? $fixture : false;
}

function ldap_read($ld, $base, $filter, $attributes)
{
    $GLOBALS['ldapFixture']['read'] = [$base, $filter, $attributes];
    $fixture = $GLOBALS['ldapFixture']['readResult'] ?? null;
    return $fixture instanceof FakeLdapResult ? $fixture : false;
}

function ldap_get_entries($ld, $result)
{
    return $result->entries;
}

function ldap_parse_result(
    $ld,
    $result,
    &$code,
    &$matchedDn,
    &$message,
    &$referrals,
    &$controls = null
) {
    if (empty($result->parse['ok'])) {
        return false;
    }
    $code = $result->parse['code'] ?? 0;
    $matchedDn = '';
    $message = '';
    $referrals = $result->parse['referrals'] ?? [];
    $controls = [];
    return true;
}

require_once dirname(__DIR__) . '/packages/web/src/Net/FOGLdap.php';

function check($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    echo "PASS: {$message}\n";
}

function values(array $items)
{
    return ['count' => count($items)] + array_values($items);
}

function entry(array $attributes)
{
    return ['count' => 1, 0 => $attributes];
}

function objectRecord($uac = '4096', $pwdLastSet = '133700000000000000')
{
    // AD GUID fields are little-endian for the first three UUID components.
    $guid = pack('Vvv', 0x00112233, 0x4455, 0x6677)
        . hex2bin('8899aabbccddeeff');
    // S-1-5-21-42-84-126 in the AD binary SID form.
    $sid = pack('CC', 1, 4) . hex2bin('000000000005')
        . pack('V*', 21, 42, 84, 126);
    return [
        'objectclass' => values(['top', 'person', 'organizationalPerson', 'user', 'computer']),
        'objectguid' => values([$guid]),
        'objectsid' => values([$sid]),
        'distinguishedname' => values(['CN=WS-02,OU=Fleet,DC=corp,DC=example']),
        'samaccountname' => values(['WS-02$']),
        'dnshostname' => values(['ws-02.corp.example']),
        'useraccountcontrol' => values([$uac]),
        'pwdlastset' => values([$pwdLastSet]),
        'serviceprincipalname' => values(['HOST/ws-02.corp.example', 'RestrictedKrbHost/WS-02'])
    ];
}

function result(array $entries, array $parse = [])
{
    return new FakeLdapResult($entries, ['ok' => true] + $parse);
}

function client()
{
    $client = new FOGLdap();
    $property = new \ReflectionProperty(FOGLdap::class, '_ld');
    $property->setAccessible(true);
    $property->setValue($client, new \stdClass());
    return $client;
}

$ldap = client();
$GLOBALS['ldapFixture']['searchResult'] = result(entry(objectRecord()));
$found = $ldap->computerRecord('DC=corp,DC=example', 'WS-02');
check($found['status'] === 'found', 'one complete computer answer is found');
check(
    $found['record']['guid'] === '00112233-4455-6677-8899-aabbccddeeff'
        && $found['record']['sid'] === 'S-1-5-21-42-84-126',
    'GUID endian conversion and strict SID decoding are canonical'
);
check(
    $found['record']['account'] === 'WS-02$'
        && $found['record']['uac'] === 4096
        && $found['record']['pwd_last_set'] === '133700000000000000'
        && $found['record']['spns'] === ['HOST/ws-02.corp.example', 'RestrictedKrbHost/WS-02'],
    'record fields preserve public types and SPNs are sorted'
);
check(
    $GLOBALS['ldapFixture']['search'][1]
        === '(&(objectClass=computer)(sAMAccountName=WS-02$))',
    'search is restricted to the escaped computer account'
);

$GLOBALS['ldapFixture']['searchResult'] = result(['count' => 0]);
check(
    $ldap->computerRecord('DC=corp,DC=example', 'WS-02')['status'] === 'absent',
    'only a completed zero-entry search is absent'
);
$GLOBALS['ldapFixture']['searchResult'] = result(['count' => 0], ['code' => 4]);
check(
    $ldap->computerRecord('DC=corp,DC=example', 'WS-02')['status'] === 'error',
    'size-limited zero-entry search is not absent'
);
$GLOBALS['ldapFixture']['searchResult'] = result(['count' => 0], ['code' => 9]);
check(
    $ldap->computerRecord('DC=corp,DC=example', 'WS-02')['status'] === 'error',
    'partial zero-entry search is not absent'
);
$GLOBALS['ldapFixture']['searchResult'] = result(['count' => 0], ['referrals' => ['ldap://other/']]);
check(
    $ldap->computerRecord('DC=corp,DC=example', 'WS-02')['status'] === 'error',
    'referral-bearing zero-entry search is not absent'
);
$GLOBALS['ldapFixture']['searchResult'] = result(['count' => 2, 0 => objectRecord(), 1 => objectRecord()]);
check(
    $ldap->computerRecord('DC=corp,DC=example', 'WS-02')['status'] === 'conflict',
    'multiple matching records are a conflict'
);
$GLOBALS['ldapFixture']['searchResult'] = result(entry(objectRecord('8192')));
check(
    $ldap->computerRecord('DC=corp,DC=example', 'WS-02')['status'] === 'conflict',
    'SERVER_TRUST_ACCOUNT UAC flag is rejected'
);
$GLOBALS['ldapFixture']['searchResult'] = result(entry(objectRecord('67108864')));
check(
    $ldap->computerRecord('DC=corp,DC=example', 'WS-02')['status'] === 'conflict',
    'PARTIAL_SECRETS_ACCOUNT UAC flag is rejected'
);
$GLOBALS['ldapFixture']['searchResult'] = result(entry(objectRecord('4096')));
check(
    $ldap->computerRecord('DC=corp,DC=example', 'WS-02')['status'] === 'found',
    'WORKSTATION_TRUST_ACCOUNT UAC flag remains eligible'
);
$GLOBALS['ldapFixture']['searchResult'] = result(entry(objectRecord('512|4096')));
check(
    $ldap->computerRecord('DC=corp,DC=example', 'WS-02')['status'] === 'error',
    'non-decimal UAC values are rejected'
);
$incomplete = objectRecord();
unset($incomplete['objectsid']);
$GLOBALS['ldapFixture']['searchResult'] = result(entry($incomplete));
check(
    $ldap->computerRecord('DC=corp,DC=example', 'WS-02')['status'] === 'error',
    'missing required attributes are errors'
);
$malformedSid = objectRecord();
$malformedSid['objectsid'] = values(["\x02\x00\x00\x00\x00\x00\x05\x00"]);
$GLOBALS['ldapFixture']['searchResult'] = result(entry($malformedSid));
check(
    $ldap->computerRecord('DC=corp,DC=example', 'WS-02')['status'] === 'error',
    'malformed binary SID is rejected'
);
$notComputer = objectRecord();
$notComputer['objectclass'] = values(['top', 'user']);
$GLOBALS['ldapFixture']['searchResult'] = result(entry($notComputer));
check(
    $ldap->computerRecord('DC=corp,DC=example', 'WS-02')['status'] === 'conflict',
    'non-computer objects are rejected even if returned by the search'
);
$wrongAccount = objectRecord();
$wrongAccount['samaccountname'] = values(['WS-OTHER$']);
$GLOBALS['ldapFixture']['searchResult'] = result(entry($wrongAccount));
check(
    $ldap->computerRecord('DC=corp,DC=example', 'WS-02')['status'] === 'error',
    'returned account must match the requested account'
);
$optionalAttributesAbsent = objectRecord('4096', '18446744073709551615');
unset($optionalAttributesAbsent['dnshostname'], $optionalAttributesAbsent['serviceprincipalname']);
$GLOBALS['ldapFixture']['searchResult'] = result(entry($optionalAttributesAbsent));
$optionalResult = $ldap->computerRecord('DC=corp,DC=example', 'WS-02');
check(
    $optionalResult['status'] === 'found'
        && $optionalResult['record']['dns'] === ''
        && $optionalResult['record']['spns'] === [],
    'precreated account may omit DNS hostname and SPNs'
);
check(
    $optionalResult['record']['pwd_last_set'] === '18446744073709551615',
    'large pwdLastSet remains an exact decimal string'
);
$invalidDns = objectRecord();
$invalidDns['dnshostname'] = values(['10.0.0.8']);
$GLOBALS['ldapFixture']['searchResult'] = result(entry($invalidDns));
check(
    $ldap->computerRecord('DC=corp,DC=example', 'WS-02')['status'] === 'error',
    'a present non-FQDN dNSHostName is rejected'
);
$GLOBALS['ldapFixture']['searchResult'] = new FakeLdapResult(entry(objectRecord()), ['ok' => false]);
check(
    $ldap->computerRecord('DC=corp,DC=example', 'WS-02')['status'] === 'error',
    'unparseable LDAP result is an error'
);

$GLOBALS['ldapFixture']['readResult'] = result(entry([
    'defaultnamingcontext' => values(['DC=corp,DC=example']),
    'dnshostname' => values(['dc01.corp.example'])
]));
$context = $ldap->domainContext('CORP.EXAMPLE');
check(
    $context === [
        'status' => 'found',
        'base_dn' => 'DC=corp,DC=example',
        'dc' => 'dc01.corp.example'
    ],
    'RootDSE domain context returns the verified naming context and FQDN'
);
check(
    $ldap->domainContext('other.example')['status'] === 'error',
    'a DNS domain that differs from defaultNamingContext is rejected'
);
$GLOBALS['ldapFixture']['readResult'] = result(entry([
    'defaultnamingcontext' => values(['DC=corp,DC=example']),
    'dnshostname' => values(['10.0.0.1'])
]));
check(
    $ldap->domainContext('corp.example')['status'] === 'error',
    'RootDSE IP literals are not accepted as a DC FQDN'
);

echo "ldap-computer-record tests passed\n";
