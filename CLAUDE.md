# FOG Project — Claude Context

## What This Is

FOG Project is an open-source network imaging and endpoint management system. It allows IT administrators to deploy OS images to computers over PXE boot, manage client hosts, schedule tasks, push snapins (software packages), manage printers, track users, and run background replication/multicast services.

- Primary language: **PHP** (7.4+, some files use `declare(strict_types=1)`)
- Frontend: **jQuery + Bootstrap + AdminLTE** (no build step — plain JS served directly)
- Background services: **PHP CLI daemons**
- ~435 PHP files, ~200 JS files
- Current working branch: `working-1.6`

---

## Development Workflow

### Sync Scripts

There are two scripts that sync between the **git repo** (`~/fogproject/`) and the **live web server** (`/var/www/fog/` or `/var/www/html/fog-1.6/`).

**`/usr/local/bin/copybacktrunk.sh`** — Git → Web (deploy for live testing)
- rsync's `~/fogproject/packages/web/` → `/var/www/html/fog-{ver}/`
- Copies config, sets symlinks, permissions, and regenerates SSL cert
- Use this after editing in the git repo to deploy and test changes live
- Usage: `copybacktrunk.sh "" "" "1.6"`

**`/usr/local/bin/copytosvn.sh`** — Web → Git (pull live edits back)
- rsync's `/var/www/fog/` → `~/fogproject/packages/web/`
- Use this ONLY after editing files directly on the live web server
- Also updates language `.pot`/`.po` files via `xgettext`/`msgmerge`
- Strips generated/runtime files (`config.class.php`, cache, ssl certs, logs)
- **Do NOT run this after `copybacktrunk.sh`** — it will overwrite git with the web copy

The web root the user edits live is `/var/www/fog/` (symlinked to `/var/www/html/fog-1.6/`).

---

## Directory Structure

```
fogproject/
├── bin/                        # installfog.sh installer
├── lib/                        # Shell library scripts (per-distro functions)
├── packages/
│   ├── service/                # PHP CLI background daemons
│   │   ├── FOGTaskScheduler/
│   │   ├── FOGImageReplicator/
│   │   ├── FOGSnapinReplicator/
│   │   ├── FOGMulticastManager/
│   │   ├── FOGPingHosts/
│   │   ├── FOGImageSize/
│   │   ├── FOGSnapinHash/
│   │   ├── FOGFileDeleter/
│   │   └── lib/service_lib.php
│   └── web/                    # The web application
│       ├── api/index.php       # REST API entry point
│       ├── commons/            # Boot/init files (loaded by ALL entry points)
│       │   ├── base.inc.php    # Security headers, starts output buffering
│       │   ├── init.php        # Initiator class: autoloader, session, sanitization
│       │   ├── schema.php      # DB schema (CREATE TABLE as PHP arrays)
│       │   └── text.php        # $foglang[] translation strings
│       ├── lib/
│       │   ├── fog/            # 121 core *.class.php files (models, managers, utilities)
│       │   ├── pages/          # 20 *.page.php UI page classes
│       │   ├── hooks/          # *.hook.php hook classes
│       │   ├── events/         # *.event.php event classes
│       │   ├── reports/        # *.report.php report classes
│       │   ├── db/             # PDODB, DatabaseManager
│       │   ├── router/         # AltoRouter-based API routing
│       │   └── plugins/        # 15 plugin directories
│       └── management/         # Apache/Nginx document root
│           ├── index.php       # Main UI entry point
│           ├── js/fog/         # FOG-specific JS (fog.js, fog.common.js, entity subdirs)
│           ├── css/            # Stylesheets + LESS source
│           └── languages/      # gettext .po/.mo files
└── src/ipxe/                   # iPXE source files
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

No Composer/PSR-4. `Initiator` uses `RecursiveDirectoryIterator` to scan all of `BASEPATH` for files matching:
`*.class.php`, `*.page.php`, `*.event.php`, `*.hook.php`, `*.report.php`

**Class name must exactly match filename** (case-sensitive). e.g., `HostManagement` → `HostManagement.page.php`.

### Class Hierarchy

```
FOGBase (abstract, ~2900 lines)
├── FOGCore              — static utility methods (getSetting, getClass, etc.)
├── FOGController        — single-entity ORM base
│   └── Host, Image, Snapin, StorageNode, etc.
├── FOGManagerController — collection/query base
│   └── HostManager, ImageManager, etc.
├── FOGPage              — UI page base (~3800 lines)
│   └── HostManagement, ImageManagement, etc.
├── Page                 — HTML shell renderer (title, body, CSS/JS lists)
├── Hook                 — base for all hooks
└── LoadGlobals          — bootstraps globals on construction
```

### ORM Pattern

Every entity model declares:
```php
protected $databaseTable = 'hosts';
protected $databaseFields = [
    'id'   => 'hostID',    // friendly name => DB column name
    'name' => 'hostName',
];
```

Access: `$host->get('name')`, `$host->set('name', 'foo')`, `$host->save()`, `$host->load()`, `$host->destroy()`.
Instantiate with ID to auto-load: `new Host(42)`.

### URL Routing

Everything is driven by `?node=host&sub=list&id=42`:
- `node` → maps to a page class (e.g., `host` → `HostManagement`)
- `sub` → maps to a method on that class (e.g., `list` → `HostManagement::index()`)

### Settings

All app config lives in the `globalSettings` MySQL table:
- Read: `FOGBase::getSetting('FOG_SETTING_NAME')`
- Write: `FOGBase::setSetting('FOG_SETTING_NAME', $value)`
- Keys use `ALL_CAPS_WITH_UNDERSCORES`

### Hook/Event System

```php
// Register (in hook constructor):
self::$HookManager->register('EVENT_NAME', [$this, 'methodName']);

