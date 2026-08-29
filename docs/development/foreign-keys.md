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

**Step 380 widens the parent.** Both directions produce a legal constraint,
so the tiebreak is cost and reversibility rather than correctness: narrowing
`msModuleID` rebuilds a 56,000-row table and is only safe while no value
exceeds 8,388,607, whereas widening a 13-row table of ids is additive and
cannot lose anything. Nothing else in the schema references `modules.id`, so
the wider parent has no other consequence.

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
| 1 | Widen the five mismatched columns — **landed, schema step 380** | 3 | 0 | no | **yes** ×5 | mostly | done |
| 2 | Orphan sweep, with counts logged — **landed, schema step 381** | 12 | 0 | **yes, destructive** | no | **no** | done |
| 3 | Host-owned junctions and satellites — **landed, schema step 382** | 10 | 14 | no | no | yes | done |
| 4 | Identity: users, roles, userGroups, sites — **landed, schema step 383** | 12 | 21 | no | no | yes | done |
| 5 | Storage: groups, nodes, image/snapin assoc — **landed, schema step 384** | 3 | 5 | no | no | yes | done |
| 6 | ~~`deletemass()` gains `storagegroup`/`storagenode` cases~~ — **not needed; step 5 proved it** | — | 0 | — | — | — | done |
| 7 | Sentinel `0` → `NULL` + the PHP that writes it | 5 | 0 | **yes** | **yes** ×7 | **no** | **yes** |
| 8 | Configuration references (SET NULL / RESTRICT) | 8 | 16 | no | no | yes | **yes** |
| 9 | Tasks and work | 4 | 12 | no | no | yes | **yes** |

Then, in `fog-plugins`, one commit per plugin (location, ou, windowskey,
ldap, oidc) — 22 constraints, no core change, each releasable on its own.

### Steps 1 and 2 as they landed

`FOG_SCHEMA` went 379 → 381.

**Step 380** widens the five columns, guarded on `information_schema`
`DATA_TYPE` so a server that already matches does no work — `MODIFY COLUMN`
rebuilds a table, so it is not free even when it changes nothing.

**Step 381** sweeps, and it sweeps **CASCADE relationships only**. That is
the whole of the semantic: a CASCADE child has no existence independent of
its parent, so a row whose parent is already gone is one the system has
already decided it does not keep. Everything else is left alone — a RESTRICT
orphan means a host points at a missing architecture, not that the host is
junk, and those are repointed by steps 7 and 8. Audit and history rows are
never swept (ADR 0021). Plugin tables sweep at plugin install, not here.

Order is computed from the frozen relationship list rather than hand-sorted,
because a hand-sorted list is exactly what an editor gets silently wrong. It
records a per-table count to the PHP error log and one `auditLog` row with
the totals.

Measured on the aged 5,000-host fixture, cloned from a real install:

```
step 380  five columns widened, AUTO_INCREMENT preserved on modules.id
step 381  6,521 rows deleted across 12 tables

          groupMembers 219   hostMAC 303      snapinAssoc 214
          printerAssoc 4     moduleStatusByHost 3732
          siteHostMembers 1  inventory 219    nfsGroupMembers 2
          tasks 1762         snapinJobs 17
          multicastSessionsAssoc 4            snapinTasks 44

then, with every CASCADE relationship enabled:
          65 added, 1 refused
```

The ordering is visible in those numbers rather than asserted. The scan
found **one** direct orphan in `multicastSessionsAssoc` and **none** in
`snapinTasks`; the sweep deleted 4 and 44, because deleting the orphaned
`tasks` and `snapinJobs` first is what stranded the rest. A sweep in the
other order would have passed them over and the constraint would have
refused on rows the sweep itself created.

Both steps were mutation-tested against the same fixture, restored between
arms:

| Mutation | Result |
|---|---|
| comparator returns `0`, so the depth sort does nothing | 64 added, **2 refused** — `fk_multicastSessionsAssoc_tID` at 1452 |
| step 380 not run | 60 added, **6 refused** — exactly the five errno 150 type mismatches |
| neither mutation | 65 added, 1 refused |

