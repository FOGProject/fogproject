# Referential integrity is declared in the database, not remembered in PHP

## Status

accepted -- steps 0 through 5 implemented on `working-1.6`: the machinery,
schema 380 (column widening), schema 381 (the orphan sweep), schema 382
(host-owned junctions and satellites, 14 constraints), schema 383 (identity:
users, roles, user groups and sites, 21) and schema 384 (storage: groups,
nodes, image and snapin assoc, 5). 40 of 87 declared; the remaining 47 still
ship disabled and land group by group.

The planned step 6 -- `deletemass()` gaining `storagegroup`/`storagenode`
cases -- was dropped after step 5 measured it as unnecessary: the database
now cascades everything such a case would delete, and a storage node has no
CASCADE children at all. Sequencing 5 before 6 was what made that a
measurement rather than a guess.

Each group is a map flip **plus an indexed schema step**. That is not
ceremony: `DatabaseManager::init()` only sends an admin to the updater while
`mySchema < FOG_SCHEMA`, so a group that is only a flag flip reaches an
up-to-date server never. The step calls
`SchemaReconciler::applyConstraints()`; the reconcile after every update run
is the standing repair, not the mechanism.

The survey behind this, with every number and every measurement, is
[`docs/development/foreign-keys.md`](../development/foreign-keys.md).
`commons/schema-constraints.php` and `bin/fk-orphan-scan.php` ship with it so
the evidence is re-runnable.

The three behavior changes listed under Consequences -- the storage
group/node RESTRICTs, the scheduled-task RESTRICTs, and the `0` sentinel
becoming `NULL` in seven columns -- were put to the maintainer separately and
agreed on 2026-08-29, before the ADR itself was read. They are settled and
should not be reopened when the rest of this is reviewed; what remains open
is the convention, the classification and the sequencing.

## Context

All 70 core tables and all 18 plugin tables are InnoDB. Not one declares a
foreign key. `commons/schema-expected.php` has none, and `commons/schema.php`
mentions the phrase only in comments explaining why one was *not* used.

The archaeology is MyISAM. MyISAM parses `FOREIGN KEY` and silently ignores
it, so integrity had to be enforced in application code, and the move to
InnoDB was made for locking and crash safety rather than for constraints.
Nothing went back afterward.

What enforces integrity today is `Route::deletemass()`'s
`switch ($classname)` — a hand-maintained map of "when you delete an X, also
delete these" — plus a few `destroy()` overrides. It is a good specification
of intent and it has been carefully extended: `apiTokens` was added to the
user cascade because *"an orphaned API token is a live way in belonging to an
account that no longer exists"*, and the four site membership tables were
added because a leftover row can put an unrelated new object into a site
nobody granted.

The problem is not that the map is wrong. It is that the map is the only
thing there is, so **every new table is a chance to forget, and forgetting
is silent.** Three specific failures follow from that, all measured:

1. **`deletemass()` has no `storagegroup` or `storagenode` case at all**, and
   neither model overrides `destroy()`. On the maintainer's own 1.6 install,
   a deleted storage group and a deleted storage node have left rows in
   `nfsGroupMembers`, `multicastSessions` and three columns of `tasks`
   pointing at ids that no longer exist.
2. **`moduleStatusByHost` holds 144 rows for 12 deleted hosts** on that same
   install, and 156 rows for 12 deleted hosts on the 1.5 install — even
   though the host cascade *does* list `moduleassociation`. Residue from
   before the entry existed, which nothing will ever clean up.
3. **A plugin that is disabled rather than uninstalled keeps its tables while
   its hooks stop firing.** Every cleanup it did through `DELETEMASS_API`
   stops, and orphans accumulate for as long as it stays disabled. A
   constraint is a property of the table and keeps working in exactly that
   window.

A declared constraint closes all three, and closes them for tables nobody
has written yet.

## Decision

Referential integrity is declared in the database. `Route::deletemass()`
stays — it fires events, cancels tasks, reissues tokens and does work no
constraint can express — but it stops being the only thing standing between
a delete and an orphan.

### 1. Every id-bearing column is classified, and the classification is a file

