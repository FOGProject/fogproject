# Foreign keys: survey, decisions, and the order they land in

Status: **proposal**. Nothing in this document has been applied to a schema
step. The scanner and the candidate map (`bin/fk-orphan-scan.php`,
`commons/schema-constraints.php`) are the only code that ships with it.

The convention this establishes is
[ADR 0031](../adr/0031-referential-integrity-is-declared-in-the-database.md).
This file is the working detail behind it: what was measured, on what, and in
what order the work can land.

---

## The finding in one paragraph

All 70 core tables and all 18 plugin tables are InnoDB. Not one declares a
foreign key. Referential integrity lives entirely in
`Route::deletemass()`'s `switch ($classname)` — a hand-maintained list of
"when you delete an X, also delete these" — plus a handful of `destroy()`
overrides. That list is correct where somebody remembered to extend it and
absent where nobody did, and **the gaps are not theoretical: they are
measurable on both of the databases surveyed.** 87 relationships are
candidates for a real constraint. 70 of them can be declared today against a
live database with no data change at all.

## What was measured, and against what

Two databases, cloned into a throwaway MariaDB 11.8 container by
`scripts/background_scripts/clone_fog_dbs_for_fk_survey.php` (not in this
repo — it is a local lab tool):

| Copy | Source | Rows |
|---|---|---|
| `fog` | the 1.6 install at `/var/www/html/fog-1.6` | 86 hosts, 50 tasks, 88 tables |
| `fog15` | the 1.5.10 install at `/var/www/html/fog-1.5` | 2079 hosts, 0 tasks, 65 tables |

Both are maintainer machines, not a decade-old production fleet, so **every
orphan count below is a floor, not an estimate.** They are useful because
they are real-shaped — ids assigned by real use, rows deleted by real UI
paths — and because a fresh install has no orphans by construction.

A relationship reporting `0` on a table holding `0` rows proves nothing, so
the scanner prints the row count it checked alongside the orphan count. That
column exists because the alternative is the survey's own version of a
silent empty result.

---

## Phase A — the orphans

### What the constraint would reject today

Applied against an untouched copy of `fog`, as 87 real `ALTER TABLE ... ADD
CONSTRAINT` statements:

```
70 added, 17 refused
```

The 17 refusals have exactly two causes, and neither is "a decade of drift"
in the way one might expect:

| Cause | Count | What it is |
|---|---|---|
| errno 150 | 5 | child column is `mediumint(9)`, parent is `int(11)` |
| error 1452 | 12 | the child holds a value naming no parent row |

Of the 12 with data problems, **7 are the `0` sentinel and not orphans at
all.** Applying the two mechanical fixes — widen the five columns, sweep the
four genuine orphan sets, convert the sentinels to `NULL` — takes the same
run to:

```
86 added, 1 refused
```

The one remaining refusal is instructive: sweeping the orphaned
`multicastSessions` row orphaned its `multicastSessionsAssoc` child in turn.
Cleanup order is load-bearing, which is an argument for doing the sweep as a
step with its own report rather than as a preamble to an `ALTER`.

### Orphan counts, per relationship

Only relationships with something wrong are listed. `rows` is how many child
rows were checked; `sentinel` counts rows holding the `0` "no reference"
value, which is a different defect with a different fix.

**`fog` — 86 hosts, current 1.6 schema**

| Child | Column | Parent | rows | orphans | sentinel |
|---|---|---|---|---|---|
| `moduleStatusByHost` | `msHostID` | `hosts` | 1164 | **144** (12 ids) | – |
| `taskLog` | `taskID` | `tasks` | 54 | 21 (11 ids) | – |
| `taskLog` | `logHostID` | `hosts` | 54 | 13 (2 ids) | – |
| `hosts` | `hostImage` | `images` | 86 | 0 | **79** |
| `tasks` | `taskLastMemberID` | `nfsGroupMembers` | 50 | 1 | 20 |
| `tasks` | `taskNFSGroupID` | `nfsGroups` | 50 | 1 | 16 |
| `multicastSessions` | `msSenderNode` | `nfsGroupMembers` | 16 | 0 | 16 |
| `tasks` | `taskNFSMemberID` | `nfsGroupMembers` | 50 | 1 | 6 |
| `tasks` | `taskImageID` | `images` | 50 | 0 | 3 |
| `multicastSessions` | `msNFSGroupID` | `nfsGroups` | 16 | 1 | 0 |
| `multicastSessionsAssoc` | `tID` | `tasks` | 4 | 1 | – |
| `inventory` | `iHostID` | `hosts` | 5 | 1 | – |
| `nfsGroupMembers` | `ngmGroupID` | `nfsGroups` | 4 | 1 | – |
| `nfsFailures` | `nfTaskID` | `tasks` | 1 | 1 | – |
| `nfsFailures` | `nfHostID` | `hosts` | 1 | 1 | – |
| `scheduledTasks` | `stImageID` | `images` | 1 | 0 | 1 |

**`fog15` — 2079 hosts, 1.5.10 schema**