**The one refusal in every arm is `fk_locationAssoc_laHostID`, and it is
correct.** `locationAssoc` belongs to the location plugin, and core must not
delete rows owned by a plugin that may not even be installed — Phase D's
direction rule, arriving as a measurement rather than as an argument. The
plugin sweeps it at install. That the run reports it and returns `true`
rather than aborting is the reconciler behavior Step 0 exists for.

Re-running either step is a no-op: the widening is guarded on the column
type, and the sweep finds nothing, logs nothing, and writes no second audit
row.

### Step 3 as it landed

`FOG_SCHEMA` 381 → 382. **The first foreign keys FOG has ever declared** — 14
across 10 tables (the sequencing table above said 15; the coherent group is
14, and the number is now taken from the map rather than from an estimate):

```
groupMembers        gmHostID -> hosts    gmGroupID -> groups
hostMAC             hmHostID -> hosts
snapinAssoc         saHostID -> hosts    saSnapinID -> snapins
printerAssoc        paHostID -> hosts    paPrinterID -> printers
moduleStatusByHost  msHostID -> hosts    msModuleID -> modules
inventory           iHostID -> hosts
hostScreenSettings  hssHostID -> hosts
hostAutoLogOut      haloHostID -> hosts
powerManagement     pmHostID -> hosts
greenFog            gfHostID -> hosts
```

All CASCADE, and **nothing an admin can see changes**: `deletemass()`
already deletes every one of these when a host goes. What it buys is the
path that *forgets*.

**Why a schema step exists at all, given the reconciler.** This is the piece
the survey did not answer and it decides the shape of steps 4–9.
`SchemaReconciler::reconcile()` applies constraints after every update run —
but that run has to happen, and `DatabaseManager::init()` returns early
unless `mySchema < FOG_SCHEMA`. A group that is only an `enabled` flip in a
PHP file reaches a server that is already up to date exactly never. So each
group is **a map flip plus an indexed step**, and since the step has to
exist to move the count, it may as well be the thing that does the work and
says so in the replay log. Pass 4 of `reconcile()` was extracted into
`SchemaReconciler::applyConstraints()` for the step to call; the reconcile a
few lines later in `SchemaUpdaterPage::update()` then finds them present and
plans nothing. That reconcile is the standing repair for a constraint
dropped by hand or lost to a restore — not the mechanism that lands one.

Measured on the clone of a real install, in three arms:

| Arm | Result |
|---|---|
| Step 382 **before** the sweep | 12 landed, **2 refused** (`fk_moduleStatusByHost_msHostID`, `fk_inventory_iHostID`, both 1452), step returned true and the update was not aborted |
| Sweep, then 382 again | the other 2 land — 14/14 |
| 382 a third time | plans nothing |

That first arm is the property the whole phased rollout rests on, and it is
not visible from the planner: a server holding an orphan this release did
not anticipate gets its constraint refused, logged with a pointer at
`bin/fk-orphan-scan.php`, and its upgrade finishes.

**The behavior, proven by raw SQL with no PHP anywhere in the path:**

```
DELETE FROM hosts WHERE hostID=105;

           macs  mods  grps  inv  taskLog
  before      2    12     1    1        8
  after       0     0     0    0        8
```

`taskLog` keeps all 8 rows. That is ADR 0021 and ADR 0031 agreeing: the
associations go, the record of what the host did stays. The non-host side
works the same way — deleting a `groups` row took its `groupMembers` with
it.

And the phasing is real rather than nominal. On the same database, deleting
a host left its `tasks` row behind, because `tasks.taskHostID` is group 7
and is still disabled. `deletemass('host')` deletes it in PHP, so the UI
path is unchanged; a raw `DELETE` is not a supported path and will stop
leaking when step 9 lands.

Cost at fleet scale, on the aged 5,000-host fixture (56,532-row
`moduleStatusByHost`), each timing including a full FOG boot:

| Step | Time |
|---|---|
| 380 widen | 0.47s |
| 381 sweep | 0.34s |
| 382 fourteen `ADD CONSTRAINT` | 2.96s |

**Two gates were added and both were made to fail before being trusted.**
`tests/foreign-key-map.test.php` now names the 14 relationships that are
expected to be on — flipping an extra one or turning one off both go red,
and the list must be edited in the same commit that enables a group.
`tests/foreign-key-reconcile.test.php` drives `applyConstraints()` against a
fake database that *fails* the `ALTER`, which is the only way to tell the
reporting path from the applying one: both return true. Making it return the
error, and removing the first-line cap on the reason, each turn it red.

