<?php
/**
 * Seeds the per-node menu msgids added by GH-435 from what each catalog
 * already renders, so no language regresses to English.
 *
 * PHP version 7.4+
 *
 * WHY THIS EXISTS
 *
 * FOGPage::_buildSubMenuItems() used to build every "List All X" / "Create
 * New X" label by sprintf()ing a translated noun into a translated format
 * string. GH-435 replaced that with whole phrases, because a single format
 * string cannot inflect for gender or pluralize correctly in any language
 * that does either.
 *
 * The catch is that whole phrases are NEW msgids. Nine catalogs carry
 * translations for the old format strings and the nouns, and none of them
 * carries one for "List All Storage Nodes". Left alone, every non-English
 * menu entry that works today would fall back to English -- a visible
 * regression shipped in order to fix one.
 *
 * So this composes each new msgstr the way the RUNTIME composed it, out of
 * that catalog's own strings:
 *
 *     msgstr("List All Users") := sprintf(msgstr("List All %s"), msgstr("User") . 's')
 *     msgstr("Create New User") := sprintf(msgstr("Create New %s"), msgstr("User"))
 *
 * Every language therefore renders exactly what it rendered before the code
 * change, and the entries are now individually correctable -- which is the
 * whole point, and what French and Spanish then get by hand.
 *
 * Deliberately conservative. An entry is skipped, leaving the msgid
 * untranslated, when:
 *
 *   - the catalog has no translation for the format string or for the noun.
 *     Composing from an English fallback would write English INTO the
 *     catalog, which msgmerge could never later distinguish from a real
 *     translation;
 *   - the format string lost its %s (es_ES ships `Crear nuevo grupo`).
 *     Seeding that would stamp "group" onto all eleven entities permanently;
 *   - the msgid already has a non-empty msgstr. This script never overwrites
 *     a human translation, so it is safe to re-run.
 *
 * The `s` appended for the plural is the old behavior being preserved, not
 * endorsed -- it is one of the two bugs GH-435 exists to end. It is
 * reproduced here only so the seeded value equals today's rendering; the
 * point of seeding is that a translator can now fix each one.
 *
 * Usage, from the repository root:
 *
 *     php bin/migrate-menu-translations.php            # report only
 *     php bin/migrate-menu-translations.php --write    # edit the .po files
 *
 * @category Tools
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

$write = in_array('--write', array_slice($argv, 1), true);
$root = dirname(__DIR__);
$langDir = $root . '/packages/web/management/languages';

// node => [singular noun msgid, list msgid, add msgid]. The nouns are the
// msgids _buildSubMenuItems() fed to sprintf -- ucfirst() of the node name --
// so composing with them reproduces the old label exactly.
$nodes = [
    'group'        => ['Group',        'List All Groups',         'Create New Group'],
    'host'         => ['Host',         'List All Hosts',          'Create New Host'],
    'image'        => ['Image',        'List All Images',         'Create New Image'],
    'ipxe'         => ['Ipxe',         'List All Ipxe Menus',     'Create New Ipxe Menu'],
    'module'       => ['Module',       'List All Modules',        'Create New Module'],
    'printer'      => ['Printer',      'List All Printers',       'Create New Printer'],
    'role'         => ['Role',         'List All Roles',          'Create New Role'],
    'site'         => ['Site',         'List All Sites',          'Create New Site'],
    'snapin'       => ['Snapin',       'List All Snapins',        'Create New Snapin'],
    'storagegroup' => ['Storagegroup', 'List All Storage Groups', 'Create New Storage Group'],
    'storagenode'  => ['Storagenode',  'List All Storage Nodes',  'Create New Storage Node'],
    'user'         => ['User',         'List All Users',          'Create New User'],
    'usergroup'    => ['Usergroup',    'List All User Groups',    'Create New User Group'],
];

/**
 * Every msgid => msgstr in a .po, single- and multi-line forms both.
 *
 * Not a general gettext parser and does not need to be: it reads the two
 * shapes msgcat emits and ignores obsolete (#~) entries, which is the whole
 * of what these files contain.
 *
 * @param string $path .po file
 *
 * @return array
 */
