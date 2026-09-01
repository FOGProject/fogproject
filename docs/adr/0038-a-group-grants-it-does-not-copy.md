# A group grants snapins and printers; it copies nothing

## Status

proposed. No code has been written for it, and this ADR deliberately proposes
none in this pass. The sizing, the evidence behind every claim, and the
questions that need a lab to settle are in
[`docs/development/group-split.md`](../development/group-split.md).

**Verified against the lab 2026-09-01** (1.6 server, `10.255.20.1`): Decision 4
holds — `snapinTasks` is already a sufficient snapshot, proven in both
directions, so the snapin half needs no new table. Decision 9 was **revised**
after reading the shipped `fog-client` (0.13.0): the failure path is already
fail-safe at both ends, and the real rule is narrower than the first draft's.
Decision 16a's two named gaps on `saveGroup()` are **confirmed real**. Evidence
and the remaining open items are in the proposal's §5.

**Decision 16 (groups become the tag concept, presented as tags) is the
maintainer's decision, not a derivation.** It is recorded with its argument and
with the rejected alternative stated in full. Decision 16a's five presentation
requirements are **binding parts of that decision**, not follow-up work: the
decision is only correct if a group is cheap to apply in bulk.

Amends [ADR 0001](0001-group-association-state.md) rather than superseding it:
0001's tri-state derivation is still how the group page reports *member* state
after this change, but its opening sentence — "a group owns no associations of
its own" — stops being true for snapins and printers, which is the whole point.

## Context

`Group::addSnapin()` writes one `snapinAssoc` row per member host
(`packages/web/src/Items/Group.php:236`). `addPrinter()` (`:136`),
`setSnapinOrder()` (`:316`), `setDisp()` (`:401`), `setAlo()` (`:436`),
`addImage()` (`:501`) and `setAD()` (`:1100`) are the same shape: read
`$this->get('hosts')`, write to each one. Every one of them is a **bulk write
onto the hosts that existed at the moment the button was pressed**.

Nothing records that the write happened, so nothing can replay it. Add a host
to the group afterward and it gets nothing. Remove a host and it keeps
everything. Membership is rendered as a declarative statement — a checkbox that
says "all member hosts have this" — and behaves as a historical one.

`GROUP_SHARED_STATE.md` is the current design honestly described, and reading
it is the clearest statement of the problem: the group page has had to build a
whole vocabulary (`All`/`Some`/`None`, `Hosts: (varies)`, Has/Missing
drill-downs) whose only job is to reconstruct, after the fact, what the group
would have looked like if it had ever owned anything.

### The field evidence

The `persistentgroups` plugin is this defect worked around in SQL. Its manager
returns a `CREATE TRIGGER` for an `AFTER INSERT ON groupMembers`
(`persistentgroups/src/Managers/PersistentGroupsManager.php:36`). On every new
member it finds a **template host whose `hostName` equals the group's
`groupName`** (`:44`) and copies that host's settings onto the new member.

Two things about that are worth stating plainly before anything else:

- The coupling between a group and its template is **string equality between
  two unrelated tables**. There is no key, no constraint, and no UI that shows
  the relationship exists. Renaming either object silently detaches it.
- Its column list is not a design. It is thirteen columns somebody added one at
  a time because each one caused real pain:

      hostImage, hostBuilding, hostUseAD, hostADDomain, hostADOU, hostADUser,
      hostADPass, hostProductKey, hostPrinterLevel, hostKernelArgs,
      hostExitBios, hostExitEfi, hostEnforce

That empirical list is the better specification of "what a group is expected to
push", and this ADR uses it in preference to one derived from reading `Group`'s
methods. Where the two disagree is Decision 2.

The trigger also copies four **association** tables the column list does not
mention — `locationAssoc` (`:58`), `printerAssoc` (`:63`), `snapinAssoc`
(`:69`) and `moduleStatusByHost` (`:92`) — and, having copied the snapins, it
**creates a snapin job and runs them** (`:78`, `:86`). So on a server with this
plugin, adding a host to a group is not only a configuration change: it starts
work on the machine.

## Decision

### 1. A group's contents split by lifetime, not by data type

Two halves, and the line between them is **whether the thing is a value the
host holds or an item the host is granted**.

**Imperative half — the thirteen columns above.** These are columns on the
`hosts` row. A host has exactly one image, one product key, one set of kernel
arguments. There is no coherent meaning to "this host is granted an image by
two groups". The group is only ever a *selection mechanism* for writing them,
and the write is legitimately one-shot. This half **leaves the group entirely**
and becomes mass edit driven from the host list (Decision 8).

**Declarative half — snapins and printers.** These are set membership. A host
can hold any number, from any number of sources, and the union is meaningful.
These **stay with the group**, in new group-owned association tables, and are
**evaluated, never copied**.

The test that separates them is not "is it a column or a row". `hostPrinterLevel`
is a column and belongs to the imperative half; `moduleStatusByHost` is a row
and also belongs to the imperative half (Decision 3). The test is whether two
sources granting the same thing can be combined without one of them losing.

### 2. Where the empirical list and the group page disagree

Both directions matter, and the disagreements are the interesting part.

| Column | persistentgroups trigger | Group page today | After this ADR |
|---|---|---|---|
| `hostImage` | copies | `groupImage` tab, `addImage()`, always clobbers | mass edit |
| `hostBuilding` | copies | **nothing writes it** | **dropped — see below** |
| `hostUseAD` | copies | AD tab, tri-state select | mass edit |
| `hostADDomain` / `ADOU` / `ADUser` | copies | AD tab, no-clobber | mass edit |
| `hostADPass` | copies | AD tab, 32-asterisk sentinel | mass edit, set-only |
| `hostProductKey` | copies | General tab, no-clobber | mass edit |
| `hostPrinterLevel` | copies | Printers tab (`GroupManagement.php:1093`) | mass edit |
| `hostKernelArgs` | copies | General tab, no-clobber | mass edit |
| `hostExitBios` / `hostExitEfi` | copies | General tab, no-clobber | mass edit |
| `hostEnforce` | copies | its own tri-state tab (`:1519`) | mass edit |
| `hostKernel` / `hostDevice` / `hostInit` | **does not copy** | General tab | mass edit |
| auto-logout | **does not copy** | `setAlo()`, replaces all rows | mass edit |
| screen resolution | **does not copy** | `setDisp()`, replaces all rows | mass edit |
| modules | copies (`msState` and all) | Modules tab, tri-state | mass edit (Decision 3) |
| location / OU | copies `locationAssoc` | plugin group hooks | mass edit, plugin field (Decision 9) |
| snapins | copies **and tasks them** | Snapins tab | **group-owned** |
| printers | copies including `paIsDefault` | Printers tab | **group-owned** |