`commons/schema-constraints.php` lists every column in the schema that holds an id
belonging to another table. Each entry names the parent, a class, and the
**`ON DELETE` action for that specific relationship** — not for its class,
because the decisions are not uniform within one: `tasks.taskHostID` is
CASCADE while `tasks.taskStateID` is RESTRICT, and `hosts.hostImage` is SET
NULL while `scheduledTasks.stImageID` is RESTRICT. A column that holds an id
and is not in that file is a bug, not an omission.

Current totals: 65 CASCADE, 19 RESTRICT, 5 SET NULL, 16 no constraint
(`audit` and `poly`).

"Holds an id" is not the same as "ends in ID". `multicastSessions.msState`
and `fileDeleteQueue.fdqState` are references to `taskStates` spelled without
the suffix, and were absent from the file until the coverage gate stopped
matching only `/ID$/`. The gate is what makes this decision real, so it
matches the naming conventions the schema actually uses rather than the one
it mostly uses.

**`satellite` vs `config` is the classification that can destroy data, so it
gets a test rather than a feel.** The question is not "is this a small table
hanging off a big one" — it is *does anything in FOG deliberately detach one
and leave it alive?* If yes, the child outlives the parent and the class is
`config`. `nfsGroupMembers.ngmGroupID` shipped as `satellite`/CASCADE in
schema step 384 and had to be corrected in 385: a storage node carries its
own hostname, credentials, paths and enable flag, and `removeNode()` detaches
one without deleting it, so CASCADE would have destroyed a node's entire
configuration when its group was deleted.

| Class | `ON DELETE` | What it means |
|---|---|---|
| `junction` | `CASCADE` | an association row that exists only to link two things |
| `satellite` | `CASCADE` | a 1:1 or 1:N row wholly owned by its parent |
| `config` | `SET NULL` or `RESTRICT` | a reference to configuration with a life of its own |
| `work` | per relationship | a task or job |
| `audit` | **no constraint** | a record of something that happened |
| `poly` | **impossible** | target table named by a sibling column |

`ON UPDATE RESTRICT` everywhere. FOG never updates a primary key, and
declaring `CASCADE` would license a rewrite nobody intends.

### 2. Where PHP already implements a behavior, the constraint pins it

`deletemass()` is a decade of decisions about what should happen when
something is deleted. Where it says "delete these too", `ON DELETE CASCADE`
is that statement made true in the database and changes nothing observable.
Where it says "set this to 0" — as the image cascade does for
`hosts.hostImage` — `ON DELETE SET NULL` is the same statement.

**`SET NULL`, not `RESTRICT`, is therefore correct for the image
references.** An image assigned to hosts can be deleted today and the hosts
are unassigned; making that a refusal would break a workflow that has worked
for years, and the admin would get an InnoDB 1451 rather than an
explanation.

`RESTRICT` is reserved for the places PHP does nothing today, where the
current behavior is "the reference silently dangles" and the child cannot
function without the parent — a scheduled task whose image is gone, a
`fileDeleteQueue` entry naming a deleted storage group. Each of those is a
real behavior change and lands in its own step with its own release note.

### 3. The audit trail takes no constraint of any kind

Not `CASCADE`, not `RESTRICT`, not `SET NULL`. The id stays a plain column
and identity is denormalized onto the row.

This is not re-litigated here because the schema already decided it. Step 341
copied `hostName`, `taskTypeName` and `imageName` onto `taskLog` precisely so
a failure stays searchable after its host is gone, and recorded the reason:
*"Refusing to delete a host because it once failed to image is a worse
product than losing a host name."* The `auditChange` create says the same for
[ADR 0021](0021-the-audit-trail.md)'s tables: a constraint there *"would make
the retention sweep's DELETE order load bearing — exactly the kind of thing
that fails on a restore onto a server with different settings."*

**Deleting a host must not delete the record of the host having been
deleted.** `CASCADE` would do exactly that, which is the audit trail
destroying its own reason to exist. `RESTRICT` inverts the dependency and
would make a diagnostic artifact block operational cleanup. `SET NULL` buys
what a `LEFT JOIN` already gives and adds a write to the delete path.

The class covers `taskLog` (3 columns), `auditChange.acAuditID`,
`userTracking.utHostID` and all four `nfsFailures` columns. Orphans in these
tables are the design working, not a defect, and the scanner reports them as
such.

