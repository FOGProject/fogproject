# The audit trail: a header at the authorization seam, changes beside it

## Status

proposed

## Context

FOG has role-based permissions (ADR 0005), an object-scope boundary (ADR 0006,
0019), external identity providers (ADR 0007, 0014) and API tokens. It cannot
answer "who tried to do what, when, from where, was it allowed, did it work,
and what changed".

`history` is the thing that looks like an answer, and ADR 0020 established why
it is not. The reason is disqualifying rather than cosmetic: `logHistory()`
returns early unless `self::$FOGUser` is valid (`fogbase.class.php:2507`), and
`$FOGUser` is assigned once per request at boot from the session
(`loadglobals.class.php:56-59`). So failed logins are absent **by
construction** — the actor is invalid at exactly the moment the guard runs —
and so are successful ones, because nothing reassigns `$GLOBALS['currentUser']`
inside the login request. Removing the guard turns `history` into a table
anonymous traffic can write. The two tables want opposite things from the same
guard, which is the clearest possible sign they are two tables.

Four further findings shaped what follows.

### 1. FOG already ships a login audit trail. It is two files in the web root.

`ProcessLogin::loginPost()` writes every accepted and rejected login to a flat
file (`processlogin.page.php:113-126`, `:136-149`), and `chmod`s it `0200` on
every attempt. The installer creates both (`lib/common/functions.sh:8973-8976`).
On the live 1.6 server:

```
--w-------. 1 nginx nginx 0 Aug 21 06:22 /var/www/fog/fog_login_accepted.log
--w-------. 1 nginx nginx 0 Aug 21 06:22 /var/www/fog/fog_login_failed.log
```

`$webdirdest` is the document root — these sit beside `index.php`.
`GET /fog/fog_login_failed.log` returns **403, and the file mode is the only
reason**: nothing in `fog.conf` excludes `*.log` and `location ~ ^/fog/(.*)$`
matches it. A list of every attempted username with its failure reason is one
`chmod` from public, and the same mode makes it unreadable to the administrator
without root.

It covers **one of four login paths**. OIDC calls
`$user->establishSession(OIDC::AUTH_SOURCE)` directly (`oidcflow.class.php:237`)
and never reaches `loginPost()`. `service/checkcredentials.php` — the iPXE
credential endpoint — has its own tempfile rate limiter (`:27-41`) and no log.
Cookie-resumed sessions (`processlogin.page.php:169-206`) log nothing. The text
is gettext-translated at write time, the files are never rotated, and nothing
reads them.

This matters to the "do nothing" option at the end: the status quo is not
"FOG does not audit logins". It is worse than nothing, because it looks like
something.

### 2. There are no transactions. Anywhere.

`beginTransaction`, `commit` and `rollBack` appear **nowhere** in
`packages/web` or `packages/service`. This kills "re-read inside the
transaction" as an option outright, and it bounds what any audit row is
allowed to claim. It is the single most constraining fact in this ADR.

### 3. Two dispatch choke points, and 40 places that bypass the ORM object

| Entry point | Choke point | Authorization |
|---|---|---|
| Web UI | `FOGPageManager::render():193` | yes |
| REST API | `Route::runMatches():1208` | yes |
| fog-client protocol (~30 files in `packages/web/service/`), `lib/reg-task/` | none | **zero** `Authorization::` calls |

Both choke points already call `Authorization::resolve*Permission()` on the
line the audit call would sit next to, and both hold everything a header needs.

Separately: **40 call sites** use `FOGManagerController::update()`, which
builds one bulk `UPDATE ... WHERE` (`fogmanagercontroller.class.php:1029-1135`)
and never instantiates a model. This matters twice below.

### 4. The secrets registry already exists, and it has already been forgotten once

Core carries five overlapping registries:

