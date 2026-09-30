<?php
/**
 * A product key goes only where it can be used.
 *
 * Design 0016 (fog-agent). The activation block carries the host's Windows
 * product key to the agent, so every case where the agent could not use it
 * must keep it off the wire:
 *
 * - no key, or a key that is not a valid product key;
 * - a host that did not enroll as Windows.
 *
 * And the one case that sends it must send the hyphenated 29-character form
 * the agent expects, decoded from whatever encoding the legacy row holds.
 *
 * DB-free: the harness's fake connection answers the enrollment read.
 *
 * Usage: php tests/agent-activation.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('agent-activation');
$db = FogTestHarness::fakeDb();

$t = new FogChecks();

// A Microsoft-published KMS client setup key: public, and real in shape.
const KEY = 'W269N-WFGWX-YVC9B-4J6C9-T83GX';

/**
 * State::_activation() for a host with this key, enrolled with this OS.
 *
 * @param string      $key the stored hostProductKey
 * @param string|null $os  the issued enrollment's OS, or null for none
 *
 * @return array|null
 */
function activation($key, $os)
{
    global $db;
    $db->responder = function ($sql) use ($os) {
        if (false === stripos($sql, 'agentEnrollment')) {
            return null;
        }
        return null === $os ? [] : [['aeOS' => $os]];
    };
    $Host = new \FOG\Items\Host();
    $Host->set('id', 7)->set('productKey', $key);
    $m = new \ReflectionMethod(\FOG\Agent\State::class, '_activation');
    $m->setAccessible(true);
    $out = $m->invoke(null, $Host);
    $db->responder = null;

    return $out;
}

$caps = new \ReflectionClassConstant(\FOG\Agent\State::class, 'CAPABILITIES');
$t->check(
    'activation is gated on the EXISTING hostnamechanger module, where the '
        . 'legacy client did it',
    'hostnamechanger' === ($caps->getValue()['activation'] ?? null)
);

$t->check(
    'a Windows host with a key gets it, hyphenated',
    ['key' => KEY] === activation(KEY, 'windows')
);
$t->check(
    'a key stored without hyphens is sent hyphenated',
    ['key' => KEY] === activation(str_replace('-', '', KEY), 'windows')
);
$t->check(
    'a base64-encoded legacy row is decoded before it is sent',
    ['key' => KEY] === activation(base64_encode(KEY), 'windows')
);
$t->check('no key sends no block', null === activation('', 'windows'));
$t->check(
    'an invalid key sends no block',
    null === activation('AAAAA-AAAAA-AAAAA-AAAAA-AAAAA', 'windows')
);
$t->check('a Linux host gets no key', null === activation(KEY, 'linux'));
$t->check(
    'a host with no issued enrollment gets no key',
    null === activation(KEY, null)
);

exit($t->finish());
