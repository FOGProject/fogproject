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

**Update 2026-09-01:** UNKNOWN-2, UNKNOWN-4 and UNKNOWN-6 were run against the
1.6 lab (`10.255.20.1`) and are recorded in §5 with their evidence. One of them
changed a decision — see UNKNOWN-4 and ADR 0038 Decision 9.

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

### 1.9 The host list, and what "presented as tags" actually costs

Evidence for ADR 0038 Decision 16a, whose five requirements are binding.

**VERIFIED — there is no groups column on the host list.**

```
sed -n '105,170p' packages/web/src/Pages/HostManagement.php
grep -n 'data-col' packages/web/src/Pages/HostManagement.php
```

**CORRECTED — the grid DOES have per-column filtering, and an earlier draft of
this document said it did not.** The comment at `HostManagement.php:150-153`
reads "this grid has no per-column search UI, so the global box matches the
STORED word". That comment is **stale**: it predates #1471/#1476/#1477
(2026-08-29), which added a SearchBuilder **Filter** button and a **Column
search** header row to every grid, server-parsed in
`FOGManagerController::filter()`. All of it is on this branch. The first draft
quoted the comment as evidence of current behaviour instead of grepping for the
mechanism — the comment should be corrected when the groups column lands.

```
git grep -l 'SearchBuilder\|_searchtypes\|_sbCriterion' -- packages/web
grep -n 'function filter\|_sbCriterion\|_searchtypes' packages/web/src/Base/FOGManagerController.php
#   755 filter()   1050 _sbCriterion()   1513 '_searchtypes'
grep -n -i filter packages/web/management/js/fog/host/fog.host.list.js   # no output
```

The host list opts into nothing — `fog.filters.js` is generic and the grid gets
both controls for free.

**VERIFIED — and this is the actual constraint: the filter path is
column-backed by construction.** `_sbCriterion()` returns empty unless the
column carries a `db`, and builds `` `$column['db']` `` directly; the type comes
from `searchBuilderType()` → `columnType()`, which reads the schema manifest for
a real column of a real table.

```
sed -n '1050,1082p' packages/web/src/Base/FOGManagerController.php
sed -n '5733,5756p' packages/web/src/Base/FOGBase.php
```

A groups column has no backing scalar on `hosts`. So requirement 16a.1 is
neither "build filtering" nor "add a column": it is **teaching the shared filter
path its first relationship column**, by growing the column contract an optional
subquery form that `_sbCriterion()` uses in place of `` `db` ``. Smaller than
the first draft implied on the UI side, and a change to a helper every grid runs
through on the server side.

Two constraints on that expression, both already paid for here:

**VERIFIED — it must go into the query, not onto the page.** `complex()` applies
the `LIMIT` before any row-level filtering, which is why the site boundary is
passed as a subquery ANDed into the row query, the filter count *and* the total
count. Post-filtering gave empty first pages and counts describing rows the user
could not see.

```
sed -n '3149,3178p' packages/web/src/Router/Route.php
```

**VERIFIED — every binding must be named by the clause that emits it.**
`sqlexec()` binds every entry of `$bindings` to all three of `complex()`'s
queries, so an unreferenced binding makes PDO refuse the statement and the list
answers HTTP 406. That shipped once in `_sbDate()` and broke Before/After — the
two conditions the feature existed for — while `=` and `between` worked. Bind
inside the branch that names it, and gate on "no binding left unnamed" rather
than on string-comparing substituted SQL, which is what missed it.

```
grep -n '_sbDate' packages/web/src/Base/FOGManagerController.php   # :1238
ls tests/searchbuilder-filter-clause.test.php
```

**VERIFIED — the grid is server-side, so a filter must be SQL.**

```
sed -n '298,310p' packages/web/management/js/fog/host/fog.host.list.js
# processing: true, serverSide: true, POST ?node=host&sub=list
```

**VERIFIED — rendering chips is cheap, because the column table already has a
per-page batch loader.** `relColumn()` takes a `prime` callback handed every row
on the page at once.

```
sed -n '7660,7690p' packages/web/src/Router/Route.php
```

