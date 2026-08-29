# Retention runs in a daemon named for retention

## Status

accepted

Reverses the placement decided in [ADR 0021](0021-the-audit-trail.md) Decision 9
and restated in [ADR 0023](0023-activity-is-a-view-and-retention-is-a-registry.md)
Decision 4. The registry, the batch bound, the audit-before-delete refusal and
the `0 = keep forever` default are all unchanged — this ADR moves the caller and
nothing else.

## Context

ADR 0021 needed somewhere to run a scheduled sweep and reasoned:

> it belongs in `FOGPluginRunner`, the existing non-root periodic daemon
> (ADR 0010), rather than a new one

That is a sound cost argument. FOG has eight daemons and seven of them run as
root; the plugin runner was the only non-root periodic one, and the sweep needs
a database connection and nothing else. A ninth unit — with an entry in
`$serviceList`, four settings rows, a log directory, and init scripts in four
trees — to issue one `DELETE` an hour did not look proportionate.

The sweep was placed above the `FOG_PLUGINSYS_ENABLED` gate so it would still
run with the plugin *system* switched off, and left below
`PLUGINRUNNERGLOBALENABLED`, with a comment calling that "honest": an operator
who turns the plugin runner off has turned off the process, so retention going
quiet with it was held to be the expected consequence.

**It is not honest, because nothing tells the operator.** The reasoning weighed
the cost of a unit file against the cost of a code path. What it never weighed
is what an administrator *reads*, and the name is the only thing most of them
will ever read:

- "FOGPluginRunner" says this daemon is for plugins. A site that installs none
  has every reason to disable it, in the UI or with `systemctl disable`, and
  every reason to believe that is a safe thing to do.
- Doing so silently stops pruning of `auditLog` (and its `auditChange` rows),
  `history`, `userTracking` and `taskLog`. Nothing in the UI, the log, the unit
  description or the setting's own help text says so.
- The failure has no symptom until the tables are large, at which point it looks
  like a bug in retention — a feature working exactly as written.

The decisive point is that **retention already has an off switch, and it is per
table**: `0` days means keep forever, which is the default and is Decision 7 of
ADR 0023. A second switch, unrelated to the feature, named after something else,
and undocumented, is not a configuration option. It is a trap.

There is also a compliance edge. `FOG_USERTRACKING_RETENTION_DAYS` governs
records naming which person signed in to which machine; ADR 0023 treats
shortening that window as a privacy control. A site that sets it to satisfy a
policy, and later disables a daemon named for plugins, is out of compliance with
nothing anywhere to say so.

## Decision

**Retention runs in `FOGRetentionRunner`, its own daemon, gated by
`RETENTIONGLOBALENABLED` and nothing else.**

1. **The daemon is named for what it does.** `packages/service/FOGRetentionRunner`
   plus unit/init scripts in all four trees (`packages/systemd`,
   `packages/init.d/{alpine,redhat,ubuntu}`), enrolled through `$initdRTfullname`
   in `$serviceList`. `RetentionRunner` holds the schedule and the log; the
   policy stays in `Retention`.

2. **It is the second non-root daemon.** Same `FOGWEBUSER` placeholder mechanism
   the plugin runner uses, rewritten by `installInitScript()`. It needs a
   database connection and nothing else, so it gets nothing else — and a sweep
   that issues `DELETE`s is the last thing that should run as root. The
   installer loop was already over every unit file, so this needed no new
   substitution.

3. **`RETENTIONGLOBALENABLED`, not `RETENTIONRUNNERGLOBALENABLED`.** The other
   three keys name the runner because they configure the process — its log, its
   tty, its cycle. This one names the feature because that is what it turns off.
   Somebody hunting for "how do I stop FOG deleting my logs" is looking for the
   second word.

4. **The sleep time IS the sweep interval.** The old `RETENTION_INTERVAL = 3600`
   was a second schedule held inside a loop that ran on a different one, so the
   setting, the log and `systemctl status` could disagree. Now
   `RETENTIONRUNNERSLEEPTIME` (default 3600) is the only knob, and lowering it
   genuinely raises the catch-up rate — one pass still removes at most
   `Retention::MAX_PER_PASS` rows per table, so the bound that keeps a first
   sweep from holding locks is untouched.

5. **Every pass writes a line, throttled while nothing changes.** `sweep()`
   deliberately writes no audit row for a table with nothing to remove, so
   before this there was no readable evidence the sweep had ever run. "Is
   retention actually running" is the question this whole change exists to make
   answerable, and a `tail` of `retention/fogretentionrunner.log` is the answer.
   The throttle exists because the sleep time is an administrator's to lower.

6. **Its own log subdirectory**, `retention/`, in `FOGLogPaths::FOG_SUBDIRS` and
   created by the installer. Not shared with `plugins/` — there is no privilege
   boundary between them to defend, since both run as the web user, but a
   retention log filed under `plugins/` would reintroduce the exact confusion
   this ADR exists to end.

## Consequences

Nine daemons instead of eight. That cost is real and was correctly identified in
0021; what was wrong was treating it as decisive against a cost paid by every
administrator who reads a name and believes it.

**One more daemon that can sit `active` doing nothing.** This is the genuine
risk (#815, #944, and the 1.5 FileDeleter that was wedged for ten months), and
Decision 5 is the answer to it: this daemon says something on every pass,
including when it finds nothing to do.

**No upgrade migration is needed, and nothing changes for a default install.**
The settings default to enabled, `installfog.sh` installs and enrolls the new
unit like any other, and the windows themselves are untouched. A site that had
deliberately disabled `FOGPluginRunner` to stop retention — which nothing
documented, so it is unlikely anyone did — starts pruning again on upgrade;
they now have a switch that says so.

**ADR 0021 Decision 9 and ADR 0023 Decision 4 are amended in place**, each with
a pointer here. Neither ADR moves in any other respect: the registry shape, the
`0 = keep forever` default, `audit.manage` as the gate, and Decision 10's
audit-before-delete refusal all stand exactly as written.

**The general rule this is an instance of**, and the reason it is worth an ADR
rather than a commit message: *a shared host for a piece of work inherits its
name to every operator who reads it.* Putting core behavior inside a component
named for something optional gives that behavior a second off switch nobody
documented — and the person who trips it will report a bug against the feature,
not against the placement.
