# Interaction rules (apply in every conversation)

1. Don't assume. Don't hide confusion. Surface tradeoffs.
2. Minimum code that solves the problem. Nothing speculative.
3. Touch only what you must. Clean up only your own mess.
4. Define success criteria. Loop until verified.

---

# FOG Project — Claude Context

## What This Is

FOG Project is an open-source network imaging and endpoint management system. It allows IT administrators to deploy OS images to computers over PXE boot, manage client hosts, schedule tasks, push snapins (software packages), manage printers, track users, and run background replication/multicast services.

- Primary language: **PHP** (7.4+, some files use `declare(strict_types=1)`)
- Frontend: **jQuery + Bootstrap + AdminLTE** (no build step — plain JS served directly)
- Background services: **PHP CLI daemons**
- ~435 PHP files, ~200 JS files
- Current working branch: `working-1.6`

---

## Feature development order

**New features land here first.** Until this branch is promoted to become
the project's stable/patches line (superseding `dev-branch`), develop new
capability against `working-1.6` and open the PR here before porting it to
`dev-branch`. `dev-branch` is the actively-patched 1.5.x line today, but this
branch is where the project's future lives — landing new features here
first, then porting backward, keeps it from falling further behind and
avoids reconciling divergence after the fact instead of designing around it
from the start.

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

### Pre-commit hook (IMPORTANT — explains "files I didn't touch" in commits)

`core.hooksPath` is set to `.githooks/`, so `.githooks/pre-commit` runs on **every** `git commit`. It auto-modifies and `git add`s files beyond what you staged — this is expected, not a bug. Do **not** revert these changes. The hook does three things:

1. **`updateLanguage()`** — regenerates `packages/web/management/languages/messages.pot` from all `*.php` via `xgettext`, sorts with `msgcat`, then `msgmerge`-updates every `.po` file. Adds the whole `languages/` dir. (Source of the harmless `Message contains an embedded URL` warning during commits.) Skipped if `xgettext`/`msgcat`/`msgmerge` aren't installed.
2. **`psrfix()`** — runs `php-cs-fixer fix packages/web --rules=@PSR2` and adds the result. So your code may be auto-reformatted to PSR-2 on commit. Skipped if `php-cs-fixer` isn't installed.
3. **Version bump** — derives a version from the branch name + commit count and `sed`-replaces `FOG_VERSION`/`FOG_CHANNEL` in `packages/web/lib/fog/system.class.php`, then adds it. On `working-1.6` this yields `1.6.0-beta.<count>` (channel `Beta`); `dev-*`/`stable` → `Patches`, `rc-*` → `Release Candidate`, `feature-*` → `Feature`.

Net effect: a typical commit will also include a `system.class.php` version bump, and often `messages.pot` + `.po` churn and/or PSR-2 reformatting. Expect it; don't be surprised by the extra files.

### Commit authorship

Commits are **authored by the maintainer and co-authored by the agent**, not the
other way round.

Whose name that is belongs to the clone, not to this file — several people work
on this repo, each from their own. Use whatever `git config user.name` /
`user.email` already say; never override them on the commit, and never fall back
to `Claude <noreply@anthropic.com>` as the author.

End the message with a co-author trailer:

```
Co-Authored-By: Claude <noreply@anthropic.com>
```

No model name in the trailer, and no model identifier anywhere else in a commit
message, PR title/body, or code comment. Some older commits in this history do
carry one (`Claude Opus 5`); they are not the pattern to copy.

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

### REST API routes and the OpenAPI document

The REST API is routed separately from the `?node=` UI above: `Route::defineRoutes()`
(`lib/router/route.class.php`) maps the URIs, and `OpenAPI::document()`
(`lib/fog/openapi.class.php`) describes them, served at `system/openapi` and `swagger.json`
and rendered by the API Documentation page. The document is generated per request, but only
*partly* from the router — so **a route change can change behaviour without changing the
document**. Treat the spec as part of the route commit, not as follow-up.

Updates itself, nothing to do:

| Changed | Covered by |
|---|---|
| `Route::$validClasses` | `_paths()` — emits the ten generic operations, a schema and a tag per class, plugin classes included |
| `$validTaskingClasses` / `$validActiveTasks` | adds `/{id}/task` and `/{id}/cancel`, or `/current` |
| a model's `$databaseFields` / `$databaseFieldsRequired` / `$databaseFieldsToIgnore` | `_entitySchema()` reflects them; column types come from `commons/schema-expected.php`, so regenerate the manifest or the field is typed `string` |
| the permission a route requires | `_op()` calls the same `Authorization::resolveApiPermission()` the router does |

Hand-edit `openapi.class.php` in the same commit:

| Changed | Also edit |
|---|---|
| added a route that is not the generic CRUD shape | `_classPaths()` for a per-class route, `_fixedPaths()` for a `system/*` or other fixed one |
| changed a hand-built response body (e.g. `Route::status()` feeding `/system/info`) | that path's literal schema in `_fixedPaths()` |
| added or renamed a route alias, or an optional trailing segment | the prose on the affected operation — aliases are collapsed to one documented path deliberately |
| added a field to a special request body (e.g. the task body) | the literal property list in `_classPaths()` |

Two standing rules:

- If a commit touches `route.class.php` and not `openapi.class.php`, say in the message why no
  spec change was needed. "It is generated" is not the answer for a non-CRUD route.
- The document is **OAS 3.0.3, not 3.1** — nullable is `nullable: true` and a genuine union is
  `_oneOfTypes()`, never `type: [x, 'null']`. The `OAS_VERSION` docblock explains why; that is a
  decision to revisit, not to quietly extend.

**Downstream consumers of the class list.** FogApi (`darksidemilk/FogApi`, the PowerShell module)
keeps its *own* hardcoded copy of the 1.6 class list in `Private/Get-DynmicParam.ps1` for
`-coreObject` tab completion — it does not read `system/openapi`. A class added here is missing
there until someone syncs it by hand, and that list has already drifted. So when a change adds or
removes a route class, call it out in the PR body under a "Downstream" note naming FogApi, so the
sync is visible rather than discovered later.

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

### Polling widgets must guard on their own widget

`doPageLoad()` cancels `setInterval` (it wraps it and clears the ids in
`clearAllIntervals()`). It does **not** cancel `setTimeout` and cannot —
`setTimeout` is also every debounce and next-tick deferral in the codebase.

So **any `setTimeout` chain that reschedules itself outlives its page**, and
every visit starts another one that runs for the life of the tab. Nothing
errors; the requests answer 200 and draw into nothing. One dashboard visit
plus 5½ minutes elsewhere used to cost 122 requests.

Guard at the top of the polling function, before the request — returning early
also ends the chain, because the reschedule lives in the `complete` handler:

```js
function poll() {
    if (!document.querySelector(SEL)) { return; }   // or document.body.contains(el)
    $.ajax({ /* ... */ complete: function() { timer = setTimeout(poll, POLL_MS); } });
}
```

Per widget, not centrally — four charts get four guards. Prefer `setInterval`
if the poll can be written that way; it is already torn down for you. Full
reasoning and the rejected alternatives:
`docs/adr/0012-self-rescheduling-polls-guard-on-their-own-widget.md`.

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

### Button colour and alignment

Position carries the meaning, colour follows it. Don't pick a colour to make a
button stand out — pick the one that matches what kind of action it is.

| Action | Class | Side |
|---|---|---|
| Create / Update / Add / commit the card | `btn btn-primary float-end` | **right** |
| Destructive (Delete, Remove selected, Cancel task) | `btn btn-danger float-start` | **left** |
| Cancel / dismiss in a modal | `btn btn-outline-secondary float-start` | left |
| Genuinely different operation (Resume, Pause, Reset token) | `btn btn-success` / `btn-warning` | per case |
| File "Browse" prefix inside an `input-group` | `btn btn-info` (via `makeLabel`) | n/a |

**Why destructive goes left:** most people click with their right hand, so
common non-destructive actions sit under it and destroying something takes
deliberate travel. Being explicit and purposeful is the point.

**Only ONE button in a right-side cluster is `btn-primary` — the rightmost.**
Anything sitting to its left is `btn btn-secondary`. Two blues touching read as
one wide button, so the supporting action must be visibly a lesser one. The
reference pair is an association tab: `[Create New X (secondary)][Add selected
(primary)]`, and the host MAC table repeats it: `[Add New MAC Address
(secondary)][Mark selected… (primary split)]`.

