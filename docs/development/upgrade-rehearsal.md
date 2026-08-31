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
points a lab `config.class.php` at the container, and runs three starting points:

| database | from | profile | why |
|---|---|---|---|
| `reh_159` | schema 270 | `decade` | 1.5.9. Verified: `git show 1.5.9:packages/web/lib/fog/system.class.php` |
| `reh_1510` | schema 278 | `decade` | 1.5.10-era; the real `fog-1.5` install on the maintainer's box records 278 |
| `reh_site` | schema 278 | `site` | the site plugin holding real assignments — the migration whose failure looks like a working server |

`--keep` leaves the container up so the databases can be queried afterward,
which is usually the point: the report is where the investigation starts.

Not a CI test, deliberately. It wants a server it may `DROP DATABASE` on, a
writable copy of the web tree, and a minute per starting point. Its deliverable
is a failure list, not a green tick.

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
