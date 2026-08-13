# Plugins become installable artifacts with a lifecycle of their own

## Status

accepted

## Context

What FOG calls a plugin system is really an extension system, and a good one:
core fires ~131 named hook events, and a plugin uses them to register a sidebar
entry, a permission node, REST classes, host/group tabs, JS files and its own
append-only schema migrations. `Hook::registerInstalled()` and
`Hook::injectPluginJS()` already absorb most of the per-plugin boilerplate.
Extensibility is not the gap.

The gap is that a plugin has no existence independent of this repository.

**Upgrades delete plugins.** `configureHttpd()` does `rm -rf $webdirdest` and
then re-lays the tree from the tarball. `backupPreservedCustomizations()`
rescues the iPXE background, the legacy refind blobs and user reports — not
`lib/plugins/`. So a plugin survives an upgrade only if it ships in
`FOGProject/fogproject`. Everything else below is secondary to this: it is the
reason no third party has ever shipped a FOG plugin.

**The manifest is a stub.** `plugin.config.php` carries a name, a description
and a menu icon, plus an `entrypoint` pointing at `html/run.php` — a file *no*
plugin ships and nothing loads. There is no version (the `plugins.pVersion`
column exists and discovery never writes it), no FOG compatibility range, no
dependencies, no author or homepage. `FOG_PLUGINSYS_DIR` looks like a setting
but `Plugin::_getDirs()` overwrites it back to `../lib/plugins/` on every call.

**The contracts are conventions.** Class name must equal filename; directory
name must equal the routing node; JS must be `fog.<node>.<sub>.js`. Real rules,
enforced by silent failure.

Two places where core had been hand-edited on a plugin's behalf — an
`API_CLASS_ENTITIES` entry and a `$sensitiveAlwaysFields` entry, both for LDAP —
were closed separately as a prerequisite (PR #1025, `API_SENSITIVE_FIELDS` plus
page-level fail-closed permissions). A plugin can now declare its own permission
node and its own secret columns. Nothing in core names a plugin any more.

## Decision

Plugins become artifacts with their own lifecycle: distributed, versioned and
installed independently of the FOG tarball.

### 1. Two plugin roots

| Root | Holds | Installer |
|---|---|---|
| `$webdirdest/lib/plugins/` | the bundled plugins | wiped and re-laid every upgrade |
| `$fogprogramdir/plugins/` (default `/opt/fog/plugins`) | third-party plugins | never touched |

`Plugin::_getDirs()` and the class-file list behind `Initiator::classFileList()`
/ `FOGBase::fileitems()` scan both. The external root sits under
`$fogprogramdir` for the same reason `$customizationsDir` does: it survives the
wipe *by construction*, rather than by a backup-and-restore step that has to be
re-made correctly on every upgrade.

A third-party plugin whose directory name collides with a bundled one is
**refused, and logged — never shadowed**. Silent shadowing of a bundled plugin
by an external one is a supply-chain attack, not a feature.

`FOG_PLUGINSYS_DIR` is retired rather than made real. The two roots are fixed;
a setting that discovery rewrites on every read is worse than no setting.

### 2. A manifest that means something

`plugin.config.php` gains, and `Plugin::getPlugins()` reads:

- `version` — written through to `plugins.pVersion`, which is currently dead.
- `fog_min` / `fog_max` — the FOG version range this plugin claims. A plugin
  outside the range **cannot be activated**, and an installed one deactivates
  itself with a message on upgrade rather than fataling a page.
- `author`, `homepage` — attribution and where to report a bug.
- `requires[]` — other plugin nodes that must be installed first.
- `entity`, `sensitive_fields` — the RBAC and secret-column declarations, so a
  plugin's security posture is in one readable place instead of spread across
  hook methods. These *feed* `PERMISSION_REGISTRY_DATA` and
  `API_SENSITIVE_FIELDS`; they do not replace them, because a plugin sometimes
  needs to register more than one node (LDAP registers `ldap` and `ldapgroup`).

`entrypoint` is dropped. It has never pointed at a file that exists.

