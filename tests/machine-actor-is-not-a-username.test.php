<?php
/**
 * The name FOG records for "no person did this" can never be an account's
 * username, and every writer uses the same one.
 *
 * THE RISK. Audit::MACHINE_ACTOR was 'fog', which is also the default
 * administrator's username. A rejected API token, a host registered by FOS,
 * an expiry sweep -- all read as the admin's own actions in the Audit Log and
 * in every Created By column. An audit trail that cannot tell the server from
 * the administrator cannot answer "who did this".
 *
 * WHAT THIS PINS
 *  - MACHINE_ACTOR fails both username rules (User::PATTERN for sign-in,
 *    UserManagement::USERNAME_REGEX for the user form), so no account can
 *    ever carry it.
 *  - FOGController::save() fills createdBy from that constant, not from a
 *    literal of its own, so the two writers cannot drift apart again.
 *
 * DB-free.
 *
 * Usage: php tests/machine-actor-is-not-a-username.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

$root = dirname(__DIR__);
$web = $root . '/packages/web';

require_once $web . '/src/Base/FOGBase.php';
require_once $web . '/src/Base/FOGController.php';
require_once $web . '/src/Items/User.php';
require_once $web . '/src/Audit/Audit.php';

use FOG\Audit\Audit;
use FOG\Items\User;

$t = new FogChecks();

// Read from source, like the other pins below. Compared as a PHP constant,
// phpstan folds 'fog' !== '(system)' to "always true" and fails the build.
$auditSrc = (string)file_get_contents($web . '/src/Audit/Audit.php');
$hasActor = preg_match("/const MACHINE_ACTOR = '([^']*)';/", $auditSrc, $am);
$t->check('Audit::MACHINE_ACTOR is readable', 1 === $hasActor);
$actor = $am[1] ?? '';
$t->check(
    'the loaded class carries the same value',
    Audit::MACHINE_ACTOR === $actor
);

$t->check(
    'the machine actor is not the default admin username',
    'fog' !== $actor
);
$t->check(
    'the machine actor cannot sign in (User::PATTERN refuses it)',
    1 !== preg_match(User::PATTERN, $actor)
);

// UserManagement pulls in the whole page stack, so read its rule from source.
$um = (string)file_get_contents($web . '/src/Pages/UserManagement.php');
$found = preg_match("/const USERNAME_REGEX = '([^']+)';/", $um, $m);
$t->check('UserManagement::USERNAME_REGEX is readable', 1 === $found);
$t->check(
    'the machine actor cannot be created (USERNAME_REGEX refuses it)',
    1 === $found && 1 !== preg_match('/' . $m[1] . '/', $actor)
);
// The two rules must still accept an ordinary name, or the checks above
// pass for the wrong reason.
$t->check(
    'both rules still accept an ordinary username',
    1 === preg_match(User::PATTERN, 'claude-lab')
        && 1 === $found && 1 === preg_match('/' . $m[1] . '/', 'claude-lab')
);

$fc = (string)file_get_contents($web . '/src/Base/FOGController.php');
$t->check(
    'save() fills createdBy from Audit::MACHINE_ACTOR',
    false !== strpos($fc, '$val = Audit::MACHINE_ACTOR;')
);
$t->check(
    'save() has no literal machine actor of its own',
    1 !== preg_match("/\\\$val = 'fog';/", $fc)
);

// Quick registration creates a task with no account behind it.
$reg = (string)file_get_contents($web . '/src/Boot/Registration.php');
$t->check(
    'quick registration records the machine actor, not the fog account',
    false !== strpos($reg, '$username = ($username ?: Audit::MACHINE_ACTOR);')
        && false === strpos($reg, "\$username ?: 'fog'")
);
// The task e-mail builds an address from the creator; the machine actor is
// not a mailbox, so it must keep the address it reached before.
$tq = (string)file_get_contents($web . '/src/TaskHandling/TaskQueue.php');
$t->check(
    'the task e-mail maps the machine actor back to the old mailbox',
    (bool)preg_match(
        "/if \\(Audit::MACHINE_ACTOR === \\\$engineer\\) \\{\\s*\\\$engineer = 'fog';/",
        $tq
    )
);

$t->finish();