// Fire (in page/base classes):
self::$HookManager->processEvent('EVENT_NAME', ['data' => &$data]);
```

---

## Sanitization and Output

### Input
- `Initiator::sanitizeItems()` at boot strips null bytes and trims all `$_GET`, `$_POST`, `$_COOKIE`, `$_SESSION` values
- Individual reads use `filter_input(INPUT_GET/POST, 'key', FILTER_*)` — not raw superglobals

### Output
- `Initiator::e($value)` — always use this when echoing user-controlled data into HTML
  ```php
  htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false)
  ```
- All output runs through `ob_start(['Initiator', 'sanitizeOutput'])` which collapses whitespace

### CSRF
- `bootstrap-csrf.js` injects tokens into AJAX requests
- `csrf.class.php` handles server-side validation

---

## JavaScript Patterns

- No build step — plain JS files served directly
- All third-party libs are vendored (jQuery, Bootstrap, AdminLTE, DataTables, Select2, etc.)
- FOG-specific JS in `management/js/fog/`:
  - `fog.js` — global vars, shared helpers
  - `fog.common.js` — `$.apiCall()`, `$.notifyFromAPI()`, Common object
  - Per-entity files: `fog.{entity}.{sub}.js` (e.g., `fog.host.edit.js`)
- AJAX hits `?node=...&sub=...` for HTML fragments or JSON, and `/api/` for REST

---

## Coding Conventions

- **Private methods**: single underscore prefix (`_init()`, `_verCheck()`)
- **PHPDoc**: present on classes and methods (`@category`, `@package`, `@author`, `@license`, `@link`)
- **Static globals**: `FOGBase::$HookManager`, `FOGBase::$DB`, etc. — set once, shared everywhere
- **Method chaining**: `Page` and `FOGPage` use fluent interface
- **Class instantiation**: use `FOGBase::getClass('ClassName')` factory, not `new ClassName()` directly
- **Translation**: `_('string')` for inline gettext; `$foglang['Key']` for pre-defined strings from `text.php`
- **Newer files**: `declare(strict_types=1)` at top; older files do not have this — don't add it retroactively
- **Avoid `new` directly**: use `FOGBase::getClass()` or `FOGBase::getManager()` as appropriate

---

## Key Files

| File | Purpose |
|------|---------|
| `packages/web/commons/init.php` | Autoloader, session config, `Initiator` class |
| `packages/web/commons/base.inc.php` | Security headers, output buffering setup |
| `packages/web/commons/schema.php` | DB schema definitions |
| `packages/web/lib/fog/fogbase.class.php` | Root of the class hierarchy (~2900 lines) |
| `packages/web/lib/fog/loadglobals.class.php` | Bootstraps all global singletons |
| `packages/web/lib/fog/config.class.php` | **Not in git** — generated at deploy time by copytosvn.sh |

---

## What NOT to Do

- Do not add `declare(strict_types=1)` to files that don't already have it
- Do not instantiate classes with `new ClassName()` where `FOGBase::getClass()` is the established pattern
- Do not use raw `$_GET`/`$_POST` — use `filter_input()` or the already-sanitized values
- Do not echo user data without `Initiator::e()`
- Do not remove hooks, events, or plugin integration points without explicit confirmation
- Do not touch `config.class.php` — it's generated and excluded from git
- Do not change existing `$databaseFields` key names without understanding the ORM impact