The compatibility range is the single highest-value field. "I upgraded FOG and
it broke" is the support cost that a plugin ecosystem generates, and a declared
range converts most of it into a clear message at activation time.

### 3. Install from the UI

Plugin Management gains an install-from-archive flow. This is genuinely
dangerous — it accepts PHP that will execute as the web user — and is therefore
constrained:

- Requires a new `install` action on the `plugin` node — the registry
  currently declares only `['view', 'edit']`. Deliberately not folded into
  `plugin.edit`: activating a plugin that is already on disk and introducing
  new executable code to the server are not the same authority, and a role
  that can do the first should not automatically do the second.
- **Two independent switches, both required.** `FOG_PLUGIN_UI_INSTALL_ENABLED`
  in `globalSettings`, off by default, is the half an admin turns on in the UI.
  The external root being writable by the web user is the other half, and it is
  a root act outside the web tier: `bin/fog-plugin-uploads.sh enable`, which
  also sets the SELinux context. Neither alone is sufficient.

  The split is the point. If the setting alone were enough, the UI would be
  able to grant itself a web-writable directory that PHP autoloads code from —
  the server handing itself the precondition for remote code execution, on the
  say-so of a single database row. Requiring a shell as root means the
  dangerous state cannot be reached from inside the application at all, and an
  admin who wants the feature off permanently can revoke the directory and
  ignore the setting entirely.

  The cost is a worse first-run experience: the button is visible to any
  `plugin.install` holder whether or not uploads are usable, and the modal
  reports which half is missing when it is opened. Hiding the button was
  rejected — an admin hunting for a feature they have permission to use and
  cannot find is a worse failure than one being told what to switch on.
- Installs to the external root only; never writes into `$webdirdest`.
- Displays the archive checksum and the parsed manifest for confirmation
  **before** extraction, and refuses an archive whose manifest is absent,
  unparseable, or outside the compatibility range.
- No auto-update and no remote plugin index. Updating is an explicit act.

The risk was raised and accepted deliberately: the alternative is that every
admin who wants a plugin untars it by hand as root, which is strictly worse and
is what happens today.

### 4. `FOGProject/fog-plugins`, one repository

The 15 bundled plugins move to a single `FOGProject/fog-plugins` monorepo, with
history preserved. Per-plugin versions live in each manifest; the repository
releases as one artifact that the FOG build consumes.

`packages/web/lib/plugins/` stops being a tree in `FOGProject/fogproject` and
becomes a staging directory the installer fills, exactly as `packages/tftp`
already is for iPXE (GH-959). `FOG_PLUGINS_VERSION` in `system.class.php` pins
the release; `bin/fetch-plugins.sh` downloads it, verifies it against its
published sha256, and unpacks it before `configureHttpd()` lays the web
package. A plugin can then be released without a FOG release, and a FOG
release still ships a known set of plugins rather than whatever the default
branch held on the day someone installed.

Fetched, not a submodule. A submodule makes every `git clone` need
`--recursive` and silently hands a wrong tree to anyone who forgets, and
"silently wrong" is the failure this whole ADR exists to remove.

**`git subtree split` does not work on this history.** `fogproject` has two
root commits -- the 2008 SourceForge SVN import and an unrelated 2014-04-15
root merged in later -- and subtree cannot map parents across the second, so
it emits whole-repository commits unrewritten. `git-filter-repo` keeping both
`packages/web/lib/plugins` and the `packages/web/management/plugins` path it
was renamed from on 2014-05-20 produces a correct history whose tip tree is
byte-for-byte the source subdirectory, and whose per-file `--follow` crosses
the rename.

