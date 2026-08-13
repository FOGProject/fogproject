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
- Off by default, behind a `globalSettings` flag an admin must deliberately set.
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
history preserved via `git subtree split`. Per-plugin versions live in each
manifest; the repository releases as one artifact that the FOG build consumes.

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
  is opt-in and permission-gated rather than on by default.
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
