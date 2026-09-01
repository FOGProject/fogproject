# Splitting what a group is: the work, sized

**Status: proposed, not decided. No code has been written for it.**
The decisions this sizes are in
[`docs/adr/0038`](../adr/0038-a-group-grants-it-does-not-copy.md).

Every claim below is labeled **VERIFIED** (with the command that produced it),
**INFERRED** (reasoned from something verified), or **UNKNOWN**. This was
written in a cloud session with **no database and no lab**, so anything that
needs a running server is UNKNOWN with a procedure, not an answer. Section 6
names the claim that would hurt most if it turned out to be false.

Repository state: `working-1.6` at `4729701`. Plugins at `fog-plugins`
`ec101b3`.

---

## 1. What is actually there today

### 1.1 The defect

**VERIFIED — every group "assignment" is a bulk write over the current
membership.** Twenty-three references to `$this->get('hosts')` in one model.

```
grep -c "get('hosts')" packages/web/src/Items/Group.php     # 23
grep -n 'function ' packages/web/src/Items/Group.php
```

| Method | Line | What it writes | Shape |
|---|---|---|---|
| `addPrinter()` | 136 | `printerAssoc` rows | one per host × printer |
| `removePrinter()` | 176 | `deletemass` | over membership |
| `updateDefault()` | 194 | `paIsDefault` | over membership |
| `addSnapin()` | 236 | `snapinAssoc` rows | one per host × snapin |
| `removeSnapin()` | 293 | `deletemass` | over membership |
| `setSnapinOrder()` | 316 | `saSequence` | `new Host()` per member |
| `addModule()` | 346 | `moduleStatusByHost` | one per host × module |
| `setDisp()` | 401 | delete-all + insert | one per host |
| `setAlo()` | 436 | delete-all + insert | one per host |
| `addImage()` | 501 | `hosts.hostImage` | one `UPDATE ... IN` |
| `setAD()` | 1100 | five `hosts` columns | one `UPDATE ... IN` |

**VERIFIED — the group table owns five columns of its own and pushes none of
them as such.** `groupKernel`, `groupKernelArgs`, `groupPrimaryDisk`,
`groupInit` are a *template* that prefills the form; `groupBuilding` is
vestigial (§1.4).

```
sed -n '42,53p' packages/web/src/Items/Group.php
```

**VERIFIED — a `Group` is already used as a bare selection carrier, unsaved.**
`HostManagement::deployMulti()` builds one to cover an ad-hoc host list so a
multicast session works the way it does for a real group; `Group::loadHosts()`
short-circuits on an unsaved group specifically to make that safe.

```
sed -n '1150,1183p' packages/web/src/Items/Group.php
grep -n 'The carrier for the selection' packages/web/src/Pages/HostManagement.php
```

This matters for ADR 0038 Decision 1: "the group is only a selection mechanism"
is not a new idea being introduced, it is an idiom the codebase already relies
on.

### 1.2 The plugin, read closely

**VERIFIED — the trigger, and what it does beyond the thirteen columns.**

```
sed -n '30,100p' <fog-plugins>/persistentgroups/src/Managers/PersistentGroupsManager.php
```

| Line | What | Note |
|---|---|---|
| 44 | template = host whose `hostName` = the group's `groupName` | scalar subquery, **no `LIMIT`** |
| 46–54 | copies the 13 `hosts` columns | the empirical list |
| 56 | `information_schema` probe for `locationAssoc` | **`table_schema = 'fog'` hardcoded** |
| 58–60 | copies `locationAssoc` | no `ON DUPLICATE KEY` |
| 63–65 | copies `printerAssoc` incl. `paIsDefault` | **no `ON DUPLICATE KEY`** |
| 69–72 | copies `snapinAssoc` | has `ON DUPLICATE KEY`; **never sets `saSequence`** |
| 75–83 | finds or creates a `snapinJobs` row | **adding a host runs snapins on it** |
| 86–89 | inserts `snapinTasks` | **never sets `stSequence`** |
| 92–94 | copies `moduleStatusByHost` incl. `msState` | **no `ON DUPLICATE KEY`** |