### 4. Constraints are added by ALTER, never inline in a CREATE

Measured, not assumed. `SchemaReconciler` executes the manifest's `create`
strings into an empty database in manifest order, which is not dependency
order — `apiTokens` precedes `users`. With constraints inlined, **34 of 70
tables fail to create** with errno 150. With the same creates followed by a
second pass of `ALTER TABLE ... ADD CONSTRAINT`, zero fail.

Two obligations follow, and they land with the first constraint step:

- `bin/schema-manifest.php generate` strips `CONSTRAINT ... FOREIGN KEY`
  clauses from the `create` strings it snapshots. A regeneration that bakes
  them in breaks `tests/schema-executes.test.php`'s reconciler database.
- `SchemaReconciler` gains a constraint pass that runs **after** its table
  and column passes, tolerating errno 121 and 1826 (duplicate constraint
  name), the same way `$_skiperrs` already tolerates a re-applied
  `ADD COLUMN`.

That pass must not sit behind `reconcile()`'s early return on an empty
structural plan. An up-to-date database is the normal case, so returning
there means the constraints never land on almost any server -- silently, with
`reconcile()` reporting success. This was live for one lab run and is now
pinned by test.

### 5. Constraints are named `fk_<childTable>_<childColumn>`

`fk_tasks_taskHostID`, `fk_groupMembers_gmHostID`. Not
`fk_<child>_<parent>`: `tasks.taskNFSMemberID` and `tasks.taskLastMemberID`
both reference `nfsGroupMembers` and would collide. Child plus column is
unique by construction, so the name is derivable from the map without a
lookup — which is what lets CI check it — and greppable, so
`grep -rn fk_tasks_` finds every constraint on the table.

### 5a. The map is normative: the reconciler corrects and retires, not only adds

A constraint pass that only ever *adds* makes decision 1 a claim it cannot
keep. The generated name encodes the child table and column but not the
`ON DELETE` action, so a pass deciding by name alone sees a constraint that
already exists, calls the relationship done, and leaves the database
disagreeing with the map forever. Correcting an entry's action would be a
permanent no-op on every server that had applied the old one.

So `constraintSnapshot()` reads the whole declaration — referenced table,
referenced column, `DELETE_RULE` — and `planConstraints()` emits `DROP` +
`ADD` when what the database holds differs from what the map says, and `DROP`
alone when the map has retired the relationship (`enabled` false, or `action`
none).

The safety property that makes dropping acceptable: **the only constraints it
will ever drop are ones carrying the name decision 5 generates, for a
relationship the map lists.** A constraint an administrator added by hand
does not carry that name and is never considered. The pass iterates the map,
not the database — a gate pins that, and it goes red if the loop is inverted.

### 6. No future step may DROP or TRUNCATE a referenced parent

Measured against a fully constrained database: `TRUNCATE taskStates` returns
**1701**, `DROP TABLE users` returns **1451**. `schema.php` contains both
patterns (lines 1338, 1527, 3407), and none of them breaks — every
constraint step lands after index 379, so on replay the drops always run
first. `tests/schema-upgrade-replay.test.php` passes unchanged.

The rule is forward-looking. `RENAME TABLE` stays safe; MariaDB follows the
constraint to the new name. A step that must rebuild a parent drops the
constraints, rebuilds, and re-adds them — which is why they need a name that
can be written down.

`Schema::importdb()` already wraps restore in `SET FOREIGN_KEY_CHECKS=0`, so
backup and restore are unaffected. That must not be regressed.

### 7. What a new table must declare

For a core table added from here on:

1. Every column holding another table's id has an entry in
   `commons/schema-constraints.php` naming child, column, parent, parent column and
   class. `audit` and `poly` are answers; absence is not.
2. Types match the parent exactly — same integer width, same signedness.
   Copy the parent's declaration.
3. The constraint is added by `ALTER`, in the same step, after the
   `CREATE TABLE`.
4. A column that may legitimately hold "no reference" is `NULL`, not `0`.
   The `0` sentinel is what makes seven of the survey's twelve data failures,
   and a foreign key accepts `NULL` for that and nothing else.

### 8. What a plugin author must do

Direction is the whole rule:

- **A plugin table may reference a core table.** This is the direction that
  prevents orphans and is why plugin tables are in scope at all.