One extra query per page, not per host.

**VERIFIED — the list's Add to Group modal already has typeahead, chips and
create-on-the-fly.** This is easy to miss and it changes what requirement 3
costs: select2 with `tags: true`, `tokenSeparators: [',', ' ']`, ajax against
`/group/names/name=%term%`, and a `createTag` handler that badges an unmatched
term `(new)`.

```
sed -n '29,120p' packages/web/management/js/fog/host/fog.host.list.js
```

**What it does not have: remove.** The modal is add-only, and `saveGroup()`
only ever calls `addHost()`. A label you can apply and cannot retract is not a
label — requirement 2.

**VERIFIED — the list modal is a second create-and-associate path, and the
looser one.** The shared helper POSTs the created id to the association tab's
own update URL and its docblock says so explicitly ("this is not a second write
path"); it is used by eleven tabs, including `host-group` on the host **edit**
page.

```
grep -rn 'registerCreateAndAssociate' packages/web/management/js/   # 11 call sites
sed -n '1752,1790p' packages/web/management/js/fog/fog.common.js    # the helper
sed -n '617p'       packages/web/management/js/fog/host/fog.host.edit.js
```

The list modal instead posts `groups[]` / `groups_new[]` to `sub=saveGroup`,
which builds a group from a raw string:

```
sed -n '4083,4141p' packages/web/src/Pages/HostManagement.php
```

`->set('name', $group)->addHost($hosts)->save()` — **no name-collision check**,
where the group page's own rename path does check via
`getManager()->exists()` (`GroupManagement.php:733`).

**VERIFIED by code reading — `saveGroup()` performs no CSRF check.**
`FOGPageManager` calls `checkAuthAndCSRF()` centrally only when the resolved
method takes an `Ajax`/`Post` suffix; the handler is `saveGroup`, there is no
`saveGroupPost`, and it does not call it itself.

```
sed -n '176,208p' packages/web/src/Base/FOGPageManager.php    # central gate
grep -c 'function saveGroupPost' packages/web/src/Pages/HostManagement.php   # 0
grep -n 'checkAuthAndCSRF' packages/web/src/Pages/HostManagement.php
# 1643 1983 2251 2922 3730 4606 4919 -- saveGroup() is at 4083, not among them
grep -n -A30 'function requireForStateChanging' packages/web/src/Auth/CSRF.php
```

`CSRF::requireForStateChanging()` is reached from exactly two places
(`FOGBase::checkAuthAndCSRF()` and `Route.php:901`), so there is no third,
global enforcement. Page **permission** is unaffected —
`requirePagePermission()` runs on every dispatch (`FOGPageManager.php:195`).

**VERIFIED — `saveGroup()` does not bound the posted host ids to the caller's
object scope**, where `deployMultiPost()` does on identical input.

```
grep -n 'requirePageObjectScopeMass' packages/web/src/Pages/HostManagement.php  # 4633 only
```

The central `requirePageObjectScope()` is passed the URL `$id`
(`FOGPageManager.php:203`), and a list-level action has none.

Both gaps are pre-existing. They are in scope because ADR 0038 Decision 16a
promotes this endpoint from a convenience to the primary membership surface.
Confirm both with a request, not a reading — UNKNOWN-6.

### 1.10 Plugin duplication

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

### 1.11 Where the plugin code lives, and what an upgrade removes

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

### 1.12 The retirement precedent

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

### 1.13 Audit

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
| **Groups column + filter** (Dec 16a.1) | `Router/Route.php` (column + `prime` + the subquery form), `Base/FOGManagerController.php` (`_sbCriterion`/`searchBuilderType` accept it), `Pages/HostManagement.php` (header + the stale comment), `tests/searchbuilder-filter-clause.test.php` | 4; the shared-helper change is plan-first on its own account |
| **Bulk add/remove + shared create path** (Dec 16a.2–4) | `Pages/HostManagement.php` (`saveGroup` rewritten: remove, CSRF, scope), `js/fog/host/fog.host.list.js` | 2 |
| **Group list "grants" column** (Dec 16a.5) | `Pages/GroupManagement.php` | 1 |
| Retirement step | `commons/schema.php` | 1 |
| Docs | `GROUP_SHARED_STATE.md` rewritten as the mass edit doc; ADR 0001 amendment note; release notes | 3 |

**Roughly 22 core files touched, 7 new** — the presentation work overlaps
almost entirely with files the rest of the change already opens. The heavy ones
are `GroupManagement.php` (3532 lines, 8 tabs), `HostManagement.php` (5256) and
`Route.php`.

Requirement 16a.3 ("it has to feel lightweight") is mostly **already built**:
the list modal has select2 chips, typeahead and create-on-the-fly today
(§1.9). The filter framework is built too — what 16a.1 needs is the column
contract extended to express a relationship, which is server-side work on a
shared helper rather than UI work. **Remove** (16a.2) is the one genuinely
absent piece of behaviour. The rest is reuse and two security fixes.

### 2.3 Plugin files

| Work | Files | When |
|---|---|---|
| `persistentgroups` deleted | 3 (`config/`, `src/Items/`, `src/Managers/`) | with step 405 |
| `AddOUGroup.php`, `AddLocationGroup.php` deleted | 2, **559 lines** | follow-up, sequenced with the group page's own tab removal |
| `AddOUHost.php`, `AddLocationHost.php` gain `HOST_MASSEDIT_*` | 2 | with the core hooks |

### 2.4 What needs a lab

Nothing in §2.1–§2.3 can be signed off from a cloud session. The lab-gated
items, in the order they block work:

**Closed 2026-09-01** against the 1.6 lab: **UNKNOWN-2** (VERIFIED — the task
is the authority, no new snapshot table needed), **UNKNOWN-4** (VERIFIED from
`fog-client` source; the live poll cycle is still unobserved), **UNKNOWN-6**
(VERIFIED — both gaps real).

Still open:

1. **The printer wire contract is now the top risk**, and it is not a question
   about the server. `error: 'np'` is load-bearing in the shipped client
   (UNKNOWN-4), so any change to the empty-case spelling is a client-visible
   change. Confirm the trace on a real mode-`ar` host before shipping the
   printer resolver — that is the one piece UNKNOWN-4 could not observe.
2. **UNKNOWN-1** (`groupName` uniqueness on real upgraded databases) — does not
   block; determines whether step 404 also needs an index repair like 401.
3. **UNKNOWN-3** (trigger duplicate-key behaviour) — does not block the
   retirement; determines how the release note describes what admins have been
   hitting. **Isolated lab database only** — see the warning on that section.
4. **UNKNOWN-5** (mass authorization cost at 400 hosts) — does not block;
   determines whether the mass edit needs a set-based scope check.
5. **The `group.create` question** raised by UNKNOWN-6: membership editing
   should not require the permission to mint groups. An access-control change,
   so it is a decision rather than a test.
6. **A migration rehearsal** on a 1.5-origin dump, per
   `docs/development/upgrade-rehearsal.md`, for step 405.

### 2.5 1.6.0 or 1.6.x

The honest read, with the argument on both sides.

**What argues for 1.6.0:**

- **The plugin ABI is unshipped** (§1.10). `HOST_MASSEDIT_*` costs nothing now
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

- **1.6.0:** the `HOST_MASSEDIT_*` hooks and the mass edit form; **all five of
  Decision 16a's presentation requirements** (groups column + filter, bulk
  add/remove from the host side, the shared create-and-associate path with
  `saveGroup()`'s CSRF and scope gaps closed, the group list's "grants"
  column); schema step 405 (the trigger drop) and the `persistentgroups`
  deletion. None of these depends on the resolver, all of them are additive,
  and the ABI half genuinely gets harder after 1.6.0.

  **16a is in the earlier release deliberately, and it is the half that must
  not slip.** It is the part of Decision 16 that closes the labelling gap, and
  the failure mode of shipping the split without it is specific: groups become
  heavier while staying just as unpleasant to apply, which is the exact state
  that made a `tags` entity look necessary. Landing 16a first also means the
  presentation improvement stands on its own even if the declarative split
  slips further — it is useful against today's groups, unchanged.