| Child | Column | Parent | rows | orphans | sentinel |
|---|---|---|---|---|---|
| `hosts` | `hostImage` | `images` | 2079 | 0 | **2076** |
| `moduleStatusByHost` | `msHostID` | `hosts` | 1183 | **156** (12 ids) | – |
| `images` | `imageOSID` | `os` | 22 | 0 | 22 |
| `taskLog` | `taskID` | `tasks` | 7 | 7 (2 ids) | – |
| `hostMAC` | `hmHostID` | `hosts` | 128 | 2 | – |
| `userAuths` | `uaUserID` | `users` | 24 | 2 | – |

No table anywhere near "thousands". The largest single number is 156 rows
across 12 deleted hosts, in a table of 1183. **Nothing here changes what the
fix looks like** — a sweep of this size is a `DELETE ... WHERE NOT IN` with a
count logged, not a migration strategy.

### What a faithful cascade actually leaves behind

The counts above come from two maintainer test installs, which have no
decade of buildup. To get volume and age, `bin/fk-lab-fixture.php` scales a
clone to 5000 hosts (58,968 `moduleStatusByHost`, 39,312 tasks, 39,416
`taskLog`) and then ages it by deleting through **three real mechanisms**,
never by writing an orphan directly:

| Mechanism | What it models |
|---|---|
| `bare` | a plain `DELETE` with no cleanup — the pre-cascade era, and any path today that does not reach `deletemass()` |
| `storage` | exactly what FOG does now for a storage group or node: delete the row, clean up nothing |
| `cascade` | today's `deletemass('host')` list, applied faithfully |

Splitting the resulting orphans by which mechanism produced them is the one
result here worth reading as a finding, because **the `cascade` arm is a test
of the current code, not of invented data**:

| Table | via `bare` | via `cascade` |
|---|---|---|
| `hostMAC` | 303 | **0** |
| `moduleStatusByHost` | 3732 | **0** |
| `groupMembers` | 219 | **0** |
| `snapinAssoc` | 214 | **0** |
| `inventory` | 219 | **0** |
| `tasks` | 1762 | **0** |
| `userTracking` | 226 | 300 *(deliberate)* |
| `taskLog` | 1808 | 2400 *(deliberate)* |

**Today's host cascade is complete.** Applied faithfully it leaves nothing
orphaned in any of the thirteen tables it covers; the only rows remaining are
`userTracking` and `taskLog`, and both are deliberate and documented in the
code (`UserTrack.php:121`, `UserTracking.php:63`, and step 341).

This changes the argument for this work, and for the better. The case is
**not** "`deletemass()` is broken and a constraint fixes it" — it is not
broken. The case is that `deletemass()` is the *only* thing there is, so
every path that does not reach it leaves everything behind, and there is no
mechanism that makes a new table join the list. A constraint is what makes
the existing cascade unforgettable and covers the paths that never had one.

### The two defects the counts actually name

**1. Deleting a storage group or a storage node cascades to nothing.**
`Route::deletemass()` has a `case` for host, group, image, module, printer,
snapin, user, role, usergroup and site. It has **no case for `storagegroup`
or `storagenode`**, and neither `StorageGroup` nor `StorageNode` overrides
`destroy()`. Every orphan in `fog` outside `moduleStatusByHost` traces to
this — storage group `3` and storage node `4` were deleted at some point, and
`nfsGroupMembers`, `multicastSessions` and three columns of `tasks` still
point at them:

```
orphan storage group ids   0,3          live storage groups   1,6,2
orphan node ids in tasks   4            live nodes            7,2,3,1
```

**2. The site-membership hazard is real; the reason written beside it is
not.** `Route.php` already sweeps the four site membership tables and the two
grant tables after the switch, and its comment gives the rationale as:

> InnoDB recomputes AUTO_INCREMENT as MAX(id)+1 on restart, so ids are
> reused

That was measured and it does **not** hold on any MariaDB FOG supports.
`AUTO_INCREMENT` persisted across a clean restart *and* across `SIGKILL` plus
crash recovery on both 10.5.29 and 11.8.8 (MariaDB has persisted the counter
since 10.2.4; it is MySQL ≤ 5.7 that recomputes). The cleanup is still
correct and should stay — a grant row naming a deleted role is wrong on its
own terms, and it will still collide with a reused id after a restore, an
explicit-id seed, or a plugin reinstall — but the comment overstates the
mechanism and is worth correcting in the same pass. This matters because the
same sentence is likely to be cited as justification for the next such fix.

---

## Phase B — cascade semantics

The maintainer's four-way split is the right one, with one correction and one
addition. Taking them in order.

### The rule that decides most of it

**Where FOG's PHP already implements a behavior, the constraint should pin
that behavior, not change it.** `Route::deletemass()` is a specification of
intent that has been maintained for years; where it says "delete these too",
`ON DELETE CASCADE` is that statement made true in the database and changes
nothing an admin can observe. Where it says "set this to 0", `SET NULL` is
the same. Only where PHP does nothing today is there a decision to make, and
that is where behavior changes get called out.