| registry | what it does |
|---|---|
| `Route::$sensitiveFields` | tier 1 — stripped from lists, **kept** on a single-entity GET (fog-client reads `host.ADPass` back to join a domain) |
| `Route::$sensitiveAlwaysFields` | tier 2 — stripped from everything |
| `API_SENSITIVE_FIELDS` hook | plugin-declared additions |
| `Route::sensitiveFieldMap()` | the memoized union, and the **only** correct accessor |
| `Route::$serverOwnedFields` | fields only the server may write |
| `SENSITIVE_SETTING_PATTERN` + `$sensitiveSettings` | `globalSettings` rows whose *value* is a credential |

Commit `58483d6` (2026-08-19) is the cautionary tale and it is directly on
point. `storagenode.pass` was never declared, and declaring it was not the
bug: `stripSensitivePayload()` resolved the class from the payload's `_lang`
stamp, so it stripped by the **outer** class. The storage *group* grid embeds
the whole master node object, so the FTP password went to anyone holding
`storagegroup.view`. Checking `/storagenode/list` said clean and missed it.

The commit message names the lesson exactly: *"naming them per route is what
hid this"*. The probe that found it walks every payload for a credential at any
depth instead of enumerating fields per route. That lesson transfers whole to
audit redaction.

The settings registry already implements the "cannot be forgotten" shape — a
pattern plus a short explicit list, "so a credential setting added later is
masked by default instead of silently leaking until someone remembers to add
it", plus a false-positive list so `FOG_USER_MINPASSLENGTH` stays readable.

And it is not a one-off. Two days after `58483d6`, PRs **#1261/#1262** fixed a
second instance of the same class from the other direction: the SQL fault log
— the failure sink added by #1257/#1258 so machine-path write failures stopped
going unrecorded — wrote the failed statement's **bound values** into a 0755
file, passwords and tokens included. A logging mechanism built *that week*,
specifically to record failures, leaked credentials on its first outing.

Two independent incidents in one week, in two subsystems, neither of which had
a redaction step at all until it was found. That is the evidence for Decision
6 defaulting closed rather than enumerating: an opt-in registry is exactly what
both of these had, and both were forgotten. It is also a direct warning for
this ADR, because an audit trail is a third mechanism whose entire job is to
write down what happened — the same shape, at greater volume, with a longer
retention.

## Measurements

The prompt asked for the before/after capture cost to be measured rather than
guessed. Script:
`/home/telliott/scripts/background_scripts/measure_audit_beforeafter.php`,
read-only against the live 1.6 database from a copy of the web tree, 15 rounds
× 100 ops per arm, arm order rotated each round, medians reported.

```
host rows on this server: 86

  baseline: load the object            0.7926 ms/op
  (a) + snapshot on load               0.7868 ms/op   delta -0.0058  (+0 queries)
  (b) + re-read before save            0.9318 ms/op   delta +0.1392  (+1 query)
  the extra SELECT alone               0.1191 ms/op

snapshot overhead as % of an object load : -0.74%
re-read  overhead as % of an object load : +17.56%
```

**(a) snapshot on load is free.** It measured *below* the baseline, which is to
say inside the noise. That is the expected result and the reason is structural:
`FOGController::load()` selects every column in one query
(`fogcontroller.class.php:1022-1041`), so the whole row is already in
`$this->data` by the time anything could snapshot it. The snapshot is an array
copy of data FOG has already paid for.

**(b) re-read costs ~18% of an object load**, every edited object, and it
cannot be done "inside the transaction" because there are none. It buys
nothing (a) does not, and it buys it later, from a row that may have moved.

**Neither works on the 40 bulk-update sites.** `perform_update()` issues one
`UPDATE ... WHERE` and there is no object to snapshot and no per-row read. A
before/after there means a pre-`SELECT` of the affected set — N rows of every
column, for a statement whose whole point is not to touch N rows individually.

So: **before/after is worth having, on the paths that load an object, by
snapshot.** It is not a uniform capability and this ADR does not pretend it is.

## Decision

### 1. Two tables, not three

The prompt proposed three: header, changes, activity feed. The header/changes
split is right and is the standard pattern — it is a **transaction-log** or
**audit header/detail** shape, the same one ServiceNow's `sys_audit` and
Rails' `audited`/`paper_trail` use: one row per authorized action, N rows per
changed field. The reasoning in the prompt for that split is also right, and
it is the stronger of the two reasons given: the header is known at the
authorization seam and the detail at the model, so neither layer invents data
it does not have.

