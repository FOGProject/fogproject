<?php
/**
 * Network-free contract checks for FOGLdap GUID DN and placement context.
 * The RootDSE response is synthetic; this does not establish AD transport behavior.
 */

namespace FOG\Net {
    class GuidContextResult
    {
        public $entries;
        public $parse;

        public function __construct(array $entries, array $parse = [])
        {
            $this->entries = $entries;
            $this->parse = ['code' => 0, 'referrals' => []] + $parse;
        }
    }

    $GLOBALS['guidContext'] = [
        'base_dn' => 'DC=corp,DC=example',
        'dc' => 'dc01.corp.example',
        'naming_contexts' => [
            'DC=corp,DC=example',
            'CN=Configuration,DC=corp,DC=example',
            'CN=Schema,CN=Configuration,DC=corp,DC=example'
        ],
        'reads' => []
    ];

    function ldap_read($ld, $base, $filter, $attributes)
    {
        $GLOBALS['guidContext']['reads'][] = [$ld, $base, $filter, $attributes];
        if ('' !== $base || '(objectClass=*)' !== $filter) {
            return false;
        }
        $root = $GLOBALS['guidContext'];
        return new GuidContextResult([
            'count' => 1,
            0 => [
                'defaultnamingcontext' => ['count' => 1, 0 => $root['base_dn']],
                'dnshostname' => ['count' => 1, 0 => $root['dc']],
                'namingcontexts' => ['count' => count($root['naming_contexts'])]
                    + array_combine(array_keys($root['naming_contexts']), array_values($root['naming_contexts']))
            ]
        ]);
    }

    function ldap_get_entries($ld, $result)
    {
        return $result instanceof GuidContextResult ? $result->entries : false;
    }

    function ldap_parse_result($ld, $result, &$code, &$matchedDn, &$message, &$referrals, &$controls = null)
    {
        if (!$result instanceof GuidContextResult) {
            return false;
        }
        $code = $result->parse['code'];
        $matchedDn = '';
        $message = '';
        $referrals = $result->parse['referrals'];
        $controls = [];
        return true;
    }
}

namespace {
    require_once __DIR__ . '/lib/fog-test-harness.php';
    \FogTestHarness::boot('ldap-guid-context');
    require_once dirname(__DIR__) . '/packages/web/src/Net/FOGLdap.php';

    $failures = [];
    $checkCount = 0;
    $check = static function ($label, $condition) use (&$failures, &$checkCount) {
        $checkCount++;
        if (!$condition) {
            $failures[] = $label;
        }
    };

    $ldap = new \FOG\Net\FOGLdap();
    $link = new \stdClass(); // Inert sentinel; no ldap_* call sends it anywhere.
    $ld = new \ReflectionProperty(\FOG\Net\FOGLdap::class, '_ld');
    $ld->setAccessible(true);
    $ld->setValue($ldap, $link);

    try {
        $guid = '00112233-4455-6677-8899-aabbccddeeff';
        $check(
            'GUID DN uses AD little-endian encoding for the first UUID fields',
            '<GUID=33221100554477668899aabbccddeeff>' === \FOG\Net\FOGLdap::guidDN($guid)
        );
        $check(
            'GUID DN canonicalizes uppercase UUID input',
            \FOG\Net\FOGLdap::guidDN(strtoupper($guid)) === \FOG\Net\FOGLdap::guidDN($guid)
        );

        $domainContext = $ldap->domainContext('corp.example');
        $check(
            'default domainContext preserves its original RootDSE attributes and result shape',
            'found' === ($domainContext['status'] ?? null)
                && 'DC=corp,DC=example' === ($domainContext['base_dn'] ?? null)
                && !array_key_exists('naming_contexts', $domainContext)
                && ['defaultNamingContext', 'dnsHostName'] === $GLOBALS['guidContext']['reads'][0][3]
        );

        foreach (['', 'not-a-guid', '00112233-4455-6677-8899-aabbccddeefg', '00112233445566778899aabbccddeeff', null, []] as $badGuid) {
            $refused = false;
            try {
                \FOG\Net\FOGLdap::guidDN($badGuid);
            } catch (\InvalidArgumentException $e) {
                $refused = true;
            }
            $check('malformed UUID is refused by guidDN', $refused);
        }

        // The configured OU subtree is parsed by the real global ldap_explode_dn;
        // only the RootDSE result is faked. No NetBIOS host-domain input is passed.
        $context = $ldap->placementContext('OU=Managed,DC=corp,DC=example');
        $check(
            'configured OU subtree is accepted under the RootDSE naming context',
            $context === [
                'status' => 'found',
                'base_dn' => 'DC=corp,DC=example',
                'dc' => 'dc01.corp.example',
                'naming_contexts' => [
                    'DC=corp,DC=example',
                    'CN=Configuration,DC=corp,DC=example',
                    'CN=Schema,CN=Configuration,DC=corp,DC=example'
                ]
            ]
        );
        $reads = $GLOBALS['guidContext']['reads'];
        $check(
            'placement context requests default, DC host, and naming-context roots on the inert connection',
            2 === count($reads)
                && $reads[1][0] === $link
                && '' === $reads[1][1]
                && '(objectClass=*)' === $reads[1][2]
                && ['defaultNamingContext', 'dnsHostName', 'namingContexts'] === $reads[1][3]
        );

        $configurationNC = $ldap->placementContext('CN=Configuration,DC=corp,DC=example');
        $check(
            'Configuration container sharing the domain DN suffix is rejected as a different NC',
            'error' === ($configurationNC['status'] ?? null)
                && 'search_base_nc_mismatch' === ($configurationNC['reason'] ?? null)
        );

        $crossNc = $ldap->placementContext('OU=Managed,DC=other,DC=example');
        $check(
            'configured search base in another naming context is refused',
            'error' === ($crossNc['status'] ?? null)
                && 'search_base_nc_mismatch' === ($crossNc['reason'] ?? null)
        );
    } finally {
        // Do not let FOGLdap cleanup treat the inert sentinel as a real link.
        $ld->setValue($ldap, null);
    }

    if ($failures) {
        fwrite(STDERR, 'FAIL (' . count($failures) . ' of ' . $checkCount . "):\n");
        foreach ($failures as $failure) {
            fwrite(STDERR, '  - ' . $failure . "\n");
        }
        exit(1);
    }
    echo 'ok  ' . $checkCount . " checks passed\n";
}
