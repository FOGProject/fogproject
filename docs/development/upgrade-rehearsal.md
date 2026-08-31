# Rehearsing a 1.5 -> 1.6 upgrade against dirty data

`tests/schema-upgrade-replay.test.php` covers the step arithmetic and the
version an upgrade lands on. `tests/schema-executes.test.php` covers whether the
statements run, against an empty database on three engines. Both are the right
CI tests and neither can see the risk ADR 0031 introduced.

FOG now declares foreign key constraints, and MySQL validates existing data when
one is added: `ALTER TABLE ... ADD CONSTRAINT` over a table holding an orphan
returns 1452 and the constraint is simply never created. An empty database has
no orphans, so both existing tests stay green on data that would refuse every
constraint in the map. Ten years in which nothing enforced referential integrity
is exactly the condition they cannot model.

`bin/upgrade-rehearsal.php` is that missing pass. `bin/upgrade-rehearsal-lab.sh`
is the environment it needs.

## The trap this is built to avoid

**The seed must go into a schema that PREDATES the constraints, and then be
upgraded.** Seeding a current 1.6 schema with old-looking rows proves nothing:
the foreign keys are already there, the violating rows cannot be inserted, and
every assertion passes vacuously.

Two things enforce that here, because it is the failure mode that would make the
whole exercise worthless:

- `build --to=N` replays `commons/schema.php`'s steps `[0, N)` and then **counts
  the foreign keys the database actually holds**, printing a warning if the
  number is not zero. A starting point that already enforces integrity is
  reported, not assumed away.
- `seed` prints `REFUSED` with the server's own error for any row the database
  rejects. A seed row that was never written looks exactly like an upgrade that
  cleaned it up, so a silent skip is the one outcome that must not be possible.

## Running it

```
bash bin/upgrade-rehearsal-lab.sh --keep
```

Stands up a MariaDB container on port 13399, copies the web tree **from the
committed HEAD** (not the working tree — runs must be reproducible against a
named commit, and this repo is worked on by more than one session at a time),
points a lab `config.class.php` at the container, and runs two starting points:

| database | from | profile | why |
|---|---|---|---|
| `reh_1510` | schema 278 | `decade` | 1.5.10-era; the real `fog-1.5` install on the maintainer's box records 278 |
| `reh_site` | schema 278 | `site` | the site plugin holding real assignments — the migration whose failure looks like a working server |

`--keep` leaves the container up so the databases can be queried afterward,
which is usually the point: the report is where the investigation starts.

The full matrix is not a CI test, deliberately. It wants a server it may
`DROP DATABASE` on, a writable copy of the web tree, and a minute per starting
point. Its deliverable is a failure list, not a green tick.

**One reduced starting point does run in CI**, and for a different reason:
`tests/upgrade-rehearsal-ci.test.sh` is a decay gate. This harness boots the
whole application, so any change to the autoloader, the config contract or the
schema runner breaks it — and nothing would say so until someone reached for it
before a release and found it dead. It runs 278/`decade` only, and asserts the
**known** result rather than a clean one: `tests/fixtures/upgrade-rehearsal-baseline.txt`
records that two constraints are expected to be missing. Asserting zero would be
a lie about the state of the code; asserting nothing would be a gate nobody can
fail. Asserting the baseline catches a fix that regresses, a seed that goes
vacuous, and a new failure appearing.

The baseline lives in this repository rather than in the workflow, so a change
that legitimately moves the numbers updates it in the same commit and the diff
is visible in review.

`makeconfig` is what makes that possible. `commons/config.class.php` is
generated and gitignored, so the lab script's copy-the-live-one approach could
only ever work on a machine that had already installed FOG. `makeconfig` writes
a lab config from the installer's own constant list, with inert placeholders
everywhere except the database block — those constants are not decoration, since
schema steps interpolate `STORAGE_HOST`, `STORAGE_FTP_USERNAME`, `WEB_ROOT` and
`TFTP_HOST` straight into their SQL. It refuses to overwrite any config that
does not already read `REHEARSAL_DB`, so it cannot be pointed at a real install's
config, which holds the only copy of the database and FTP passwords.

`reh_159` (schema 270, `decade`) **was** a third starting point and was removed
after being measured: it produced a byte-identical result to `reh_1510` on every
run. Steps 270-277 are two `globalSettings` text edits, two `nfsGroupMembers`
column additions and step 276's renames, and the decade seed touches none of
them, so the 1.5.9 starting point had nothing it could fail at that 1.5.10 did
not.

## The branch-divergence trap cannot be modeled from this build path

Read this before trying to give schema 270 a job again. Both obvious attempts
were built, run, and produce a fixture that **lies** — in opposite directions.

working-1.6 and dev-branch share step positions to 263 and diverge from 264, so a
1.5 database's `schemaVersion` counts against **dev-branch's** array and an
upgrade treats 264-277 as already applied — skipping step 276's renames
(`plugins` `pAnon1..4`, `multicastSessions` `msAnon3/4`). `SchemaReconciler`'s
first pass then creates the missing column from the manifest, and the manifest
declares that rename as `ENUM('0','1') NOT NULL DEFAULT '0'` — so the column
arrives with the type it always had, after the boolean conversion at step 368
has already gone by.

That is the mechanism behind both shipped shape-drift fixes: schema 400
(`msShutdown` stuck as `enum('0','1')`) and schema 399 (the site retirement gate
reading an empty `pLocation`).

