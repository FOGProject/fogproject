# PXE Unattended Secure Boot Enrollment Menu Item Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a second PXE boot menu item that exposes task type 25's unattended Secure Boot enrollment (`mode=enrollsb`) directly, gated on its `.auth` prerequisites, alongside a relabeled attended item.

**Architecture:** Two independent, additive changes: (1) a schema migration adding pxeID 15 and renaming pxeID 14's description, and (2) two new filter passes in `BootMenu::printDefault()` that hide pxeID 15 on non-EFI platforms and when `PK.auth`/`KEK.auth`/`db.auth` aren't all present. No new classes, no changes to task type 25 or FOS.

**Tech Stack:** PHP 7.4+ (no `declare(strict_types=1)` — neither touched file has it), MySQL (via the schema-step array convention in `schema.php`), plain-PHP static-parse test scripts (this repo has no PHPUnit; see `tests/route-filter-fields.test.php` for the established pattern).

## Global Constraints

- Do not add `declare(strict_types=1)` — `schema.php` and `bootmenu.class.php` don't have it.
- Do not edit existing schema steps (321, 322, 323) in place — each schema step is immutable once shipped; changes are new steps that `UPDATE`/redefine.
- Follow the existing `pxeMenu` INSERT convention exactly: column order `(pxeID, pxeName, pxeDesc, pxeDefault, pxeRegOnly, pxeArgs)`.
- Test files are standalone PHP scripts runnable via `php tests/<name>.test.php [optional path]`, exit 0 = pass / 1 = fail, no framework, no DB. Follow `tests/route-filter-fields.test.php`'s shape.
- The pre-commit hook (`.githooks/pre-commit`) will auto-run `php-cs-fixer` (PSR-2) and bump `system.class.php`'s version on commit — expected, don't revert it.
- Spec: `docs/superpowers/specs/2026-08-11-pxe-unattended-secureboot-enroll-design.md`.

---

### Task 1: Schema — rename pxeID 14, add pxeID 15