function poRead($path)
{
    $out = [];
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if (false === $lines) {
        return $out;
    }
    $id = '';
    $buf = '';
    $in = '';
    // Written as a flat loop rather than with a flush closure. A closure
    // capturing $id/$in by reference is correct at runtime but opaque to
    // static analysis -- phpstan types the captured variables from their
    // INITIALIZERS, so every comparison inside reads as always-false and the
    // build fails on five findings that are not bugs. Inlining the two-line
    // flush costs a repetition and keeps the file analyzable.
    foreach ($lines as $line) {
        $t = trim($line);
        if ('' === $t || '#' === substr($t, 0, 1)) {
            // Comments and obsolete (#~) entries alike: an obsolete entry is
            // not compiled and is not shown to anyone, so it is not a value
            // this script may read a translation out of.
            continue;
        }
        $start = '';
        if (0 === strpos($t, 'msgid ')) {
            $start = 'msgid';
            $frag = substr($t, 6);
        } elseif (0 === strpos($t, 'msgstr ')) {
            $start = 'msgstr';
            $frag = substr($t, 7);
        }
        if ('' !== $start) {
            if ('msgstr' === $in && '' !== $id) {
                $out[$id] = $buf;
            } elseif ('msgid' === $in) {
                $id = $buf;
            }
            $in = $start;
            $buf = poUnquote((string)$frag);
            continue;
        }
        if ('"' === substr($t, 0, 1) && '' !== $in) {
            $buf .= poUnquote($t);
        }
    }
    if ('msgstr' === $in && '' !== $id) {
        $out[$id] = $buf;
    }
    return $out;
}


/**
 * The msgids in a .po whose entry is flagged `#, fuzzy`.
 *
 * A fuzzy entry is msgmerge's GUESS, carried over from a similar msgid and
 * never confirmed by a person. msgfmt excludes fuzzy entries from the compiled
 * .mo, so nothing in one has ever been shown to a user -- which is exactly why
 * they are so wrong here. At HEAD every single `Create New X` entry in every
 * catalog is fuzzy, and de_DE's guesses include "Neuen Drucker erstellen"
 * (Create New PRINTER) under msgid `Create New Storage Node` and "Neuen
 * Schlüssel erstellen" (Create New KEY) under `Create New Group`.
 *
 * That matters because this change makes those msgids live for the first time:
 * the labels used to be composed at runtime and never looked these up. Treating
 * a fuzzy msgstr as a real translation would therefore not preserve anything --
 * it would PROMOTE nine catalogs' worth of unreviewed guesses into text users
 * finally see. So the seeder treats fuzzy as untranslated and composes over it.
 *
 * @param string $path .po file
 *
 * @return array msgid => true
 */
function poFuzzy($path)
{
    $out = [];
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if (false === $lines) {
        return $out;
    }
    $fuzzy = false;
    foreach ($lines as $line) {
        $t = trim($line);
        if (0 === strpos($t, '#,')) {
            $fuzzy = (false !== strpos($t, 'fuzzy'));
            continue;
        }
        if (preg_match('/^msgid "(.*)"$/', $t, $m)) {
            if ($fuzzy) {
                $out[poUnquote('"' . $m[1] . '"')] = true;
            }
            $fuzzy = false;
            continue;
        }
        if ('' === $t) {
            $fuzzy = false;
        }
    }
    return $out;
}

/**
 * The value of one quoted .po string fragment.
 *
 * @param string $s fragment including its surrounding quotes
 *
 * @return string
 */
function poUnquote($s)
{
    $s = trim($s);
    if ('"' !== substr($s, 0, 1)) {
        return '';
    }
    $s = substr($s, 1, -1);
    return str_replace(
        ['\\\\n', '\\\\t', '\\\\"', '\\\\\\\\'],
        ["\n", "\t", '"', '\\\\'],
        $s
    );
}

/**
 * A .po-safe quoted literal.
 *
 * @param string $s raw value
 *
 * @return string
 */
function poQuote($s)
{
    return '"' . str_replace(
        ['\\\\', '"', "\n", "\t"],
        ['\\\\\\\\', '\\\\"', '\\\\n', '\\\\t'],
        $s
    ) . '"';
}

/**
 * sprintf that reports failure instead of throwing or warning.
 *
 * PHP 8 throws on a bad specifier, 7.4 warns and returns false. Both mean
 * "this catalog's format string is unusable", and both must skip.
 *
 * @param string $fmt format string
 * @param string $val substitution
 *
 * @return string|null null when the format is unusable
 */
function safeFormat($fmt, $val)
{
    if (false === strpos($fmt, '%s')) {
        return null;
    }
    try {
        $r = @sprintf($fmt, $val);
    } catch (\Throwable $e) {
        return null;
    }
    return '' === (string)$r ? null : (string)$r;
}