This is deliberately conservative. It buys the whole point of the exercise —
a path that forgets can no longer leak — without a single new refusal an
admin has to learn about, except in the three places named below.

### Junction and association rows — CASCADE. Agreed.

34 relationships across 17 tables. All CASCADE. This is the class that leaks
today and the class where PHP already agrees.

### 1:1 and 1:N satellites — CASCADE. Agreed.

`inventory`, `hostScreenSettings`, `hostAutoLogOut`, `powerManagement`,
`greenFog`, `apiTokens`, `userAuths`, `nfsGroupMembers`, and the plugins'
`LDAPGroups`, `OIDCGroups`, `oidcIdentity`.

`apiTokens` deserves a note: `deletemass('user')` already deletes it, and the
comment there records why — *"an orphaned API token is a live way in
belonging to an account that no longer exists"*. A constraint is a second
net under a fix that was already thought worth two of them.

### Audit, history and the ADR 0021 trail — NO CONSTRAINT. Strongly agreed.

**The audit tables must not take a foreign key of any kind — not CASCADE, not
RESTRICT, not SET NULL — and the id stays a plain column.**

This is not a judgment call and it does not need re-deriving, because the
schema already argues it in two places:

- **Step 341** copied `hostName`, `taskTypeName` and `imageName` onto
  `taskLog` rows precisely so a failure stays searchable after the host is
  gone, and states the alternative it rejected: *"Refusing to delete a host
  because it once failed to image is a worse product than losing a host
  name."* That is RESTRICT, rejected on the merits.
- The **`auditChange` create** says it in as many words: *"Deliberately NOT a
  foreign key... one here would make the retention sweep's DELETE order load
  bearing — exactly the kind of thing that fails on a restore onto a server
  with different settings, in a way that looks nothing like its cause."*

Walking the three options for completeness, since the survey was asked for
one:

| Option | Why not |
|---|---|
| CASCADE | Deleting a host would delete the record of the host being deleted. This is the audit trail destroying its own reason to exist. |
| RESTRICT | Inverts the dependency: a diagnostic artifact would block operational cleanup, and to be consistent it would have to block *host* deletion too, since that is the path that removes tasks. |
| SET NULL | Loses the id while keeping the row, which is what a LEFT JOIN already gives you. It buys nothing and adds a write to the delete path. |

**No constraint, id kept as a plain column, identity denormalized onto the
row** — which is what step 341 already built. The 21 orphaned `taskLog` rows
in the survey are not a defect; they are the design working.

The nine relationships in this class: `taskLog` ×3, `auditChange.acAuditID`,
`userTracking.utHostID`, `nfsFailures` ×4. `nfsFailures` is included on the
same reasoning — it is a failure log, and a node failure record that vanishes
when the node is deleted cannot answer "why did we remove that node".

`auditLog.alSubjectID`, `history.hSubjectID` and `auditChange.acSubjectID`
are additionally polymorphic (the target table is named by a sibling
`*SubjectType` column), so no constraint is expressible even if one were
wanted.

### Referenced configuration — mostly SET NULL, not RESTRICT

This is where the proposed split needs correcting. The question asked was
"probably RESTRICT... say whether SET NULL is better anywhere". **SET NULL is
better in every place FOG already does it in PHP, and the code says exactly
where.**

`deletemass('image')` does this today:

```php
self::getClass('HostManager')->update($findWhere, '', ['imageID' => 0]);
```

An image assigned to hosts *can* be deleted; the hosts are unassigned. That
is `ON DELETE SET NULL` written in PHP. Making it RESTRICT would break a
workflow that has worked for a decade: you would have to unassign every host
before deleting an image, and the error the admin gets would be an InnoDB
1451, not an explanation.

| Relationship | Action | Behavior change? |
|---|---|---|
| `hosts.hostImage` → `images` | **SET NULL** | none — `deletemass` already zeroes it |
| `tasks.taskImageID` → `images` | **SET NULL** | none — active tasks are canceled, finished ones deliberately kept |
| `hosts.hostArchID` → `architectures` | **SET NULL** | none; already nullable |
| `images.imageArchID` → `architectures` | **SET NULL** | none; already nullable |
| `images.imageOSID`, `imageTypeID`, `imagePartitionTypeID` | **RESTRICT** | yes — see below |
| `scheduledTasks.stTaskTypeID` → `taskTypes` | **RESTRICT** | yes |
| `scheduledTasks.stImageID` → `images` | **RESTRICT** | yes |
| `multicastSessions.msNFSGroupID`, `msSenderNode` | **RESTRICT** | yes |
| `fileDeleteQueue.fdqStorageGroupID` | **RESTRICT** | yes |
| `location.lStorageGroupID`, `lStorageNodeID` (plugin) | **RESTRICT** | yes |