**Files:**
- Modify: `packages/web/commons/schema.php` (append after the last schema step, currently ending line 4974 with step 323's closing `];`)
- Test: `tests/pxe-secureboot-menu-schema.test.php` (new)

**Interfaces:**
- Consumes: nothing from other tasks.
- Produces: DB rows `pxeMenu.pxeID = 14` (renamed) and `pxeMenu.pxeID = 15` (new) that Task 2's `BootMenu::printDefault()` filters reference by literal id.

- [ ] **Step 1: Write the failing test**

Create `tests/pxe-secureboot-menu-schema.test.php`:

```php
<?php
/**
 * Guards the two pxeMenu schema changes for unattended Secure Boot enrol:
 *  - pxeID 14 ("Enroll Secure Boot Key") gets relabeled to distinguish it
 *    from the new unattended item, via a new UPDATE step (321 already ran
 *    on existing installs, so it cannot be edited in place).
 *  - pxeID 15 is inserted for the unattended path: `mode=enrollsb` is task
 *    type 25's kernel arg (schema step 323), exposed directly on the menu.
 *
 * Static source check (no DB) -- schema.php runs inside a loader method
 * context ($this/self::) and cannot be required standalone.
 *
 * Usage: php tests/pxe-secureboot-menu-schema.test.php [path/to/schema.php]
 * Exit status 0 = pass, 1 = fail.
 */

$file = $argv[1] ?? dirname(__DIR__) . '/packages/web/commons/schema.php';

if (!is_readable($file)) {
    fwrite(STDERR, "FAIL: cannot read $file\n");
    exit(1);
}

$src = file_get_contents($file);

// Collapse PHP string concatenations split across lines ("...".\n."...")
// into one contiguous string, the same way PHP itself would at parse time,
// so a literal can be checked for even when its source wraps.
$flat = preg_replace('/"\s*\r?\n\s*\.\s*"/', '', $src);

$failures = [];

if (strpos(
    $flat,
    "UPDATE `pxeMenu` SET `pxeDesc`='Enroll Secure Boot Key "
    . "(MOK attended setup)' WHERE `pxeID`=14"
) === false) {
    $failures[] = "no UPDATE renaming pxeID 14 to "
        . "'Enroll Secure Boot Key (MOK attended setup)'";
}

if (strpos(
    $flat,
    "(15, 'fog.enrollsecurebootunattended', 'Enroll Secure Boot Key "
    . "(Unattended - secure boot in setup mode required)', '0', '2', "
    . "'mode=enrollsb')"
) === false) {
    $failures[] = "no pxeID 15 INSERT for fog.enrollsecurebootunattended "
        . "with the expected desc/default/regOnly/args";
}

if (count($failures) > 0) {
    foreach ($failures as $f) {
        fwrite(STDERR, "FAIL: $f\n");
    }
    exit(1);
}

echo "PASS\n";
exit(0);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/pxe-secureboot-menu-schema.test.php`
Expected: FAIL, both failure lines printed (neither string exists in `schema.php` yet).

- [ ] **Step 3: Add schema steps 324 and 325**

Append to `packages/web/commons/schema.php`, immediately after the existing step 323 (its closing `];` is currently the file's last line):

```php
// 324
$this->schema[] = [
    // Distinguishes pxeID 14 from the unattended item added in step 325
    // below. It has always chained straight to MokManager for a technician
    // to drive by hand; the plain "Enroll Secure Boot Key" name stopped
    // being enough once there is a second, unattended way to enrol from
    // the same menu.
    //
    // A new step, not an edit to step 321: that INSERT has already run on
    // every existing 1.6 beta server, and a server does not re-run a step
    // it has passed.
    "UPDATE `pxeMenu` SET "
    . "`pxeDesc`='Enroll Secure Boot Key (MOK attended setup)' "
    . "WHERE `pxeID`=14",
];
// 325
$this->schema[] = [
    // Exposes task type 25's mode=enrollsb (schema step 323) directly on
    // the PXE menu, so a technician standing at a machine already in Setup
    // Mode does not have to leave the console to schedule a task. Falls
    // through BootMenu::_menuOpt()'s default kernel-chain branch exactly
    // like the existing mode=autoreg/mode=onlydebug/mode=sysinfo items --
    // no special case needed there.
    //
    // pxeID 15 is the next free id (8 and 13 were removed by name in
    // earlier steps but their ids are not reused; 1-14 are otherwise
    // taken). pxeRegOnly=2: same "always shown" grouping as item 14, since
    // a machine needing its Secure Boot key enrolled has usually never
    // registered yet.
    //
    // BootMenu::printDefault() additionally hides this item unless
    // PK.auth/KEK.auth/db.auth all exist in service/secureboot/ -- without
    // them mode=enrollsb's auto-enrol path has nothing valid to write.
    "INSERT IGNORE INTO `pxeMenu` "
    . "(`pxeID`,`pxeName`,`pxeDesc`,`pxeDefault`,`pxeRegOnly`,`pxeArgs`) "
    . "VALUES "
    . "(15, 'fog.enrollsecurebootunattended', 'Enroll Secure Boot Key "
    . "(Unattended - secure boot in setup mode required)', '0', '2', "
    . "'mode=enrollsb')",
];
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/pxe-secureboot-menu-schema.test.php`
Expected: `PASS`

- [ ] **Step 5: Commit**

```bash
git add packages/web/commons/schema.php tests/pxe-secureboot-menu-schema.test.php
git commit -m "Add pxeMenu schema step for unattended Secure Boot enrol, relabel attended item"
```

(The pre-commit hook will also touch `languages/messages.pot`/`.po` files and `system.class.php`'s version — expected.)

---

### Task 2: BootMenu — gate pxeID 15's visibility

**Files:**
- Modify: `packages/web/lib/fog/bootmenu.class.php:2202-2235` (the existing non-EFI filter inside `printDefault()`)
- Test: `tests/pxe-secureboot-menu-gating.test.php` (new)

**Interfaces:**
- Consumes: pxeID literals `14` and `15` from Task 1's schema rows (referenced here only as integer literals, no code dependency).
- Produces: nothing consumed by later tasks — this is the last task.

- [ ] **Step 1: Write the failing test**

Create `tests/pxe-secureboot-menu-gating.test.php`:

```php
<?php
/**
 * Guards BootMenu::printDefault()'s PXE-menu gating for pxeID 15 ("Enroll
 * Secure Boot Key (Unattended...)", mode=enrollsb): it must stay hidden on
 * non-EFI platforms exactly like pxeID 14, and must additionally stay
 * hidden unless PK.auth/KEK.auth/db.auth all exist in service/secureboot/
 * -- without them mode=enrollsb's auto-enrol path has nothing valid to
 * write.
 *
 * Static source check (no DB, no server) -- BootMenu needs the full FOG
 * bootstrap to run, so this parses printDefault() instead.
 *
 * Usage: php tests/pxe-secureboot-menu-gating.test.php [path/to/bootmenu.class.php]
 * Exit status 0 = pass, 1 = fail.
 */

$file = $argv[1] ?? dirname(__DIR__) . '/packages/web/lib/fog/bootmenu.class.php';

if (!is_readable($file)) {
    fwrite(STDERR, "FAIL: cannot read $file\n");
    exit(1);
}

$src = file_get_contents($file);

$failures = [];

// The non-EFI platform filter must hide pxeID 15 alongside pxeID 14.
if (!preg_match(
    "/\\\$_REQUEST\['platform'\]\s*!=\s*'efi'.{0,400}?"
    . "in_array\(\(int\)\\\$Menu->id,\s*\[14,\s*15\],\s*true\)/s",
    $src
)) {
    $failures[] = "platform != efi filter does not hide pxeID 15 "
        . "alongside pxeID 14";
}

// A separate filter must hide pxeID 15 unless all three .auth files exist.
if (!preg_match(
    "/'PK\.auth'.{0,200}?'KEK\.auth'.{0,200}?'db\.auth'.{0,400}?"
    . "return\s*\(int\)\\\$Menu->id\s*!==\s*15/s",
    $src
)) {
    $failures[] = "no filter hiding pxeID 15 unless PK.auth/KEK.auth/"
        . "db.auth all exist";
}

if (count($failures) > 0) {
    foreach ($failures as $f) {
        fwrite(STDERR, "FAIL: $f\n");
    }
    exit(1);
}

echo "PASS\n";
exit(0);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/pxe-secureboot-menu-gating.test.php`
Expected: FAIL, both failure lines printed (current source only filters on pxeID 14, and has no `.auth` gating at all).

- [ ] **Step 3: Replace the filter block in `printDefault()`**

In `packages/web/lib/fog/bootmenu.class.php`, replace the existing comment + filter block (currently lines 2202-2235 — the block starting `// pxeID 14 ("Enroll Secure Boot Key") is meaningless on a legacy BIOS` and ending at the `}` that closes the `if (isset($_REQUEST['platform'])...)` filter, immediately before `array_map(`) with:

```php
        // pxeID 14 ("Enroll Secure Boot Key (MOK attended setup)") and
        // pxeID 15 ("Enroll Secure Boot Key (Unattended...)") are both
        // meaningless on a legacy BIOS boot: there is no UEFI variable
        // store to enrol into, so every route out of them -- MokManager,
        // and the FOS task behind mode=enrollsb -- can only fail. Both
        // carry pxeRegOnly=2 so a technician never has to repoint a
        // client's boot file to reach them, and that "always shown" is
        // what puts them in front of BIOS clients too.
        //
        // Gate on platform, not arch: ia32 UEFI is still UEFI (it gets a
        // different refusal, with its own reason), and a 64-bit CPU booted
        // in CSM mode is not UEFI at all -- so arch answers a different
        // question than the one being asked here.
        //
        // Only hide when the platform is positively known not to be EFI.
        // default.ipxe and every menu emission below post
        // "param platform ${platform}", so it is reliably present; but an
        // absent value means unknown, and hiding a working option from a
        // UEFI client is a worse failure than showing a dead one to a BIOS
        // client.
        //
        // FOS keeps its own check (fog.enrollsb refuses BIOS/CSM
        // explicitly). This is not redundant: a task scheduled server-side
        // cannot know how the host will next boot, and a host that is BIOS
        // today may be UEFI tomorrow. This hides what cannot work; FOS
        // explains what happened.
        if (isset($_REQUEST['platform'])
            && $_REQUEST['platform'] != 'efi'
        ) {
            $Menus->data = array_values(
                array_filter(
                    $Menus->data,
                    function ($Menu) {
                        return !in_array((int)$Menu->id, [14, 15], true);
                    }
                )
            );
        }
        // pxeID 15's unattended enrol (mode=enrollsb) only auto-enrols when
        // PK.auth, KEK.auth and db.auth all exist in service/secureboot/ --
        // fog-build-sb-authvars' output, the same directory MOK.der already
        // lives in. Without all three the task type itself has nothing
        // valid to write and refuses (see schema step 323), so hide the PXE
        // entry point rather than advertise a choice that can only fail.
        // pxeID 14 is unaffected: it only ever needed MOK.der, checked
        // separately inside _enrollSecureBootChoice().
        $authDir = BASEPATH . 'service/secureboot' . DS;
        if (!file_exists($authDir . 'PK.auth')
            || !file_exists($authDir . 'KEK.auth')
            || !file_exists($authDir . 'db.auth')
        ) {
            $Menus->data = array_values(
                array_filter(
                    $Menus->data,
                    function ($Menu) {
                        return (int)$Menu->id !== 15;
                    }
                )
            );
        }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/pxe-secureboot-menu-gating.test.php`
Expected: `PASS`

- [ ] **Step 5: Run Task 1's test too, to confirm nothing regressed**

Run: `php tests/pxe-secureboot-menu-schema.test.php`
Expected: `PASS`

- [ ] **Step 6: Commit**

```bash
git add packages/web/lib/fog/bootmenu.class.php tests/pxe-secureboot-menu-gating.test.php
git commit -m "Hide unattended Secure Boot enrol PXE item until .auth files exist"
```

---

## Manual verification (post-implementation, needs a live FOG server)

Not automatable in this environment (no DB/live iPXE client) — call this out to whoever runs the plan rather than skipping it silently:

1. With no `.auth` files in `service/secureboot/`: PXE menu on a UEFI test client shows only "Enroll Secure Boot Key (MOK attended setup)". No unattended item.
2. Run `fog-build-sb-authvars` (or otherwise populate `PK.auth`/`KEK.auth`/`db.auth`) and reload the menu: both items now appear, correctly labelled.
3. Select the unattended item on a client currently in UEFI Setup Mode: it boots FOS with `mode=enrollsb` in the kernel command line and completes the enrol automatically.
4. On a BIOS/CSM client (`platform != efi`): neither item appears, regardless of `.auth`/`MOK.der` state.
