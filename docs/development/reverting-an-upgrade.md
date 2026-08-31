# Reverting a 1.6 upgrade back to 1.5

`bin/revertfog.sh` puts a server that upgraded to 1.6 back onto its pre-upgrade
1.5 database, web tree and FOS kernels. It restores **data**, not code: the last
thing it prints is a reminder to re-run `bin/installfog.sh` from a 1.5 checkout,
which is what rebuilds the vhost, TFTP tree and services against 1.5.

```
bin/revertfog.sh --list        # what is stored, and why anything is unusable
bin/revertfog.sh --dry-run     # every check, every action, nothing changed
bin/revertfog.sh               # do it, with a confirmation prompt
bin/revertfog.sh --yes         # do it unattended
```

## What it does, in order

Every precondition is checked **before** the first destructive act, so the
script can never stop a daemon or drop a table and only then discover there is
nothing to restore.

| # | Step | Skippable |
|---|---|---|
| 1 | Dump the current 1.6 database to `fog_sql_pre-revert_*.sql` | only if `mysqldump` is missing |
| 2 | Stop the FOG daemons (`stopInitScript`, so systemd and OpenRC alike) | no |
| 3 | Drop every table in the FOG database and restore the 1.5 dump | **no — this is the hard precondition** |
| 4 | Move the 1.6 web tree aside and restore the 1.5 one | yes, reported |
| 5 | Append the pre-GH-1120 key spellings to `.fogsettings` | yes, reported |
| 6 | Restore FOS kernels via `bin/restorekernel.sh --generation 1` | yes, reported |
| 7 | Start the FOG daemons | no |

Nothing is deleted. The 1.6 web tree is **moved** to
`fog_web_pre-revert_<timestamp>`, `.fogsettings` is copied to
`.fogsettings.pre-revert_<timestamp>` before a line is appended, and the 1.6
database is dumped before it is dropped.

## The three cases

**1. Clean revert after a successful upgrade.** Everything is present, every
step runs.

**2. Revert after an upgrade that failed partway.** This is the case the script
exists for. The schema updater does `break 2` on the first hard failure and
still stamps the version it reached, so the database is a valid but
half-migrated one and the web tree has already been replaced. Nothing in the
script requires a consistent schema: it drops the tables it finds and restores
the dump over the top, so a database stopped at step N of the remaining steps
reverts exactly like a complete one.

**3. No dump present.** `backupDB()` has silently skipped in the field before
(GH-314, and GH-1146 where a symlinked webroot meant it never ran). With no
usable dump the script **refuses and does nothing** — no daemon stopped, no
table dropped, no directory moved. It prints where it looked and, if candidate
files were found, why each was rejected.

## How it decides a dump is 1.5-era

Neither of the two obvious tests works, and both look like they should.

**The filename lies.** `backupDB()` names the file
`fog_sql_${version}_<timestamp>.sql`, and `$version` is read out of the
*checkout being installed* (`bin/installfog.sh`, the awk over
`packages/web/commons/version.php`). The pre-upgrade dump taken on the way to
1.6 therefore carries a **1.6 version in its name and a 1.5 database in its
body**. The filename is used to order candidates, newest first, and for nothing
else.

**The schema number does not separate the branches.** `schemaVersion`.`vValue`
is a count of applied elements of `$this->schema`, and the ranges overlap in
both directions:

- a fully patched 1.5.10 arrives carrying **287** — `count($this->schema)` on
  `dev-branch`, which is what `FOG_SCHEMA` equals there;
- working-1.6 counts 398 (`packages/web/src/Base/System.php`, `FOG_SCHEMA`),
  but a 1.6 upgrade that died early sits *below* 287.

There is no "1.6 range" to test against. See `SchemaReconciler`'s docstring for
the same fact from the other direction: the two branches fill the same schema
positions with different migrations, which is why that class exists at all.
`revertfog.sh` **reports** the count (it belongs in a bug report) and does not
gate on it.