Also corrected here: the comment in `Route::deletemass()` claiming InnoDB
recomputes `AUTO_INCREMENT` as `MAX(id)+1` on restart. Measured during the
survey — MariaDB 10.5.29 and 11.8.8 both persist it across a clean restart
and a `SIGKILL`, and have since 10.2.4. The id-reuse hazard the comment
describes is real on MySQL 5.7 and older and after a restore into a rebuilt
table, so the cleanup stays; only the mechanism was wrong.

### Step 4 as it landed

`FOG_SCHEMA` 382 → 383. 21 constraints across 12 tables (the sequencing
table said 9/22; the group is 12/21, taken from the map). All CASCADE.

```
siteHostMembers       shmSiteID -> sites    shmHostID -> hosts
siteGroupMembers      sgmSiteID -> sites    sgmGroupID -> groups
siteUserMembers       sumSiteID -> sites    sumUserID -> users
siteUserGroupMembers  sugmSiteID -> sites   sugmUserGroupID -> userGroups
siteRoleGrants        srgSiteID -> sites    srgRoleID -> roles
siteUserGroupGrants   suggSiteID -> sites   suggGroupID -> userGroups
roleUserAssoc         ruaRoleID -> roles    ruaUserID -> users
roleUserGroupAssoc    rugRoleID -> roles    rugGroupID -> userGroups
rolePermissions       rpRoleID -> roles
userGroupMembers      ugmGroupID -> userGroups   ugmUserID -> users
apiTokens             atUserID -> users
userAuths             uaUserID -> users
```

**This is the group where a leftover row is an access decision**, which is
why it goes second rather than later. `Route::deletemass()` already argues it
for the site tables in its own comments — a membership row left by a deleted
host can put an unrelated *new* host into that site, and a stale grant "leaks
a whole population" rather than one object. Those cleanups stay; this makes
them true for the paths that never call `deletemass()`.

**One behavior addition, and it is what makes this group worth more than
tidiness.** `userAuths` is **not** in `deletemass('user')`'s list. That table
holds live remember-me credentials — `uaSelectorHash`, `uaPasswordHash`, an
expiry — so deleting a user leaves a working persistent-login row behind
today.

It is not directly exploitable. `ProcessLogin` verifies both hashes and then
does `new User($userauth->userID)` and requires `isLoggedIn() && isValid()`,
so a deleted owner fails closed. What it *is* exposed to is **id reuse** —
the same hazard the site-membership cleanup is written against, and the one
whose mechanism this branch just corrected. If the id is later handed to a
new account, a surviving cookie authenticates its holder **as that account**.
`deletemass('user')` deletes `apiTokens` for exactly this reason and its
comment says so: *"an orphaned API token is a live way in belonging to an
account that no longer exists."* This does for `userAuths` what that entry
does for tokens, in the one place no call site can skip. No PHP change
accompanies it — a redundant delete would be a second thing to keep in step
with the first.

Everything else here pins what `deletemass()` already does: role →
`rolePermissions` + the two role associations; usergroup →
`userGroupMembers` + `roleUserGroupAssoc`; site → its four membership lists
and the grant map; user → `roleUserAssoc`, `userGroupMembers`, `apiTokens`.

On the live 1.6 database all 21 are clean — 0 orphans, no type or collation
difference — and step 381 had already swept the class anyway.

**Behavior, by raw SQL with no PHP in the path:**

```
DELETE FROM users WHERE uId=110    roles 4->0  groups 1->0  tokens 1->0
                                   userAuths 1->0  sites 1->0
DELETE FROM users WHERE uId=111    auditLog row about that user: 1 -> 1
DELETE FROM roles WHERE rID=1      perms 1->0  users 4->0  siteGrants 1->0
DELETE FROM sites WHERE siteID=1   users 1->0  hosts 1->0  grants 0->0
```

### The fresh-install path, exercised for the first time