**VERIFIED — `printerAssoc` and `moduleStatusByHost` both carry composite
UNIQUE keys.**

```
php -r '$m=require "packages/web/commons/schema-expected.php";
foreach (["printerAssoc","moduleStatusByHost","snapinAssoc"] as $t)
  echo $m["tables"][$t]["create"], "\n\n";' | grep -o 'UNIQUE KEY [^,]*'
```

**INFERRED — adding a host to a group fails outright if that host already
shares any printer, or any module override, with the template host.** The
copies at lines 63 and 92 carry no `ON DUPLICATE KEY UPDATE`, so a duplicate
raises error 1062 inside an `AFTER INSERT` trigger, which rolls back the
`INSERT INTO groupMembers` that fired it. The snapin copy is immune because it
has the clause. Reproduction in §5.1 (UNKNOWN-3) — this is a mechanism argument
from MySQL semantics, not something observed.

**VERIFIED — the location branch has silently never run on a server whose
database is not named `fog`.** Line 56 tests `table_schema = 'fog'` as a
literal. The core equivalent, schema step 399, uses
`TABLE_SCHEMA = DATABASE()`.

```
grep -n "table_schema" <fog-plugins>/persistentgroups/src/Managers/PersistentGroupsManager.php
grep -n 'TABLE_SCHEMA. = DATABASE()' packages/web/commons/schema.php
```

**INFERRED — every snapin task the trigger creates ties at sequence 0.** Line
86 names four columns and `stSequence` is not among them; the column defaults
to `0` and `snapinTasks` is read in sequence order. So on a plugin-managed
server the snapin run order for a newly added host is whatever the engine
returns.

### 1.3 What the group page grew to compensate

**VERIFIED — the tri-state / mixed-value machinery already exists, and it is
one query over the whole selection.** `_uniformHostValues()` builds
`COUNT(DISTINCT COALESCE(col,'')) / MIN(COALESCE(col,''))` per requested column
in a single `SELECT ... WHERE hostID IN (...)`.

```
sed -n '264,342p' packages/web/src/Pages/GroupManagement.php
```

This is the single biggest sizing finding for the mass edit form: the "40 hosts
with 6 images must show *(varies)*" requirement is **already implemented and
already scale-safe**. It is parameterised by a `key => column` map and keyed off
a host id list. Porting it to a selection is changing where the id list comes
from.

**VERIFIED — the no-clobber convention is an in-band string sentinel.** Blank
means leave alone; the literal `NULL`, case-insensitively, means clear.

```
sed -n '744,772p' packages/web/src/Pages/GroupManagement.php   # general tab
sed -n '859,882p' packages/web/src/Pages/GroupManagement.php   # AD tab
```

**VERIFIED — two fields already use the correct out-of-band form instead.** The
AD join state and `hostEnforce` are tri-state `<select>`s reading *No change /
Enable on all / Disable on all*.

```
sed -n '1396,1430p' packages/web/src/Pages/GroupManagement.php
grep -n "adstate" packages/web/src/Pages/GroupManagement.php
```

So the codebase contains both the pattern ADR 0038 Decision 11 adopts and the
one it rejects, ten screens apart. That is the argument, not a preference.

**VERIFIED — `hostEnforce` is `tinyint(1)` on `working-1.6`, and
`GROUP_SHARED_STATE.md` still describes it as `enum('0','1')`.** ADR 0028
changed it; the doc did not follow. Worth fixing when that document is
rewritten as the mass edit document.

```
grep -n 'hostEnforce' packages/web/commons/schema-expected.php docs/GROUP_SHARED_STATE.md
```

### 1.4 `hostBuilding`

**VERIFIED — declared on both models, written by nothing.**

```
grep -rn "'building'" packages/web/src/ | grep -v @          # 2 hits, both field maps
grep -rni building packages/web/src packages/web/management/js --include=*.php --include=*.js
```

The only non-field-map hit anywhere is `fog.host.export.js:8`, an
`{data: 'building', visible: false}` datatable column. The trigger copies it.
It does not go in the mass edit form.

### 1.5 The write paths, measured