The RESTRICT rows are all cases where PHP does nothing today, so the current
behavior is "the reference silently dangles". Each is a real behavior change
and each is defensible on the same ground: the child cannot function without
the parent. An image whose `imageTypeID` names nothing cannot be deployed; a
scheduled task whose image is gone can never run and will never say so; a
`fileDeleteQueue` entry naming a deleted storage group is work the replicator
cannot do. Turning a silent permanent failure into a refusal at the moment of
deletion is the trade, and it is the right one — but it is a change, and it
should land in its own step with its own release note.

**The storage-group and storage-node cases are the ones that most need
saying out loud.** Today deleting a storage group succeeds and quietly
strands its nodes, its image and snapin associations, its queued deletions
and any task pointing at it. Under this proposal it either cascades (nodes
and associations) or refuses (tasks, sessions, queue). Either is better than
what happens now; refusing is better than cascading for the ones that
represent work in flight.

### Tasks and active work — CASCADE for the host, RESTRICT for the rest

The question was whether deleting a host with a running task should be
blocked or should cascade. **It should cascade, because that is what FOG
does today and the alternative was already considered and rejected.**

`deletemass('host')` puts `task` in `$removeItems`. Step 341's comment states
the principle directly: *"deleting a host takes its history with it because
the subject of that history is gone"*. So `tasks.taskHostID` is
`ON DELETE CASCADE`, and the constraint is a no-op on the existing path.

This was measured rather than assumed, and the measurement is the reason the
answer is not RESTRICT. With `taskHostID` declared RESTRICT on the trial
database, the very first host tried could not be deleted at all:

```
ERROR 1451: Cannot delete or update a parent row: a foreign key constraint
fails (`fogtrial`.`tasks`, CONSTRAINT `fk_tasks_taskHostID` ...)
```

— and that host's tasks were *finished*. RESTRICT here does not mean "you
cannot delete a host that is imaging right now"; it means "you cannot delete
a host that has ever imaged", which is not the rule anybody wants. Blocking
deletion of a host with a *live* task is a legitimate product decision, but
it is a PHP guard with a readable message, not a foreign key — the database
cannot tell a queued task from a finished one without the constraint knowing
about `taskStateID`, which no foreign key can express.

The rest of the class:

| Relationship | Action |
|---|---|
| `tasks.taskHostID` → `hosts` | CASCADE |
| `tasks.taskStateID` → `taskStates`, `taskTypeID` → `taskTypes` | RESTRICT |
| `tasks.taskNFSGroupID`, `taskNFSMemberID`, `taskLastMemberID` | RESTRICT |
| `snapinJobs.sjHostID` → `hosts` | CASCADE |
| `snapinJobs.sjStateID` → `taskStates` | RESTRICT |
| `snapinTasks.stJobID` → `snapinJobs` | CASCADE |
| `snapinTasks.stSnapinID` → `snapins` | CASCADE (matches `deletemass('snapin')`) |
| `snapinTasks.stState` → `taskStates` | RESTRICT |

### `ON UPDATE`

`ON UPDATE RESTRICT` everywhere. FOG never updates a primary key; declaring
CASCADE would license a rewrite nobody intends and would make an accidental
`UPDATE ... SET id =` propagate silently across the schema.

### What cannot take a constraint at all

Five polymorphic columns, recorded in the map so the survey states why rather
than leaving them looking overlooked:

| Column | Target chosen by |
|---|---|
| `scheduledTasks.stGroupHostID` | `stIsGroup` — a host id or a group id |
| `auditLog.alSubjectID` | `alSubjectType` |
| `history.hSubjectID` | `hSubjectType` |
| `auditChange.acSubjectID` | `acSubjectType` |
| `ldapUserGrant.lugTargetID`, `oidcUserGrant.ougTargetID` | `*TargetType` — role or usergroup |

`virus.vHostMAC` is a sixth: it names a MAC address rather than an id, and
`hostMAC.hmMAC` carries no unique index (a host may legitimately hold several
rows), so there is no key to point at.

---

## Phase C — the mechanics, each one measured

### 1. Column types must match exactly — 5 do not

InnoDB requires identical type, signedness and (for string keys) charset and
collation. Every candidate is an integer key, so **collation is not a factor
anywhere in this work** — the 2026-08 collation pin (#1240, step 342, all 73
tables on `utf8mb3_general_ci`) leaves nothing to reconcile here. The
scanner checks collation regardless and reports none.

Five children are narrower than their parents:

| Child column | Is | Parent is | Table rows (`fog` / `fog15`) |
|---|---|---|---|
| `snapinGroupAssoc.sgaSnapinID` | `mediumint(9)` | `int(11)` | 4 / 4 |
| `snapinGroupAssoc.sgaStorageGroupID` | `mediumint(9)` | `int(11)` | 4 / 4 |
| `imageGroupAssoc.igaImageID` | `mediumint(9)` | `int(11)` | 29 / 22 |
| `imageGroupAssoc.igaStorageGroupID` | `mediumint(9)` | `int(11)` | 29 / 22 |
| `moduleStatusByHost.msModuleID` | `int(11)` | `mediumint(9)` | 1164 / 1183 |