Worth its own heading because nothing had ever run it.
`tests/schema-executes.test.php` replays the indexed steps with a plain PDO
and **skips the closures** — its own output says "32 closure step(s)
skipped" — so no data migration, and since ADR 0031 no constraint step, had
ever executed against a database built from scratch. The failure that would
live there is ordering: a constraint declared before the seed rows it
validates have landed.

`scripts/background_scripts/replay_schema_fresh.php` runs every step,
closures included, against an empty database. It gets past
`DatabaseManager::init()`'s redirect by stamping `schemaVersion` at
`FOG_SCHEMA` first, which is a lie to the boot gate and to nothing else.

```
steps 383, statements 990, tolerated 3, FAILED 0
tables 70, foreign keys 35
```

And it reports refusals rather than swallowing them, proven by enabling a
relationship with a known type mismatch (`taskLog.taskStateID`,
`mediumint(9)` against `int(11)`):

```
  refused fk_taskLog_taskStateID: ... errno: 150 ...
```

`FAILED` correctly stays 0 there — a refused constraint is not an update
failure, by design.

Cost at fleet scale (5,000 hosts): step 383 takes **1.54s** to declare its 21
constraints, against 4.56s for step 382's 14, because 382 validates the
56,532-row `moduleStatusByHost` and the identity tables are tens of rows.

### Step 5 as it landed, and why step 6 does not exist

`FOG_SCHEMA` 383 → 384. Four constraints across two tables — the smallest
group and the one the survey's orphan counts actually named.

```
imageGroupAssoc   igaStorageGroupID -> nfsGroups   igaImageID -> images
snapinGroupAssoc  sgaStorageGroupID -> nfsGroups   sgaSnapinID -> snapins
```

It originally declared a fifth, `nfsGroupMembers.ngmGroupID -> nfsGroups` ON
DELETE CASCADE, and that was wrong. See *Step 385: correcting a constraint
that had already landed* below — the measurement immediately after this
paragraph is what exposed it.

Unlike groups 1 and 2 this one does **not** pin behavior FOG already has.
`deletemass()` has no `storagegroup` or `storagenode` case and neither model
overrides `destroy()`, so deleting a storage group cascaded to nothing in
every path including the UI. This supplies the behavior, in the one place
that cannot be skipped.

**Measured, raw SQL, with the constraints on:**

```
DELETE FROM nfsGroups WHERE ngID=1

                       before   after
  nfsGroupMembers          1       0     cascaded -- WRONG, see step 385
  imageGroupAssoc         29       0     cascaded
  snapinGroupAssoc         4       0     cascaded
  nfsFailures              1       1     audit, never constrained
  tasks (taskNFSGroupID)  33      33     RESTRICT + sentinel, group 5
  multicastSessions       15      15     RESTRICT + sentinel, group 5
```

**Step 6 was going to add `storagegroup`/`storagenode` cases to
`deletemass()`. It is not needed, and landing step 5 first is what proved
it** — which is exactly the reason the sequencing put them in this order.

- For a storage **group**, everything such a case would delete is the two
  association tables above, and the database now deletes them on every path.
  A PHP copy would be a second thing to keep in step with the first, for no
  behavior. (`nfsGroupMembers` is not one of them — under step 385 its nodes
  are detached, not deleted.)
- For a storage **node** there is nothing to delete at all. Every reference
  to `nfsGroupMembers` in the map is either RESTRICT (`multicastSessions.
  msSenderNode`, `tasks.taskNFSMemberID`, `tasks.taskLastMemberID`,
  `location.lStorageNodeID`) or audit (`nfsFailures.nfNodeID`). A
  `deletemass('storagenode')` case would have an empty `$removeItems`.
  Confirmed by deleting a node and watching nothing move.

What *will* need PHP is group 5, not this one: once
`tasks.taskNFSGroupID` and `multicastSessions.msNFSGroupID` become RESTRICT,
deleting a storage group with live work pointing at it is **refused** at
1451 where today it succeeds. That is a handled-path question and it belongs
with the step that creates it.

The step numbers are left as they are rather than renumbered — every commit
message and cross-reference already uses them.

### Step 385: correcting a constraint that had already landed

`FOG_SCHEMA` 384 → 385. No new group. This step removes
`fk_nfsGroupMembers_ngmGroupID`, which step 384 should never have created,
and it exists because the reconciler could not previously remove anything.