**VERIFIED — host-column mass writes are one statement, already atomic.**
`FOGManagerController::perform_update()` builds a single
`UPDATE ... WHERE id IN (...)`.

```
sed -n '1929,2010p' packages/web/src/Base/FOGManagerController.php
```

**VERIFIED — association writes chunk at 500 rows, one statement per chunk.**

```
grep -n 'array_chunk' packages/web/src/Base/FOGManagerController.php    # :1854
```

**VERIFIED — there is no transaction support anywhere in the web tier.**

```
grep -rn 'beginTransaction\|->commit()\|rollBack\|inTransaction' packages/web/src/ | wc -l   # 0
```

So "all-or-nothing" is not available today without new plumbing in `PDODB`.
ADR 0038 Decision 12 declines to add it and instead points out that the split
reduces the declarative half to one chunk.

**VERIFIED — the existing mass authorization gate is per-id.**

```
sed -n '2270,2280p' packages/web/src/Auth/Authorization.php
grep -n 'requirePageObjectScopeMass' packages/web/src/Pages/HostManagement.php
```

### 1.6 The client contract

**VERIFIED — the printer endpoint conflates "no printers" and "resolver
failed" under the same key, and the failure shape is nevertheless the safe
one.**

```
sed -n '52,80p'  packages/web/src/Client/PrinterClient.php    # 'error' => 'np'
sed -n '218,240p' packages/web/src/Client/FOGClient.php       # catch -> ['error' => msg]
```

An exception in `json()` yields `{"error": "..."}` with **no `printers` key**.
The empty case yields `{"error":"np","printers":[],"mode":...}`. So a client
branching on the presence of `printers` is already safe and a client branching
on `error` is already wrong.

**VERIFIED — removal is gated on printer level `ar`.**

```
sed -n '36,50p' packages/web/src/Client/PrinterClient.php
```

Three modes: `0` no management, `a` FOG-managed only, `ar` FOG handles all
printers. Only `ar` implies removal. Whether the shipped `fog-client` honours
that is UNKNOWN-4 — it is a different repository and not readable from here.

### 1.7 The snapshot that already exists

**VERIFIED — `snapinTasks` carries the resolved snapin and its sequence per
task, under a unique key.**

```
php -r '$m=require "packages/web/commons/schema-expected.php";
echo $m["tables"]["snapinTasks"]["create"], "\n";'
```

`stSnapinID`, `stSequence`, `UNIQUE (stJobID, stSnapinID)`.

**VERIFIED — task creation already reads the association table once for the
whole membership and materializes an ordered list.** The one-query-per-host
version was GH-707 and the comment records the fix.

```
sed -n '966,1080p' packages/web/src/Items/Group.php
```

This is why the snapin half of ADR 0038 is cheap, and it is also the claim in
§6.

### 1.8 Group name uniqueness

**VERIFIED at the manifest and in the code's own belief.** The expected schema
declares `UNIQUE KEY groupName (groupName)` (twice, plus a redundant
non-unique `new_index`), and `group` is absent from
`Route::$nonUniqueNameClasses`, whose docblock states the test is "a UNIQUE
index covering the name column ALONE" and which is held to the manifest by
`tests/nonunique-names-match-schema.test.php`.

```
grep -n "UNIQUE KEY .groupName" packages/web/commons/schema-expected.php
sed -n '677,690p' packages/web/src/Router/Route.php
```

**VERIFIED — 1.5 adds the index unconditionally in schema step 36.**

```
git show origin/stable:packages/web/commons/schema.php | grep -n -B2 -A2 'ADD UNIQUE ( .groupName. )'
```

**UNKNOWN-1 — whether a real upgraded 1.5-origin database actually has it.**
`roles.rName` is the precedent for exactly this gap: the manifest declared it
UNIQUE, the accesscontrol plugin never created it, native RBAC adopted the
table as it found it, and schema step 401 exists to repair it. Query in §5.

### 1.9 Plugin duplication

**VERIFIED — two plugins each ship a host hook and a near-identical group hook
whose only job is to write one value across many hosts.**