Watch the emission order, and check what the *container* is first — **floats do
nothing inside a flex container**, and most of the containers here are flex:
- Bare `float-end` siblings in a **block** container — **first emitted lands
  rightmost**. So the primary is emitted *first* and the secondary *after* it.
- Inside `<div class="btn-group float-end">` — normal left-to-right flex order,
  floats don't apply to children. So the secondary is emitted *first*.
- Inside a **`.modal-footer`** — also flex (`justify-content: flex-end`), so
  floats are equally inert. `fog-default-ui.scss` maps `.float-start` onto
  `order: -1` **plus** `margin-right: auto` there, so a modal's dismiss button
  lands left and its commit button right whatever order they are emitted in.
  Both parts matter: `order` is the only thing that reorders flex items, and
  the auto margin only pushes the slack to one side — #919 shipped the margin
  alone and the buttons stayed in emission order, just left-packed instead of
  right-packed. Keep tagging the dismiss `float-start`; that class is what both
  the position and the plain (non type-coloured) fill key off.

That flex/float mismatch has bitten twice: the task panes wrapped their buttons
in a bare `.btn-group`, which silently killed `float-start`/`float-end` and
rendered all three as one left-aligned pill (#909); and the create-tasking
modal emitted Create first, which in a flex footer put the commit button on the
*left* (#919). If buttons come out in the wrong place, check the container's
`display` before touching the classes.

Three consequences worth remembering:
- A card's primary commit button is `btn-primary` **even when the label isn't
  "Update"** — "Make primary" and "Make default" are the same kind of action.
  They were `btn-info` and read as a different one.
- Don't override `renderAssocTab()`'s `$sendClass`. Every association tab's
  "Add selected" is primary; nine tabs used to pass `btn-success` and the two
  colours got mixed inside a single page.
- Adjacent right-side buttons that are *already* distinguishable are fine as
  they are — e.g. image replication's `[Resume Reload (success)][Create
  (primary)]`, where green marks a genuinely different operation. The rule is
  "clearly different", not "always secondary".

### Create-and-associate on association tabs

Any tab that associates a thing should also let you *create* that thing without
leaving the page. `FOGPage::renderAssocCreate()` owns the button and the modal;
it is public static and takes the owner id as a parameter, so **plugin hooks
injecting a tab via `PLUGINS_INJECT_TABDATA` call the same helper** rather than
hand-rolling markup the shared JS then has to guess at.

The modal ships empty and the browser fetches the real form from
`?node={createNode}&sub=addModal`, so the fields can never drift from the create
page's own. The button is suppressed unless the user holds `{node}.create` —
which means a plugin **must** register its permissions via
`PERMISSION_REGISTRY_DATA` or the button is invisible to everyone but a `*`
holder.

Two JS entry points, by tab shape:

| Tab shape | Wire with | Associates by |
|---|---|---|
| Association grid | `$.registerCreateAndAssociate(slug, table)` | POSTing `additems[]` |
| Single dropdown | `$.registerSelectTab({slug, send, node})` | selecting the new option, then clicking the tab's own Update |

**Trap:** the single-dropdown plugin tabs wrap their card in a `<form>`. The
create modal contains its own `<form>`, and nested forms are invalid — the
browser drops the inner one and the create posts nothing. Echo `$createModal`
*after* `</form>`, not in the card-footer. Grid tabs have no wrapping form, so
the footer is fine there.

Pass the optional 5th `$noun` argument whenever `ucfirst($node)` would read
badly — `ou` and `windowskey` become "Ou" and "Windowskey" without it.

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
- Do not add or change a REST route without checking whether `OpenAPI::document()` still describes it
- Do not touch `config.class.php` — it's generated and excluded from git
- Do not change existing `$databaseFields` key names without understanding the ORM impact

---

## Agent skills

### Issue tracker

Issues live in GitHub Issues at `FOGProject/fogproject`. See `docs/agents/issue-tracker.md`.

### Triage labels

Canonical labels: `needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, `wontfix`. See `docs/agents/triage-labels.md`.

### Domain docs

Single-context: `CONTEXT.md` and `docs/adr/` at the repo root. See `docs/agents/domain.md`.
