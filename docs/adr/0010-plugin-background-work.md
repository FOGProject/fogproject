# A single core daemon runs plugin-declared background work

## Status

accepted

## Context

ADR 0009 gave plugins a lifecycle independent of this repository. The extension
surface they reach is, for the web tier, complete:

| Surface | Mechanism |
|---|---|
| Page and sidebar entry | `pages/*.page.php`, routed as `?node=<name>` |
| Data model and manager | `class/*.class.php` on the existing ORM |
| Its own tables, versioned | `schema()` on the manager, applied count in `plugins.pSchema` |
| REST resource | hook on `API_VALID_CLASSES` |
| Permission nodes | `PERMISSION_REGISTRY_DATA` |
| Behavior on core events | `*.hook.php`, `*.event.php` |
| Tabs on core pages | `PLUGINS_INJECT_TABDATA` |
| Reports | `reports/*.report.php` |
| Settings on the FOG Configuration page | `globalSettings` rows stamped with a `Plugin: <Name>` `settingCategory`; the page groups by that column and builds its left-hand nav from the distinct values |
| Browser assets | `js/`, reached through the asset symlink |

Every one of those runs inside a request. **Nothing a plugin declares runs when
nobody is looking at a browser.** A plugin can notify on an event core fires; it
cannot poll, reconcile, expire, retry or re-sync. That is the gap.

The eight daemons are fixed. `serviceList` in `lib/common/config.sh` names them,
and `installfog.sh` lays down one unit per name from `packages/systemd/` (plus
three init.d variants under `packages/init.d/{alpine,redhat,ubuntu}`). A plugin
installed after the installer ran cannot add a ninth.

Two facts shape the decision:

**The plumbing to reach plugin code from a daemon already exists.** Every daemon
entry point requires `WEBROOT/commons/base.inc.php`, which runs the same
`Initiator` autoloader — including the external plugin root — and the same
`LoadGlobals`. Plugin classes autoload inside a daemon today, and plugin hooks
already fire there. What is missing is a place to *declare* periodic work and
something to run it, not the machinery to load it.

**Someone started this before.** `plugins.pRunfile` is a `longtext` column,
created by a schema step, renamed from `pAnon2`, and mapped in `Plugin`'s
`$databaseFields` — with no reader and no writer anywhere in the tree. The old
manifest's `entrypoint` key pointing at a `html/run.php` that no plugin ships
(ADR 0009) is the other half of the same abandoned attempt.

## Decision

One core-owned daemon, **`FOGPluginRunner`**, discovers and executes work
declared by active, installed plugins. **Plugins never ship a systemd unit.**

### 1. The seam is `<plugin>/tasks/*.task.php`

A class extending a new `PluginTask` base, declaring:

- `$interval` — seconds between runs
- `run()` — the work
- `$name` / `$description` — for the log and, later, for the UI

`task` joins `report|event|class|hook|page` in the autoloader's file-scan regex,
so the directory layout stays uniform with every other plugin sub-directory and
discovery costs nothing new.

### 2. The lifecycle is the plugin's, not systemd's

A task runs only while its plugin is both **active** and **installed**.
Deactivating a plugin stops its tasks on the next cycle; Forget removes them
with the plugin. There is no unit to enable, no unit to disable, and nothing
left behind on removal.

This is the main advantage over a unit per plugin, and it falls out for free.

### 3. Execution is sequential and in-process

The runner walks due tasks in order, calls `run()`, catches `Throwable`, logs
the failure against plugin and task name, and continues to the next.

Fork-per-task with a wall-clock deadline was considered and rejected — see
Alternatives.

### 4. Both ends of every run are logged

A start line and a finish line carrying elapsed time. #815, #917, #944 and the
ten-month-wedged 1.5 FileDeleter all shared one shape: systemd reports the unit
active while the daemon does nothing, and the log says nothing at all. **A start
line with no matching finish line is exactly the signal that was missing every
time.** Third-party code in the loop makes that shape more likely, not less, so
the runner must be held to this rule from the first commit.

### 5. Schedule state lives in memory

Next-run times are held in the daemon. A restart makes every task immediately
due. Accepted for a first cut: it is one fewer table, and re-running a task
early is a correctness problem only for tasks that are not idempotent — which
the `PluginTask` contract will require them to be. If a plugin appears that
genuinely cannot tolerate it, a `pluginTaskRuns` table is an additive change.

### 6. The runner does NOT run as root

This is the decision that changed while writing the ADR, and it is the important
one.