```
wc -l <fog-plugins>/{ou/src/Hooks/AddOU{Host,Group}.php,location/src/Hooks/AddLocation{Host,Group}.php}
# 318 / 277 / 317 / 282  = 1194 total, 559 of it group-side
sed -n '176,205p' <fog-plugins>/ou/src/Hooks/AddOUGroup.php
```

**VERIFIED — the plugin group hooks always clobber.** `groupOUPost()`
`deletemass`es every member's association and re-inserts. There is no "leave
alone" state, so core's no-clobber convention was never extended to the plugin
fields sitting next to it on the same page.

**VERIFIED — the ABI has a bulk-read seam and a bulk-delete seam and no
bulk-edit seam.** Only two mass hooks exist in core, and neither is an edit:

```
grep -rno "'[A-Z_]*MASS[A-Z_]*'\|'[A-Z_]*BULK[A-Z_]*'" packages/web/src/
# Route.php:3188,:4785  API_MASSDATA_MAPPING   -- decorates a LIST result
# Route.php:8722        DELETEMASS_API         -- cascading DELETE over a set
```

Every editing extension point a plugin registers is per-object. The 48 distinct
hook names the bundled plugins listen on contain no bulk-edit event:

```
grep -rh "registerInstalled(\[" -A 10 <fog-plugins>/*/src/Hooks/*.php \
  | grep -o "'[A-Z][A-Z_]*'" | sort -u
```

**VERIFIED — the plugin ABI is unshipped and changing it is free right now.**
ADR 0017: "There is no shipped 1.6 plugin ABI, which is why none of the five
decisions needed a deprecation window."

```
grep -n 'no shipped 1.6 plugin ABI' docs/adr/0017-hook-dispatch-contract.md
```

This is the strongest single argument for putting `HOST_MASSEDIT_*` in **1.6.0**
rather than 1.6.x.

### 1.10 Where the plugin code lives, and what an upgrade removes

**VERIFIED — bundled plugins are fetched into the web tree, which is
`rm -rf`'d on upgrade; the external plugin root is not.**

```
grep -n 'Fetched into packages/web/lib/plugins' lib/common/functions.sh   # :3143
sed -n '2246,2258p' lib/common/functions.sh                              # external root, "never touched"
```

**INFERRED — deleting `persistentgroups` from `fog-plugins` removes the code
from a bundled install on the next upgrade, and does nothing whatsoever to the
trigger.** `DROP TRIGGER` appears only in the plugin's own `uninstall()` and
`schema()` steps, neither of which an upgrade runs.

```
grep -n 'DROP TRIGGER' <fog-plugins>/persistentgroups/src/Managers/PersistentGroupsManager.php
```

### 1.11 The retirement precedent

**VERIFIED — schema step 399 is the pattern, and its comment is the argument
for why the gate must read table facts.**

```
sed -n '9980,10036p' packages/web/commons/schema.php
```

**VERIFIED — the next free core schema step is 402.**

```
grep -n '^// [0-9]\+$' packages/web/commons/schema.php | tail -3      # 399, 400, 401
grep -c '^// [0-9]\+$' packages/web/commons/schema.php                # 360 numbered comments in file
```

### 1.12 Audit

**VERIFIED — ADR 0021 Decision 11 already answers the 400-host question,
against this exact example.** One header, `affectedCount`, `outcome` allowed or
partial; no per-object headers; bulk updates carry no `auditChange` rows and
that gap is named rather than engineered around.

```
sed -n '484,520p' docs/adr/0021-the-audit-trail.md
sed -n '300,318p'  docs/adr/0021-the-audit-trail.md
```

**VERIFIED — `ADPass` and `productKey` are both credentials by pattern and are
redacted in audit rows regardless of registration.**

```
grep -n 'CREDENTIAL_PATTERN' packages/web/src/Auth/Redaction.php
```

---

## 2. Sizing

No clock estimates. Sized by schema steps, file counts, what needs a lab, and
what is reversible.

### 2.1 Schema steps