All five are tiny tables and none is on a hot path. The first four widen the
child; the fifth is the odd one out — the child is *wider* than the parent,
and the right fix is arguably to widen `modules.id` instead, since a table
of ids should not be narrower than the column that references it. Either
works; narrowing `msModuleID` was what the trial ran and it succeeded on both
copies. `modules` holds 13 rows, so `mediumint` will not run out either way.

This is a column change, so it is `MODIFY COLUMN` on a rebuilt table. On
these row counts it is instantaneous. **`hosts`, `tasks` and `taskLog` are
untouched by any of it**, which is the one thing that would have made this a
risk item.

### 2. The reconciler path breaks if constraints live in the CREATE strings

This is the finding that determines the mechanism, and it was measured
against the harness rather than reasoned about.

`tests/schema-executes.test.php` runs two independent databases. DB 2
executes the `create` strings from `commons/schema-expected.php` into an
**empty** database, in manifest order — modelling `SchemaReconciler::plan()`.
Manifest order is not dependency order: `apiTokens` precedes `users`,
`groupMembers` precedes `hosts`. Simulating that with constraints inlined
into the `CREATE TABLE` statements:

```
70 create statements, 34 failed
```

34 of 70 tables cannot be created, all errno 150. Running the same creates
without constraints and then adding the constraints as a second pass of
`ALTER` statements:

```
creates: 70 (0 failed)   constraints: 65 (5 failed)
```

— zero create failures, and the only refusals are the five type mismatches
above, which step 1 removes.

**So: constraints are added by `ALTER TABLE`, after every table exists, and
never inline in a `CREATE`.** Two consequences that must be handled in the
same commit as the first constraint step:

- `bin/schema-manifest.php generate` must strip `CONSTRAINT ... FOREIGN KEY`
  clauses out of the `create` strings it snapshots, or the next regeneration
  bakes them in and breaks DB 2.
- `SchemaReconciler` needs a constraint pass that runs **after** its table
  and column passes, with its own errno tolerance (121 duplicate name, 150
  incompatible) mirroring the existing `$_skiperrs` arrangement. Without it
  the reconciler is blind to a missing constraint, which is exactly the
  divergence it exists to close.

### 3. Existing DROP / TRUNCATE steps — two will break, but not on replay

Measured against the fully-constrained trial database:

| Statement | Result | Where it is |
|---|---|---|
| `TRUNCATE \`taskStates\`` | **ERROR 1701** | `schema.php:1527` |
| `TRUNCATE \`taskTypes\`` | ERROR 1701 (same shape) | `schema.php:1338` |
| `DROP TABLE \`users\`` | **ERROR 1451** | `schema.php:3407` |
| `DROP TABLE \`groupMembers\`` | succeeds — nothing references it | `schema.php:3186` |
| `RENAME TABLE \`users\` TO ...` | **succeeds** — MariaDB follows the constraint | `schema.php:3408` |
| `ALTER TABLE \`hosts\` CONVERT TO CHARACTER SET ...` | succeeds | step 342 |

**None of these breaks the upgrade replay**, and the harness says so:
`schema-upgrade-replay.test.php` passes unchanged today, and its own docblock
explains why it would keep passing — a server upgrading from version N runs
steps `[0,N)` then `[N,end)`, the same statements in the same order as a
fresh install. The `TRUNCATE`s are at indices around 40 and 60; the
`DROP TABLE users` rebuild is around 150. Every constraint step lands after
379. The drops therefore always run *before* any constraint exists.

Baseline harness state, run on MariaDB 11.8.8 before any of this work:

```
ok  schema executes on 11.8.8-MariaDB-ubu2404
      schema.php steps (fresh install path)  297 statements, 71 tables
      schema-expected.php (reconciler path)   70 statements, 70 tables
ok  every server from version 0 to 378 reaches FOG_SCHEMA (379)
ok  704 checks passed          (schema-manifest-consistent)
```

**The forward-looking rule is the real output of this item**, and it belongs
in the ADR: once constraints exist, no future step may `DROP` or `TRUNCATE`
a referenced parent table. `RENAME` remains safe. A step that must rebuild a
parent has to drop the constraints, rebuild, and re-add them — which is
precisely why they need a naming convention.

### 4. Backup and restore already handle it

`Schema::importdb()` wraps the whole restore in `SET FOREIGN_KEY_CHECKS=0` /
`START TRANSACTION` and restores it at the end
(`src/Items/Schema.php:270`, `:375`). That is what makes the pre-drop on
`CREATE TABLE` in the same method work against a constrained schema, and it
means a restore is unaffected. This was already correct; it did not need
changing and must not be regressed.

### 5. The sweep must run in topological order

Found by running it wrong at scale, which is the only way this shows up. A
sweep that deletes `multicastSessionsAssoc`'s orphans and *then* deletes
orphaned `tasks` strands a fresh set of `multicastSessionsAssoc` rows behind
it; the same inversion applies to `snapinTasks` and `snapinJobs`. On the
5000-host fixture that took an otherwise clean run to:

```
85 added, 2 refused      # fk_multicastSessionsAssoc_tID, fk_snapinTasks_stJobID
```