The existing eight units carry no `User=`, so every FOG daemon runs as root —
they mount NFS, write image trees and manage device nodes. A plugin's web-tier
code, by contrast, runs as the web user. Putting plugin code into a root daemon
would therefore **escalate every installed plugin from web-user to root**, and
would do it silently, as a side effect of a feature about scheduling.

`FOGPluginRunner` gets `User=`/`Group=` set to the web user in its unit, so
installing a plugin grants exactly the privilege installing a plugin already
granted. Cost: a plugin task cannot do root-only work. That is the correct
default, and any plugin needing more should be arguing for a specific,
reviewable core capability rather than inheriting root by accident.

Two consequences follow, both about the log:

**The runner gets its own log sub-directory**, `$servicelogs/plugins/`, owned by
the web user — not a file alongside the other seven. `wlog()` rotates by
`rename()` and `unlink()`, which need write on the *directory*, so a rotatable
log in the shared directory would hand the web user, and therefore every
plugin, the ability to rename or delete the root daemons' logs. That is the
same escalation this decision avoids, arriving through the log path instead.
The sub-directory is core policy, so `PLUGINRUNNERLOGFILENAME` stays a plain
filename like every other service's.

**`servicemaster.log` becomes group-writable** by the web user (root still owns
it, mode 660). It is where `service_lib.php` writes every daemon's start, stop
and fatal lines and where PHP's own `error_log` points; without the group bit
the runner's supervisor lines silently divert to journald while the other eight
keep landing in the file. One log with a hole in it is worse than either
alternative. No directory write is implied — that file is appended to, never
rotated.

The `User=`/`Group=` lines cannot be shipped resolved, because the account
differs per distro (`apache`, `www-data`, `nginx`, `http`). The unit and the
three init scripts carry the literal `FOGWEBUSER`, and `installInitScript()`
rewrites it to `$apacheuser` in the *installed* copy only — the same
"`cp -f` restores the source every run, so rewrite the copy" discipline GH-850
established for the hardcoded service path. That substitution is
unconditional, unlike the path one: a placeholder left in place makes systemd
refuse to start the unit on an unresolvable user, which is the intended
failure. Loud, rather than quietly running plugin code as root.

## Alternatives rejected

**A systemd unit per plugin.** The external plugin root is web-writable
whenever uploads are enabled (`FOG_PLUGIN_UI_INSTALL_ENABLED` plus
`bin/fog-plugin-uploads.sh enable`). A unit file is executed by systemd as
root. A web-writable path feeding root execution is precisely the shape of
GHSA-2hqx. Enabling the unit would additionally need root from the web tier,
which is the same hole through a second door.

**Fork per task with a wall-clock deadline.** Real isolation against a hung
task, but the parent holds a live PDO handle that the child inherits, and a
child exiting can close that socket out from under the parent — trading a
hypothetical failure for a new and subtle one. The blast radius is already
contained: this daemon does nothing but plugin tasks, so a hang stops plugin
work and touches no imaging. Revisit if a real plugin makes it necessary.
Note that no in-process timeout substitutes for it — PHP's
`max_execution_time` excludes time blocked in I/O on Unix, which is where a
realistic hang occurs.

**cron or systemd timers.** Needs root at plugin-install time, has no tie to
plugin active/installed state, and sends output somewhere other than the FOG
service log.

**Reusing `FOGTaskScheduler`.** It schedules imaging tasks against hosts — a
different domain object and a different table. Coupling plugin work to it means
a plugin bug can stall imaging, which is the one thing this design is trying to
avoid.

## Consequences

- A ninth daemon: entry point under `packages/service/FOGPluginRunner/`, a
  systemd unit, three init.d variants, an `initdPRfullname` and a `serviceList`
  entry in `lib/common/config.sh`, four `globalSettings` matching the other
  services' categories (schema step 329), and the `FOG_SCHEMA` bump that any
  new setting requires.
- `.task.php` joins the autoloader's scan regex, its suffix-stripping regex and
  `spl_autoload_extensions()`. The file-list cache is keyed on the scan roots,
  not the regex, so an existing install picks the new extension up when the
  300-second TTL expires — no flush, no migration.
- `packages/service` is not carried by `copybacktrunk.sh`; deploying the runner
  is a separate step from deploying the web tier.
- The runner is itself a new candidate for the "active but silent" failure it
  is designed to make visible. Decision 4 is not optional.
- Plugin authors get periodic work with no new privilege and no root.
- `plugins.pRunfile` stays dead. This decision does not revive it; the column
  should be dropped in a later schema step rather than repurposed, so that no
  install carries a value written under the old, unimplemented meaning.