| # | Step | Reversible? |
|---|---|---|
| 402 | `groupSnapinAssoc` (`gsaGroupID`, `gsaSnapinID`, `gsaSequence`) + FKs per ADR 0031 | yes — drop table, no data lost, nothing read it before |
| 403 | `groupPrinterAssoc` (`gpaGroupID`, `gpaPrinterID`, `gpaIsDefault`) + FKs | yes, same |
| 404 | `groups.groupOrder INT NOT NULL DEFAULT 0` + index | yes |
| 405 | `DROP TRIGGER IF EXISTS persistentGroups` + retire the `plugins` row | **no** — a dropped trigger cannot be un-dropped, only recreated from the plugin source |
| 406 | *(optional, labelling only)* watermark row: `MAX(saID)`, `MAX(paID)` | yes |

**Four required steps, one optional, and exactly one of them is irreversible.**
Step 405 is the only step that touches existing state at all; 402–404 are pure
additions that nothing reads until the resolver ships.

**No step migrates or deletes an existing association row** — that is ADR 0038
Decision 18, and it is why this list is short.

### 2.2 Core files

Counted, not estimated.

```
grep -rl "snapinassociation\|SnapinAssociation"  packages/web/src packages/web/service packages/service   # 11
grep -rl "printerassociation\|PrinterAssociation" packages/web/src packages/web/service packages/service  # 12
```

| Work | Files | Notes |
|---|---|---|
| The resolver | 1 new (`src/Assign/Resolver.php`) + 2 new tests | new autoload-only bucket, free per ADR 0035 |
| Redirect snapin reads to it | `Items/Group.php`, `Items/Host.php`, `Pages/GroupManagement.php`, `Pages/HostManagement.php` | 4 |
| Redirect printer reads to it | + `Client/PrinterClient.php`, `Items/Printer.php` | 6 |
| New group-owned models/managers | 4 new (`Items/` × 2, `Managers/` × 2) | mechanical |
| Route surface | `Router/Route.php` (`$validClasses`, deletemass cascade), `Auth/Authorization.php` (`API_CLASS_ENTITIES`) | 2 |
| Group page: snapins/printers become group-owned | `Pages/GroupManagement.php`, `js/fog/group/fog.group.edit.js` | 2 |
| Mass edit form + apply | `Pages/HostManagement.php`, `js/fog/host/fog.host.list.js` | 2, plus `_uniformHostValues()` lifted to a shared home |
| Bulk group-membership editing | same 2 files | extends the existing `#addToGroupModal` |
| `HOST_MASSEDIT_*` hooks | `Pages/HostManagement.php` + `docs/plugin-development.md` | 2 |
| Retirement step | `commons/schema.php` | 1 |
| Docs | `GROUP_SHARED_STATE.md` rewritten as the mass edit doc; ADR 0001 amendment note; release notes | 3 |

**Roughly 20 core files touched, 7 new.** The heavy ones are
`GroupManagement.php` (3532 lines, 8 tabs) and `HostManagement.php` (5256).

### 2.3 Plugin files

| Work | Files | When |
|---|---|---|
| `persistentgroups` deleted | 3 (`config/`, `src/Items/`, `src/Managers/`) | with step 405 |
| `AddOUGroup.php`, `AddLocationGroup.php` deleted | 2, **559 lines** | follow-up, sequenced with the group page's own tab removal |
| `AddOUHost.php`, `AddLocationHost.php` gain `HOST_MASSEDIT_*` | 2 | with the core hooks |

### 2.4 What needs a lab

Nothing in §2.1–§2.3 can be signed off from a cloud session. The lab-gated
items, in the order they block work:

1. **UNKNOWN-2** (snapshot sufficiency) — blocks the snapin half's whole design.
   Cheapest to answer, answer it first.
2. **UNKNOWN-1** (`groupName` uniqueness on real upgraded databases) — does not
   block; determines whether step 404 also needs an index repair like 401.
3. **UNKNOWN-3** (trigger duplicate-key behaviour) — does not block the
   retirement; determines how the release note describes what admins have been
   hitting.
4. **UNKNOWN-4** (client removal semantics under each mode) — blocks shipping
   the printer resolver, because it decides the blast radius of a resolver bug.