/**
 * Sets each msgid's msgstr in a .po, in place.
 *
 * Appending instead of rewriting is what the first cut of this script did,
 * and msgfmt rejected all nine catalogs with `duplicate message definition`.
 * Two reasons a msgid that "needs seeding" is already in the file:
 *
 *   - an entry with an EMPTY msgstr is still an entry;
 *   - a msgid that fell out of the sources is kept as an OBSOLETE entry,
 *     commented with #~. That is exactly what gettext keeps them for -- so a
 *     returning string can be revived rather than retyped -- and these
 *     strings are returning. fr_FR carries `#~ msgid "List All Roles"`.
 *
 * So: rewrite a live entry where one exists, revive an obsolete one where it
 * does not, and only append when the msgid is genuinely absent. A `#, fuzzy`
 * marker immediately above is dropped in both cases -- the value being
 * written is derived deliberately, and leaving it flagged fuzzy invites
 * msgmerge to discard it again.
 *
 * @param string $path .po file
 * @param array  $set  msgid => msgstr
 *
 * @return void
 */
function poWrite($path, array $set)
{
    $lines = explode("\n", file_get_contents($path));
    $out = [];
    $seen = [];
    $n = count($lines);
    for ($i = 0; $i < $n; $i++) {
        $line = $lines[$i];
        // Order matters: preg_match EMPTIES $m when it fails, so testing for
        // an obsolete entry after a live one has already matched would throw
        // the live capture away and send every live msgid down the append
        // path instead -- which is how the first cut of this produced nine
        // catalogs full of duplicate definitions.
        $live = preg_match('/^msgid "(.*)"$/', $line, $m);
        $dead = $live ? 0 : preg_match('/^#~[ \t]*msgid "(.*)"$/', $line, $m);
        if ((!$live && !$dead) || !array_key_exists($m[1], $set)) {
            $out[] = $line;
            continue;
        }
        $prefix = $dead ? '/^#~[ \t]*/' : null;
        $j = $i + 1;
        // Skip to the end of this entry's msgstr, continuation lines included.
        while ($j < $n
            && !preg_match($dead ? '/^#~[ \t]*msgstr/' : '/^msgstr/', $lines[$j])
        ) {
            $j++;
        }
        if ($j < $n) {
            $j++;
            while ($j < $n
                && preg_match(
                    $dead ? '/^#~[ \t]*"/' : '/^"/',
                    $lines[$j]
                )
            ) {
                $j++;
            }
        }
        while (count($out)
            && 0 === strpos(end($out), '#,')
            && false !== strpos(end($out), 'fuzzy')
        ) {
            array_pop($out);
        }
        $out[] = 'msgid ' . poQuote($m[1]);
        $out[] = 'msgstr ' . poQuote($set[$m[1]]);
        $seen[$m[1]] = true;
        $i = $j - 1;
        unset($prefix);
    }
    $text = rtrim(implode("\n", $out), "\n") . "\n";
    $missing = array_diff_key($set, $seen);
    if (count($missing)) {
        $text .= "\n#. GH-435: seeded from this catalog's own \"List All %s\" /\n"
            . "#. \"Create New %s\" and noun, so the label renders exactly as it\n"
            . "#. did before those format strings became whole phrases.\n";
        ksort($missing);
        foreach ($missing as $msgid => $msgstr) {
            $text .= "\nmsgid " . poQuote($msgid) . "\nmsgstr " . poQuote($msgstr) . "\n";
        }
    }
    file_put_contents($path, $text);
}


