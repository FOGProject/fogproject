<?php
/**
 * The real FOGLdap::moveTo() with a fake LDAP transport. A Move that names the
 * object by its GUID DN reaches the server by that GUID, never by a DN that
 * another object could have taken after a delete and re-create. The outcome
 * says whether a request was sent, and what the server answered.
 *
 * No database and no network.
 */

namespace FOG\Net {
    function ldap_rename($ld, $entry, $rdn, $parent, $deleteOldRdn)
    {
        $state =& $GLOBALS['moveByGuid'];
        $state['calls'][] = ['entry' => $entry, 'rdn' => $rdn, 'parent' => $parent, 'delete_old_rdn' => $deleteOldRdn];
        if (!isset($state['objects'][$entry])) {
            // Only a GUID DN names the object. A reused DN reaches nothing.
            $state['errno'] = 32;
            $state['error'] = 'No such object';
            return false;
        }
        $state['errno'] = $state['answer_errno'];
        $state['error'] = $state['answer_error'];
        return 0 === $state['answer_errno'];
    }

    function ldap_set_option($ld, $option, $value)
    {
        $GLOBALS['moveByGuid']['options'][] = [$option, $value];
        return true;
    }

    function ldap_error($ld)
    {
        return (string)$GLOBALS['moveByGuid']['error'];
    }

    function ldap_errno($ld)
    {
        return (int)$GLOBALS['moveByGuid']['errno'];
    }
}

namespace {
    require_once __DIR__ . '/lib/fog-test-harness.php';
    \FogTestHarness::boot('ldap-move-by-guid');
    require_once dirname(__DIR__) . '/packages/web/src/Net/FOGLdap.php';

    $failures = [];
    $checkCount = 0;
    $check = static function ($label, $condition) use (&$failures, &$checkCount) {
        $checkCount++;
        if (!$condition) {
            $failures[] = $label;
        }
    };

    $targetGuid = '00112233-4455-6677-8899-aabbccddeeff';
    $destinationGuid = 'ffeeddcc-bbaa-4988-8776-655443322110';
    $targetDn = \FOG\Net\FOGLdap::guidDN($targetGuid);
    $destinationDn = \FOG\Net\FOGLdap::guidDN($destinationGuid);
    $GLOBALS['moveByGuid'] = ['calls' => [], 'objects' => [$targetDn => true], 'errno' => 0, 'error' => '',
        'answer_errno' => 0, 'answer_error' => ''];

    $ldap = new \FOG\Net\FOGLdap();
    $link = new \stdClass(); // Inert sentinel; the fake transport never sends it anywhere.
    $ld = new \ReflectionProperty(\FOG\Net\FOGLdap::class, '_ld');
    $ld->setAccessible(true);
    $ld->setValue($ldap, $link);

    try {
        $check('a Move with no connection is not sent', (function () {
            $offline = new \FOG\Net\FOGLdap();
            return false === $offline->moveTo('CN=PC1,OU=Old,DC=corp,DC=example', 'OU=New,DC=corp,DC=example')
                && 'not_sent' === $offline->moveOutcome()['kind'] && [] === $GLOBALS['moveByGuid']['calls'];
        })());

        $moved = $ldap->moveTo($targetDn, $destinationDn, 'CN=PC1');
        $call = $GLOBALS['moveByGuid']['calls'][0] ?? [];
        $check(
            'a GUID Move sends the GUID DN, the given RDN and the destination GUID DN, and replaces the old RDN',
            true === $moved && $targetDn === ($call['entry'] ?? null) && 'CN=PC1' === ($call['rdn'] ?? null)
                && $destinationDn === ($call['parent'] ?? null) && true === ($call['delete_old_rdn'] ?? null)
        );
        $check(
            'a Move bounds the directory\'s answer time before it sends',
            [[LDAP_OPT_TIMEOUT, \FOG\Net\FOGLdap::MOVE_TIMEOUT]] === ($GLOBALS['moveByGuid']['options'] ?? [])
        );
        $check('a successful Move records ldap_success', ['kind' => 'ldap_success', 'code' => 0] === $ldap->moveOutcome());

        // Without an RDN argument the RDN is read from a DN.
        $GLOBALS['moveByGuid']['objects']['CN=PC2,OU=Old,DC=corp,DC=example'] = true;
        $ldap->moveTo('CN=PC2,OU=Old,DC=corp,DC=example', 'OU=New,DC=corp,DC=example');
        $call = end($GLOBALS['moveByGuid']['calls']);
        $check('the RDN is read from a plain DN when none is given', 'CN=PC2' === $call['rdn']);

        // A GUID DN has no readable RDN, so a Move without one is refused before it is sent.
        $before = count($GLOBALS['moveByGuid']['calls']);
        $refused = $ldap->moveTo($targetDn, $destinationDn);
        $check(
            'a GUID Move with no RDN is not sent',
            false === $refused && $before === count($GLOBALS['moveByGuid']['calls']) && 'not_sent' === $ldap->moveOutcome()['kind']
        );

        // The server rejects the Move.
        $GLOBALS['moveByGuid']['answer_errno'] = 50;
        $GLOBALS['moveByGuid']['answer_error'] = 'Insufficient access';
        $rejected = $ldap->moveTo($targetDn, $destinationDn, 'CN=PC1');
        $outcome = $ldap->moveOutcome();
        $check(
            'a server rejection is recorded with its code and message',
            false === $rejected && 'ldap_rejection' === $outcome['kind'] && 50 === $outcome['code']
                && 'Insufficient access' === $outcome['message']
        );

        // The object was deleted and its DN reused: the GUID matches nothing.
        $GLOBALS['moveByGuid']['objects'] = [];
        $GLOBALS['moveByGuid']['answer_errno'] = 0;
        $gone = $ldap->moveTo($targetDn, $destinationDn, 'CN=PC1');
        $call = end($GLOBALS['moveByGuid']['calls']);
        $check(
            'a deleted object is not moved by a reused DN',
            false === $gone && $targetDn === $call['entry'] && 'ldap_rejection' === $ldap->moveOutcome()['kind']
        );

        // An error code above the LDAP result range is a local or transport fault.
        $GLOBALS['moveByGuid']['objects'] = [$targetDn => true];
        $GLOBALS['moveByGuid']['answer_errno'] = -1;
        $GLOBALS['moveByGuid']['answer_error'] = "Can't contact LDAP server";
        $ldap->moveTo($targetDn, $destinationDn, 'CN=PC1');
        $check(
            'a transport fault is not reported as a server rejection',
            'local_or_transport_error' === $ldap->moveOutcome()['kind']
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