5. **UNKNOWN-5** (mass authorization cost at 400 hosts) — does not block;
   determines whether the mass edit needs a set-based scope check.
6. **A migration rehearsal** on a 1.5-origin dump, per
   `docs/development/upgrade-rehearsal.md`, for step 405.

### 2.5 1.6.0 or 1.6.x

The honest read, with the argument on both sides.

**What argues for 1.6.0:**

- **The plugin ABI is unshipped** (§1.9). `HOST_MASSEDIT_*` costs nothing now
  and needs a deprecation window after 1.6.0 ships. This is the strongest
  argument and it is not about the group split at all — it is about the hook.
- **There is no destructive migration** (Decision 18). The upgrade is four
  additive schema steps plus one `DROP TRIGGER`. The usual reason to hold a
  model change for a point release — "we cannot un-migrate the data" — does not
  apply, because no data is migrated.
- **The trigger is active on real servers right now** and every day it runs it
  copies a domain password between hosts (Decision 15). Step 405 is worth
  shipping on its own schedule.
- Behaviour for every existing host and group is unchanged on upgrade, which is
  what makes the risk profile a point-release profile even though the model
  change is a major one.

**What argues for 1.6.x:**

- **UNKNOWN-2 is unanswered.** If `snapinTasks` is not a sufficient snapshot,
  the snapin half needs a real snapshot table and the sizing above is wrong by
  a schema step and a service-path change.
- **UNKNOWN-4 is unanswered**, and the failure mode is printers being stripped
  from a fleet. That is not a bug you ship to find out about.
- The mass edit form is the largest single piece and it is the one with no
  existing equivalent to fall back on. Decision 10 forbids removing the group
  tabs before it is proven, so a half-landed release is *safe* but is also two
  ways to do the same thing.

**Recommendation, and it is a split rather than a single answer:**

- **1.6.0:** the `HOST_MASSEDIT_*` hooks and the mass edit form; the bulk
  group-membership editor; schema step 405 (the trigger drop) and the
  `persistentgroups` deletion. None of these depends on the resolver, all of
  them are additive, and the ABI half genuinely gets harder after 1.6.0.
- **1.6.x:** the declarative split itself — steps 402–404, the resolver, the
  group page rework, and the removal of the group page's imperative tabs. It
  depends on two UNKNOWNs that need a lab, and Decision 10's sequencing already
  says the removals come a release later than the replacement.

That ordering also satisfies Decision 10 by construction: mass edit is in the
earlier release, the removals in the later one.

---

## 3. The three things most likely to be built wrong

Recorded here rather than in the ADR because they are implementation hazards,
not decisions.

1. **A resolver whose natural unit is one host.** GH-707 was exactly this
   (§1.7). The signature takes an array of host ids or it will be a thousand
   round trips the first time a group task calls it.
2. **A mass edit form that posts every field.** The three-state control is not
   a nicety; a form without it silently overwrites every field the admin did
   not touch, and it looks correct in every test where the admin touched
   everything.
3. **A printer resolver that returns `[]` on failure.** Under mode `ar` that is
   a fleet-wide printer removal, delivered one machine at a time as they poll.
   The resolver throws; §1.6 shows the endpoint already turns a throw into a
   body with no `printers` key.

---

## 4. Alternatives rejected, with reasons

Design alternatives are in the ADR. These are the ones about *how to do the
work*.

**Do the split first and the mass edit after.** Rejected: it produces a release
in which the group page's image tab is gone and nothing replaces it. ADR 0038
Decision 10.

**Migrate existing associations into group ownership.** Rejected: the data
cannot distinguish a group push from forty deliberate choices, and the
destructive form silently removes a snapin from a host that chose it. Decision
18, and the same argument the UTC document makes at §2.1 for not sweeping
timestamps.

**Add transactions to `PDODB` so mass edit can be all-or-nothing.** Rejected:
the imperative half is already one atomic statement (§1.5), and the split
reduces the declarative half to one chunk, so the case that needs a transaction
is the one the design removes. Introducing transactions to the DB layer for it
would be a much larger change with its own failure modes.