**The wrong call.** The map classed `nfsGroupMembers.ngmGroupID` as a
*satellite* of `nfsGroups` and gave it ON DELETE CASCADE, alongside
`inventory.iHostID` and the rest. A storage node is not a satellite. It
carries its own hostname, credentials, root/FTP/snapin paths, interface,
bandwidth limit, max clients and enable flag — none of which is recoverable
from the group — and `StorageGroup::removeNode()` detaches one by writing `0`
into `ngmGroupID` without deleting anything. "Belongs to no group" is a state
FOG creates on purpose and the Storage Node list still renders.

So under the CASCADE, deleting a storage group would have silently destroyed
every one of its nodes' configuration. The measurement in the step 5 section
above shows it happening (`nfsGroupMembers 1 → 0, cascaded`) and it was read
at the time as the constraint working.

**Two things it broke, both verified against a server, not reasoned about:**

```
UPDATE nfsGroupMembers SET ngmGroupID = 0 WHERE ngmMemberName='probe-node'
ERROR 1452 ... CONSTRAINT `fk_nfsGroupMembers_ngmGroupID`
```

`StorageGroup::removeNode()` issues exactly that statement. Detaching a
storage node through the UI was broken from step 384 onward.

And step 381's orphan sweep listed the same relationship, so it *deleted*
detached nodes as though they were dangling junction rows. Tom's own 1.6
server carries one — `fognode1.lan`, enabled, `ngmGroupID = 0`. The sweep
would have destroyed it. That relationship is now removed from the sweep's
frozen list, with the reasoning inline.

**Why this needed a reconciler change.** `planConstraints()` read constraint
*names* out of `information_schema` and skipped any name it found. The name
is `fk_<child>_<column>`; it does not encode ON DELETE. So correcting an
entry's action was a permanent no-op on every server that had already applied
the old one — the map said SET NULL, the database said CASCADE, and nothing
would ever have reconciled the two. That makes ADR 0031's central claim, that
the map is normative, simply false.

`constraintSnapshot()` now returns the whole declaration — referenced table,
referenced column and `DELETE_RULE` — and `planConstraints()` has three
outcomes per relationship instead of two:

| Database | Map | Plan |
|---|---|---|
| no constraint of that name | wants one | `ADD CONSTRAINT` |
| has one, declaration matches | wants it | nothing |
| has one, declaration differs | wants one | `DROP` then `ADD` |
| has one | retired (`enabled` false, or `action` none) | `DROP` |

It will only ever drop a constraint carrying the name
`constraintName()` generates for a relationship the map lists. One an
administrator added by hand does not carry that name and is untouched — a
gate pins that, and it goes red if the pass is changed to iterate the
database's constraints rather than the map's.

**What the relationship is now:** `config`, ON DELETE SET NULL, disabled
until the sentinel conversion makes the column nullable, so it lands with
group 5. Deleting a storage group will then *detach* its nodes — which is
what `removeNode()` already means, is what the UI can undo, and is the only
option that does not destroy configuration.

**Verified:**

```
fk385 (a clone of the post-384 database)
  before  40 constraints, ngmGroupID = CASCADE
  step 385 ran: ALTER TABLE `nfsGroupMembers` DROP FOREIGN KEY ...
  after   39 constraints, ngmGroupID = ABSENT
  re-run: no statements, still 39            (idempotent)
  UPDATE ... SET ngmGroupID = 0: succeeds    (removeNode works again)

fresh replay, closures included
  steps 385, statements 992, tolerated 3, FAILED 0
  tables 70, foreign keys 39
```

Three new gate families in `tests/foreign-key-reconcile.test.php`, each
mutation-proven: reverting the comparison to name-only turns 3 red, removing
the DROP turns 5 red, and dropping constraints the map does not name turns 7
red.

**The general lesson, worth more than the specific fix:** classifying a
relationship is not a clerical step. `satellite` and `config` differ only in
whether the child can outlive the parent, and getting it wrong writes a
destructive rule into the database that no PHP path can override. The test
for `satellite` is not "is it a small table hanging off a big one" — it is
*does anything in FOG deliberately detach one and leave it alive?* For
storage nodes there is a method whose name is literally `removeNode()`.

### Step 386: the `0` sentinel becomes NULL