The third table does not survive. The stated reason for it is a dated purge
over the activity feed that a view could not support — but that only holds if
activity is a *view*. Make it a **projection**: a `renderable` flag plus a
`type` on the header, and a purge that deletes header rows older than N days
independently of the changes rows. A separate physical table would have to be
written by the same call at the same moment from the same data, differing only
in prose — which is ADR 0020's `history` defect rebuilt on purpose, in the
locale of whoever triggered it.

The concrete cost of the third table is worth naming: a human-readable feed
written at audit time is a translated string, and every consequence ADR 0020
records for `history` follows. The feed's text belongs to the *renderer*,
built at read time in the reader's locale from `type` + `subject*`, which is
ADR 0020 Decision 5 applied here.

- **`auditLog`** — one row per authorized action. Carries ADR 0020's frame
  (`createdTime`, `createdBy`, `ip`, `type`, `subjectType`/`subjectID`/
  `subjectLabel`, `text`) plus `authSource`, `permission`, `outcome`,
  `correlationID`, `affectedCount`.
- **`auditChange`** — one row per changed field, FK `auditID`, plus `field`,
  `oldValue`, `newValue`, `redacted`.

`authSource` exists because FOG already draws the distinction and documents
it: `User::sessionAuthSource()` (`user.class.php:297-303`) is about the
*request*, `users.uAuthSource` about the *account*, "and the two genuinely
differ". An audit row is a fact about a request.

### 2. The seam is confirmed: `Authorization::require*Permission()`

The prompt's reasoning holds, and the evidence is stronger than the prompt
claimed.

Both gates respond and exit with no record —
`requirePagePermission()` (`authorization.class.php:574-596`) and
`requireApiPermission()` (`:817-826`) — so **a denial is unrecorded today**,
and a denial is unreachable from `FOGController::save()` by definition: the
save never happens.

The additional argument, which the prompt did not have: `save()` audits by
*side effect* rather than by *intent*. One UI action is a dozen `save()` calls
across associations, so "the admin edited a host" becomes fourteen rows with
no way to tell they were one operation — and on 40 call sites there is no
`save()` at all, because `FOGManagerController::update()` writes bulk SQL
directly. A seam that misses denials, splinters one action into many rows, and
has 40 blind spots is the wrong seam three times over.

So the **header** is written at authorization. The **changes** are written at
the model, joined by correlation id.

### 3. The correlation id is request-scoped static state, and that is FOG's established pattern

The prompt was unsure whether request-scoped state is acceptable practice or
just what FOG happens to do. It is established practice here, with precedent
in both directions:

- `FOGBase::$FOGUser` — `protected static`, assigned once per request as a
  reference to `$GLOBALS['currentUser']` (`fogbase.class.php:216, 440`).
- `Route::$_rethrowDepth` — `private static`, and its docblock explains
  *why* it is state rather than a parameter: "listem() fires hooks that may
  re-enter Route, and the inner call must inherit the outer caller's context"
  (`route.class.php:195-205`).

The second is the closer analogue and the better precedent: it is
request-scoped state existing specifically so a nested call inherits an outer
decision, which is exactly what a correlation id is for.

One warning comes with it, and it is load-bearing. `Route::sensitiveFieldMap()`
carries forty lines on a re-entrancy trap: it fires a hook, `processEvent()`
calls `Route::getIds('hookevent')`, which re-enters `Route`, which asks for the
map again — "an OOM in ~40 frames". **So the correlation id must be set
without firing a hook, and read without one.** A plain static assigned at the
choke point, never lazily initialized through an event.

### 4. Machine-originated writes get a header with a synthetic actor

`service/`, `lib/client/` and `lib/reg-task/` contain **zero** `Authorization::`
calls, so there is no gate to hang a header on. They should not therefore get
*no* header — a host registering itself or a task reporting failure is exactly
the kind of thing an audit trail is for.

They get a header written at the point of the write, with:

- `createdBy = 'fog'` — which is what `FOGController::save()`'s `createdBy`
  auto-fill already produces when no user is valid
  (`fogcontroller.class.php:762-768`), so this is consistent with ADR 0020's
  frame rather than a new convention.
- `authSource` recording the machine credential kind — `host-token`,
  `node`, `anonymous` — which is the only actor-like fact those paths hold.
- `permission = ''`, because none was consulted. An empty permission is itself
  the useful signal: it says "this write bypassed authorization", and a query
  for it is a query for FOG's whole unauthenticated write surface.

Scope limit: only writes, and only the ones that change state a person would
care about — registration, task state transitions, inventory. Not every
checkin. That is a volume decision, not a principle, and it is revisitable
per-endpoint.

### 5. Before/after by snapshot on load, and only where an object exists

Per the measurements. `FOGController::load()` sets `$this->original = $this->data`
after its existing SELECT; `save()` diffs `original` against `data` restricted
to `$this->dirty`. Zero extra queries, no measurable cost.

Three honest gaps, stated rather than engineered around:

- **Bulk updates have no before/after.** The 40 `update()` sites write a
  header with `affectedCount` and no `auditChange` rows. Adding a pre-SELECT
  is possible and is deliberately *not* proposed: it would turn a single
  statement into N+1 for the paths chosen precisely to avoid that.
- **A create has no before.** `auditChange` rows for a create carry
  `oldValue = NULL`, and the frame's `subjectID` is filled after the insert.
- **An object saved without being loaded has no before.** `logHistory()`'s own
  writers do this. The diff is against an empty snapshot, which reads as a
  create, and that is correct.

### 6. Redaction is a property of the field, resolved centrally, and defaults closed

HARD constraint, and the design follows `58483d6`'s lesson rather than
repeating its bug.

**Where the rule lives.** Not a new list. `Route::sensitiveFieldMap()` already
unions core tier 1, core tier 2 and plugin-declared fields, and its docblock
already warns to "read both tiers via `sensitiveFieldMap()`, never these
properties directly, or plugin-declared fields are skipped". Audit consumes
that accessor. The registry moves out of `Route` to a neutral owner — it is
no longer an API-emitter concern once a second subsystem depends on it — with
`Route`'s properties kept as the declaration site so no existing plugin
breaks.

**Audit uses tier-2 semantics for both tiers.** The tier-1 carve-out exists
because fog-client legitimately reads `host.ADPass` back to join a domain.
There is no equivalent legitimate reader of an audit row's old password. So
anything in either tier is redacted in `auditChange`.

**What is recorded.** `field` and `redacted = 1`. `oldValue` and `newValue`
are `NULL` — not masked strings, not lengths, not hashes. "This column
changed" is the fact worth keeping; anything derived from the value is a
disclosure with extra steps.

**How a new sensitive column cannot be forgotten.** Three layers, because one
is what failed:

1. **Default closed by name.** A pattern over the friendly key —
   `PASS|PWD|SECRET|TOKEN|KEY` — redacts by default, modelled directly on
   `SENSITIVE_SETTING_PATTERN`, which exists so "a credential setting added
   later is masked by default instead of silently leaking until someone
   remembers". A field matching the pattern that is *not* a credential goes
   on an explicit allow-list, the same shape as the existing
   `FOG_ENABLE_SHOW_PASSWORDS` carve-out. The failure mode inverts: forgetting
   costs a redacted field, not a leaked one.
2. **The registry**, for credentials the pattern misses — `host.ADPass` does
   not match `PASS` as a whole word but does match the pattern above;
   `storagenode.key` needs the registry.
3. **A test that walks every class**, not every route. `58483d6` shipped
   `tests/route-read-path-guards.test.php` and a probe that "walks every
   payload for a credential at ANY depth rather than naming fields per route".
   The audit equivalent enumerates `Route::$validClasses`, diffs every
   `databaseFields` key against the pattern and the registry, and fails on any
   key that looks like a credential and is in neither. A new column is caught
   by CI, not by memory.

### 7. A delete records field names, never field values

HARD constraint, and it is the sharpest one because uniform before/after
recording reintroduces exactly the exposure Decision 6 closes: a delete has a
`before` for **every** column, including all seven the prompt names.