- **1.6.x:** the declarative split itself — steps 402–404, the resolver, the
  group page rework, and the removal of the group page's imperative tabs. It
  depends on two UNKNOWNs that need a lab, and Decision 10's sequencing already
  says the removals come a release later than the replacement.

That ordering also satisfies Decision 10 by construction: mass edit is in the
earlier release, the removals in the later one.

---

## 3. The six things most likely to be built wrong

Recorded here rather than in the ADR because they are implementation hazards,
not decisions.

1. **A resolver whose natural unit is one host.** GH-707 was exactly this
   (§1.7). The signature takes an array of host ids or it will be a thousand
   round trips the first time a group task calls it.
2. **A resolver that reads membership through a manager.**
   `FOGController::buildQuery()` walks `$databaseFieldClassRelationships`
   transitively and folds a fourth-element filter into the WHERE, not the ON,
   so any query chaining through `Host` picks up
   `` AND `hostMAC`.`hmPrimary` = '1' `` and silently drops every host with no
   primary MAC — 95 rows returned where the raw `COUNT(*)` was 1000, measured on
   the 1.5 lab. No flag suppresses it; read the association table directly (the
   fix used in PR #1233). A resolver that silently omits hosts is a printer
   resolver that strips their printers.
3. **A mass edit form that posts every field.** The three-state control is not
   a nicety; a form without it silently overwrites every field the admin did
   not touch, and it looks correct in every test where the admin touched
   everything.
4. **A printer resolver that returns `[]` on failure.** Under mode `ar` that is
   a fleet-wide printer removal, delivered one machine at a time as they poll.
   The resolver throws; §1.6 shows the endpoint already turns a throw into a
   body with no `printers` key.
5. **A groups filter applied to the returned page instead of the query**, or
   one that binds a parameter its clause does not name. Both already happened
   here: the first to the site boundary (`Route.php:3149-3172` records it), the
   second to `_sbDate()`, which answered HTTP 406 on exactly the two conditions
   the feature existed for. Into the query, into all three counts, and bind
   inside the branch that names it.
6. **A second create-and-associate path for host↔group.** There are already two
   (§1.9) and the newer one skips the name-collision check. The list modal
   should use `$.registerCreateAndAssociate()` like the other eleven tabs, not
   grow a third.

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

**Build a `tags` entity.** Rejected in ADR 0038 Decision 16: `groupMembers` is
already an attribute-free many-to-many labelling table
(`gmID, gmHostID, gmGroupID`, §1.1), so a second set-of-hosts concept solves a
presentation gap with a data model. The cost of the entity is measurable from
the `site` plugin's absorption: `$validClasses`, the deletemass cascade,
`SiteScope::$_nodes`, permissions, FKs, a page, five JS files, the API
description — and none of it moves a host into a group any faster.

**Ship the split and treat the presentation work as a follow-up.** Rejected,
and this is the one rejection that is a scheduling decision rather than a
design one. Decision 16 is only correct if a group is cheap to apply in bulk;
the split alone makes groups *heavier* and leaves the labelling gap untouched,
which reproduces the state that made tags look necessary. §2.5 puts all of
Decision 16a in 1.6.0 for that reason.

**Retire `persistentgroups` by deleting the directory.** Rejected: the trigger
outlives the code (§1.11), and it is active rather than cosmetic.

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

### UNKNOWN-2 — is `snapinTasks` a sufficient snapshot? — **VERIFIED, closed**

**Answered 2026-09-01 on the 1.6 lab (`10.255.20.1`). The task is the
authority; `snapinTasks` is a sufficient snapshot. ADR 0038 Decision 4 holds
and needs no new table.**

Both halves agree.

*Code:* zero reads of `snapinAssoc` / `snapinAssociation` anywhere in the
checkin path. `SnapinClient::json()` drives entirely off
`Route::getList('snapintask', ['jobID' => ..., 'stateID' => queued/progress])`,
and `SnapinTask::getSnapin()` is `new Snapin($this->get('snapinID'))` — the
stored `stSnapinID` off the task row.

*Behaviour*, on throwaway host `te-u2-test` (id 228, deleted afterwards):

| Case | Setup | Result |
|---|---|---|
| **Removal** | job 26 with tasks for snapins A and B; **deleted B from `snapinAssoc` before checkin** | both task 67 (A) and task 68 (B) went Queued → Checked-In on the same `stCheckinDate`. **B still ran.** |
| **Addition** | job 27 with a task for A only; **added snapin C to `snapinAssoc` after job creation** | task 69 (A) advanced; **no task row ever existed for C.** C was never served. |

Method note, because it matters for anyone repeating this: the JSON body is not
readable without a full RSA/AES client emulator — `SnapinClient`'s
`requestClientInfo` branch runs inside `FOGClient::__construct()` and PHP
discards a constructor's return value, so real clients get their answer through
the encrypted `#!enkey=` path in `sendData()`. The test therefore reads the
**database side-effects** of the checkin call, before and after. The task-state
transitions happen inside the same loop that builds the response, so they are
direct evidence of what would have been served rather than a proxy for it.

**Consequence for the docs:** "a group edited mid-run does not change a task in
flight" is true *today*, undocumented, and becomes a promise the split makes
explicit. Re-tasking really is the only way to pick up a change.

<details>
<summary>Original procedure, kept for the record</summary>

The question: does anything read `snapinAssoc` **after** a snapin job has been
created, for that job?

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

</details>

### UNKNOWN-3 — does the trigger break `add host to group`?

**🔴 Run this on the isolated lab database only, never on a lab server whose
hosts are real.** The trigger under test does not merely copy columns: it
creates a `snapinJobs` row and `snapinTasks` rows for the host being added
(`PersistentGroupsManager.php:75-89`), so firing it on a live-ish box **queues
software to run on that machine** at its next check-in. The fixture host must be
a throwaway that nothing polls.

Same discipline as any lab fixture: assert the discriminators you are borrowing
are unoccupied before creating anything, and clean up at the START of the run as
well as the end — a run that errors inside the trigger leaves its rows behind.
"Empty of my rows" is not "empty".

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

### UNKNOWN-4 — what does the client do with the printer list? — **VERIFIED from source; live poll COULDN'T-TEST**

**Answered 2026-09-01. `fog-client` is checked out locally at
`/home/telliott/fog-client`, `master` @ `610ad5f` (Release 0.13.0), so this was
read rather than inferred.** `Modules/PrinterManager/PrinterManager.cs:60-121`:

```csharp
if (msg.Mode == "0") return;
var installedPrinters = _instance.GetPrinters();

if (data.Error && data.ReturnCode.Equals("np", StringComparison.OrdinalIgnoreCase))
{
    RemoveExtraPrinters(new List<Printer>(), msg, installedPrinters);   // remove-all
    return;
}
if (data.Error) return;                                    // <-- fail-safe
if (!data.Encrypted) { Log.Error(Name, "Response was not encrypted"); return; }
RemoveExtraPrinters(msg.Printers, msg, installedPrinters);
```

and

```csharp
private void RemoveExtraPrinters(List<Printer> newPrinters, ... ) {
    var managedPrinters = newPrinters.Where(p => p != null).Select(p => p.Name).ToList();
    if (!msg.Mode.Equals("ar", StringComparison.OrdinalIgnoreCase))
        foreach (var name in msg.AllPrinters.Where(n => !managedPrinters.Contains(n)
                                                     && installedPrinters.Contains(n)))
            CleanPrinter(name, true);
    else
        foreach (var name in installedPrinters.Where(n => !managedPrinters.Contains(n)))
            _instance.Remove(name);
}
```

**1. A response with no `printers` key (the exception shape), under `ar`:
printers are NOT removed.** `if (data.Error) return;` fires before anything
touches them. The fleet-wide-strip scenario the design was written against
**cannot happen with the shipped client** — the failure path is already safe at
both ends. (It is safe by that guard alone: `RemoveExtraPrinters(null, …)` would
throw on `.Where`, so nothing downstream is defending it.)

**2. `{"error":"np","printers":[]}` under `ar` removes EVERY installed
printer**, not merely the ones FOG added — `managedPrinters` is empty and the
`ar` branch removes every name in `installedPrinters`. That is the legitimate
empty case behaving as intended, and it is worth knowing how wide it is.

**3. Under mode `a`, "FOG-managed" is inferred fresh from each response's
`AllPrinters`** — the server's entire printer catalogue, from
`Route::getIds('printer', [], 'name')` (`PrinterClient.php:60`) — and is **not**
persisted client-side. `_configuredPrinters` is in-memory per service run and
means "already configured this pass", not identity. So under `a` the client
removes a printer only if it is in FOG's catalogue, absent from the resolved
list, and currently installed.

#### 🔴 The consequence that changes a decision

**`error: 'np'` is a load-bearing wire contract, not an accident of spelling.**
It is the *only* string that triggers removal-on-empty, matched
case-insensitively against `ReturnCode`. Two things follow, and ADR 0038
Decision 9 was rewritten for them:

- **The rule "never return an empty list on failure" is still right, but its
  justification changes.** It is not "or printers get stripped today" — this
  client will not. It is: *never let a failure be spelled `np`*, because that
  one string is indistinguishable from the legitimate empty case, and because a
  less careful client (or a future one) has no other signal.
- **Re-spelling the empty case is safe with this client, but only by
  coincidence, and the trace has to be checked rather than assumed.** Sending
  `{printers: [], mode: 'ar'}` with no `error` leaves `data.Error` false, passes
  the encryption check, and reaches `RemoveExtraPrinters([], …)`, whose `ar`
  branch removes every installed printer — the same outcome. One real
  difference: the `np` branch sits **above** the `!data.Encrypted` guard, so
  today the empty-case removal happens even on an unencrypted response, and
  after the change it would not. That is an improvement, but it is a behaviour
  change on the wire and belongs in the release notes rather than being
  discovered.

**COULDN'T-TEST: the live poll cycle.** No Windows fog-client host is in the
required state — `telliottwin11` (host 105) is offline (`lastcheckin`
2026-08-30) and its `printerLevel` is empty (mode `0`, not `ar`). Standing one
up was out of scope for the verification run. So items 1–3 are source-verified
and unobserved; the behavioural confirmation remains open and is cheap once a
mode-`ar` host with two steady printers exists.

<details>
<summary>Original procedure, kept for the record</summary>

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
the resolver must not be shipped until it is fixed. *(Answered: it does not.)*

</details>

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

### UNKNOWN-6 — is `saveGroup()` really unprotected? — **VERIFIED, both gaps**

**Answered 2026-09-01 on the 1.6 lab. Both gaps are real, both confirmed with
requests against controls.**

| Test | `saveGroup` | Control (`deployMulti`) |
|---|---|---|
| **CSRF** — admin session, no `X-CSRF-Token`, no `_csrf` | `202 {"msg":"Successfully added hosts to the provided groups!"}` | `403 Forbidden (invalid CSRF token)` |
| **Object scope** — site1-scoped user, `hosts[]=1` (a host outside site1) | `202`, host joined the group | `403 {"error":"You do not have permission to perform this action."}` |

The controls matter: they are the same request shape to a `*Post`-suffixed
handler, so the difference is the missing gate and not something about the
session or the user.

**A detail the code reading missed, and it has a design consequence.**
`saveGroup`'s required permission is **`group.create`**, not `host.edit` —
`Authorization::SUB_OVERRIDES['host']['savegroup'] => 'group.create'`
(`Authorization.php:157`), which overrides what `_subToAction()` would derive.
The verification run had to grant the Technician role to its throwaway user to
get past the permission gate at all before object scope could be reached.

That is right for the endpoint as it exists, because the endpoint can create
groups from `groups_new[]`. It is **wrong for the endpoint ADR 0038 Decision
16a is asking for**: adding forty existing hosts to three existing groups is
not a group *creation*, and requiring `group.create` for it means an operator
who may label hosts must also be able to mint groups. Splitting that —
`group.create` only when `groups_new[]` is non-empty, membership editing behind
something narrower — is an **access-control change** and is flagged here rather
than decided.

Fixtures created and removed, verified back to baseline: host 228 and its
`snapinJobs` / `snapinTasks` / `snapinAssoc` / `tasks` rows; groups
`te_csrf_throwaway_grp` and `te_scopetest_throwaway_grp`; user `te_scopetest`
(id 123) with its role and site assignments. No standing lab fixtures touched.

<details>
<summary>Original procedure, kept for the record</summary>

**CSRF.** Logged in as an admin in a browser, from a page on another origin (or
with `curl` reusing the session cookie and **omitting** both the
`X-CSRF-Token` header and a `_csrf` body field):

```
curl -i -b "<session cookie>" \
  -X POST '<server>/management/index.php?node=host&sub=saveGroup' \
  --data 'hosts[]=<id>&groups[]=<groupID>'
```

Expected if the reading is right: `202` and the host is added. Expected if
something else enforces CSRF: `403 Forbidden (invalid CSRF token)`. Compare
against the same request to `sub=deployMulti` (a `*Post` handler), which must
give the 403.

**Object scope.** As a user restricted to site A, with host `H` in site B:

```
curl -i -b "<cookie for the restricted user>" \
  -X POST '<server>/management/index.php?node=host&sub=saveGroup' \
  --data 'hosts[]=<H>&groups[]=<a group the user can see>'
```

Expected if the reading is right: the request succeeds and `H` joins the group.
The same ids sent to `sub=deployMultiPost` must be refused with 403, which is
the control that shows the difference is the missing
`requirePageObjectScopeMass()` call and not something about the user.

If either comes back protected, find what protects it before removing anything
— a gap that is not there does not need fixing, and the reading was wrong about
where enforcement lives. *(Answered: both gaps are real.)*

</details>

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

**Superseded 2026-09-01.** The original answer was that `snapinTasks` already
constitutes the snapshot. **It does** — UNKNOWN-2 is VERIFIED in both
directions on the lab, so the snapin half is sized correctly, needs no new
table and no schema step, and Decision 4's documentation promise is true rather
than aspirational.

The risk has moved to the printers, and it is narrower and more specific than
the one it replaces:

**That the printer wire contract can be changed server-side alone.**

ADR 0038 Decision 9 re-spells the empty case so that `error` means "I could not
answer" and nothing else. UNKNOWN-4 shows the shipped client
(`fog-client` 0.13.0) removes printers on exactly one signal —
`data.Error && ReturnCode == "np"` — and that reading the trace end to end says
the change is safe, because a no-error response carrying `printers: []` reaches
`RemoveExtraPrinters([], …)` and removes the same set.

**Safe by that trace, not by design.** Two things would make it false:

- **A deployed client older than the one that was read.** The trace covers
  `master @ 610ad5f`. Any client whose removal path requires the `np` sentinel
  rather than falling through to the general branch would simply stop removing
  printers under `ar` — silently, because nothing errors and nothing logs. The
  failure is printers that never disappear, which nobody reports for months.
- **The `!data.Encrypted` guard sitting below the `np` branch.** Today the
  empty-case removal happens even on an unencrypted response; after the change
  it would not. That is the right direction, and it is still a behaviour change
  on a path nobody is watching.

It is checkable the same way the rest of UNKNOWN-4 was — on one mode-`ar` host
with two steady printers, induce the empty case both ways and see whether the
printers go. Until that is observed, the honest position is that Decision 9's
re-spelling is *reasoned* safe and not *shown* safe, and it should not ship
without either the observation or keeping `np` alongside the new field for a
release.

Second place: **UNKNOWN-1**, and only because its consequence is cheap. If a
real upgraded database turns out not to have a UNIQUE index on `groupName`, the
resolver's `groupID` tiebreak stops being dead code and starts earning its
keep — which is exactly why Decision 6 keeps it.
