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

Two scripts sync between the **git repo** (`~/fogproject/`) and the **live web
server** (`/var/www/fog/`, symlinked to `/var/www/html/fog-1.6/`). Both live in
[`FOGProject/fog-community-scripts`](https://github.com/FOGProject/fog-community-scripts);
neither is part of this repo, and **what they are installed as locally is up to
whoever installed them** — do not assume a path. `command -v` first, and check
the machine's own notes.

**`CopyBackTrunk/copybacktrunk.sh`** — Git → Web (deploy for live testing)
- rsync's `~/fogproject/packages/web/` → `/var/www/html/fog-{ver}/`
- Copies the generated config into place, sets symlinks and permissions, and
  regenerates the SSL cert
- Use this after editing in the git repo to deploy and test changes live
- Usage: `copybacktrunk.sh "" "" "1.6"`

**`CopyToSVN/copytosvn.sh`** — Web → Git (pull live edits back)
- rsync's the webroot → `~/fogproject/packages/web/`
- Use this ONLY after editing files directly on the live web server
- Strips generated and runtime files: the config, cache, SSL certs, the
  published CA, compiled `.mo` files, and the Secure Boot signed artifacts
- **Do NOT run this after `copybacktrunk.sh`** — it will overwrite git with the
  web copy
- It does **not** touch the `.pot`/`.po` files, run php-cs-fixer or rewrite
  `FOG_VERSION` any more. The first two moved to `.githooks/pre-commit`, which
  does them scoped to the files staged for that one commit instead of across
  the whole live webroot; `FOG_VERSION` is no longer written locally at all
  (see below). A doc or a memory still saying otherwise is describing a copy
  from before 2026-08.

**Neither script may move a Secure Boot signed artifact in either direction.**
`installfog.sh` countersigns the deployed kernels and `refind*.efi` with the
server's own key, so pulling one into the checkout publishes that key's output
as though it were the upstream source, and deploying the repo copy over a signed
one strips the countersignature and stops Secure Boot clients booting. Both
scripts exclude them; `test-signed-artifacts-excluded.sh` in that repo pins it.

### Pre-commit hook (IMPORTANT — explains "files I didn't touch" in commits)

`core.hooksPath` is set to `.githooks/`, so `.githooks/pre-commit` runs on **every** `git commit`. It auto-modifies and `git add`s files beyond what you staged — this is expected, not a bug. Do **not** revert these changes. The hook does two things:

1. **`updateLanguage()`** — regenerates `packages/web/management/languages/messages.pot` from all `*.php` via `xgettext`, sorts with `msgcat`, then `msgmerge`-updates every `.po` file. Adds the whole `languages/` dir. (Source of the harmless `Message contains an embedded URL` warning during commits.) Skipped if `xgettext`/`msgcat`/`msgmerge` aren't installed.
2. **`psrfix()`** — runs `php-cs-fixer fix packages/web --rules=@PSR2` and adds the result. So your code may be auto-reformatted to PSR-2 on commit. Skipped if `php-cs-fixer` isn't installed.
It used to do a third — a **version bump**, `sed`-replacing `FOG_VERSION`/`FOG_CHANNEL` in `packages/web/src/Base/System.php` from the branch name and the commit count. That was removed, along with `.githooks/pre-push` (which existed only to refuse a push whose committed version had drifted). See **Why `FOG_VERSION` is not written on a branch** below.

Net effect: a typical commit will often include `messages.pot` + `.po` churn and/or PSR-2 reformatting. Expect it; don't be surprised by the extra files.

### Why `FOG_VERSION` is not written on a branch

`FOG_VERSION` is `git rev-list master..HEAD --count` put through
`.githooks/lib/fog-version.sh` — a property of the commit graph. Anything that
writes it into a *tracked* file makes every branch open at the same time hold a
**different value on the same line**, and each merge then leaves the others with
a hand-resolved conflict on `System.php` before they can be updated (which the
ruleset requires) and re-tested. Worse, the quantity is self-perturbing: getting
a branch current adds a commit, which moves the count, which earns another
rewrite, which has to be pushed and pulled, which adds another commit.

That is structural, not a race. Evidence: #1504 needed three version commits
(4598 → 4600 → 4608) as its neighbors landed, merge `3fbacbc73` records the
conflict in its own message, and #1507/#1508 sat open together holding 4620 and
4619.

So it is a **generated file**, for the same reason `commons/config.class.php`
is:

| | |
|---|---|
| `packages/web/commons/version.php` | **generated, gitignored**. Written by `.githooks/lib/write-version-file.sh` |
| Written on | commit, checkout, merge (`.githooks/post-*`), and by `bin/installfog.sh` |
| `packages/web/src/Base/System.php` | includes it when readable, else falls back to the **release** constants it carries |
| Rewrites the fallback | only a release. Not a commit, not a merge, not CI |

Every reader parses the identical `define('FOG_VERSION', '...');` shape, so one
`awk` works against either file — `bin/installfog.sh`, `bin/updatefog.sh` and
`lib/common/utils.sh` all try the generated file first and fall back.

Nothing breaks without the generated file: a source zip with no `.git`, or a
checkout by someone who never enabled hooks, reports the release version —
truthful, if less precise than a build count. `tests/generated-version-file.test.sh`
pins the whole contract.

Consequence worth knowing: **there is no merge-time version sync any more.**
`.github/workflows/sync-generated-files.yml` was deleted from this branch, since
PSR-2 and the gettext catalog are already corrected on the pull request and
the version is no longer in git. fog-workflows' daily sweep still covers fork
PRs and direct pushes for those two, and skips the version on any branch
carrying `write-version-file.sh`.

### `working-1.6` is gated by a branch ruleset

`working-1.6` carries a ruleset (`working-1.6 pull request gate`), so landing work
there is not a plain `git push`:

- **changes arrive by pull request** — the rule that pins the merge method also
  requires one, so a direct push is rejected for everyone except the
  `fog-workflows` App and org admins;
- **merge commits only** — no squash, no rebase. This keeps the history
  linearly countable, which is what a locally generated `FOG_VERSION` is
  derived from;
- **branches need NOT be up to date** — `strict_required_status_checks_policy`
  is `false`. It used to be true, and that was the rule that made every merge
  invalidate every other open PR and force a re-run of every required check.
  The **merge queue** replaced it (2026-08-30): instead of making N-1 branches
  rebase, the queue builds one temporary `gh-readonly-queue/…` ref carrying
  base + the entry and tests that. So the queue is not a version-bump
  mechanism — the version left git in GH-1510/GH-1513 — it is the thing that
  now catches a pull request whose base moved under it;
- **nine checks must pass**: the eight `fogproject / …` contexts plus
  `phpstan`. `regenerate / …` is deliberately *not* required: it is skipped on
  fork PRs, and requiring it would leave those unmergeable.
  `fogproject / upgrade rehearsal` was added on 2026-09-01, having reported
  since the day it landed without ever being required — the ruleset was last
  edited the day before. That gap is not academic: #1604 merged with the
  rehearsal RED, because the required-context list is the only thing the merge
  queue consults, and the stale baseline it left behind then failed every
  branch that came after it.
- **the price is two full suite runs per pull request.** The required contexts
  have to be reported against the merge-group ref as well, so `tests.yml`
  triggers on both `pull_request` and `merge_group` and there is no way to
  report a reduced set on the queue. When pull requests are merged one at a
  time onto a quiet base — the normal case here — the queue run tests a tree
  byte-identical to the one the pull request already tested.

No version is written into git by any of this. See **Why `FOG_VERSION` is not
written on a branch** above.

Full reasoning: `docs/development/version-sync-automation.md` in `FOGProject/fog-docs`
— note it predates GH-1510/GH-1513 and still describes the version as a synced,
tracked value.

### The `phpstan` check is two passes — run both, unscoped

One check-run named `phpstan`, one job, **two steps**. Run both before pushing:

```
vendor/bin/phpstan analyse --memory-limit=2G --no-progress
vendor/bin/phpstan analyse -c phpstan-tests.neon --memory-limit=2G --no-progress
```

`phpstan analyse <one file>` is not a smaller version of either. Naming a path
overrides the config's own `paths`, so a scoped run skips the whole `tests/`
pass and passes for reasons the real command never touches. The two live in the
same job on purpose: the ruleset requires the single context `phpstan`, so
neither pass can be required without the other.

The second pass analyzes `tests/` against `phpstan-tests-baseline.neon`, and
**that baseline counts occurrences, not lines.** Adding a second use of an
already-baselined pattern fails the build on an entry nobody touched:

```
Ignored error pattern ... is expected to occur 2 times, but occurred 3 times
```

Bump that entry's `count:`; don't regenerate the baseline, which would sweep up
unrelated drift. Note this is the opposite direction from
`reportUnmatchedIgnoredErrors: false`, which exists so that *fixing* a baselined
error does not fail the build — nothing guards the over-count, so it is the one
you find in CI. (#1448: one added `Route::$rows` write in
`tests/lib/bootmenu-render.php` took a count from 2 to 3.)

**A local failure CI does not show is the cache, not the code — and
`clear-result-cache` is not enough to rule that out.** PHPStan's `tmpDir` is
`/tmp/phpstan`, and it holds two separate things:

| | cleared by `phpstan clear-result-cache`? |
|---|---|
| `/tmp/phpstan/resultCache.php` | yes |
| `/tmp/phpstan/cache/` (~26 MB, reflection and container) | **no** |

So the documented clear command leaves the larger half in place, and a stale
entry there keeps reporting an error against source that no longer contains it.
It looks exactly like a real regression: same phpstan version, same config, same
pinned PHP range, reproducible on every run, and reproducible on an unmodified
checkout of the branch — which is what makes it convincing rather than obviously
wrong. Meanwhile CI, which is always cold, is green and correct.

To rule it out, remove the whole directory before believing a local-only
failure:

```
rm -rf /tmp/phpstan
vendor/bin/phpstan analyse -c phpstan-tests.neon --memory-limit=2G --no-progress
```

Worth knowing on this box specifically: `/tmp` is tmpfs, so the cache is shared
by every worktree and does not survive a reboot.

(2026-08-31: four `include.fileNotFound` errors in
`tests/lib/rehearsal-runner.php` were chased as a CI-versus-local discrepancy —
version, config, stub, baseline and PHP were all compared and matched — and
were reported in a PR body as "one of the two is wrong". `rm -rf /tmp/phpstan`
made them vanish. The code was never at fault.)

### Commit authorship

Commits are **authored by the maintainer and co-authored by the agent**, not the
other way round.

Whose name that is belongs to the clone, not to this file — several people work
on this repo, each from their own. Use whatever `git config user.name` /
`user.email` already say; never override them on the commit, and never fall back
to `Claude <noreply@anthropic.com>` as the author.

End the message with a co-author trailer, and let the agent's own tooling
name the model it ran as:

```
Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_<id>
```

**The model name in the trailer is deliberate.** This file used to forbid it —
`Co-Authored-By: Claude <noreply@anthropic.com>`, no model identifier anywhere.
That rule lost on both counts. The harness emits the model-named trailer and
the session line itself, so the rule was unenforceable in practice and the
history already carried both shapes; and the name turns out to be worth having,
because which model wrote a commit is a real thing to know when reading back a
change that went wrong. Older commits with the bare trailer are equally fine —
neither shape needs correcting.

Still off limits: a model identifier in a **PR title or body**, or in a **code
comment**. Those are read by people looking at FOG, not at how FOG was written,
and the provenance question the trailer answers is not one they are asking.

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
│       ├── src/                # ALL core classes: PSR-4, FOG\<Bucket>\<Class>
│       │   ├── Items/          # 67 models (Host, Image, Snapin, ...)
│       │   ├── Managers/       # 66 collection/query classes
│       │   ├── Base/           # 18 FOGBase, FOGController, FOGPage, managers
│       │   ├── Pages/          # 28 UI page classes
│       │   ├── Reports/        # 13 report classes
│       │   ├── Hooks/          # 10 hook classes
│       │   ├── Events/         # 1 event class
│       │   └── Audit,Auth,Boot,Client,Db,Exception,Net,Router,Service,
│       │                       # TaskHandling,Util
│       ├── lib/                # only two things live here now
│       │   ├── router/         # altorouter/altotransformer — a FORK, kept
│       │   │                   # under upstream's name and MIT license
│       │   │                   # (ADR 0013 §3); everything else moved to src/
│       │   └── plugins/        # installed plugin artifacts, NOT repo source
│       │                       # (ADR 0009) — gitignored, root-owned
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
*partly* from the router — so **a route change can change behavior without changing the
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
- **Class instantiation**: `new ClassName()` with a `use` import. `getClass()` is
  only for a class named by a **variable** (see below)
- **Translation**: `_('string')` for inline gettext; `$foglang['Key']` for pre-defined strings from `text.php`
- **Newer files**: `declare(strict_types=1)` at top; older files do not have this — don't add it retroactively
- **`getManager()`**: still the way to reach a model's manager, unchanged

### Button color and alignment

Position carries the meaning, color follows it. Don't pick a color to make a
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
  the position and the plain (non type-colored) fill key off.

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
  colors got mixed inside a single page.
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
| `packages/web/src/Base/FOGBase.php` | Root of the class hierarchy (~2900 lines) |
| `packages/web/src/Base/LoadGlobals.php` | Bootstraps all global singletons |
| `packages/web/commons/config.class.php` | **Not in git** — generated by the installer, beside `fogpaths.php`. Was `lib/fog/config.class.php`; that directory is gone |

---

## What NOT to Do

- Do not add `declare(strict_types=1)` to files that don't already have it
- Do not call `getClass()` with a string literal -- write `new ClassName()`; `tests/getclass-literals.test.php` refuses it
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

Single-context: `CONTEXT.md` and `docs/adr/` at the repo root.
`CONTEXT.md` is the glossary and nothing else -- no implementation detail, no
decisions. A decision that is hard to reverse, surprising without context, and
the result of a real trade-off gets an ADR instead.