// Correct French for every per-node label. Written out rather than derived:
// agreement is the entire point of GH-435, and no rule generates it from the
// English. `machine` and `image` are feminine (toutes les / une nouvelle),
// `utilisateur` is masculine but vowel-initial (nouvel, not nouveau) -- the
// three cases the reporter cited, in that order.
$hand = [];
$hand['fr_FR'] = [
    'List All Groups'          => 'Lister tous les groupes',
    'Create New Group'         => 'Créer un nouveau groupe',
    'List All Hosts'           => 'Lister toutes les machines',
    'Create New Host'          => 'Créer une nouvelle machine',
    'List All Images'          => 'Lister toutes les images',
    'Create New Image'         => 'Créer une nouvelle image',
    'List All Ipxe Menus'      => 'Lister tous les menus iPXE',
    'Create New Ipxe Menu'     => 'Créer un nouveau menu iPXE',
    'List All Modules'         => 'Lister tous les modules',
    'Create New Module'        => 'Créer un nouveau module',
    'List All Printers'        => 'Lister toutes les imprimantes',
    'Create New Printer'       => 'Créer une nouvelle imprimante',
    'List All Roles'           => 'Lister tous les rôles',
    'Create New Role'          => 'Créer un nouveau rôle',
    'List All Sites'           => 'Lister tous les sites',
    'Create New Site'          => 'Créer un nouveau site',
    'List All Snapins'         => 'Lister tous les snapins',
    'Create New Snapin'        => 'Créer un nouveau snapin',
    'List All Storage Groups'  => 'Lister tous les groupes de stockage',
    'Create New Storage Group' => 'Créer un nouveau groupe de stockage',
    'List All Storage Nodes'   => 'Lister tous les nœuds de stockage',
    'Create New Storage Node'  => 'Créer un nouveau nœud de stockage',
    'List All Users'           => 'Lister tous les utilisateurs',
    'Create New User'          => 'Créer un nouvel utilisateur',
    'List All User Groups'     => "Lister tous les groupes d'utilisateurs",
    'Create New User Group'    => "Créer un nouveau groupe d'utilisateurs",
    // Not menu labels, but the same catalog damage and the same one-line fix.
    // `Site` read "minutes" and `Role` read "Nom du module" -- which is how
    // "Lister tous les minutess" was being generated at runtime while this
    // was still composed. Corrected here because the composed form is what
    // made them invisible; they are wrong wherever else they are used too.
    'Site'                     => 'Site',
    'Role'                     => 'Rôle',
    'Module'                   => 'Module',
];

// Correct Spanish, for the same reason and with the same damage: every single
// "Create New X" in this catalog reads "Crear nuevo grupo" -- Create New
// *Group* -- because a fuzzy match against `Create New %s` was accepted
// wholesale. A Spanish user on the host page is told they are creating a
// group. `imagen` and `impresora` are feminine (todas las / nueva); the rest
// are masculine.
$hand['es_ES'] = [
    'List All Groups'          => 'Listar todos los grupos',
    'Create New Group'         => 'Crear nuevo grupo',
    'List All Hosts'           => 'Listar todos los anfitriones',
    'Create New Host'          => 'Crear nuevo anfitrión',
    'List All Images'          => 'Listar todas las imágenes',
    'Create New Image'         => 'Crear nueva imagen',
    'List All Ipxe Menus'      => 'Listar todos los menús iPXE',
    'Create New Ipxe Menu'     => 'Crear nuevo menú iPXE',
    'List All Modules'         => 'Listar todos los módulos',
    'Create New Module'        => 'Crear nuevo módulo',
    'List All Printers'        => 'Listar todas las impresoras',
    'Create New Printer'       => 'Crear nueva impresora',
    'List All Roles'           => 'Listar todos los roles',
    'Create New Role'          => 'Crear nuevo rol',
    'List All Sites'           => 'Listar todos los sitios',
    'Create New Site'          => 'Crear nuevo sitio',
    'List All Snapins'         => 'Listar todos los snapins',
    'Create New Snapin'        => 'Crear nuevo snapin',
    'List All Storage Groups'  => 'Listar todos los grupos de almacenamiento',
    'Create New Storage Group' => 'Crear nuevo grupo de almacenamiento',
    'List All Storage Nodes'   => 'Listar todos los nodos de almacenamiento',
    'Create New Storage Node'  => 'Crear nuevo nodo de almacenamiento',
    'List All Users'           => 'Listar todos los usuarios',
    'Create New User'          => 'Crear nuevo usuario',
    'List All User Groups'     => 'Listar todos los grupos de usuarios',
    'Create New User Group'    => 'Crear nuevo grupo de usuarios',
    // Same class of damage in the bare nouns, which the composed labels were
    // reading from: `Site` said "minutos", `Role` and `Module` both said
    // "Nombre del módulo", `iPXE Menu` held a whole sentence about gpxe.
    'Site'                     => 'Sitio',
    'Role'                     => 'Rol',
    'Module'                   => 'Módulo',
    'Printer'                  => 'Impresora',
    'iPXE Menu'                => 'Menú iPXE',
    'User Group'               => 'Grupo de usuarios',
];


$locales = glob($langDir . '/*.UTF-8/LC_MESSAGES/messages.po');
sort($locales);
$grandAdded = 0;
$grandSkipped = 0;