`FOGController::destroy()` reaches only for `id` and `name` today (the four
`logHistory()` sites in `fogcontroller.class.php:840-887, 1200-1247`), and
that is the right amount.

So a delete writes a header with `subjectType`/`subjectID`/`subjectLabel` and
**no `auditChange` rows at all**. Not redacted rows — none. The header already
says what was destroyed and by whom; a per-column inventory of a deleted host
is a full credential dump wearing an audit badge.

This is a deliberate asymmetry with edit, and it is the reason undelete is out
of scope (Decision 10).

### 8. Immutability: two real mechanisms, and no promise beyond them

The prompt asked what FOG can honestly promise. The honest answer is: **not
tamper-proof, and the ADR should not use the word.**

The web tier holds `GRANT ALL PRIVILEGES ON <db>.*`
(`lib/common/functions.sh:4204`). Anyone holding the FOG database credential
can rewrite or drop the audit table, and no application-level design changes
that. What FOG can offer:

- **No write routes.** `auditlog` and `auditchange` are not added to
  `Route::$validClasses`. Read is an explicit route behind a new `audit.view`.
  There is no edit route, no delete route, no generic CRUD. This is ADR 0020
  Decision 7 applied from the start rather than retrofitted.
- **A documented least-privilege grant.** FOG already ships per-table grants —
  `fogstorage` gets `SELECT` on the database plus `INSERT,UPDATE` on ten named
  tables (`functions.sh:1134-1145`) — so the mechanism is established. The
  obstacle is that restricting the web tier means a second credential in
  `config.class.php`, which the installer must create and the schema-deploy
  probe must know about; steps 336 and 338 each broke that probe over a
  *column*. So: ship the table, document the
  `REVOKE UPDATE, DELETE ON <db>.auditLog` an operator can apply, and treat
  the second credential as follow-up work. An audit table that ships beats one
  blocked on a grant refactor.

Explicitly **not** a `BEFORE UPDATE`/`BEFORE DELETE` trigger. It would block
the retention sweep in Decision 9, block an operator repairing a bad row, and
ride into every `mysqldump` — so a restore onto a server with different
privileges fails in a way that looks nothing like its cause. It also defends
only against an attacker who already holds the database credential, and that
attacker can drop the trigger.

The docs say "append-only by construction of the application, not by
cryptographic guarantee". Hash chaining and external shipping are a real
follow-up with a different threat model; Decision 8's route and grant
positions are what keep that possible without a schema change.

### 9. Retention: `audit.manage`, not `settings.edit`

The prompt's objection to `settings.edit` is correct and understated. The
permission node is `'settings' => ['view', 'edit']`
(`authorization.class.php:328`) and **six** page nodes map onto it —
`about`, `apidocs`, `hookevent`, `notifyevent`, `oui`, `setting`
(`:58, 62, 180, 198, 200, 210`). So `settings.edit` is simultaneously "may
change the audit retention window" and "may edit the OUI table". It is not a
gate.

The proportionate answer is a **new permission node**, `audit`, with
`view` and `manage`. That is the mechanism FOG already has for exactly this,
it costs one registry entry, and it is how every other sensitive capability
here is gated. Elevation-per-session is disproportionate, and the prompt's own
instinct is right: it is a second authentication concept landing next to
OIDC and the `login()`/`establishSession()` split, for a setting a
narrowly-granted permission already fits. If FOG ever wants elevation it
should want it for a class of actions, decided on its own terms.

Pruning: one setting (`FOG_AUDIT_RETENTION_DAYS`, `0` = keep forever) applied
by one scheduled sweep. No daemon deletes database rows today — verified, no
`DELETE FROM` or `deletemass` in `packages/service` — so this is a new
capability, and it belongs in `FOGPluginRunner`, the existing non-root
periodic daemon (ADR 0010), rather than a new one. Adding the setting requires
bumping `FOG_SCHEMA` in the same step or the insert is silently skipped.

### 10. Shrinking the audit trail is itself audited, and refused if it cannot be

