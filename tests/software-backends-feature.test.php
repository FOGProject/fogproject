<?php
/**
 * Software backends are sent only to an agent that declares the feature.
 *
 * Agents 0.1.21 and earlier run every software entry through Chocolatey
 * whatever its backend says. SoftwareSet::requested() reads the declared
 * feature and SoftwareSet::sendable() decides which entries an agent gets.
 *
 * DB-free: both are pure functions.
 *
 * Usage: php tests/software-backends-feature.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('software-backends-feature');

$t = new FogChecks();

$token = \FOG\Agent\SoftwareSet::FEATURE;

// The literal is the agent's wire token (fog-agent internal/enroll); a
// rename of the constant must not silently change what the server accepts.
$t->check(
    'the agent\'s literal token is accepted',
    true === \FOG\Agent\SoftwareSet::requested(['features' => ['software-backends-v1']])
);
$t->check(
    'requested: token present',
    true === \FOG\Agent\SoftwareSet::requested(['features' => [$token]])
);
$t->check(
    'requested: token among others',
    true === \FOG\Agent\SoftwareSet::requested(
        ['features' => ['directory-join-target-v1', $token]]
    )
);
$t->check(
    'requested: other token only',
    false === \FOG\Agent\SoftwareSet::requested(
        ['features' => ['directory-join-target-v1']]
    )
);
$t->check(
    'requested: no features key',
    false === \FOG\Agent\SoftwareSet::requested([])
);
$t->check(
    'requested: features is not an array',
    false === \FOG\Agent\SoftwareSet::requested(['features' => 'nope'])
);

foreach (['choco', ''] as $backend) {
    $t->check(
        "sendable: '$backend' to an old agent",
        true === \FOG\Agent\SoftwareSet::sendable($backend, false)
    );
    $t->check(
        "sendable: '$backend' to a new agent",
        true === \FOG\Agent\SoftwareSet::sendable($backend, true)
    );
}
foreach (['flatpak', 'snap'] as $backend) {
    $t->check(
        "sendable: $backend withheld from an old agent",
        false === \FOG\Agent\SoftwareSet::sendable($backend, false)
    );
    $t->check(
        "sendable: $backend sent to a new agent",
        true === \FOG\Agent\SoftwareSet::sendable($backend, true)
    );
}

$t->finish();