**What actually separates them is structure only 1.6 creates.** The branches
share every schema position up to 263 and diverge from 264 on, so the earliest
trace a 1.6 upgrade can leave is step 264's `groups`.`groupInit`. A dump is
rejected if it carries any of:

- a 1.6-only table: `userAuths`, `roles`, `sites`, `userPrefs`, `savedFilters`,
  `savedFilterUserAssoc`;
- a 1.6-only column: `groupInit`, `pIcon`, `pDescription`, `msShutdown`;
- any `CONSTRAINT ... FOREIGN KEY` — 1.5 declares none, they arrived with
  ADR 0031.

It is also rejected if it is empty, has no `schemaVersion` table (not a FOG
dump), or is missing mysqldump-php's closing statement block (truncated).

A 1.6 database that died *before* step 264 has none of these markers and is
accepted — correctly, because such a database is structurally a 1.5 one. That
is the only residual ambiguity and it is benign by construction.

`--dump <path>` picks a specific file but is checked identically. **There is no
`--force`.** A 1.6 dump restored onto 1.5 code is a server that boots to a
schema page it can never satisfy.

The web-tree backup is checked the same way and for the same reason:
`configureHttpd()` *removes* an existing `fog_web_<ver>.BACKUP` before writing a
new one, so a second `installfog.sh` run after a failed upgrade replaces the 1.5
copy with the 1.6 one under an identical directory name. A backup counts as 1.5
only if it has `lib/fog/system.class.php` and no `src/Base/System.php`.

## Why there is no down-migration, and why nobody should propose one

Walking `commons/schema.php` backwards cannot work:

- **Steps are lossy by design.** Step 331 onward moves the site plugin's rows
  into core's `sites` and then drops `site`, `siteHostAssoc`, `siteUserAssoc`,
  `siteGroupAssoc` and `siteUserGroupAssoc`. Nothing records what was in them,
  so "undo" has nothing to read.
- **A step is a position, not an identity.** `vValue` is a count, and
  working-1.6 and dev-branch fill the same positions with different migrations
  from index 264 on. "Undo step 270" names two different migrations depending
  on which branch you ask.
- **The dump already covers everything a down-migration would**, including the
  rows, which no schema walk restores — at a fraction of the cost.

## What an admin must do BEFORE upgrading

The revert is only possible because of things that happen during the upgrade
itself. Check these first, and stop if any is missing.

1. **Confirm the pre-upgrade dump was written.** `backupDB()` prints
   `Backing up database....Done`. `Skipped` means there were no tables to dump;
   `Failed` means there is **no dump to roll back to**, and the installer says
   so and asks before continuing. Do not press Enter past that.
2. **Confirm it landed.** `ls -lt $DB_backup_path/fogDBbackups/` — the newest
   `fog_sql_*.sql` should be from this run and should not be zero bytes.
   `bin/revertfog.sh --list` answers the stronger question of whether it is
   restorable.
3. **Do not re-run `installfog.sh` after a failed upgrade before reverting.**
   The second run overwrites `fog_web_<ver>.BACKUP` with the 1.6 tree and adds
   a second dump that is 1.6-era. The database dump is still recoverable — the
   script picks the newest *usable* one, not simply the newest — but the 1.5
   web tree is gone and has to come back from a 1.5 checkout.
4. **Keep a 1.5 checkout.** `revertfog.sh` restores data; only 1.5's own
   `installfog.sh` puts 1.5 code back in service.
5. **Take your own backup as well.** `DB_backup_path` defaults to `/home/`, on
   the same machine and often the same disk as the database it is protecting.

## Related

- `bin/restorekernel.sh` — the FOS kernel/init generations, reused as step 6.
- `docs/development/upgrade-rehearsal.md` — rehearsing the upgrade before you
  run it for real.
- `docs/SUPPORTED_CUSTOMIZATIONS.md` — what survives an install either way.