`FOG_SCHEMA` 385 → 386. Ten columns across six tables. The only genuinely
irreversible step besides the sweep, and the last one before the remaining
constraint groups.

**What was actually there.** The sequencing table estimated "5 tables, 7
columns", counting relationships with *observed* sentinel rows. Reading the
schema rather than the data gives 18 columns that either hold a `0` sentinel
or are declared `NOT NULL` under a `SET NULL` action. They are not one
population, so the step needed a rule rather than a list.

**The rule used:** a column converts when FOG can actually produce a `0` in
it — rows that hold one today, *or* a code path that writes one. Not a
census. Two installs is a snapshot, and the columns holding no zeros today
are exactly the ones where a rare path would put one tomorrow.
`multicastSessions.msState` is the case that proves it: zero rows hold a `0`
on either install, and three separate call sites set it to `0` at session
creation, because a session that has not started has no state.

| Converts | Evidence |
|---|---|
| `hosts.hostImage` | rows on both installs; `RegisterClient`, `Image::destroy`, `Route`'s image delete |
| `images.imageOSID` | 22 of 22 images on the 1.5 install |
| `scheduledTasks.stImageID` | rows |
| `tasks.taskImageID` | rows |
| `tasks.taskNFSGroupID` | rows |
| `tasks.taskNFSMemberID` | rows |
| `tasks.taskLastMemberID` | rows |
| `multicastSessions.msSenderNode` | rows; `MulticastManager`, `MulticastTask` |
| `multicastSessions.msState` | `Group`, `Host` and `imagemanagement` write 0 at creation |
| `nfsGroupMembers.ngmGroupID` | rows; `StorageGroup::removeNode()` |

Deliberately **not** converted, and staying `NOT NULL`:
`images.imageTypeID`, `images.imagePartitionTypeID`,
`scheduledTasks.stTaskTypeID`, `multicastSessions.msNFSGroupID`,
`fileDeleteQueue.fdqStorageGroupID`, `fileDeleteQueue.fdqState`,
`tasks.taskStateID`, `tasks.taskTypeID`, `snapinJobs.sjStateID`,
`snapinTasks.stState`. No row holds a `0` and no code path writes one. "No
type" and "no state" are not states a task or an image can legitimately be
in, so `NOT NULL` plus RESTRICT is the stronger declaration — and that is a
group 5 decision about a constraint, not a conversion.

> **Carried forward to group 5.** `tasks.taskStateID` and
> `snapinTasks.stState` are *not* in their model's
> `$databaseFieldsRequired`, so `save()`'s optional-`*id` branch writes `0`
> for an empty value. Under a RESTRICT constraint that becomes a runtime
> 1452 on a path that works today. Either add them to the required list or
> make them nullable; it belongs with the constraint, not here.

**Nullable column + required in the model** is the pairing worth naming.
`images.imageOSID` becomes nullable so the 22 OS-less images an upgrade
brings across can be represented and constrained, while `osID` stays in
`Image::$databaseFieldsRequired` so `save()` still refuses to create a *new*
image without one. The database tolerates the history; the ORM prevents new
instances of it.

**The manifest edit is behavior, not documentation.** `save()`'s GH-1245
branch asks `FOGBase::columnIsNullable()` whether to write `null` or `0` for
an empty optional `*id`, and that reads `commons/schema-expected.php` — not
the server. A manifest still saying `NOT NULL` keeps FOG writing `0` into a
column the server made nullable, with nothing in any log to show for it.
Proven by mutation: reverting `hosts.hostImage` in the manifest alone, server
unchanged, turns two of the six live checks red.

**PHP changed in the same commit.** Nine sites wrote the sentinel; six now
write `null` and three were already correct in intent:

```
StorageGroup::removeNode()     storagegroupID => 0    -> null
Image::destroy()               imageID => 0           -> null
Route (image delete)           imageID => 0           -> null
RegisterClient                 ->set('imageID', 0)    -> null
MulticastManager               ->set('sendernode', 0) -> null
MulticastTask                  sendernode => 0        -> null
Group / Host / imagemanagement ->set('stateID', 0)    -> null
```

`senderpid` stays `0` on purpose: it is a unix process id, and `0` there
means "no process", not "no row".