Two findings out of that table are load-bearing.

**`hostBuilding` is dead and the trigger copies it.** `building` is declared in
both `Host` and `Group`'s field maps (`Host.php:50`, `Group.php:49`) and is
**written by nothing** — no page, no JS, no report, no service. Its only other
appearance in the tree is as an always-hidden column in the host export table
(`fog.host.export.js:8`, `visible: false`). It is in the trigger's list because
somebody added it when it meant something. It is not in the mass edit form, and
saying so here is cheaper than somebody re-deriving the same list from the same
plugin in two years.

**The group page covers more than the trigger, and better.** Kernel, primary
disk, init, auto-logout and screen resolution are all pushable from the group
page and are all absent from the trigger. So "cover the persistentgroups list"
is a floor, not a ceiling: mass edit must cover the **union**, or retiring
either mechanism is a regression. That union is what Decision 8 sizes against.

### 3. Modules go to the imperative half, and this is the one place ADR 0001's asymmetry pays off

ADR 0001 established that a module counts as "had" only when
`moduleStatusByHost.msState = 1`, because a module carries an enabled/disabled
state that snapins and printers do not.

That state is exactly why modules cannot join the declarative half. A group
granting a module and a second group granting the same module *disabled* is a
contradiction with no correct resolution — and unlike a snapin, where the union
is obviously right, both readings of a module conflict are defensible. So
modules stay a per-host value that a bulk edit writes, which is what they
already are.

ADR 0001's closing sentence anticipated the opposite migration ("if snapins or
printers ever gain a per-host enabled/disabled state, they should converge on
the module rule"). This decision takes the other fork and the reason is the
same fact: an item with a tri-state cannot be rolled up.

### 4. Snapins resolve at task creation, and the snapshot already exists

The resolved, ordered snapin list is computed **when a task is created** and
written onto the task. A task then records what was decided. A group edited
while a task is in flight does not change that task. **Re-tasking is the only
way to pick up a change, and the documentation must say so in those words**,
because every admin will assume live evaluation and be wrong.

This is far less new machinery than it sounds, and that is the point:
`snapinTasks` **is already the snapshot**. It carries `stSnapinID` and
`stSequence` per row under `UNIQUE (stJobID, stSnapinID)`
(`packages/web/commons/schema-expected.php:813`), and
`Group::_createSnapinTasking()` already materializes an ordered list into it
(`Group.php:1060-1070`). Nothing about the task side changes. What changes is
only where the list it materializes comes from.

### 5. Printers resolve live, at the client's request

There is no task to hang a snapshot on. `fog-client` reconciles desired state
on a schedule against `service/Printers.php`, and a removal has to reach the
machine, so the list must be computed on each request.

### 6. Order is explicit, and a rename must never change it

The resolved order for a host is:

1. **Host-direct snapins**, by `saSequence`, then by `saID`.
2. **Group-granted snapins**, groups ordered by an explicit integer order
   column on the group, then `groupName`, then `groupID`; within a group, by
   the group association's own sequence column.

The `saID` tiebreak at step 1 is not decoration. `snapinAssoc.saSequence`
defaults to `0` and `Host::loadSnapins()` orders by sequence alone
(`Host.php:660`), so any two rows that both sit at 0 — which is every row
`Group::addSnapin()` ever wrote before `appendSnapinSequence()` numbered them,
and every row the persistentgroups trigger writes today — are returned in
whatever order the engine chose. That is a real nondeterminism in the current
code and the resolver fixes it in passing.

**Not name alone, at any level.** Renaming a group must not silently reorder
what runs on a thousand machines. The explicit order column is what makes
ordering a decision an admin made rather than a side effect of alphabetising.

`groupName` is the second key rather than the first because an install that
never sets an order column needs *some* stable answer, and alphabetical is the
one an admin can predict. `groupID` is third. **If `groupName` really is
UNIQUE, the `groupID` tiebreak is unreachable by construction — keep it
anyway**, because it costs one clause and it is the difference between a
resolver that is deterministic and a resolver that is deterministic as long as
an index nobody re-checks is still there. The manifest declares
`UNIQUE KEY groupName (groupName)` and `Route::$nonUniqueNameClasses` does not
list `group` (`Route.php:677`), so the code already believes it. Whether a real
upgraded 1.5 database agrees is unverified here and is UNKNOWN-1 in the
proposal — `roles.rName` is the precedent for the manifest and the disk
disagreeing, and schema step 401 exists because of it.

### 7. Deduplication takes the host's position

A snapin reached both directly and through a group appears **once**, at its
host-direct position. Rationale: the host-direct row is the one an admin
deliberately placed in an order, and a group grant should not be able to move
it. The dedupe happens in the resolver, not by leaning on
`UNIQUE (stJobID, stSnapinID)` — `insertBatch()` upserts, so a duplicate
reaching the insert would silently overwrite the sequence rather than being
rejected.

Default printer follows the same precedence: a host-direct default wins;
otherwise the default from the first group in the Decision 6 order that names
one.

### 8. One resolver, and it takes a set of hosts

The ordered list for a host comes from **one function**. Task creation, the
client's printer request, and any UI preview all call it. Three sorts in three
files drift, and the symptom is a preview that does not match what runs — which
is unfalsifiable from a bug report and miserable to diagnose.

The precedent is `SiteScope::_inScopeSelect()` (`Auth/SiteScope.php:436`),
whose docblock states the identical reasoning for the identical reason: "Two
copies of a membership rule in two dialects is the failure this codebase
already documents elsewhere: when they drift nothing fails, the boundary simply
stops matching in one of the two places."

**The signature takes a set of host ids, not one host.** This is a hard
constraint, not a convenience. GH-707 was exactly this: the "all snapins" path
queried `snapinAssoc` once per member host inside a loop, a thousand round
trips for a thousand-host group, and the fix was to read the association table
once and index by host (`Group.php:975-991`). A resolver whose natural unit is
one host reintroduces that the first time a group task uses it. So:

```
resolveSnapins(array $hostIDs): array   // hostID => ordered [snapinID, ...]
resolvePrinters(array $hostIDs): array  // hostID => ['printers' => [...], 'default' => id|null]
```

The single-host client path calls it with a one-element array. The group task
path calls it with the whole membership. Neither gets its own sort.

