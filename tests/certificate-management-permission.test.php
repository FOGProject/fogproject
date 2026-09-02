<?php
/**
 * Changing this server's PKI from the Certificates page takes its own
 * permission, and the helper is the only thing that can act on it.
 *
 * The page can now import a root CA into this host's system trust store and
 * write three install preferences into .fogsettings. Both are a larger
 * authority than the page's other cards:
 *
 *  - an imported root reaches /etc/pki/ca-trust (or the distro equivalent), so
 *    every server-side HTTPS call on the box accepts anything issued beneath
 *    it, for as long as it stays there;
 *  - .fogsettings is SOURCED AS SHELL by root on the next installer run, so a
 *    value written into it unvalidated executes as root.
 *
 * settings.edit is what SIX page nodes already map onto. "May edit the OUI
 * table" and "may decide what this server trusts" are not the same grant, so
 * this arrives the way system.export and impersonate.start did: a new action
 * on the system node, seeded by no schema step, held by nobody but '*' until
 * an administrator grants it (GH-1121).
 *
 * The permission half is EXECUTED -- resolvePagePermission() is the same call
 * the router makes, so a map entry edited back, or a registry node dropped,
 * fails here. A grep for the string would pass on a page rewired around it.
 *
 * The second half is about the OTHER side of sudo. The web tier is the side
 * that might be compromised, so the validation that matters cannot live in
 * PHP: the key allowlist and the ^(yes|no)$ value pattern have to be in the
 * root helper. Asserted here as the absence of a PHP-side decision that would
 * make the helper's version redundant and therefore removable.
 *
 * Refs https://github.com/FOGProject/fogproject/issues/1121
 *
 * Boots through FogTestHarness with a fake DB, the way
 * impersonation-read-only-gate.test.php does: resolvePagePermission() fires
 * PERMISSION_REGISTRY_DATA so plugins can add nodes, so it needs a hook
 * manager to exist. Nothing here touches a real database.
 *
 * Usage: php tests/certificate-management-permission.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('certificate-management-permission');
FogTestHarness::fakeDb();

$root = dirname(__DIR__);
$webroot = $root . '/packages/web';

$results = [];

/**
 * Record one assertion and echo its result.
 *
 * Named uniquely rather than check(): tests/ is analyzed as one program by
 * phpstan-tests.neon, and a global check() collides with the dozens of other
 * files that declare their own.
 *
 * @param string $label what is being asserted
 * @param bool   $ok    whether it held
 * @param string $extra detail printed on failure
 *
 * @return bool the value of $ok, for the caller to collect
 */
function pkiPermCheck($label, $ok, $extra = '')
{
    $suffix = ('' !== $extra ? " ($extra)" : '');
    echo ($ok ? 'ok   - ' : 'FAIL - ') . $label . ($ok ? '' : $suffix) . "\n";
    return $ok;
}

/*
 * 1. The two verbs resolve differently, executed through the router's own
 *    resolver. Reading the chain is reading server configuration; changing it
 *    is not.
 */
$get = FOG\Auth\Authorization::resolvePagePermission('about', 'certificates', false);
$post = FOG\Auth\Authorization::resolvePagePermission('about', 'certificates', true);
$results[] = pkiPermCheck(
    'GET ?node=about&sub=certificates stays on settings.view',
    'settings.view' === $get,
    'got ' . var_export($get, true)
);
/*
 * This is also what pins the override to the ALIASED node. `about` resolves to
 * `settings` through NODE_ALIASES before SUB_OVERRIDES is consulted, so an
 * override written under 'about' is never read and this POST falls through to
 * settings.edit -- silently, and with the page still working for an
 * administrator, who holds both. Asserted by executing the resolver rather
 * than by inspecting the constant, which phpstan folds to a literal.
 */
$results[] = pkiPermCheck(
    'POST to the same sub resolves to system.pki',
    'system.pki' === $post,
    'got ' . var_export($post, true)
);
$results[] = pkiPermCheck(
    'it is not settings.edit, which six page nodes share',
    'settings.edit' !== $post,
    'got ' . var_export($post, true)
);
/*
 * 2. Downloading a public certificate is not a change. Every slot the helper
 *    will export is a certificate FOG either publishes already or hands to
 *    anyone who connects, so it belongs with the page rather than behind the
 *    write grant -- otherwise an operator who may look at the chain cannot
 *    save a copy of the root they are looking at.
 */
$dl = FOG\Auth\Authorization::resolvePagePermission('about', 'certificatedownload', false);
$results[] = pkiPermCheck(
    'the certificate download is gated on settings.view',
    'settings.view' === $dl,
    'got ' . var_export($dl, true)
);

/*
 * 3. The permission has to be grantable, or the only holder is '*' forever and
 *    assertCanGrant() refuses to delegate it.
 */
$registry = FOG\Auth\Authorization::coreRegistry();
$results[] = pkiPermCheck(
    "the registry declares a 'system' node",
    array_key_exists('system', $registry),
    'nodes: ' . implode(',', array_keys($registry))
);
$results[] = pkiPermCheck(
    "'system' offers the pki action",
    in_array('pki', (array) ($registry['system'] ?? []), true),
    'actions: ' . implode(',', (array) ($registry['system'] ?? []))
);

/*
 * 4. Deny by default. Nothing may seed system.pki into a role, so an upgrade
 *    hands it to nobody and an install that never wants it gets that by doing
 *    nothing. Matched against the schema source because seeding is what a
 *    schema step would do.
 */
$schema = (string) file_get_contents($webroot . '/commons/schema.php');
$results[] = pkiPermCheck(
    'no schema step seeds system.pki into a role',
    false === strpos($schema, 'system.pki'),
    'commons/schema.php mentions system.pki'
);

