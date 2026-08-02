# Interaction rules (apply in every conversation)

1. Don't assume. Don't hide confusion. Surface tradeoffs.
2. Minimum code that solves the problem. Nothing speculative.
3. Touch only what you must. Clean up only your own mess.
4. Define success criteria. Loop until verified.

---

# FOG Project — Claude Context (dev-branch / 1.5.x)

> **Branch note:** This is `dev-branch`, the **1.5.x stable/patches line** (currently `1.5.10`).
> It is a *different codebase* from `working-1.6` — file naming, layout, and some conventions
> differ (see below). Do **not** assume 1.6 patterns or file paths apply here. Fixes often need
> to be ported between the two branches by hand, not merged.

## What This Is

FOG Project is an open-source network imaging and endpoint management system. It allows IT administrators to deploy OS images to computers over PXE boot, manage client hosts, schedule tasks, push snapins (software packages), manage printers, track users, and run background replication/multicast services.

- Primary language: **PHP** (the codebase runs on PHP 8; `commons/init.php` uses typed signatures like `Initiator::e(mixed $value): string`)
- Frontend: **jQuery + Bootstrap + AdminLTE** (no build step — plain JS served directly)
- Background services: **PHP CLI daemons**
- ~128 core `*.class.php` files under `lib/fog/`
- Version line: **1.5.10** (the pre-commit hook stamps `dev`/`stable` branches with the `Patches` channel)

---

## How this differs from `working-1.6` (read this first)

| Thing | dev-branch (1.5.x) | working-1.6 |
|------|-----------------------|-------------|
| Page file names | `imagemanagementpage.class.php` (one word, `.class.php`) | `ImageManagement.page.php` |
| Page class names | `ImageManagementPage` (with `Page` suffix) | `ImageManagement` (no suffix) |
| ORM array syntax | older `array(...)` in many files | `[...]` short syntax |
| `declare(strict_types=1)` | **none** in `lib/fog/` | present in newer files |
| Node routing | `$nodes` allowlist array in `management/index.php` | node→class mapping |
| Service daemons | no `FOGFileDeleter` | adds `FOGFileDeleter` |
| `lib/` extra dirs | has `client/`, `reg-task/`, `service/` | reorganized |

When porting a fix from 1.6, expect the target file to have a **different name and class name** here, and the surrounding code to use `array()` and other older idioms.

---

## Feature development order: working-1.6 first, then port here

**Until `working-1.6` is promoted to become the new stable/patches line**,
any *new feature* (as opposed to a bug fix scoped to one branch) should be
developed on `working-1.6` first — branch from it, open a PR against it —
and only afterward ported to `dev-branch` as a **separate** PR. This is the
reverse of what feels natural given `dev-branch` is the actively-patched line
today, but `working-1.6` is where the project's future lives, and landing
new capability there first keeps it from falling further behind.

See the Secure Boot signing work (`fogproject#961`, later ported to
`working-1.6` in `8226cd9`) for what happens when it is done backwards: the
port had to reconcile real divergence after the fact — different page-file
conventions, a page-class shape (`_downloadPost($type)`) that would have
silently signed the initrd instead of just the kernel without an added gate,
hardcoded paths that broke on non-default install locations — rather than
being designed around any of that from the start.

---

## Directory Structure

```
fogproject/                     (dev-branch)
��퀔─ bin/                        # installfog.sh installer
��퀔─ lib/                        # Shell library scripts (per-distro functions)
��퀔─ packages/
��   ├── service/                # PHP CLI background daemons
��   │  ├── FOGTaskScheduler/
��   │  ├── FOGImageReplicator/
��   │  ├── FOGSnapinReplicator/
��   │  ├── FOGMulticastManager/
��   │  ├── FOGPingHosts/
��   │  ├── FOGImageSize/
��   │  ├── FOGSnapinHash/
��   │  └── lib/service_lib.php
��   └── web/                    # The web application
��       ├── api/                # REST API
��       ├── client/              # fog-client related files
��       ├── commons/            # Boot/init files (loaded by ALL entry points)
��       │  ├── base.inc.php    # Security headers, starts output buffering
��       │  ├── init.php        # Initiator class: autoloader, session, sanitization
��       │  ├── schema.php      # DB schema (CREATE TABLE as PHP arrays)
��       │  └── text.php        # $foglang[] translation strings
��       ├── lib/
��       │  ├── fog/             # ~128 core *.class.php files (models, managers, utilities)
��       │  ├── pages/           # *page.class.php UI page classes (e.g. hostmanagementpage.class.php)
��       │  ├── hooks/           # *.hook.php hook classes
��       │  ├── events/          # *.event.php event classes
��       │  ├── reports/         # *.report.php report classes
��       │  ├── reg-task/       # registration/task helpers
��       │  ├── client/         # client-side support
��       │  ├── db/             # PDODB, DatabaseManager
��       │  ├── router/         # API routing
��       │  ├── service/        # service support classes
��       │  └── plugins/         # plugin directories
��       └── management/         # Apache/Nginx document root
��          ├── index.php       # Main UI entry point ($nodes allowlist lives here)
��          ├── js/fog/         # FOG-specific JS
��          ├── css/            # Stylesheets + LESS source
��          └── languages/       # gettext .po/.mo files
└── src/                        # iPXE / binaries source
```

---