- **A core table must never reference a plugin table.** Uninstall would fail
  with 1451 on a table core does not own, leaving the plugin half-removed.
- **A plugin table may reference another table in the same plugin.**
- **A plugin table must not reference another plugin's table.** Uninstall
  order between two plugins is not something either controls.

Plugin install runs three steps in order: create or upgrade the tables;
**sweep orphans, logging the count**; then add the constraints, tolerating
121. The sweep is not optional and not a policy choice — `ADD CONSTRAINT`
against a table holding orphans returns 1452, so an install that skips it
fails. It is a no-op on a first install, which is the common case. Silent is
wrong: a plugin that deletes rows on install must be able to say how many.

Uninstall drops the plugin's tables, so there is nothing left to orphan and
no sweep is needed on that side.

### 9. CI enforces the classification, not the constraints

A gate test (`tests/foreign-keys-declared.test.php`, to land with step 3)
reads `commons/schema-expected.php`, finds every column whose name and type
mark it as holding another table's id, and requires each to appear in
`commons/schema-constraints.php`. A new table with an unclassified id column fails the
build; the author's escape is to classify it, including as `audit` or `poly`,
not to leave it out.

It gates the **map**, not the constraint list, deliberately. Gating the
constraints would mean CI needed a database; gating the map is textual and
runs in the existing no-database matrix. It also gates the thing that
actually gets forgotten — nobody forgets a constraint they have decided to
add, they forget to decide.

Per the repo's own rule that a gate is not a gate until it has been made to
fail, that test lands only once it has been proven red by adding an
unclassified `*ID` column to the manifest and watching it break. An
occurrence count in `phpstan-tests-baseline.neon` may need bumping with it.

## Consequences

- **70 of 87 constraints can be declared against a live database with no
  data change at all.** The remaining 17 need one of two mechanical fixes —
  five column widenings and a sweep-plus-sentinel-conversion — after which
  86 of 87 apply. The 87th is an ordering artifact of the sweep itself.
- **Nine steps, not one migration.** Each is a separate commit leaving the
  tree green; the destructive one (the orphan sweep) is early, alone, and
  logs a count per table. Sequencing is in the survey document.
- **`hosts`, `tasks` and `taskLog` take no column change.** Every type
  mismatch is in a table of fewer than 1200 rows.
- **Three behavior changes, all in the last three steps and all deliberate:**
  deleting a storage group or node that is still referenced by live work is
  refused rather than silently orphaning; a scheduled task's image and task
  type cannot be deleted out from under it; and the `0` sentinel becomes
  `NULL` in seven columns, which changes what the API emits for "no image
  assigned" from `0` to `null`. **`darksidemilk/FogApi` is the known
  downstream consumer** and the third of those is the one it can see.
- **Deleting a host with a running task still succeeds**, unchanged.
  `tasks.taskHostID` is `CASCADE` because that is what `deletemass('host')`
  already does. `RESTRICT` was tried on the survey database and refused the
  deletion of a host whose tasks were *finished* — "you cannot delete a host
  that has ever imaged" is not a rule anyone wants, and blocking deletion of
  a host with a *live* task is a PHP guard with a readable message, not a
  foreign key.
- **A correction lands with step 3.** `Route.php`'s site-membership comment
  justifies its cleanup with "InnoDB recomputes AUTO_INCREMENT as MAX(id)+1
  on restart". That was measured across a clean restart and a `SIGKILL` on
  both MariaDB 10.5.29 and 11.8.8, and the counter persisted every time;
  MariaDB has persisted it since 10.2.4, and it is MySQL ≤ 5.7 that
  recomputes. The cleanup stays — a grant naming a deleted role is wrong on
  its own terms — but the sentence overstates the mechanism and would
  otherwise be cited as justification for the next such fix.
- **`dev-branch` does not take this.** 1.5 has no `SchemaReconciler`, no
  manifest and no constraint pass, its steps 263-276 already diverge, and it
  is a patches line carrying the largest installed base FOG has.
- **Not included, deliberately:** `virus.vHostMAC`. It names a MAC rather
  than an id, and `hostMAC.hmMAC` carries no unique index because a host may
  hold several. There is no key to point at, and giving it one is a change to
  what a MAC means, not a constraint.