No reader changes were needed, and that is worth knowing rather than
assuming. `FOGController::get()` returns `''` for a missing key — it tests
`isset()`, which is false for NULL — so every `if (!$this->get('imageID'))`,
`(int)$this->get(...)` and `> 0` reads exactly as it did with `0`. A grep for
SQL-level `= 0` comparisons against the ten columns found none, and no ORM
filter passes `0` for any of them.

**Verified against three databases:**

```
fresh replay, closures included
  steps 386, statements 993, tolerated 3, FAILED 0
  tables 70, foreign keys 39
  all ten columns nullable

upgrade, clone of the live 1.6 install
  hostImage        79 zeros ->  79 NULL, 0 left      (86 rows)
  stImageID         1        ->   1                  (1 row)
  taskImageID       3        ->   3                  (50 rows)
  taskNFSGroupID   16        ->  16
  taskNFSMemberID   6        ->   6
  taskLastMemberID 20        ->  20
  msSenderNode     16        ->  16                  (16 rows)
  ngmGroupID        1        ->   1                  (4 rows)

upgrade, clone of the live 1.5 install
  hostImage      2076 zeros -> 2076 NULL, 0 left     (2079 rows)
  imageOSID        22        ->  22                  (22 rows)
```

Every count matches its NULL count exactly and no column keeps a zero.

**Live write paths, against a real server** (`verify_sentinel_null_writes.php`):
`save()` on a host with no image writes NULL; an explicit `->set(imageID,
null)` stays NULL; and `HostManager::update(['imageID' => null])` — which
never goes through `save()` and binds through `PDODB::_bind()` with
`PDO::PARAM_STR` — writes SQL NULL rather than `0` or `''`. That last one was
worth confirming rather than assuming: it is the same door the
boolean-binds-as-`''` defect came through.

**Two map entries were missing and nothing could say so.** The coverage gate
in `tests/foreign-key-map.test.php` matched `/ID$/`, so it could not see a
reference spelled `msState` or `fdqState`. Widening it to `/(ID|State)$/`
immediately found `multicastSessions.msState` and `fileDeleteQueue.fdqState`,
both live references to `taskStates` and both absent from the map — 103
relationships became 105. Widening the pattern was the fix rather than adding
the two by hand: a gate blind to a whole naming convention will miss the next
column in it too. `snapins.sPackType` and `users.uType` join the allowlist
with reasons; `moduleStatusByHost.msState` is a `varchar(1)` enable flag and
the type filter already skips it.

### A consequence of the mechanism, to be handled at group 5

`applyConstraints()` takes no filter: it declares whatever the map has
enabled. So on an upgrade that crosses several of these steps, **the first
constraint step in the run applies them all** and the later ones are
no-ops — visible in the fleet timings, where step 382 went from 4.56s to
9.86s as groups 2 and 3 were added while 383 and 384 dropped to ~0.1s.

That is deliberate and mostly a feature: a server that somehow missed one
step's constraints picks them up at the next. But it has one edge that is
**not** yet handled, and naming it here is cheaper than rediscovering it:

> Group 5's constraints are RESTRICT on columns that still hold a `0`
> sentinel, and the sentinel becomes NULL in the step *before* them. On an
> upgrade from below 382, the first constraint step would try them while the
> columns are still `NOT NULL DEFAULT 0`, get refused, and log a failure on
> every such upgrade — before the later step applies them correctly.

Nothing breaks (a refusal is reported, not fatal, and the correct step still
lands them) but a loud log line on every upgrade is the kind of noise that
makes people stop reading the log. The fix belongs with the step that
introduces the ordering: either `planConstraints()` learns to skip a
relationship whose column is not yet nullable, or the sentinel conversion and
its constraints share a step. Decided when the numbers are in front of us,
not now.

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
- **Steps 7–9 carry every behavior change** and are last. 7 was expected to
  be the risky one: it changes column nullability *and* the PHP that writes
  those columns. It got its own lab run against clones of both real installs
  plus live write-path probes, and the risk turned out to sit somewhere other
  than expected — no *reader* needed changing, because `get()` returns `''`
  for a NULL just as it did for a missing key, while the *manifest* turned
  out to be load-bearing behavior rather than documentation. See step 386
  above.
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