HARD constraint, taken literally. Retention changes, manual purges and
disabling audit each write an `auditLog` row **before** the change takes
effect, and the write is checked:

```
if (!$auditRow) { refuse the shrink; }
```

That is the constraint's teeth, and it needs the failure to be *visible*,
which is not free here. ADR 0020's related finding applies:
`FOGController::save()` has historically returned success on a swallowed SQL
error. So the audit writer's own failure path must propagate rather than
return `$this` — and the retention sweep must check it, not assume it.

The one honest limit: this protects against an operator turning audit off
quietly through FOG's own UI. It does not protect against `DELETE FROM
auditLog` at the MySQL prompt. See Decision 8; do not oversell it.

### 11. Partial success is `affectedCount` on one header, and the prompt's example is not partial

The prompt asked how a group edit over 400 hosts where 397 land should be
represented. Checking the path changes the question: `GroupManagement`'s mass
edits call `HostManager->update(['id' => $this->obj->get('hosts')], '', [...])`
(`groupmanagement.page.php:1082, 1511`), which is **one bulk
`UPDATE ... WHERE`**. It does not partially succeed 397/400 — it is one
statement that succeeds or fails, and the row count is the only outcome
available.

So:

- **One authorized action is one header.** Always. `affectedCount` records how
  many rows the statement touched. `outcome` is `allowed`.
- **Genuine partial success exists on the iterating paths** — a loop calling
  `save()` per object, where some throw. There, the header stays one row,
  `outcome` becomes `partial`, and `affectedCount` records the successes. The
  per-object detail is `auditChange` rows for the ones that landed; an object
  that failed produces no change rows, and its absence is the record.
- **No per-object headers.** That is the `save()`-as-seam mistake from
  Decision 2 arriving by another door.

### 12. Out of scope, named

- **Undelete / revert.** HARD constraint accepted, and Decision 7 makes it
  structural rather than a policy choice: with no field values on delete there
  is nothing to restore from. The prompt's own reasoning stands —
  `Route::deletemass('host')` cascades to `task` while `taskLog` is in no
  cascade (`route.class.php:5559-5575`), so a restorable unit is a subgraph,
  not an object. If it is ever wanted it is separate work with separate
  storage and its own redaction answer.
- **Read auditing.** `view` actions do not write audit rows. This is what
  stops the table becoming the 2016 `history` firehose that
  `UNIQUE (hText, hTime)` was invented to survive (ADR 0020). Read auditing is
  a different feature with a different volume profile and needs its own
  decision.
- **Tamper-evidence.** Decision 8.

## Sequencing

Separately reviewable merges. Nothing is backfilled — both tables start empty —
so the phases are about coverage, not data.

| # | Merge | Ships independently? | Why |
|---|---|---|---|
| 1 | `auditLog` + `auditChange` schema, `FOG_AUDIT_RETENTION_DAYS`, `FOG_SCHEMA` bump | yes, inert | Nothing writes. Name every column in the step — steps 336/338 broke the installer probe by not doing so. |
| 2 | The redaction resolver + the CI test that walks every class | **yes, and it is valuable alone** | It is a standing check on `Route`'s existing registries. It would have caught `storagenode.pass` before `58483d6`. |
| 3 | Authentication events: login accepted/rejected, logout, token rejected | yes | Five call sites. Closes the worst gap and proves the write path on a low-volume event. `User::logout()` needs the call *before* `set('id', 0)` (`user.class.php:627-630`) or the row records nobody. |
| 4 | Header at both choke points + `outcome=failed` via `Route::sendResponse()` | yes | Volume becomes real here, which is why it follows 3. |
| 5 | Denials at `require*Permission()` and the three `Authorization::assert*` refusals | yes | The rows most worth having. |
| 6 | `$original` snapshot + `auditChange` on object save paths | needs 1, 4 | Depends on nothing else. |
| 7 | Machine-path headers (`service/`, `reg-task/`) | needs 1 | Per-endpoint; can land endpoint by endpoint. |
| 8 | `audit` permission node, Audit Log page, retention sweep, Decision 10 refusal | needs 1, 4 | Not before there is something to read. |
| 9 | Retire the two login files; drop `functions.sh:8973-8976` | needs 3 shipped for a release | Last. Leave existing files on disk — they are the only pre-upgrade record. |

Phases 1–3 alone give FOG a readable record of failed logins across all four
login paths, which it does not have today. Phase 2 is worth merging even if
everything else is rejected.

## Dependency on ADR 0020, and on the span ADR

**Depends on 0020, does not absorb it.** `auditLog` takes 0020's six-part
frame verbatim; if that ADR's frame changes, this table changes with it. Two
one-way constraints flow back: 0020's Decision 7 (event tables lose write
routes) is a floor that `auditLog` starts below, and `history` keeps its
operational-narrative job rather than becoming an audit store — so 0020's
phases on `history` are not blocked by anything here.

What this ADR does **not** need from 0020: it does not need `history` fixed
first, and it does not need 0020's Decision 1 (record contract vs. shared base
class) resolved either way. If 0020 later grows an `EventController` base
class, `auditLog` is a candidate to extend it; nothing here forecloses that.

**Constrains the span ADR in one place.** A span is not an audit record. A
completed image is an *outcome*; the task that started it is the auditable
event and is audited here at `host.task`. Do not duplicate it. Machine
provenance for the `service/` endpoints is shared between the two — Decision 4
gives them a header shape; which of those endpoints is worth a row is a
volume question the span ADR is better placed to answer, since it owns the
tables they write.

## Consequences

- FOG can answer the question in the first line of this ADR, including for
  denied and errored attempts, for the first time.
- `audit.view` is a new permission worth granting narrowly: it necessarily
  discloses attempted usernames.
- One extra INSERT per authorized write action, plus N per changed field on
  object paths. The measured cost of the snapshot itself is zero.
- Before/after is not uniform. Bulk updates and deletes carry a header and no
  change rows, and the docs must say so rather than letting the gap read as a
  bug.
- The redaction default inverts the current failure mode: a forgotten
  credential column is redacted, not leaked.
- FOG promises append-only by construction, not by guarantee.

## Alternatives considered

**Three tables, as proposed.** Rejected in Decision 1 — the activity feed's
one advantage (independent purge) is available from a flag on the header, and
its cost is a translated string written at audit time, which is the exact
`history` defect ADR 0020 exists to undo.

**One table with a discriminator.** Rejected on cardinality rather than taste.
The header is one row per action and the changes are N; folding them means
every header column is `NULL` on change rows and every change column is `NULL`
on headers, and `affectedCount`/`outcome`/`permission` stop being answerable
by a single row. It also makes Decision 7 harder to enforce — "a delete writes
no change rows" is a clean invariant across two tables and a filtered
constraint in one.

**Audit in `FOGController::save()`.** Rejected in Decision 2, three ways:
denials are unreachable, one action splinters into many rows, and 40 call
sites have no object.

**A hook seam so plugins can supply the audit sink.** Rejected. The appeal is
real — shipping audit to syslog or a SIEM is a legitimate want — but the
answer is a *read*-side export, not a write-side seam that lets a listener
decide whether the row exists. Two specific reasons here: ADR 0017 records
that no core hook has ever loaded on any FOG server (all declare
`$active = false`), so a core audit listener would ship inactive and look like
it worked; and the nearest existing auth-adjacent hook, `LoginFail`, hands
every listener the plaintext credential by reference
(`user.class.php:436-442`). Decision 3's re-entrancy warning is the third.

**Files, done properly** — outside the web root, rotated, structured. Cheapest
option, and it has a genuine argument: a compromised FOG credential cannot
rewrite a file the web user cannot read. Rejected because it has no reader and
no permission model, and `audit.view` is the feature. FOG has run this
experiment for years; the result is two empty write-only files in its own
document root.

**Do nothing.** The strongest case is volume, and Decision 12's exclusion of
read auditing is doing most of the work to keep this bounded. The counter is
Context 1: the status quo is not an absence of auditing, it is an audit trail
in the document root covering a quarter of the login paths, which is worse
than an absence because it reads as coverage.