**Build a `tags` entity.** Rejected in ADR 0038 Decision 16. The cost is
measurable from the `site` plugin's absorption: `$validClasses`, the deletemass
cascade, `SiteScope::$_nodes`, permissions, FKs, a page, five JS files, the API
description.

**Retire `persistentgroups` by deleting the directory.** Rejected: the trigger
outlives the code (§1.10), and it is active rather than cosmetic.

**Gate the rollup semantics on a provenance boundary.** Rejected: deciding a
legacy row came from a group is the same unknowable inference as backfilling
it, moved to read time. Decision 18.

---

## 5. Queries and procedures to close the UNKNOWNs

Run these against a lab server with a 1.5-origin database restored. Replace
`fog` with the real schema name where a literal is used.

### UNKNOWN-1 — is `groupName` actually UNIQUE on upgraded servers?

Blocks nothing; decides whether step 404 also repairs an index.

```sql
-- (a) is the index there?
SELECT INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'groups'
ORDER BY INDEX_NAME, SEQ_IN_INDEX;

-- (b) are there duplicates it would have prevented?
SELECT groupName, COUNT(*) AS n
FROM `groups` GROUP BY groupName HAVING n > 1;

-- (c) same two questions for hosts, since the trigger joins on hostName
SELECT hostName, COUNT(*) AS n
FROM `hosts` GROUP BY hostName HAVING n > 1;
```

Expected: (a) shows a `NON_UNIQUE = 0` index on `groupName` alone; (b) and (c)
empty. If (b) returns rows, 1.5's schema step 36 failed on this database and
step 404 needs the rename-never-delete treatment schema 401 gives `roles`.

If (c) returns rows, the trigger's template subquery (`:44`) returns more than
one row and **every** `INSERT INTO groupMembers` on this server errors — worth
knowing before writing the release note.

### UNKNOWN-2 — is `snapinTasks` a sufficient snapshot? *(answer this first)*

This is §6. The question: does anything read `snapinAssoc` **after** a snapin
job has been created, for that job?

```bash
# Every read of the association table on the running-task side.
grep -rn "snapinassociation\|SnapinAssociation\|snapinAssoc" \
  packages/web/service/ packages/service/ packages/web/src/Client/

# The two client endpoints that matter.
sed -n '1,200p' packages/web/service/snapins.checkin.php
sed -n '1,200p' packages/web/service/snapinlisting.php
```

Then, on the lab, the behavioural test that settles it regardless of what the
code reading suggests:

1. Host H, snapins A and B associated. Create a snapin job.
2. Before the client checks in, remove B from H.
3. Let the client check in and run to completion.

If B runs, the task is the authority and Decision 4 holds. If B is skipped, the
association table is re-read at run time, Decision 4 is false as written, and
the snapin half needs a real snapshot table plus a service-path change.

Repeat with the addition case (add C after job creation, see whether C runs).

### UNKNOWN-3 — does the trigger break `add host to group`?

```sql
-- Setup: a group G, a host T named exactly like G, and a host H that already
-- shares one printer with T.
INSERT INTO `printerAssoc` (`paHostID`,`paPrinterID`,`paIsDefault`)
  VALUES (<T>, <P>, '0'), (<H>, <P>, '0');

-- Now the thing under test:
INSERT INTO `groupMembers` (`gmHostID`,`gmGroupID`) VALUES (<H>, <G>);
```

Expected under the inference: error 1062 on `printerAssoc.paHostID`, and the
`groupMembers` row is **not** created. Repeat with a shared
`moduleStatusByHost` row.

Also worth capturing, since it decides whether the release note says "this
plugin has been doing something surprising" or "this plugin has been failing":

```sql
-- How many servers would hit it: members sharing a printer with the template.
SELECT g.groupName, COUNT(DISTINCT pa2.paHostID) AS collidingMembers
FROM `groups` g
JOIN `hosts` t ON t.hostName = g.groupName
JOIN `groupMembers` gm ON gm.gmGroupID = g.groupID
JOIN `printerAssoc` pa1 ON pa1.paHostID = t.hostID
JOIN `printerAssoc` pa2 ON pa2.paPrinterID = pa1.paPrinterID
                       AND pa2.paHostID = gm.gmHostID
WHERE gm.gmHostID <> t.hostID
GROUP BY g.groupID;
```