**It reads the association tables directly, not through their managers.**
`FOGController::buildQuery()` walks `$databaseFieldClassRelationships`
*transitively* and folds any fourth-element filter into the WHERE rather than
the ON, so every query whose class chain reaches `Host` picks up
`` AND `hostMAC`.`hmPrimary` = '1' `` from `Host`'s own
`MACAddressAssociation` declaration — which turns the LEFT JOIN into an
effective inner one and **silently drops every host with no primary MAC**.
Measured on the 1.5 lab: a membership lookup returned 95 where the raw
`COUNT(*)` on the association table was 1000. There is no flag that suppresses
it; the fix used elsewhere (PR #1233) was to read the association table
directly. A resolver that silently omits hosts is a printer resolver that
strips their printers, so this is not a performance note.

Proposed home: a new autoload-only bucket `FOG\Assign\`. The bucket is not
load-bearing — `FOG\Util\` would do — but `Auth/SiteScope.php` is the model for
"a non-model class that owns one rule", and putting it in `Items/` next to the
ORM models would misdescribe it.

### 9. The printer resolver must not be able to fail quietly

Under printer level `ar` ("FOG Handles all printers",
`Client/PrinterClient.php:44`) the resolved list is **authoritative in both
directions**: the client removes printers that are not on it. A resolver that
errs by returning nothing does not fail to add a printer — it strips printers
from every machine that polls, one machine at a time, for as long as nobody
notices.

Today's endpoint conflates the two cases under one key. A host with no printers
gets `['error' => 'np', 'printers' => []]` (`PrinterClient.php:72`); an
exception anywhere in `json()` is caught by `FOGClient::__construct()` and
becomes `['error' => <message>]` with **no `printers` key at all**
(`FOGClient.php:234`).

**The shipped client was read rather than assumed** — `fog-client` `master` @
`610ad5f` (0.13.0), `Modules/PrinterManager/PrinterManager.cs:60-121` — and it
changes what this decision has to say.

```csharp
if (data.Error && data.ReturnCode.Equals("np", StringComparison.OrdinalIgnoreCase))
{
    RemoveExtraPrinters(new List<Printer>(), msg, installedPrinters);   // remove-all
    return;
}
if (data.Error) return;                                    // <-- the fail-safe
if (!data.Encrypted) { ...; return; }
RemoveExtraPrinters(msg.Printers, msg, installedPrinters);
```

**The fleet-wide strip this decision was written to prevent cannot happen with
this client.** A thrown resolver produces `data.Error` with a `ReturnCode` that
is the exception text, and `if (data.Error) return;` fires before anything
touches a printer. The server's failure shape and the client's failure handling
were already aligned; nobody had written down that they were.

So the rules stand, and one of them stands for a different reason than the
first draft of this ADR gave:

- **The resolver throws on failure. It never returns an empty list to mean
  failure.** Not "or printers get stripped today" — this client will not. The
  real rule is sharper: **never let a failure be spelled `np`.** That one
  string, matched case-insensitively against `ReturnCode`, is the *only*
  removal-on-empty trigger in the client, and it is indistinguishable from the
  legitimate empty case. A resolver that fails and happens to report `np` does
  strip the fleet. Everything else is caught.
- **"This host has no printers" stops being an `error`** and becomes an
  ordinary success carrying `printers: []` with an explicit flag. Traced
  through the client, this is safe: no error means `data.Error` is false, the
  encryption guard passes, and `RemoveExtraPrinters([], …)` removes the same
  set under `ar`. **Safe by that trace, not by design** — see the risk section
  at the end of the proposal. It changes one thing on the wire: the `np` branch
  sits *above* the `!data.Encrypted` guard, so today the empty-case removal
  happens on an unencrypted response and afterwards it would not. That is an
  improvement and it is a behaviour change; it goes in the release notes, and
  the safest shipping order keeps `np` alongside the new field for a release.
- **The mode gate stays and is documented as the blast radius, which is wider
  than "the printers FOG added".** Under `ar`, `RemoveExtraPrinters` removes
  **every installed printer** not in the resolved list, FOG's or not. Under `a`
  it removes only names present in `AllPrinters` — the server's entire printer
  catalogue, sent on every response — so mode `a`'s notion of "FOG-managed" is
  *inferred fresh from the catalogue each time* and is not persisted client
  side. A change to what the catalogue contains is therefore a change to what
  mode `a` removes.
- **One behaviour remains unobserved.** The above is source-verified and has
  not been watched happen: no mode-`ar` host with steady printers was available
  (UNKNOWN-4). The printer resolver should not ship on the reading alone.

The first of these is the same shape as `inScopeWhere()`'s tri-state return
(`SiteScope.php:498`), where the docblock spells out that the *falsy* value is
the permissive one "on purpose" so that a caller writing the natural
`if (!$x) { skip }` skips only the case where skipping is correct. The printer
resolver has the same obligation and the opposite polarity: there is no falsy
return that is safe to skip on, so it does not have one.

### 10. Mass edit lands before anything is removed from the group page

Sequencing is a decision, not a project-management detail, so it is recorded
here: **the group page keeps every imperative tab it has today until the host
list version is shipped and proven.** There is no release in which the only way
to set an image on many hosts at once has been deleted.

The order is: mass edit ships → the group page's imperative tabs are marked
deprecated in the UI and the docs → they are removed in a later release. The
declarative work (snapins, printers moving to group ownership) is independent
of that sequence and can land in parallel.

### 11. Three states per field, out of band

A mass edit form that posts every field is a form that overwrites everything
the admin did not touch. Every field needs three states — **leave alone**, **set
to this value**, **clear** — and this is the single requirement most likely to
be got wrong, because the wrong version looks identical until somebody's images
are gone.

The codebase already has both a right answer and a wrong one, side by side.

- **Right:** the AD join state and `hostEnforce` use an explicit tri-state
  `<select>` — *No change / Enable on all / Disable on all*
  (`GroupManagement.php:1405`, `:1519`). The state is a separate control from
  the value.
- **Wrong, and it must not be carried over:** every text field uses an **in-band
  sentinel** — blank means leave alone, the literal string `NULL`
  (case-insensitive) means clear (`GroupManagement.php:746-753`, `:863-870`).

The sentinel is rejected for three reasons, in increasing order of weight.
It is undiscoverable — nothing in the form says the word `NULL` is magic. It
cannot express "clear" for a control that has no text box, which is why image,
building and printer level had to be special-cased in the first place. And it
has no escape: a value that is legitimately the string `NULL` cannot be set.

So the mass edit form pairs **every** field with an explicit action, and the
value control is disabled unless the action is *set*. This is more markup than
the group page has and it is the correct amount.

**Mixed values are shown, never resolved.** Forty hosts with six images must
render as *(varies)*, not as one of the six. The machinery exists:
`_uniformHostValues()` (`GroupManagement.php:264`) computes
`COUNT(DISTINCT ...)` and `MIN(...)` per column in **one** query over the whole
selection, and `_sharedValueText()` (`:327`) renders `(varies)` /
`(empty on all)` / `<value> (all)`. It is parameterised by a column map and
keyed off a host id list, so it ports to a selection with no change to its
shape.

**The AD password is set-only and never displayed.** Not the 32-asterisk
placeholder — that renders a fake value into a form and then pattern-matches it
back out (`GroupManagement.php:878`), which works but teaches the wrong lesson
and has to be re-remembered at every call site. The mass edit form renders an
**empty** password input whose action defaults to *leave alone*; typing into it
selects *set*. There is no read path. `ADPass` is a credential under
`Redaction::CREDENTIAL_PATTERN` (`Auth/Redaction.php:94`) and so is
`productKey` — both are redacted in the audit trail, and neither should be
displayed by a form that is editing four hundred of them at once.

### 12. One bulk edit is one audit row, and the batching follows from that

**ADR 0021 Decision 11 already decided this, and it decided it against this
exact example** — "the prompt asked how a group edit over 400 hosts where 397
land should be represented". Restating it rather than re-deciding it:

- **One authorized action is one header.** Always. `affectedCount` records how
  many rows the statement touched. No per-object headers — that is the
  `save()`-as-seam mistake arriving by another door.
- Genuine partial success exists only on iterating paths that call `save()` per
  object. There the header stays one row, `outcome` becomes `partial`, and
  `affectedCount` records the successes.

Applied here, and this is what decides the batching:

- **The imperative half is one statement and is already atomic.**
  `FOGManagerController::perform_update()` builds one
  `UPDATE hosts SET ... WHERE hostID IN (...)`
  (`Base/FOGManagerController.php:1963`). Four hundred hosts is one statement,
  not four hundred, so "a 400-row loop that times out" is not a risk this path
  has. It succeeds or fails whole. **One audit header, `outcome = allowed`,
  `affectedCount` = the row count, and — per ADR 0021 Decision 5 — no
  `auditChange` rows, because a bulk update has no before/after.** That gap is
  named in ADR 0021 and the docs must repeat it rather than let it read as a
  bug.
- **Association writes chunk at 500 and are not transactional.**
  `insertBatch()` does `array_chunk($values, 500)` and issues one statement per
  chunk (`FOGManagerController.php:1854`), and **there is no transaction
  support anywhere in the DB layer** — no `beginTransaction`, `commit` or
  `rollBack` in `Db/PDODB.php` or any base class. So a multi-chunk write can
  partially succeed today.

  **The answer is not to add transactions; it is that the split removes the
  volume.** Once a group owns its snapins, granting ten snapins to a
  four-hundred-host group writes **ten rows**, not four thousand. It is one
  chunk. The partial-failure question stops being reachable for the declarative
  half rather than being solved.

- Therefore: **all-or-nothing where the database gives it for free, partial
  with a report where it does not.** Nothing new is introduced to manufacture
  atomicity, and the one path that can still partially land (a mass edit that
  writes both host columns and associations in one submission) reports which
  hosts succeeded. If that reporting turns out to be the expensive part, the
  cheaper answer is to refuse the mixed submission, not to fake a transaction.

**Authorization is all-or-nothing and already is.** `deployMultiPost()` calls
`Authorization::requirePageObjectScopeMass('host', $hosts)` with the comment
"Airtight: one id outside the caller's site scope denies the whole request
rather than quietly tasking the rest" (`HostManagement.php:4633`). Mass edit
takes the same gate for the same reason. Note that this call is a per-id loop
(`Authorization.php:2274`) that dispatches an `OBJECT_SCOPE_CHECK` hook per id
— a known cost at 400 hosts, pre-existing, measured in UNKNOWN-5 rather than
assumed.

### 13. Plugin fields participate, or the ABI has a hole with a name

`location` and `ou` each ship two near-identical hook files —
`AddLocationHost`/`AddLocationGroup`, `AddOUHost`/`AddOUGroup`, 317/282 and
318/277 lines. The group copy exists **only** to set one value across many
hosts at once: `AddOUGroup::groupOUPost()` deletes every member's
`ouAssociation` row and re-inserts one per host
(`ou/src/Hooks/AddOUGroup.php:176`). That is mass edit, reimplemented per
plugin, carrying the same membership defect — and it is worse than core's,
because it *always* clobbers. It cannot express "leave alone" at all, so the
no-clobber convention core adopted for its own group fields was never extended
to the plugins beside them.

So **the mass edit form takes plugin-contributed fields with the same
three-state semantics as core ones**, through a hook pair modelled on the
existing `HOST_ADD_FIELDS` / `HOST_EDIT_SUCCESS` shape that both plugins
already register against (`AddOUHost.php:60-63`):

- `HOST_MASSEDIT_FIELDS` — contribute controls. A plugin supplies a field key,
  a label, a value control, and a mixed-value hint computed over the selection.
  The three-state action control is rendered by core, not by the plugin, so a
  plugin cannot accidentally ship a two-state field.
- `HOST_MASSEDIT_APPLY` — receives the host id list and the **resolved**
  actions for the plugin's own keys, already reduced to *leave alone* / *set
  value* / *clear*. A plugin never parses the sentinel, because there is no
  sentinel.

**The gap this names, precisely.** The ABI already has a bulk **read** seam and
a bulk **delete** seam and no bulk **edit** seam. `API_MASSDATA_MAPPING`
(`Route.php:3188`, `:4785`) lets a plugin decorate a list result for a whole
page of rows; `DELETEMASS_API` (`Route.php:8722`) lets it participate in a
cascading delete over a set. Between them sits the operation neither covers —
changing a value across a set — and there is nothing. Every editing extension
point a plugin has (`HOST_ADD_FIELDS`, `HOST_EDIT_SUCCESS`, `GROUP_ADD_FIELDS`,
`GROUP_EDIT_SUCCESS`) is a per-object edit with a loop behind it.

That hole is why `AddOUGroup` exists as a separate file from `AddOUHost` at
all: there was no other way to say "set this across many". Naming the gap is
half the decision; `HOST_MASSEDIT_*` is the other half, and it is the shape the
two neighbouring seams already imply.

Two consequences follow, and the second is a genuine open scope question:

- `AddLocationGroup.php` and `AddOUGroup.php` become redundant — roughly 560
  lines across two plugins — as does the group-side half of any third-party
  plugin that copied the pattern.
- **Removing them is follow-up work in `fog-plugins`, not part of this.** The
  core change is additive: `HOST_MASSEDIT_*` can ship while the group hooks
  still work. Deleting them is a separate PR against `fog-plugins` gated on
  Decision 10's sequencing — the group page's own imperative tabs and the
  plugins' group tabs are the same deprecation and should be removed in the
  same release, or the group page ends up with an OU tab and no image tab,
  which is worse than either end state.

### 14. Retiring persistentgroups means dropping a trigger, not deleting a directory

Once the split lands the plugin compensates for a defect that no longer exists.
Retirement is not `rm -rf`, for a reason that is specific and serious.

**A server that installed it has a live trigger in its database, and removing
the plugin does not drop it.** Bundled plugins are fetched into
`packages/web/lib/plugins` (`lib/common/functions.sh:3143`), which is inside
`$webdirdest` and is `rm -rf`'d by `configureHttpd()` on every upgrade. So
deleting the plugin from `fog-plugins` does remove the code. It does not touch
the trigger. The trigger keeps copying template-host settings onto every new
group member, forever, silently, long after the plugin that created it is gone
— and unlike the stale `site` plugins row that schema 399 cleaned up, this one
is **active rather than cosmetic**.

So an upgrade step must `DROP TRIGGER IF EXISTS \`persistentGroups\`` and
retire the `plugins` row, **on schema 399's evidence gate pattern**: read table
facts a 1.5-origin database can actually carry, not a column whose value
depends on which branch counted the steps. Step 399's own comment is the
argument — step 334 tried to retire the `site` row by reading
`plugins.pLocation`, which is a rename of `pAnon3`, which 1.5 never wrote, so
the gate matched the empty string on every upgraded row and the DELETE was
unreachable. A step runs once and cannot be corrected in place.

Here the evidence is easier than it was for `site`, because the trigger is
itself the fact:

```sql
SELECT COUNT(*) FROM information_schema.TRIGGERS
WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME = 'persistentGroups';
```

`TRIGGER_SCHEMA = DATABASE()` and not a literal, which is also the fix for a
latent bug in the plugin: its own location-copy branch tests
`table_schema = 'fog'` hardcoded (`PersistentGroupsManager.php:56`), so on any
server whose database is not named `fog` that branch has silently never run.

The step drops the trigger unconditionally (`IF EXISTS` makes it a no-op on a
server that never installed the plugin) and deletes the `plugins` row only when
the trigger was actually there or the row names a plugin whose code no longer
ships.

**One risk that the drop alone does not close.** The external plugin root
`/opt/fog/plugins` is "never touched" by the installer
(`lib/common/functions.sh:2250`, ADR 0009). An admin who copied
`persistentgroups` there keeps the files across the upgrade, and the plugin's
`install()` re-creates the trigger on demand. Dropping a trigger is not
idempotent against a re-install. The cheap mitigation is for core to refuse to
install a plugin on a retirement list; the alternative is to accept it and say
so in the release notes. Either is defensible; silently doing neither is not.

### 15. The trigger has been copying a domain password between hosts, and retirement says so

`hostADPass` is in the copied column list (`PersistentGroupsManager.php:48`).
On every server running this plugin, adding a host to a group has copied the
template host's Active Directory join password onto it — in the database, below
the PHP layer, with no audit row, since the plugin shipped.

**This belongs in the release notes for the retirement, in plain language, and
not only in a changelog line.** The reasoning is the project's own: `Redaction`
exists because two credential leaks landed in one week and both had an opt-in
registry somebody forgot to add to (`Auth/Redaction.php:28-38`). A mechanism
that has been propagating a domain credential for a decade is not a smaller
version of that; it is a larger one, and the only reason it does not appear in
the audit trail is that it never went through PHP.

What the note should say, and its limits: the password was copied **between
hosts within one FOG server's database**, from a host the admin designated by
naming it after the group. It was not transmitted anywhere new and it was
already readable to anything that could read the `hosts` table. The reason to
say it out loud anyway is that an admin who is rotating a domain join account
needs to know that the credential propagated to hosts they never edited, and
nothing in the UI would have told them. This is a **notification**, not an
advisory; whether it warrants more than that is the maintainer's call and is
flagged as such rather than decided here.

### 16. Groups become the tag concept, and are presented as tags

No new entity. **A group is the tag**, and the UI presents it as one.

**The argument is the schema.** A tag is a set of hosts you filter and select
by. That is precisely what group membership already is, and `groupMembers` says
so in three columns:

```sql
CREATE TABLE `groupMembers` (
  `gmID` int(11) NOT NULL AUTO_INCREMENT,
  `gmHostID` int(11) NOT NULL,
  `gmGroupID` int(11) NOT NULL,
  PRIMARY KEY (`gmID`),
  UNIQUE KEY (`gmHostID`,`gmGroupID`), ...
)
```

A plain many-to-many join with **no attributes at all** — no ordering, no
state, no metadata. That is a labelling table and nothing else. Everything
heavier was hung off it later, and hung off it *because it was the only place
to hang things*: a group was the only object in FOG that meant "these hosts",
so every feature needing "these hosts" grew a group tab, and each one wrote its
result onto the member rows because the group had nowhere of its own to keep
it. That accumulation is the defect this ADR is about.

So the split takes a group **back toward what its schema always looked like**.
Adding a second overlapping set-of-hosts concept alongside it would be solving a
**presentation gap with a data model** — introducing an entity, its tables, its
routes, its permissions and its scope rules to fix the fact that one existing
entity is edited from the wrong side and rendered in a dropdown.

**Rejected: a separate `tags` entity.** Stated rather than omitted, because the
case for it is not weak.

The case: group membership is edited from the group side, so "add 40 hosts to 3
groups" is three trips through Group Management, while "apply 3 tags to 40
hosts" is one selection and one action. And after this ADR groups get *heavier*
— they own snapins, printers, an order column and a resolution semantics — and
a heavy object is a poor fit for "label these forty machines ex-lab-B". People
will make forty tags and will not make forty groups. That last observation is
true and is the strongest thing on that side.

Why it loses anyway:

1. **The complaint is editing direction and rendering, and neither is an
   entity.** Both are fixable on the object that already exists, and Decision
   16a makes fixing them binding.
2. **Weight is opt-in and its floor is zero.** A group with no snapins and no
   printers *is* a tag: one row in `groups`, N rows in `groupMembers`, no
   behaviour. Forty label-groups cost forty rows. The heaviness people will
   feel is the dropdown, not the schema.
3. **A second entity doubles the semantics, not just the storage.** The day
   tags exist somebody asks whether a tag can carry a snapin. If no, tags and
   groups differ only by a rule invisible in the UI and every admin picks wrong
   half the time. If yes, there are two group systems and Decision 8's single
   resolver has to merge them — which is the same drift failure Decision 8
   exists to prevent, reintroduced at the entity level.
4. **The bill is measurable**, because absorbing the `site` plugin paid it
   recently: `Route::$validClasses`, `$nonUniqueNameClasses`, the `deletemass`
   cascade case, `SiteScope::$_nodes`, the permission registry, FK declarations
   under ADR 0031, a page class, five JS files, saved-filter targets and the
   API description. None of it is hard; all of it is real; and none of it moves
   a host into a group any faster.

**Rejected: do nothing and let people use groups as they are.** This is what
happens today, and what happens today is that people do not use groups for
labelling, because applying one to forty hosts is unpleasant enough that they
keep the information somewhere else. That is the gap tags were being reached
for. Deciding against tags without closing it decides nothing.

### 16a. What "presented as tags" requires — binding, not follow-up

These are part of this decision. A group that is correct but unpleasant to
apply in bulk fails at the thing this decision exists to enable, and the
failure mode is specific: people keep not using groups for labelling, and the
gap that tags were reached for comes straight back with the split having made
groups *heavier* in the meantime.

**1. The host list carries a groups column, rendered as chips, and it is
filterable.**

Not a comma-joined string. Chips, because the unit an admin acts on is one
label and they need to see at a glance which of several a host carries. The
column does not exist today — `HostManagement.php:114-169` enumerates the
header set and there is no groups entry.

**The filtering framework already exists, and the constraint is that it is
column-backed.** Every grid gets a **Filter** button (SearchBuilder) and a
**Column search** header row, server-parsed in
`FOGManagerController::filter()` — #1471/#1476/#1477, on this branch. The host
list opts into nothing; `fog.filters.js` is generic and it gets both for free.
(The comment at `HostManagement.php:150-153` still says "this grid has no
per-column search UI". It is stale, it predates that work, and it should be
corrected when this column lands — it is exactly the kind of comment that gets
read as current.)

What that framework cannot do is the thing this requirement needs.
`_sbCriterion()` resolves a criterion to `` `$column['db']` `` and returns
empty when a column has no `db`, or carries `removeFromQuery` or `nosearch`
(`FOGManagerController.php:1050-1082`); the type comes from
`searchBuilderType()` → `columnType()`, which reads the schema manifest for a
**real column of a real table** (`FOGBase.php:5733`). A groups column has no
backing scalar on `hosts`. It is a relationship, and the filter path has never
had to express one.

So requirement 1 is not "build filtering" and it is not "add a column". It is
**teach the existing filter path its first relationship column**: the column
contract grows an optional SQL form — a subquery expression `_sbCriterion()`
uses in place of `` `db` `` — plus a `searchBuilderType()` answer for it. That
is a change to a shared helper every grid runs through, so it is plan-first work
in its own right and it sets the pattern every later relationship filter will
copy.

Two constraints on that expression, both already learned here:

- **It goes into the query, not onto the page.** `complex()` applies the
  `LIMIT` before any row-level filtering, which is why the site boundary is
  passed as a subquery ANDed into the row query, the filter count *and* the
  total count (`Route.php:3149-3172`). A post-filter returns empty first pages
  and counts describing rows the caller cannot see. A groups filter is the same
  shape — `hostID IN (SELECT gmHostID FROM groupMembers WHERE gmGroupID IN
  (...))` — and inherits the same rule.
- **Every binding it emits must be named by the clause it emits.** `sqlexec()`
  binds every entry of `$bindings` to all three of `complex()`'s queries, so an
  unreferenced binding makes PDO refuse the statement and the whole list answers
  HTTP 406. That shipped once already, in `_sbDate()`, and broke exactly the two
  conditions the feature existed for. Bind inside the branch that names it.

The rendering half is cheap and already has its mechanism: `relColumn()` takes
a `prime` callback that receives every row on the page at once
(`Route.php:7667`), so the chips cost one extra query per page rather than one
per host.

**2. Membership is editable from the host side, in bulk, both directions.**

Add **and remove**, over the list selection, on the same gate as Queue Task /
Add to Group / Delete. Today membership is add-only from the list and otherwise
only editable from the group side, which is the whole "three trips through
Group Management" complaint. Remove is not a nicety: a label you can apply and
cannot retract is not a label, and its absence is why the current modal reads
as a group operation rather than a tagging one.

**3. It has to *feel* lightweight, and that is a requirement with a test.**

Chips, typeahead, and create-on-the-fly from the list. Nobody minds twenty
tags; everybody minds twenty groups in a dropdown. The test this requirement
has to pass: **applying three labels to forty hosts is one selection and one
action** — the same sentence that was the argument for tags. If the
implementation cannot pass that sentence, it has not satisfied this decision.

Two thirds of this already exists and is easy to miss: the list's Add to Group
modal is already a select2 with `tags: true`, `tokenSeparators`, ajax typeahead
against `/group/names/`, and a `createTag` handler that badges an unmatched
term `(new)` (`fog.host.list.js:29-90`). What is missing is remove, multi-group
clarity, and requirement 4.

**4. Create-and-associate goes through the shared path, not a second one.**

The host **edit** page already does this properly:
`$.registerCreateAndAssociate('host-group', hostGroupsTable)`
(`fog.host.edit.js:617`). The helper (`fog.common.js:1761`) POSTs the created
id to the association tab's own update URL — its docblock is explicit that this
is "the same call *Add selected* makes, so this is not a second write path" —
and it is already used by eleven tabs across host, group and site.

The **list** modal does not use it. It posts `groups[]` and `groups_new[]` to
`sub=saveGroup`, which creates a group from a raw string with
`->set('name', $group)->addHost($hosts)->save()` and no name-collision check
(`HostManagement.php:4115-4123`) — where the group page's own rename path does
check, via `getManager()->exists()` (`GroupManagement.php:733`). So there are
already two creation paths and the newer one is the looser one.

Reuse the shared helper. This is not tidiness: the list modal is about to
become the *primary* surface for membership, and a second write path that
skips the first one's validation is the wrong thing to promote.

**Two defects on that path must be fixed as part of this, not after it.**
`saveGroup()` (`HostManagement.php:4083`) is a state-changing POST handler and:

- **It performs no CSRF check.** `FOGPageManager` calls `checkAuthAndCSRF()`
  centrally only when the resolved method takes an `Ajax` or `Post` suffix
  (`FOGPageManager.php:178-189`); the handler is named `saveGroup`, there is no
  `saveGroupPost`, and the handler does not call it itself — it is absent from
  all seven `checkAuthAndCSRF()` call sites in the file. Page *permission* is
  still checked, because `requirePagePermission()` runs unconditionally on
  every dispatch (`FOGPageManager.php:195`); CSRF is the gap.
- **It does not bound the posted host ids to the caller's object scope.**
  `deployMultiPost()` calls `requirePageObjectScopeMass('host', $hosts)` on the
  identical shape of input, with the comment "Airtight: one id outside the
  caller's site scope denies the whole request rather than quietly tasking the
  rest" (`HostManagement.php:4633`). `saveGroup()` has no equivalent. The
  central `requirePageObjectScope()` is passed the URL `$id`, of which a
  list-level action has none.

Both are pre-existing and neither is created by this ADR. They are named here
because this decision promotes that endpoint from a convenience to the main
way membership is edited, and promoting an unbounded, un-CSRF'd write is not
something to discover afterward.

**Both confirmed on the lab, 2026-09-01** (proposal §5, UNKNOWN-6): the CSRF-
less POST returned `202` where the same shape to `deployMulti` returned `403`,
and a site1-scoped user added a host from another site, where `deployMulti`
refused the same id.

**And a third thing the code reading had missed, which is a decision rather
than a fix.** `saveGroup`'s required permission is **`group.create`**, not
`host.edit` — `Authorization::SUB_OVERRIDES['host']['savegroup']`
(`Authorization.php:157`) overrides what `_subToAction()` would derive. That is
correct for the endpoint as it exists, because it can mint groups from
`groups_new[]`. It is **wrong for the endpoint this decision asks for**: adding
forty existing hosts to three existing groups is not a group creation, and
requiring `group.create` means anyone who may label hosts may also create
groups. The membership editor should require `group.create` only when
`groups_new[]` is non-empty, and something narrower otherwise.

**Decided: `group.create` only when `groups_new[]` is non-empty; membership
editing behind `group.edit`.**

`group.edit`, and not a new `group.assign`, because the permission registry has
a **fixed action vocabulary per node** — `coreRegistry()` declares
`'group' => ['view', 'create', 'edit', 'delete', 'task']`
(`Authorization.php:496`) and permissions are `<node>.<action>` strings (ADR
0005). A sixth action for one endpoint would be the first node in the registry
with a bespoke verb, and the thing it describes is not bespoke: **adding a host
to a group is editing the group.** The membership rows are the group's.

It also avoids an upgrade regression a new permission would create. Every
existing role would lack `group.assign` on the day it shipped, so bulk
membership editing would stop working for everyone holding `group.edit` until
an admin went round the roles — a silent loss of a capability people already
have, to gain a distinction nobody asked for.

`host.edit` was the other candidate and is rejected: it would let anyone who
can rename a host also rewrite what every group contains, which is the wider
grant, not the narrower one.

So the endpoint checks two permissions rather than one, and which it needs
depends on the request body:

| Request | Requires |
|---|---|
| add/remove existing hosts to/from existing groups | `group.edit` |
| the same, plus `groups_new[]` naming a group that does not exist | `group.edit` **and** `group.create` |

That is a **narrowing** for the common case — today's endpoint demands
`group.create` for all of it — so a role holding `group.edit` but not
`group.create` gains bulk membership editing it could not previously reach,
and no role loses anything. Both checks are inside the handler rather than in
`SUB_OVERRIDES`, because the answer depends on the body and a route-level
override can only name one permission; that is the same reasoning
`savedfilters` records for its own `null` entry (`Authorization.php:289`).

The existing `SUB_OVERRIDES['host']['savegroup'] => 'group.create'` entry stays
as the floor until the handler is split, so nothing is loosened before the
narrower check exists to replace it.

**5. The group list shows what a group grants.**

A column saying whether a group carries snapins or printers, so a label-group
reads as a label at a glance and a heavy group reads as heavy. This is the
cheapest half of making one entity serve both jobs legibly, and without it the
Group Management list is forty rows that all look identically consequential.

**What this decision costs, stated rather than hidden.** "Group" remains a bad
name for a label. The model is right and the word is wrong, and no amount of
chips fixes a noun. If the requests for tags continue after 1.6 with all five
requirements shipped, that is evidence about the *word*, and the answer is
renaming or aliasing the concept in the UI — not building the second entity
this decision rejected.

### 17. No exclusion mechanism, and the loss is real

Under rollup there is no way to say "this host gets the group's snapins except
S2". Today that is expressible, because everything is a direct row: remove the
row and it is gone.

**Decision: no exclusion mechanism in this work. "Make another group" is the
answer, and the capability loss goes in the release notes as a loss.**

Why not build one:

- **Negatives do not compose.** Two groups grant S; one of them excludes this
  host. Does the host get S? Both answers are defensible and neither is
  guessable from the UI, which is the CSS-specificity failure mode: a rule
  system where the answer requires reading the rules rather than looking at the
  screen.
- The workaround got cheaper with this change, not more expensive. Before, a
  second group meant a second bulk copy onto every member. After, a group is a
  row and a membership list.
- An exclusion is a third source in the Decision 6 order, and the resolver's
  value is that its order is short enough to state in one sentence.

Why it is nevertheless a real loss, and asymmetric: **the capability exists
today by accident** — it is a side effect of the copy semantics being broken in
the first place — and it disappears the moment the semantics are fixed. An
admin who has been using "remove the row from the one host" as an exception
mechanism will find it stops working, and the failure is silent: the snapin
comes back on the next resolve.

The mitigation that is in scope: the host's snapin tab shows group-granted
snapins as read-only rows **naming the granting group**, beside the removable
host-direct ones. The admin sees why the row will not go away and where to go
to change it. That does not restore the capability; it stops the loss being
mysterious.

### 18. Existing associations are not migrated, and nothing guesses their provenance

Every upgraded server carries `snapinAssoc` and `printerAssoc` rows that were
copied from a group years ago. **Nothing distinguishes "chosen directly for
this host" from "copied from a group in 2019"** — `snapinAssoc` is
`(saID, saHostID, saSnapinID, saSequence)` and `printerAssoc` is
`(paID, paHostID, paPrinterID, paIsDefault, paAnon1..5)`
(`schema-expected.php:760`, `:582`). There is no timestamp, no creator, no
source.

**Decision: no backfill. Existing direct rows stay direct rows, which is what
they literally are.**

What that means concretely, and it is less alarming than it sounds:

- On upgrade, every group owns **zero** snapins and **zero** printers, because
  no group ever did. Every host keeps every row it has. **Behaviour is
  unchanged for every existing host and every existing group.**
- The new removal semantics apply to what is granted after the upgrade. Take a
  host out of a group and the old copied rows stay — which is **exactly what
  happens today**, so it is not a regression. It is the new promise not
  reaching back, which is a different and much smaller thing.

**Rejected: infer group ownership from what members currently share.** This is
the `CONVERT_TZ` sweep that `docs/development/utc-storage-boundary.md` §2.1
rejects, in a different table. A snapin held by all forty members might be a
group push or might be forty deliberate choices, and the data cannot tell. The
inference is unfalsifiable, and the destructive version — create the group row
*and* delete the host rows — silently removes a snapin from a host that chose
it directly, the moment that host leaves the group. The non-destructive version
(create group rows, keep host rows) is a no-op under Decision 7's dedupe and so
buys nothing but clutter.

**Rejected: a boundary that gates the semantics**, on the model of the UTC
document's `seBoundary`. It would let the resolver treat pre-upgrade direct
rows as group-derived and remove them on membership change — but deciding that
a legacy row *came from* a group is the same unknowable inference, just
deferred to read time. The UTC boundary works because the question there is
"how should this value be *interpreted*", and interpretation can be
conditional. Here the question is "what did the admin *mean*", and there is no
conditional form of that.

**What replaces a migration: an opt-in, previewable reconciliation the admin
performs.** A tool on the group page that says *"every member of this group
holds snapin X directly — promote it to a group snapin and remove the 40 direct
rows?"*, with the count and the host list shown before anything is written. The
admin asserts the intent; the software does not guess it. It is reversible —
re-pushing from the host list restores direct rows — and it is testable, which
a silent sweep is not. It is also **not required for correctness**, so it can
ship after the split rather than blocking it.

**Optional, and only for labelling:** a one-row watermark table recording
`MAX(saID)` and `MAX(paID)` at upgrade, so the UI can mark a direct row as
"pre-1.6". `saID` and `paID` are `AUTO_INCREMENT`, so a watermark orders
creation without a timestamp column. Its one caveat is that InnoDB's
auto-increment counter is not guaranteed monotonic across every restore and
server-restart path, so an id below the watermark is *evidence* of a legacy row
and not proof — which is fine for a label and would not be fine for a
semantics gate, which is the second reason the boundary is not one.

## Consequences

- **`persistentgroups` becomes unnecessary**, and its retirement is a schema
  step rather than a deletion. The trigger's side effect of *running* snapins
  on a newly added host has no equivalent in the new model, deliberately:
  granting a snapin is not tasking it.
- **Roughly 560 lines across two bundled plugins become redundant**
  (`AddLocationGroup`, `AddOUGroup`), plus the group-side half of any
  third-party plugin that copied the pattern. Removing them is separate work in
  `fog-plugins`, sequenced with Decision 10.
- **Storage for the declarative half drops by a factor of the membership.** Ten
  snapins on a four-hundred-host group is ten rows instead of four thousand,
  and the change to a group's snapins is one write instead of four thousand.
- **A group edited mid-run does not change a task in flight, and people will be
  surprised.** This is the documentation obligation in Decision 4 and it is the
  most likely support question this change generates.
- **ADR 0001 becomes half-true and stays in force.** Its tri-state derivation
  is still how the group page reports member state for modules; its premise
  that a group owns nothing is no longer true for snapins and printers. The
  file gets an amendment note rather than a rewrite, because the module
  asymmetry it argues for is what Decision 3 relies on.
- **`GROUP_SHARED_STATE.md` becomes the mass edit document.** Most of it
  survives the move — the no-clobber convention becomes the three-state
  convention (Decision 11), the `Hosts: (varies)` hint becomes the selection
  hint — and the sections describing snapins and printers as derived coverage
  are replaced rather than edited.
- **One new ABI surface (`HOST_MASSEDIT_*`) with no deprecation window
  required.** ADR 0017 records that "there is no shipped 1.6 plugin ABI", so
  this is free before 1.6.0 and expensive after it. That is an argument about
  *when*, and it is made in the proposal.
- **The filter path gains its first relationship column.** Everything it
  filters today is a real column of a real table, resolved through
  `$column['db']` and typed from the schema manifest. Decision 16a's groups
  column is the first that is neither, so the column contract grows an optional
  SQL form — and that is a change to a helper every grid runs through, whose
  shape every later relationship filter will copy. It is the one piece of this
  ADR that is plan-first work on its own account.
- **`saveGroup()` gets a CSRF check and an object-scope bound.** Both are
  pre-existing gaps this ADR does not create; it promotes the endpoint, which
  is why closing them is inside the work rather than beside it.
- **There will be exactly one create-and-associate path for host↔group.** The
  list modal stops creating groups from raw strings and joins the eleven tabs
  already using `$.registerCreateAndAssociate()`.
- **The presentation requirements are not a follow-up and a release that ships
  the split without them has not delivered this decision.** Decision 16 is only
  correct if a group is cheap to apply in bulk; the model change alone makes
  groups heavier and leaves the labelling gap exactly where it was, which is
  the outcome that made a `tags` entity look necessary in the first place.

## Alternatives considered

**Keep the copy semantics and fix the symptom — re-push a group's settings when
a host joins.** This is the persistentgroups design with the string-equality
coupling replaced by something sane, and it is the smallest change that closes
the reported bug. Rejected because it does not close the *other* half: removal
still does nothing, a host taken out of a group keeps everything, and the
group's state is still unknowable from the group's own rows. It would also make
the group page's tri-state derivation permanent, which is a lot of machinery
whose only job is to compensate for the model.

**Move everything to the group, including the imperative columns.** Uniform and
tempting. Rejected because a host has one `hostImage`, and two groups granting
different images has no correct answer — the resolver would need a conflict
policy for exactly the fields where a conflict is a configuration error rather
than a union. Decision 1's test exists to keep those fields out.

**Copy on join, evaluate on read — both.** Rejected as the worst of the two: it
has the copy's staleness *and* the resolver's indirection, and when they
disagree there is no way to say which is right.

**Give `snapinAssoc` a provenance column and migrate.** A `saSourceGroupID`
would make removal semantics work on existing data. Rejected because the column
can only be populated by the inference Decision 18 rejects — there is no
provenance to record retroactively, only a guess to write down, and writing a
guess into a column makes it look like a fact.

**Build the exclusion mechanism now, before anyone asks.** Rejected per
Decision 17; the composition rule is the problem, not the storage.

**Build a `tags` entity.** Rejected per Decision 16: `groupMembers` is already
an attribute-free many-to-many labelling table, so a second set-of-hosts
concept would be solving a presentation gap with a data model. The
counter-argument is recorded there rather than dismissed, along with what the
decision costs.

**Decide against tags and stop there.** Rejected as deciding nothing. The
labelling gap is real and is what made tags look necessary; Decision 16a is the
half of the decision that closes it, which is why those requirements are
binding rather than a follow-up.

## The claim that would hurt most if it is false

**That `snapinTasks` is already a sufficient snapshot** (Decision 4).

Everything about the snapin half is sized on the assumption that task creation
already materializes an ordered per-host list into a table that the running
task reads, so the change is only to the source of that list. If the client or
`service/snapins.checkin.php` re-reads `snapinAssoc` at run time for anything
beyond the task rows — a re-resolve, a "has this host still got this snapin"
check, a fallback when the job is missing — then a group edited mid-run *does*
change a task in flight, Decision 4 is a lie, and the snapin half needs a
genuine snapshot table rather than a redirected read.

It is one grep and a three-step lab test — UNKNOWN-2 in the proposal. Nothing
else here is load-bearing in the same way: if `groupName` turns out not to be
unique the resolver's third tiebreak starts earning its keep, which is why it
is there; if the trigger's duplicate-key behaviour is different from the
inference, the retirement step is unchanged.