Both refusals were 1452 on rows the sweep itself had just created. Reordering
so a parent's own orphans go first — and the child's sweep runs after, seeing
what that stranded — takes the same run to:

```
87 added, 0 refused
```

So the sweep step is written in dependency depth order, not table order, and
the three pairs that matter (`tasks` before `multicastSessionsAssoc`,
`multicastSessions` before `multicastSessionsAssoc`, `snapinJobs` before
`snapinTasks`) carry a comment saying why. This is also an argument for the
sweep being its own step: entangled with an `ALTER`, the 1452 arrives with
nothing to say which of the two statements caused it.

### 6. Cost at fleet scale

Measured on the 5000-host fixture, MariaDB 11.8 in a container:

| Work | Time |
|---|---|
| Widen all five columns (largest is 56,532 rows) | 0.53s |
| Full orphan sweep, all 15 statements | 0.19s |
| Sentinel `0` → `NULL`, 7 columns incl. 4 on 37k `tasks` rows | 1.31s |
| **All 87 constraints** | **14.1s** |

Nothing here needs a maintenance window, and nothing here is the reason to
phase the work — the phasing is for reviewability, which was the stated goal.

### 7. Naming convention

```
fk_<childTable>_<childColumn>
```

`fk_groupMembers_gmHostID`, `fk_tasks_taskHostID`,
`fk_oidcGroupUserGroupAssoc_ogugUserGroupID` (42 characters — the longest,
well inside the 64-character identifier limit).

Chosen over `fk_<child>_<parent>` because a child may reference the same
parent twice (`tasks.taskNFSMemberID` and `tasks.taskLastMemberID` both name
`nfsGroupMembers`) and the pair would collide. Child plus column is unique by
construction, so the name is derivable from the map without a lookup, which
is what lets CI check it. It is greppable (`grep -rn fk_tasks_` finds every
constraint on tasks), droppable without consulting `information_schema`, and
sorts with its table.

### 8. Rollback: which steps are reversible

| Step kind | Reversible | How, and what it costs |
|---|---|---|
| `ADD CONSTRAINT` | **yes, fully** | `ALTER TABLE x DROP FOREIGN KEY fk_...`. No data touched. |
| `MODIFY COLUMN` widening `mediumint`→`int` | **yes** | narrowing back is safe only while no id exceeds 8388607. On these tables it never will, but the reverse is not unconditionally safe and should not be advertised as such. |
| Sentinel `0` → `NULL` | **no** | the information "this was 0" and "this was NULL" is the same information afterward; there is nothing to distinguish. Reversing means writing 0 back everywhere, which is fine in practice but is not a restoration. |
| Orphan sweep (`DELETE`) | **no** | the rows are gone. This is why the sweep gets its own step with a logged count, and why it runs before the constraint rather than as part of it. |

**What a server does if step N adds a constraint and step N+1 fails.** FOG's
updater applies steps in order and saves the version after each; a failing
statement throws and the version is not advanced past it, so the server is
left with step N applied and sitting on the schema page. Because
`ADD CONSTRAINT` is fully reversible and *additive*, that state is safe and
re-runnable: the constraints from step N are already correct, and re-running
N is a duplicate-name error (121), which belongs on the tolerance list
alongside 1050/1060/1061 exactly as the existing `$skiperrs` handles
re-applied `ADD COLUMN`.

The genuinely one-way steps are the sweep and the sentinel conversion. Both
must therefore be ordered **before** the constraints that depend on them and
**never in the same step**, so that a constraint failure never leaves a
half-converted column.

---

## Phase D — plugins, and the direction rule

18 plugin tables ship in `FOGProject/fog-plugins`. All 18 clone cleanly into
the survey and 22 of the 87 candidate relationships live in them.

### Direction is the whole rule

A plugin table is created on install and dropped on uninstall, so:

- **A plugin table may reference a core table.** This is the direction that
  prevents the orphan class: `locationAssoc.laHostID → hosts` means deleting
  a host removes its location assignment, which today nothing does.
- **A core table must never reference a plugin table.** Uninstall would fail
  with 1451 on a table core does not own, leaving the plugin half-removed and
  the admin with an InnoDB error naming a table they were trying to delete.
- **A plugin table may reference another table in the same plugin.** All of
  the LDAP and OIDC internal relationships are of this kind.
- **A plugin table referencing *another plugin's* table** is not proposed and
  should be prohibited: uninstall order between two plugins is not something
  either one controls.

No core table references a plugin table today, and the survey found no
candidate that would. The rule is therefore a rule for the future, not a
change.

### What happens when a host is deleted while the plugin is uninstalled

Nothing bad, and the reasoning is worth writing down because the intuition
points the other way.

Uninstall **drops** the plugin's tables. There is no row left to orphan, so
the accumulation the question anticipates cannot happen — reinstalling
creates the tables empty. This is materially different from the
`siteUserMembers` case, where the table is core, always present, and simply
not swept.