### UNKNOWN-4 — what does the client actually do with the printer list?

**Blocks shipping the printer resolver.** Not answerable from this repository —
`fog-client` is a separate codebase.

Read side, in the `fog-client` source: find the printer module's handling of
the response and answer three questions.

1. Under mode `ar`, does a response with **no `printers` key** cause removal,
   or is it treated as an error and skipped?
2. Under mode `ar`, does `{"error":"np","printers":[]}` cause removal of every
   FOG-managed printer? (It should — that is the legitimate empty case.)
3. Under mode `a`, what exactly counts as "FOG-managed"? Is it tracked
   client-side, or inferred from the current response?

Behavioural test, on a lab host with mode `ar` and two printers:

```
# 1. Confirm steady state: both printers present after a poll.
# 2. Make the endpoint throw (e.g. temporarily point it at a bad snapin id, or
#    stop MySQL between polls) and watch one poll cycle.
# 3. Check whether the printers are still on the machine.
```

Step 3's answer is the blast radius. If a thrown resolver strips printers today,
that is a **pre-existing** bug worth its own fix regardless of this work, and
the resolver must not be shipped until it is fixed.

### UNKNOWN-5 — what does the per-id scope check cost at 400 hosts?

```
# On the lab, with a user restricted to a site, time a 400-host Queue Task
# (which already runs this gate) and count queries.
grep -n 'requirePageObjectScopeMass' packages/web/src/Pages/HostManagement.php
sed -n '2270,2280p' packages/web/src/Auth/Authorization.php
```

Instrument `Authorization::objectInScope()` to count invocations and elapsed
time for one `deployMultiPost` of 400 hosts. If it is material, the fix is to
use `SiteScope::allInScopeIDs()` once and diff, which is the set-based answer
the class already provides — and it is a pre-existing improvement to the Queue
Task path, not new work this creates.

### The retirement rehearsal

Per `docs/development/upgrade-rehearsal.md`, on a restored 1.5-origin dump that
**has** the plugin installed:

```sql
-- Before: the trigger is there.
SELECT TRIGGER_NAME, EVENT_MANIPULATION, EVENT_OBJECT_TABLE
FROM information_schema.TRIGGERS
WHERE TRIGGER_SCHEMA = DATABASE();

SELECT pName, pInstalled, pVersion, pSchema
FROM `plugins` WHERE LOWER(pName) = 'persistentgroups';
```

Run the upgrade. After: the trigger is gone, the row is gone, and — the
regression that matters — adding a host to a group still works and grants
nothing.

Then the negative case: the same rehearsal on a dump that **never** installed
the plugin, where step 405 must be a clean no-op.

---

## 6. The claim that would hurt most if it is false

**That `snapinTasks` already constitutes the snapshot** — §1.7, ADR 0038
Decision 4.

The snapin half is sized on it. If task creation already materializes an
ordered per-host list into a table the running task reads, then "snapshot at
task creation" costs one changed data source in `_createSnapinTasking()` and
nothing else: no new table, no schema step, no service-path change, and the
promise that a group edited mid-run does not affect a task in flight is already
true and merely undocumented.

If it is false — if `service/snapins.checkin.php` or the client re-reads
`snapinAssoc` for a running job, as a re-resolve or a validity check or a
fallback when the job row is missing — then three things follow at once. The
snapin half needs a genuine snapshot table and a schema step it does not
currently have. Decision 4's documentation promise is a lie that would ship in
the release notes. And a group edited mid-run *does* change a task in flight
today, which is a live bug independent of any of this and probably the reason
somebody would file it.

It is the cheapest UNKNOWN to close (one grep and one three-step lab test,
§5/UNKNOWN-2) and the one with the largest downstream cost, so it should be
closed before any code is written.

Second place, and only because its consequence is narrower rather than smaller:
**UNKNOWN-4**. If the shipped client removes printers on a response it should
have treated as an error, then that is true *today*, and the printer half of
this work cannot ship until it is not.