One repo per plugin was the earlier sketch (#848) and is rejected for the
bundled set: one maintainer with 15 repositories is 15 release chores for no
user-visible gain. Third-party plugins own their own repositories — this is the
Home Assistant split, core integrations in-tree and everything else out.

### What stays in core, and why

`site` remains bundled and is **not** a candidate for the external root. It is
the best-decoupled plugin in the tree — core names it nowhere, and it works
entirely through `OBJECT_SCOPE_CHECK`, `AJAX_DATA_DISPLAY_CHANGE`,
`API_MASSDATA_MAPPING` and the public `Authorization::isUnrestricted()`. The
reason to keep it is not coupling but consequence: per ADR 0006 the object-scope
boundary is default-allow, so **with no listener the boundary does not exist**.
A security boundary that disappears silently when a plugin is absent must not be
something an admin can uninstall, or that a half-failed upgrade can remove.

Moving scope *enforcement* into core, leaving `site` as the data model for
scopes, is the eventual fix. It is out of scope here and is not a prerequisite.

## Consequences

- A third party can ship a FOG plugin for the first time. `git clone` into
  `/opt/fog/plugins` is the baseline distribution mechanism; the UI installer is
  a convenience on top of it, not the only route.
- **PHP must be able to read a tree outside the docroot.** `open_basedir`, if
  set, must include the external root, and on SELinux systems the directory
  needs a context the web server can read. This is the real implementation cost
  of the two-root decision and it is a per-distro problem, not a code one.
- **For the UI installer, that directory must also be web-server-writable** — a
  writable PHP directory reachable by the interpreter is exactly the shape that
  turns any file-write bug into remote code execution. It is why the installer
  needs both switches above, and why the writable half is deliberately not
  something the application can grant itself.
- **Code can now appear under a scanned root while the server is running**,
  which was never previously possible: the class-file list is TTL-cached, so
  without an explicit invalidation on install a freshly uploaded plugin stays
  invisible to the autoloader for the length of the TTL. Everything downstream
  then silently no-ops — no manager class, so no schema runs, no hooks
  register, no page renders — while reporting success. Any future writer to a
  scanned root has to invalidate the same cache.
- ADR 0006's consequence that "unknown nodes/classes stay allowed, matching the
  RBAC stance" no longer holds: PR #1025 made both the API and page paths fail
  closed. A plugin that does not register a permission node is now unreachable
  rather than ungated.
- Bundled plugins keep working untouched. All 14 that have a page already
  register their permission node, own their tables through the `schema()`
  contract, and declare their API classes; the port is adding manifest fields.
  The fifteenth, `persistentgroups`, is model classes and a config file with no
  page, no hooks and nothing to register — a useful reminder that the manifest
  must not make page-shaped fields mandatory.
- The FOG tarball and `fog-plugins` can now disagree about a plugin's version.
  The build pins a `fog-plugins` revision; the manifest range is what actually
  gates activation.
- **A fresh `git clone` of `fogproject` has no plugins** until
  `bin/fetch-plugins.sh` runs, which the installer does for you. That is the
  cost of the split, and it is the cost `packages/tftp` already imposes. The
  script leaves a hand-placed tree alone, so an offline site pre-populates the
  directory once and never thinks about it again.
- **A change spanning core and a plugin is no longer one commit, so the order is
  now load-bearing: release `fog-plugins` first, then land the core change and
  the `FOG_PLUGINS_VERSION` bump together in a single PR.** This PR is the worked
  example — it empties `Route::$sensitiveAlwaysFields`, and the LDAP plugin
  re-adds `bindPwd` through `API_SENSITIVE_FIELDS`. Merging the core half alone
  would have published a cleartext directory-service password on the API to
  anyone who upgraded in the window. The pin makes the two halves arrive
  together because `downloadplugins()` runs before `configureHttpd()`, so a
  server is never serving requests against a core it has plugins older than.
- Uninstall still does not drop a plugin's tables. `schema()` is an append-only
  forward migration list with no down-steps, and that stays true — losing an
  admin's data on an uninstall is worse than leaving orphan tables.

## Open

- Whether `requires[]` needs real dependency resolution (ordering, cycles) or
  can stay a flat "these must already be installed" assertion. Flat is assumed
  until a plugin needs more.
- Whether bundled plugins should also be installable from the external root, so
  an admin can run a newer `site` than the tarball shipped. Deferred; it
  reintroduces the shadowing question the collision rule exists to close.