**`build --to=N` cannot reproduce it.** It replays `[0, N)` from *this branch's*
`schema.php`, and working-1.6's own early steps already create the post-rename
names. Verified: a database built to 270 carries
`pIcon`/`pRunfile`/`pLocation`/`pDescription` and `msShutdown`, with no
`pAnon3`/`msAnon3` anywhere.

The obvious fix is to rename the columns *back* in the seed, the way the seed
already drops the `hostMAC` unique index before planting under it. It was built
and it fails at both available starting points:

| planted at | what happens | why it lies |
|---|---|---|
| schema 270 | the upgrade **replays step 276**, which renames `msAnon3` back to `msShutdown` at its proper position; step 368 then converts it normally. Shape drift 6, `msShutdown` correct. | The plant is undone by the very step it claims is skipped. The run exercises the NORMAL path and reports a pass, which is the vacuous case again. Proven by mutation: neutering step 400 changes nothing, because step 400 was never what fixed it. |
| schema 278 | the plant survives (276 is behind), and the upgrade **hard-fails at step 345** with `1054 Unknown column 'msShutdown'`, stranding it 57 steps short with 45 shape differences. | The real world does not do this. A real 1.5-origin database **has** `msShutdown` — the reconciler created it from the manifest, preserving the enum. Renaming the column away models a state that does not occur, and reports a catastrophic failure that is not real. |

The distinction that matters, and the one both attempts get wrong: the trap is
**"the rename step was skipped and the reconciler created the column afterward,
preserving its old type"**, not **"the column does not exist"**. Modeling it
faithfully needs the reconciler's create-from-manifest pass to run against a
version stamp that counts against the other branch's array — which is a
different harness from replaying `[0, N)` of this one.

Both fixes were therefore found against a real 1.5.10 dump, and that remains the
way to find the next one. A genuine 1.5.10 database is on the maintainer's box
(`/var/www/html/fog-1.5`, schema 278) and can be cloned into the same container.

## There is no collation seed, and that is a finding

The seed used to carry
`ALTER TABLE groupMembers CONVERT TO CHARACTER SET utf8mb4 ...` beside its
type-mismatch row, described as planting the errno 3780 InnoDB raises when the
two sides of a foreign key disagree on collation. It planted nothing:
`groupMembers` has three columns and all three are `int(11)`, so the convert
changed the table default and left every key column's collation `NULL`. Both of
its constraints landed on every run, and the rehearsal reported a clean pass for
a case it had never exercised.

Repointing it was not available either. Of the **101 enabled** relationships in
`commons/schema-constraints.php`, every column resolvable in the core manifest is
`int` or `mediumint`. The single string-typed entry in the whole map,
`virus.vHostMAC -> hostMAC.hmMAC`, is deliberately `'class' => 'poly',
'action' => 'none'` with no `enabled` key, and its two sides are `varchar(50)`
and `varchar(59)` — it could not be a foreign key even if someone enabled it.

So errno 3780 is unreachable **today**. That is a census rather than an
invariant, so it is pinned by `tests/fk-columns-are-integer-typed.test.php`:
enable a string-typed relationship and that test goes red and says the collation
seed is needed again.

## How faithful it is

The upgrade half is the shipped code path. `upgrade` runs the real
`commons/schema.php` steps through a runner that copies
`SchemaUpdaterPage::update()`'s loop exactly — its `skiperrs` list and its
`break 2` on the first hard failure — followed by the same
`SchemaReconciler::reconcile()` the page runs afterward. The constraint groups
apply through `SchemaReconciler::applyConstraints()` with their own sweeps in
front of them, as the steps do.

Three concessions, all to the CLI SAPI rather than to the upgrade:

- `DatabaseManager::establish()` redirects every request to `?node=schema` while
  the recorded version is below `FOG_SCHEMA`, and under CLI that redirect is a
  silent exit. The guard it honors reads `FOGBase::$querystring`, which cannot
  be set from CLI: `filter_input(INPUT_SERVER, ...)` returns NULL whatever
  `$_SERVER` holds, and `FOGBase::_init()` re-reads it on every construction.
  So the recorded version is parked at `FOG_SCHEMA` for the duration of the boot
  and restored before any command runs. The redirect exists to stop a human
  using a half-migrated UI; there is no human here.
- `Config::_initSetting()` — which defines `TFTP_HOST`, `WEB_ROOT` and the other
  constants the steps interpolate — runs only when the global `$node` is
  `schema`, and `Initiator::startInit()` overwrites that global from
  `filter_input` immediately before `new Config()`. It is called directly by
  reflection instead.
- The database is created with a plain PDO before the boot, because `PDODB`
  leaves `FOGBase::$DB` null when it cannot connect and the next call dies with
  "on null" rather than saying the database is missing.

The **starting** half is a replay of `[0, N)` from this branch's `schema.php`.
That is by construction the database a server sitting at schema N has, but note
it is *this branch's* rendering of those steps: `dev-branch` may have filled some
of the same indexes differently, so a byte-comparison against a real 1.5 dump is
not implied. A genuine 1.5.10 database is available on the maintainer's box
(`/var/www/html/fog-1.5`, schema 278) and can be cloned into the same container
to cross-check.

## Down-migration is not offered, here or anywhere

Some steps are lossy — the site plugin's tables are dropped once their data has
moved into core — so there is no general inverse to run. Going back is a restore
from the dump `backupDB()` took, which covers everything a down-migration would
at a fraction of the cost. See `docs/development/reverting-an-upgrade.md`.