The residual risk is narrower and real: a plugin that **disables** rather
than uninstalls (`plugins.pState` vs `pInstalled` are separate columns) keeps
its tables and its rows while its hooks stop firing. Any cleanup that plugin
did via `DELETEMASS_API` stops happening, and orphans accumulate for exactly
as long as it stays disabled.

**A declared constraint fixes this outright and is the strongest argument for
doing plugin tables at all.** The constraint is a property of the table, not
of the hook, so it keeps working while the plugin is disabled — which is
precisely the window where the PHP cleanup does not.

### Should plugin install sweep orphans before creating its constraints?

**Yes, and it is not optional — the install fails without it.**

This is not a policy question. `ADD CONSTRAINT` against a table holding
orphans returns 1452, so a plugin that creates its table, populates it,
is uninstalled-with-data-kept or upgraded, and then tries to add a constraint
will simply fail to install. The sweep is the precondition for the statement,
the same way it is for core.

The shape it should take:

1. Create or upgrade the tables.
2. Sweep orphans, **counting and logging** what was removed. Silent is wrong:
   a plugin that deletes rows on install must be able to say how many.
3. Add the constraints, tolerating 121.

Step 2 is a no-op on a first install, which is the common case.

---

## Step 0 — the machinery (landed)

The reconciler drives the constraints, so steps 3-9 become a flag flip in
`packages/web/commons/schema-constraints.php` rather than hand-written SQL.
What that took:

- **The map moved into the deployed tree.** `bin/` is never deployed --
  `copybacktrunk.sh` rsyncs `packages/web/` only -- so a map the reconciler
  reads at runtime cannot live there. It is now
  `packages/web/commons/schema-constraints.php`, beside the manifest it
  works with, and `bin/fk-orphan-scan.php` and `bin/fk-lab-fixture.php` read
  it from there so the survey and the migration cannot drift apart.
- **Each entry gained `enabled`.** That is the phasing. The reconciler
  ignores anything still false, so flipping a group to `true` *is* the commit
  that adds it, and the diff for each step is exactly the constraints that
  step adds. Everything ships disabled today.
- **`SchemaReconciler::planConstraints()`**, a fourth pass after tables,
  renames and columns. Pure, like `plan()`, and separate from it so the two
  kinds of failure stay separable at execution.
- **`bin/schema-manifest.php` strips `CONSTRAINT ... FOREIGN KEY` out of what
  it snapshots.** Mutation-proven: with the strip removed, a regeneration
  from the constrained fixture put FK clauses into 34 tables and 34 of 70
  creates then failed. With it, 0 and 0.

### A constraint failure is reported, not returned

`reconcile()` returns an error string to abort the update. `ADD CONSTRAINT`
validates existing rows, so a server holding an orphan this release did not
anticipate gets 1452 -- and aborting there would strand it on `?node=schema`
over data that is otherwise intact, with no way out from the browser.

So constraint failures are collected into `constraintFailures()`, logged one
line each with a pointer to `bin/fk-orphan-scan.php`, and the update
proceeds. The distinction is real: a missing column breaks the code, a
missing constraint only means FOG is still relying on `Route::deletemass()`
alone, which is where it has been for a decade. What is not acceptable is
silence -- a constraint that can never apply and that nobody is told about is
worse than one that was never declared.

Proven against a lab database with the first group enabled and the sweep
deliberately not yet run:

```
constraints before: 0
  ... 10 x Schema reconcile: ALTER TABLE ... ADD CONSTRAINT ...
Schema reconcile: 2 foreign key(s) could not be added.
                  Run bin/fk-orphan-scan.php to find the rows.
Schema reconcile: fk_moduleStatusByHost_msHostID: ... 1452 ...
Schema reconcile: fk_inventory_iHostID: ... 1452 ...
constraints after:  10
reconcile returned: true
```

then, after sweeping the 144 + 1 orphan rows those two named:

```
constraints before: 10
constraints after:  12
reconcile returned: true
reported failures:  0
planned:            0
```

The loop closes and is idempotent: a second run plans nothing.

### The bug that only a real server could find

`reconcile()` returned early when the **structural** plan was empty:

```php
$plan = self::plan($manifest, $have);
if (!count($plan ?: [])) {
    return true;      // <- the constraint pass is below this
}
```

An up-to-date database -- missing no table and no column -- is the normal
case, so on almost every server the constraint pass would never have run,
`reconcile()` would have returned `true`, and the constraints would simply
never have appeared. Nothing would have said so.

`planConstraints()` was fully unit-tested and correct throughout; the defect
was entirely in whether it was reached. It is now pinned by a test that
drives `reconcile()` through the harness's fake database with a structural
plan that is genuinely empty, and asserts the `ALTER` was issued --
reintroducing the early return turns it red.

### What CI gates

`tests/foreign-key-map.test.php` (248 checks) gates the **map**, not the
constraint list -- gating the constraints would need a live server, and the
thing that actually gets forgotten is not the constraint but the *decision*.
Every `*ID` integer column in the manifest that is not its table's primary
key must be classified; `audit` and `poly` are answers, absence is not. The
heuristic yields exactly one false positive across all 70 tables
(`multicastSessions.msSenderPID`, a process id), so the allowlist is one
named entry rather than a list that could quietly absorb real ones.