## PHP Architecture

### Boot Chain

Every entry point (web UI, API, background services) starts the same way:

```
commons/base.inc.php
 → commons/init.php (defines Initiator, runs autoloader registration)
   → new LoadGlobals() (bootstraps $DB, $HookManager, $EventManager, $currentUser)
```

### Autoloader

No Composer/PSR-4. `Initiator` scans `BASEPATH` with `RecursiveDirectoryIterator` and registers these extensions (`commons/init.php`):

```
spl_autoload_extensions('.class.php,.page.php,.event.php,.hook.php,.report.php');
```

**Class name must match the filename** (the page files are lowercase one-word names, e.g. `imagemanagementpage.class.php` → class `ImageManagementPage`).

### Class Hierarchy

```
FOGBase (abstract)
├── FOGCore              — static utility methods (getSetting, getClass, etc.)
├── FOGController        — single-entity ORM base
��   └── Host, Image, Snapin, StorageNode, etc.
├── FOGManagerController — collection/query base
��   └── HostManager, ImageManager, etc.
├── FOGPage              — UI page base
��   └── HostManagementPage, ImageManagementPage, etc.
├── Page                — HTML shell renderer
├── Hook                — base for all hooks
└── LoadGlobals          — bootstraps globals on construction
```

### ORM Pattern

Every entity model declares (note the older `array()` syntax):
```php
protected $databaseTable = 'hosts';
protected $databaseFields = array(
    'id'   => 'hostID',    // friendly name => DB column name
    'name' => 'hostName',
);
```

Access: `$host->get('name')`, `$host->set('name', 'foo')`, `$host->save()`, `$host->load()`, `$host->destroy()`.
Instantiate with ID to auto-load: `new Host(42)`.

### URL Routing

Driven by `?node=host&sub=list&id=42`. Allowed `node` values are whitelisted in `management/index.php` (the `$nodes` array); `node` maps to the matching `*page.class.php`, and `sub` maps to a method on that class.

### Settings

All app config lives in the `globalSettings` MySQL table:
- Read: `FOGBase::getSetting('FOG_SETTING_NAME')`
- Write: `FOGBase::setSetting('FOG_SETTING_NAME', $value)`

### Hook/Event System

```php
// Register (in hook constructor):
self::$HookManager->register('EVENT_NAME', array($this, 'methodName'));
// Fire:
self::$HookManager->processEvent('EVENT_NAME', array('data' => &$data));
```

---

## Sanitization and Output

- `Initiator::e($value)` — use when echoing user-controlled data into HTML (htmlspecialchars wrapper).
- Input reads should use `filter_input(INPUT_GET/POST, 'key', FILTER_*)` rather than raw superglobals.
- Output runs through an `ob_start` sanitizer.
- CSRF handled via `csrf.class.php` server-side + token injection client-side.

---

## Coding Conventions

- **Private methods**: single underscore prefix (`_init()`, `_verCheck()`)
- **PHPDoc**: present on classes and methods
- **Static globals**: `FOGBase::$HookManager`, `FOGBase::$DB`, etc.
- **Class instantiation**: prefer `FOGBase::getClass('ClassName')` factory
- **Translation**: `_('string')` for inline gettext; `$foglang['Key']` for pre-defined strings from `text.php`
- **Do NOT add `declare(strict_types=1)`** — no file in `lib/fog/` uses it on this branch
- Match the existing `array()` style in the file you are editing; don't mass-convert to `[]`

---

## Pre-commit hook (IMPORTANT — explains "files I didn't touch" in commits)

`core.hooksPath` is `.githooks/`, so `.githooks/pre-commit` runs on **every** `git commit` (and `pre-merge-commit` delegates to it). It auto-modifies and `git add`s files beyond what you staged — this is expected, not a bug. Do **not** revert these. It does three things:

1. **`updateLanguage()`** — regenerates `management/languages/messages.pot` via `xgettext`, sorts with `msgcat`, then `msgmerge`-updates every `.po`. Adds the whole `languages/` dir. Skipped if those tools aren't installed.
2. **`psrfix()`** — runs `php-cs-fixer fix packages/web --rules=@PSR2` and **`git add packages/web`** unconditionally. Two consequences: your code may be auto-reformatted to PSR-2, and **any other dirty file under `packages/web/` gets swept into your commit** regardless of what you staged. Commit files outside `packages/web/` (like this `CLAUDE.md`) separately if you need them isolated.
3. **Version bump** — derives a version from the branch name + commit count and rewrites `FOG_VERSION`/`FOG_CHANNEL` in `packages/web/lib/fog/system.class.php`. On `dev`/`stable` branches the channel is `Patches`. This step also tends to leave a **dangling staged `system.class.php``* bump after the commit; discard it with `git checkout -- packages/web/lib/fog/system.class.php` if you don't want it in the next commit.

---

## What NOT to Do

- Do not add `declare(strict_types=1)`
- Do not assume 1.6 file names/paths — this branch uses `*page.class.php` and `array()` idioms
- Do not use raw `$_GET`/`$_POST` — use `filter_input()`
- Do not echo user data without `Initiator::e()`
- Do not remove hooks, events, or plugin integration points without explicit confirmation
- Do not touch `config.class.php` — it's generated and excluded from git
- Do not commit to `stable` or `master`; this branch (`dev-branch`) is the patches line