/*
 * 5. The validation lives on the far side of sudo, and has to.
 *
 * PHP is the side that might be compromised, so a key allowlist or a value
 * pattern written HERE decides nothing -- an attacker running code in the web
 * tier simply calls the helper directly, which is exactly what the sudoers
 * rule permits. Both therefore live in packages/pki/fog-pki-admin.
 *
 * Asserted as their PRESENCE in the helper rather than their absence from the
 * page: a duplicate check in PHP is harmless defense in depth, while a missing
 * one in the helper is the whole boundary gone.
 */
$helper = (string) file_get_contents($root . '/packages/pki/fog-pki-admin');
$results[] = pkiPermCheck(
    'the helper constrains the three switches to yes or no',
    1 === preg_match("#\\*\\)\\s+printf '%s' '\\^\\(yes\\|no\\)\\$'#", $helper),
    'the ^(yes|no)$ pattern is not the default in prefPattern()'
);
/*
 * The netboot transport's domain is http|https, not yes|no, so the value check
 * became per-key. That is not a relaxation -- every key still names a fixed
 * set, which is what ADR 0036's rule requires -- but it has to be pinned in
 * both directions, because a per-key lookup is exactly the shape that could
 * quietly acquire a permissive default.
 */
$results[] = pkiPermCheck(
    'the netboot transport is constrained to http or https',
    1 === preg_match(
        "#BOOT_url_proto\\)\\s+printf '%s' '\\^\\(http\\|https\\)\\$'#",
        $helper
    ),
    'the ^(http|https)$ pattern is not bound to BOOT_url_proto'
);
$results[] = pkiPermCheck(
    'http is reachable through no key but BOOT_url_proto',
    1 === preg_match_all("#'\\^\\(http\\|https\\)\\\$'#", $helper),
    'the http|https domain appears more than once in fog-pki-admin'
);
$results[] = pkiPermCheck(
    'the value is matched against a pattern the helper chose, not the caller',
    1 === preg_match('#pattern=\$\(prefPattern "\$key"\)#', $helper)
        && 1 === preg_match('#\[\[ \$value =~ \$pattern \]\]#', $helper),
    'set-preference does not validate against prefPattern()'
);
$results[] = pkiPermCheck(
    'the helper carries a key allowlist, and it is the four preferences',
    1 === preg_match(
        '#PREF_KEYS="PKI_web_cert_publicly_trusted WEB_https_redirect '
        . 'BOOT_rebuild_ipxe_with_my_ca BOOT_url_proto"#',
        $helper
    ),
    'PREF_KEYS is missing or has gained an entry'
);
/*
 * BOOT_url_proto_forced stays out. Setting BOOT_url_proto already forces the
 * transport -- _resolveInstallMode() sets the flag whenever an explicit value
 * is supplied -- so a separate settable flag would be a second way to say the
 * same thing, and the one that carries no steering keys with it.
 */
$results[] = pkiPermCheck(
    'the allowlist does not carry BOOT_url_proto_forced',
    1 !== preg_match('#PREF_KEYS="[^"]*BOOT_url_proto_forced#', $helper),
    'PREF_KEYS reaches BOOT_url_proto_forced'
);
/*
 * Neither secret in .fogsettings may be reachable, whatever else changes.
 * SVC_password is the FTP account image replication logs in with fleet-wide.
 */
foreach (['SVC_password', 'DB_password', 'FOG_program_dir'] as $forbidden) {
    $results[] = pkiPermCheck(
        "the allowlist does not carry $forbidden",
        1 !== preg_match('#PREF_KEYS="[^"]*' . preg_quote($forbidden, '#') . '#', $helper),
        'PREF_KEYS reaches ' . $forbidden
    );
}
/*
 * The helper must never accept a path. Every path it uses comes from a
 * root-only config written at install time, which is what stops a compromised
 * web server naming its own CA key or its own .fogsettings.
 */
$results[] = pkiPermCheck(
    'the helper reads its paths from a root-only config, not from arguments',
    1 === preg_match('#^CONF="/opt/fog/\.fog-pki-admin"$#m', $helper),
    'the CONF assignment is missing or is no longer a literal'
);

/*
 * 6. The page hands the helper escaped arguments. exec() gives its string to a
 *    shell, and FOG_BASE_DIR is installer-written and may contain a space --
 *    which would split into two arguments and stop matching the sudoers rule.
 */
$page = (string) file_get_contents($webroot . '/src/Pages/FOGConfigurationPage.php');
$results[] = pkiPermCheck(
    'every argument passed to the helper goes through escapeshellarg',
    1 === preg_match(
        "#\\\$cmd = 'sudo -n ' \\. escapeshellarg\\(\\\$helper\\);.*?"
        . "foreach \\(\\\$args as \\\$arg\\) \\{.*?"
        . "escapeshellarg\\(\\(string\\) \\\$arg\\)#s",
        $page
    ),
    'the sudo invocation no longer escapes both the helper and its arguments'
);
/*
 * The download streams PEM, whose line structure the output buffer would
 * destroy: Initiator::sanitizeOutput collapses whitespace, and a PEM body run
 * together on one line is a file openssl cannot read. The buffer has to be
 * discarded before the bytes go out.
 */
$results[] = pkiPermCheck(
    'the certificate download discards the output buffer before sending PEM',
    1 === preg_match(
        '#while \(ob_get_level\(\) > 0\) \{\s*ob_end_clean\(\);\s*\}\s*echo \$pem;#',
        $page
    ),
    'the buffer is not discarded, so the PEM would be whitespace-collapsed'
);

$failed = count(array_filter($results, static function ($r) {
    return !$r;
}));
printf(
    "\n%d check(s), %d failed\n",
    count($results),
    $failed
);
exit($failed > 0 ? 1 : 0);