It also holds the manifest constraint-free, rejects a real `ON DELETE` on an
`audit` or `poly` entry, and refuses to let a relationship be enabled while
its types still differ or its column is still `NOT NULL` with a sentinel.

`tests/foreign-key-reconcile.test.php` (19 checks) gates the pass.

Every assertion in both was proven by mutation -- ignoring `enabled`,
deriving the action from the class, dropping the already-exists skip,
dropping the absent-table skip, restoring the early return, letting a FK
through the generator, giving an audit row an action, adding an unclassified
id column, and enabling over a type mismatch each turn the suite red with the
message that names the cause.

## Sequencing

Nine steps. Each is a separate commit, each leaves the tree green, and each
carries its own orphan report where it removes data. Sizes are given by what
the step contains.

| # | Step | Tables | Constraints | Data change | Column change | Reversible | Lab run |
|---|---|---|---|---|---|---|---|
| 1 | Widen the five mismatched columns | 3 | 0 | no | **yes** ×5 | mostly | no |
| 2 | Orphan sweep, with counts logged | 6 | 0 | **yes, destructive** | no | **no** | yes |
| 3 | Host-owned junctions and satellites | 10 | 15 | no | no | yes | yes |
| 4 | Identity: users, roles, userGroups, sites | 9 | 22 | no | no | yes | yes |
| 5 | Storage: groups, nodes, image/snapin assoc | 6 | 9 | no | no | yes | yes |
| 6 | `deletemass()` gains `storagegroup`/`storagenode` cases | — | 0 | no | no | yes | yes |
| 7 | Sentinel `0` → `NULL` + the PHP that writes it | 5 | 0 | **yes** | **yes** ×7 | **no** | **yes** |
| 8 | Configuration references (SET NULL / RESTRICT) | 8 | 16 | no | no | yes | **yes** |
| 9 | Tasks and work | 4 | 12 | no | no | yes | **yes** |

Then, in `fog-plugins`, one commit per plugin (location, ou, windowskey,
ldap, oidc) — 22 constraints, no core change, each releasable on its own.

Notes on the ordering:

- **Step 1 before everything** because five constraints cannot be declared
  until it lands, and it touches no data.
- **Step 2 is the only destructive step and it is early**, deliberately: it
  is the one that needs a real lab run and a rollback plan, and it should not
  be entangled with anything else. Its output is a count per table in the
  update log.
- **Step 6 is PHP, not schema**, and it goes *after* the storage constraints
  rather than before. Once step 5 lands, deleting a storage group without
  the PHP cascade fails loudly with 1451 instead of silently orphaning —
  which is a bug report, so the PHP follows immediately. Landing 6 first
  would be equally correct and would avoid the window; the argument for this
  order is that step 5 is what proves the PHP gap is real.
- **Steps 7–9 carry every behavior change** and are last. 7 is the risky one:
  it changes column nullability *and* the PHP that reads those columns, and
  `FOGController` treats `0`, `''` and `null` differently on both branches
  (see the `get()` divergence). It wants its own lab run against a real
  install, not just a schema replay.
- **Nothing here touches `dev-branch`.** 1.5 has no `SchemaReconciler`, no
  manifest and no constraint pass, and steps 263–276 already diverge. This is
  a 1.6 convention.

## The rehearsal

The whole sequence has been run end to end against the aged 5000-host
fixture, in the order above and using the `ON DELETE` action each
relationship declares in `commons/schema-constraints.php`:

```
step 1  widen the five mismatched columns
step 2  ordered orphan sweep
step 7  sentinel 0 -> NULL
        87 added, 0 refused
```

and the resulting database behaves as decided:

```
host 4829 before -> macs=1 mods=12 tasks=8 inv=1 grp=1
host 4829 after  -> macs=0 mods=0 tasks=0 inv=0 grp=0
taskLog kept (audit, by design): 8

hosts pointing at image 1: 90
after deleting it, hosts left dangling: 0 / unassigned to NULL: 90

DELETE FROM nfsGroups WHERE ngID=<in use>
  ERROR 1451 ... CONSTRAINT `fk_location_lStorageGroupID`
```

A host delete cascades to every satellite and junction with no PHP involved
and keeps its audit rows; an image delete unassigns its hosts rather than
refusing; a storage group still referenced by live work is refused instead of
silently orphaning.

## Reproducing the survey

```
php bin/fk-orphan-scan.php /var/www/html/fog-1.6
php bin/fk-orphan-scan.php --host=127.0.0.1 --port=13320 --db=fog \
    --user=root --pass=... --all --format=csv
```

Read-only; it issues nothing but `SELECT`. `--all` includes the
relationships with nothing wrong, which is most of them.

To rebuild the aged fixture (DESTRUCTIVE, and it refuses port 3306):

```
php bin/fk-lab-fixture.php --host=127.0.0.1 --port=13320 --db=<lab> \
    --user=root --pass=... --hosts=5000 --yes-destroy
```