foreach ($locales as $po) {
    preg_match('#/([^/]+)\.UTF-8/#', $po, $lm);
    $locale = $lm[1] ?? $po;
    // The msgid IS the English, so seeding en_US would add nothing and would
    // make every entry look translated. It still gets the stray-%s sweep
    // below: an en_US msgstr reading "Create New %s" under msgid "Create New
    // Group" shows that %s to an English user, and blanking it falls back to
    // the msgid, which is already the right words.
    $seed = ('en_US' !== $locale);
    $tr = poRead($po);
    $fz = poFuzzy($po);
    $listFmt = $tr['List All %s'] ?? '';
    $addFmt = $tr['Create New %s'] ?? '';

    $added = [];
    $skipped = [];
    foreach ($seed ? $nodes : [] as $node => $spec) {
        list($noun, $listId, $addId) = $spec;
        // Where the catalog never translated the noun, compose with the
        // English one rather than skipping. Skipping drops the whole label to
        // English; composing keeps the locale's own verb, which is what these
        // catalogs actually rendered before. It is also strictly better than
        // before: the old code passed _(ucfirst($node)), so an untranslated
        // German storage group read "Alle Storagegroups auflisten" -- a word
        // that exists in no language. The properly spaced English noun is
        // carried by the msgid itself, so it needs no second table.
        $nounTr = $tr[$noun] ?? '';
        $listArg = '' !== $nounTr
            ? $nounTr . 's'
            : substr($listId, strlen('List All '));
        $addArg = '' !== $nounTr
            ? $nounTr
            : substr($addId, strlen('Create New '));
        foreach ([[$listFmt, $listId, $listArg], [$addFmt, $addId, $addArg]] as $job) {
            list($fmt, $msgid, $arg) = $job;
            // Never overwrite a real translation -- but a msgstr carrying a
            // literal %s is not one. Those came from a fuzzy match against
            // `Create New %s` that somebody accepted, so the catalog claims
            // `Create New Host` is translated and renders "Neue %s erstellen"
            // on screen. Composing over it is strictly better, and it is what
            // keeps this migration lossless: blanking such an entry instead
            // would drop that locale to English for a label it can express.
            $cur = (string)($tr[$msgid] ?? '');
            if (isset($fz[$msgid]) || false !== strpos($cur, '%s')) {
                $cur = '';
            }
            if ('' !== $cur) {
                continue;
            }
            if ('' === $fmt) {
                $skipped[] = $msgid . ' (no format string in this catalog)';
                continue;
            }
            $val = safeFormat($fmt, $arg);
            if (null === $val) {
                $skipped[] = $msgid . ' (format has no usable %s)';
                continue;
            }
            $added[$msgid] = $val;
        }
    }

    printf(
        "%-8s %2d seeded, %2d skipped\n",
        $locale,
        count($added),
        count($skipped)
    );
    foreach ($skipped as $s) {
        printf("           skip %s\n", $s);
    }
    $grandAdded += count($added);
    $grandSkipped += count($skipped);

    if (!$write) {
        continue;
    }

    // A msgstr that still carries a literal %s is SHOWING that %s to users:
    // it came from a fuzzy match against `Create New %s` that somebody
    // accepted, and these msgids already existed because the codebase writes
    // _('Create New User') and friends as page titles. Blanking makes gettext
    // fall back to the English msgid, which is the wrong language but a real
    // phrase; writing German or Chinese here would be guessing.
    $blank = [];
    foreach ($nodes as $spec) {
        foreach ([$spec[1], $spec[2]] as $msgid) {
            if (isset($added[$msgid])) {
                continue;
            }
            if (false !== strpos((string)($tr[$msgid] ?? ''), '%s')) {
                $blank[$msgid] = '';
            }
        }
    }
    if (count($blank)) {
        printf("%-8s %2d msgstr(s) carrying a literal %%s blanked\n", $locale, count($blank));
        $added += $blank;
    }

    if (isset($hand[$locale])) {
        // Overwrites the seeds and the blanks above on purpose: seeding
        // reproduces what the catalog rendered before, and for these two
        // locales what it rendered before is precisely the bug.
        $added = $hand[$locale] + $added;
        printf(
            "%-8s %2d hand-written entries applied\n",
            $locale,
            count($hand[$locale])
        );
    }

    if (count($added)) {
        poWrite($po, $added);
    }
}

printf("\n%d entries seeded, %d skipped\n", $grandAdded, $grandSkipped);
