# Event logs share a record shape, not a table

## Status

proposed

## Context

FOG has three tables that record "something happened": `history`,
`userTracking` and `taskLog`. They were written years apart, they name the
same facts differently, and nothing enforces any of it.

That is the observation this ADR was asked to test. Testing it changed the
answer in three ways, and each of the three changes what should be built.

### The convention already exists — at the model layer, not the column layer

The column names really are three vocabularies. But `FOGController` does not
address columns; it addresses *friendly keys*, and `save()` gives two of those
keys behaviour that no other key has:

```php
// fogcontroller.class.php:762
case 'createdBy':
    $val = self::$FOGUser->isValid() ? trim(self::$FOGUser->get('name')) : 'fog';
    break;
case 'createdTime':
    $val = self::formatTime('now', 'Y-m-d H:i:s');
    break;
```

Any model that maps a column to `createdBy` or `createdTime` gets it filled
automatically. So the real state of the estate is:

| friendly key | `history` | `userTracking` | `taskLog` |
|---|---|---|---|
| `createdTime` | `hTime` | `utDateTime` | `createTime` |
| `createdBy` | `hUser` | — | `createdBy` |
| `ip` | `hIP` | — | `ip` |

`createdTime` is **already unified across all three** (`history.class.php:42`,
`usertracking.class.php:43`, `tasklog.class.php:56`). `createdBy` and `ip` are
unified across two of three. The three-vocabulary problem is a *column*
problem that the ORM has already half-solved above it.

This matters for scope. A proposal to invent a naming convention would be
inventing one that exists; the work is finishing it and writing it down, which
is a much smaller and much safer change than it looked.

### "Actor" is not one concept, and one of the three is not an actor at all

The original observation was that the actor is `hUser`, `utUserName` and
`createdBy`. Two of those are the same thing. The third is not.

- `history.hUser` — auto-filled from `self::$FOGUser`. A FOG operator.
- `taskLog.createdBy` — the same auto-fill: a FOG operator, or the literal
  `'fog'` when no user is valid, which is what every daemon-written row gets.
- `userTracking.utUserName` — written by `UserTrack::json()`
  (`lib/client/usertrack.class.php:94`) from `$_REQUEST['user']`. That is the
  **Windows/OS account that logged into the endpoint**, reported by the
  fog-client. It is not a FOG identity, it cannot be resolved against `users`,
  and it is not who caused the row to be written.

The actor of a `userTracking` row is the fog-client, which is to say `'fog'` —
exactly what `createdBy`'s auto-fill would produce. `utUserName` is what the
event is *about*.

Getting this backwards is the specific thing a physical table merge would make
permanent: it would put an operator name and an endpoint OS account in one
column, and every later query that filters "events by user X" would silently
mix two identity namespaces. The mistake is cheap to make — the columns look
identical — and expensive to undo once rows exist.

### `taskLog` already implements the shape; the other two are behind it

Schema 338 and 341 gave `taskLog` a machine-readable type, a subject id, a
denormalized subject label and a denormalized task-type name. Read the three
tables against what an event row actually needs and the picture is not "no
shape" — it is one table that arrived and two that did not:

| the frame | `taskLog` | `userTracking` | `history` |
|---|---|---|---|
| when | `createTime` | `utDateTime` | `hTime` |
| actor (FOG identity) | `createdBy` | — | `hUser` |
| origin address | `ip` | — | `hIP` |
| what kind of event, as a code | `logType` | `utAction` | — |
| what it is about, by id | `logHostID` | `utHostID` | — |
| what it is about, by class | `logTaskTypeName`\* | implied | — |
| what it is about, by label | `logHostName` | — | — |
| detail for a human | `logText` | `utDesc` | `hText` |

\* not a class name — a task-type name. `taskLog`'s subject class is always
`Host`, so it has never had to say so.

`history` is the outlier, and its defect is structural rather than cosmetic: it
has **no subject at all**. The entity a row is about exists only inside the
prose string, and that string is assembled from gettext calls at write time
(`fogcontroller.class.php:840-860` and three more sites):

```php
$msg = sprintf('%s %s: %s %s: %s %s.',
    self::shortName($this), _('ID'), $this->get('id'),
    _('NAME'), $this->get('name'), _('has been successfully updated'));
```

So the row is stored in the locale of whoever triggered it. There is nothing to
query. There is also nothing to query *within* one locale: the successful-update
arm uses `_('NAME')` and the other three arms use `_('Name')`, so even an
English-only install has two spellings of the same field label in the same
column.

Both of the other two tables already do the right thing here — `utAction` stores
`0`/`1`/`99` and is translated in the grid formatter
(`route.class.php:2637-2648`), `logType` stores `state`/`error`/`warning`. The
rule "a code goes in the column, the translation happens at render" is already
FOG's practice in two places out of three.

### What the lack of shape costs, verified

**1. `userTracking`'s own writer uses a fourth spelling, and loses data.**

`UserTrack::json()` writes:

```php
self::getClass('UserTracking')
    ->set('datetime', $tmpDate->format('Y-m-d H:i:s'))
    ->set('date', $tmpDate->format('Y-m-d'))
```

`UserTracking` declares no `datetime` key — it declares `createdTime`
(`usertracking.class.php:43`). `FOGController::set()` resolves the key against
`databaseFields`, `databaseFieldsFlipped` and `additionalFields`
(`fogcontroller.class.php:252-263`); `datetime` is in none of them, so `set()`
throws `Invalid key being set` and catches its own exception into
`self::debug()` (`fogcontroller.class.php:331-341`), which is silent at default
log level.

The consequence is a row whose two date columns disagree. `utDate` gets the
client-supplied `date=`; `utDateTime` never receives it, falls through to
`save()`'s `createdTime` auto-fill, and records the moment the server processed
the request. A fog-client reporting a queued or offline login writes a row dated
one day and timestamped another. This is the naming drift costing something
today, in the one table with two independent time columns.

**2. `history` records no logins — successful or failed.**

`User::attemptLogin()` and `User::establishSession()` both call `self::log()`
(`user.class.php:370` and `:420`) to record login success and failure. Neither
ever reaches the table:

- `FOGBase::log()` returns early `if (self::$ajax)`
  (`fogbase.class.php:2470`).
- `logHistory()` returns early unless `self::$FOGUser instanceof User` **and**
  `isValid()` (`fogbase.class.php:2507`).
- `self::$FOGUser` is a reference to `$GLOBALS['currentUser']`
  (`fogbase.class.php:440`), which is assigned exactly once per request, at
  boot, from `$_SESSION['FOG_USER']` (`loadglobals.class.php:56-59`). It is the
  only assignment of that global in `packages/web`.

On the login request itself the session has no `FOG_USER` yet, so `currentUser`
is `new User(0)` and `isValid()` is false. `processlogin.page.php:91` does set
`self::$FOGUser` — but from `attemptLogin()`'s *return value*, after the log
calls inside it have already run.

So the guard that exists to stop anonymous traffic writing history rows also
discards the two events for which the actor's identity is the entire point. The
same guard means no daemon, no fog-client checkin and no API-token request
writes history either.

**3. Deleting a host does three different things to four log tables.**

`Route::deletemass('host')`'s `$removeItems` map (`route.class.php:5559-5575`):

| table | on host delete | result |
|---|---|---|
| `imagingLog` | deleted (`'imaginglog' => $findWhere`) | imaging history gone |
| `nfsFailures` | deleted (`'nodefailure' => $findWhere`) | gone |
| `taskLog` | kept, and denormalized by schema 341 | readable forever |
| `userTracking` | kept, **not** denormalized | orphan rows |

None of this was decided as a policy. `taskLog` was fixed in schema 341 because
9 of 56 rows on one install had already lost their host name; the ADR-worthy
part of that step is its reasoning, which applies verbatim to `userTracking`
and was never carried across.

`userTracking` is the worse case of the two, because it is the *only* one of
the four whose grid resolves the host live:

```php
// route.class.php:2628-2635
self::relColumn('utHostID', 'hostname', 'Host',
    function ($d, $row) { return \Initiator::e(self::rel('Host', $d)->get('name')); })
```

A deleted host makes every one of its login rows render a blank hostname. The
rows survive and become unreadable, which is the worst of both policies.

**4. `history`'s UNIQUE KEY is a rate limiter, not an integrity constraint.**

Traced to `b59013dd` (2016-06-27). That commit's other change is the answer:

```diff
-        //$this->logHistory($txt);
+        $this->logHistory($txt);
```

It **re-enabled** the general debug logger's write into `history`, which had
been commented out. `UNIQUE (hText, hTime)` was added in the same commit to
stop that firehose repeating itself — "so as to not continuously repeat updates
due to many actions all at once", in the commit's own words.

Schema step 228 is the whole migration (`schema.php:3302-3306`):

```sql
TRUNCATE TABLE `history`;
ALTER TABLE `history` CHANGE `hText` `hText` VARCHAR(255) NOT NULL;
ALTER TABLE `history` ADD UNIQUE INDEX `updateTime` (`hText`,`hTime`);
```

Three things follow, all still true:

- `hText` is `varchar(255)` **because** of the index. It was `LONGTEXT`
  (`schema.php:64`) and MySQL cannot index a `LONGTEXT` without a prefix
  length. The truncation of every history message is a side effect of a rate
  limiter.
- The de-duplication is lossy and silent. `save()` builds
  `INSERT ... ON DUPLICATE KEY UPDATE` (`fogcontroller.class.php:809`), so a
  collision quietly rewrites the existing row with the same values. Two
  genuinely distinct events in the same second become one row, no error, no
  count.
- The index cannot serve the one thing that reads the table. `History_Report`
  lists via `Route::listem('history')`, ordered by time; the only non-primary
  index is `hText`-leading, so a time-ordered read of `history` is a full scan
  with a filesort at every install size.

There is one more artifact of that commit. `log()` prefixes `[Y-m-d H:i:s]`
before calling `logHistory()`, which prefixes it again — so a `log()`-sourced
row carries the timestamp twice inside `hText` and once in `hTime`, spending
about 42 of its 255 characters restating a column it already has.

**5. Loose ends in `userTracking`.**

- `utAnon3 varchar(2) NOT NULL` — mapped as `anon3`
  (`usertracking.class.php:46`), never read or written anywhere in
  `packages/web`. The same dead `anonN` column exists on `snapins` and
  `printerAssoc`; it is a 2000s-era schema habit, not a `userTracking` quirk.
- `utAction varchar(2)` has no lookup table and no constants. Its three values
  are literals in one `switch` in `route.class.php:2640-2647`, and that switch
  has **no default arm** — an unrecognized code renders as an empty cell rather
  than as itself. Contrast `TaskLog::TYPE_STATE`/`TYPE_ERROR`/`TYPE_WARNING`,
  which are class constants.
- `99` is the widest value the column can hold. A fourth action needing three
  characters is a schema change.

**6. All four tables are ordinary REST resources.**

`history`, `imaginglog`, `tasklog` and `usertracking` are all in
`Route::$validClasses` (`route.class.php:386, 395, 428, 434`), which means each
gets the full generic operation set — including `POST`, `PUT` and `DELETE`. An
append-only record of what happened is, today, editable and deletable by
anything holding `<class>.edit`. That is a decision nobody made; it is what
being in the list means.

### Tables checked and excluded

`hookEvents`, `notifyEvents`, `userCleanup` and `dirCleaner` are name
registries — two columns, id and name. `greenFog` is a schedule. `inventory` is
a current-state row per host with a `UNIQUE KEY (iHostID)`, not an event log.
`nfsFailures` is genuinely event-shaped (`nfNodeID`, `nfHostID`, `nfTaskID`,
`nfDateTime`) but is a transient replication-retry ledger, deleted on host
delete and never surfaced in the UI. No bundled plugin creates a log table.
`imagingLog`, `snapinTasks`, `multicastSessions` and `fileDeleteQueue` are
spans and belong to the companion ADR.

## Decision

### 1. The shape is a record contract, not a table

Three tables stay three tables. Two arguments, in order of weight:

**The columns are not interchangeable.** `history.hText` is 255 characters of
prose; `taskLog.logText` is `TEXT`; `userTracking` has a `date` column that
`taskLog` has no use for and an `hIP` that it has no way to fill. A single
table either takes the union — a wide row that is mostly `NULL` for every
writer — or forces the payloads together, which is how the actor mistake in the
Context section becomes permanent.

**Nothing that reads them would get simpler.** Each table has exactly one
reader: `History_Report`, the host/group Login History tabs, and Task
Management's log pane. There is no query today that wants all three at once,
and no request for one. A merge would rewrite three readers, migrate three
tables and delete no code.

The convention is enforced where `createdBy` and `createdTime` are already
enforced — in `FOGController` — not by a foreign key.

### 2. The frame is fixed; the payload is per-domain

Every event row carries the same six-part frame, using these friendly keys:

| key | meaning | rule |
|---|---|---|
| `createdTime` | when it happened | auto-filled by `save()`. Already correct in all three. |
| `createdBy` | the **FOG** identity responsible — operator, API user, or `'fog'` | auto-filled by `save()`. Never an endpoint OS account. |
| `ip` | the address the event arrived from | |
| `type` | what kind of event, as a stable machine code | class constants. **Never translated at write time.** |
| `subjectID` / `subjectLabel` | what it is about: the id, and a denormalized label that survives the id's deletion | schema 341's pattern, generalized |
| `text` | detail for a human | untranslated at write; `TEXT`, not `varchar` |

`subjectType` is added only where the subject is not always the same class.
`taskLog` and `userTracking` are always about a `Host`; `history` is about
anything, so `history` needs it and they do not. A model whose subject class is
fixed states it as a constant rather than storing it on every row.

Everything else is domain payload and stays as it is: `userTracking.utDate`,
`taskLog.taskID`/`taskStateID`/`logTaskTypeName`.

### 3. `utUserName` maps to `subjectLabel`, not to `createdBy`

Its `createdBy` is `'fog'`, which `save()` already produces for free. This is
the load-bearing correction in this ADR and the reason to write it down before
touching any table.

### 4. Deletion policy is declared per table, in the model

Three policies are defensible; three policies chosen by accident are not. Each
event model declares which it is, and `Route::deletemass()` is the one place it
is honoured:

- **cascade** — the rows go with the subject (`nfsFailures`; arguably
  `imagingLog`, which the span ADR should settle).
- **retain-denormalized** — the rows survive and carry `subjectLabel`
  (`taskLog`, and `userTracking` once it has one).

"Retain, with a dangling id and no label" is not one of the options.

### 5. `history` gets a subject, a type, and an untranslated text

The six `logHistory()` call sites — four in `FOGController::save()` and
`destroy()`, one in `FOGBase::log()`, plus the model itself — stop assembling
translated prose and pass the parts. The renderer builds the sentence, in the
*reader's* locale, from `type`, `subjectType`, `subjectID` and `subjectLabel`.

### 6. `UNIQUE (hText, hTime)` is removed, and replaced with `KEY (hTime)`

It is a lossy deduplicator sitting on the table most likely to be asked "what
happened at 14:32". Once `history` rows carry a type and a subject id, two
identical rows in one second are two real events, and the collision that
currently swallows one of them is the silent-overwrite bug class.

The firehose it was built for is worth re-checking rather than assuming away:
`FOGBase::log()` has seven callers today (`hookmanager`, `eventmanager` ×2,
`user` ×2, `hookdebugger`, `template`), and `hookdebugger`/`template` are the
volume risk. The replacement for a unique index is a bound on the writer — a
level check or a per-request cap — not a constraint that discards rows after
the fact.

Removing the index also unblocks `hText` returning to `TEXT`, which is the
only reason it is 255 characters.

### 7. Event tables leave `Route::$validClasses`' write operations

Read routes stay. `POST`, `PUT`, `PATCH` and `DELETE` on `history`,
`taskLog` and `userTracking` are removed. Retention pruning, if it is wanted,
is a named operation with its own permission, not `DELETE /api/history/{id}`.

## Migration

The three tables have data on every install, and a schema step that fails
half-way leaves a server that cannot boot. So: **no big-bang step, and no step
that both adds a column and rewrites rows.** Each phase below is separately
shippable, separately revertable, and leaves every reader working.

Phases 0 and 1 are worth doing whatever happens to the rest.

**Phase 0 — bugs, no DDL.** `set('datetime')` → `set('createdTime')` in
`UserTrack::json()`. A `default` arm on the `utAction` formatter. Neither
depends on this ADR being accepted; both are live defects found while writing
it.

**Phase 1 — write the convention down, no DDL.** Document the six frame keys on
`FOGController` next to `createdBy`/`createdTime`. Add the class constants
`userTracking` is missing. Nothing on disk changes; new code stops drifting.

**Phase 2 — additive columns, no writes.** One schema step per table, each a
closure guarded on `information_schema` in the style of steps 336/338/341, each
adding nullable or `DEFAULT ''` columns only:

- `userTracking`: `utCreatedBy`, `utIP`, `utHostName`.
- `history`: `hType`, `hSubjectType`, `hSubjectID`, `hSubjectLabel`.

An install that stops here is unchanged in behaviour. This is the only phase
that touches `ALTER TABLE`, and it is the reversible half of the DDL.

**Phase 3 — writers fill them; readers still read the old columns.** Two
releases of overlap. Rows written after the upgrade carry both the prose and
the structure. Nothing has switched over, so a revert is a code revert.

**Phase 4 — readers switch.** `History_Report` and the Login History tabs read
the structured columns and fall back to prose when `hType` is empty. This is
where the discontinuity becomes visible, and it becomes visible in exactly one
place.

**Phase 5, and not before a full release cycle after Phase 4 —** backfill
`userTracking.utHostName` from `hosts` for rows whose host still exists (the
same restricted `UPDATE ... JOIN` schema 341 used, which is a no-op on re-run),
and drop `history`'s unique index. `hText` to `TEXT` rides with the index drop.

Deliberately not in any phase: dropping `utAnon3`, or narrowing `utAction`.
Dead columns cost a byte and break third-party readers; leave them.

### The old `history` rows

They stay, and they are **not** backfilled.

The prose is machine-generated and has a parseable form — but only in the
locale it was written in. A parser would recover structure on English installs
and nothing on the rest, producing a table whose completeness depends on the
admin's language. A dataset that is silently complete in some deployments and
silently partial in others is worse to query than one with a clean boundary, so
the boundary is the honest choice: rows before the Phase 4 upgrade have prose
and no subject; rows after have both.

`History_Report` renders a legacy row exactly as it does today. The visible
consequence is that filtering by subject or type returns nothing before the
upgrade date, which is what actually happened and should look like it.

FOG has a precedent here and it is worth naming to reject it. **Schema step 228
opens with `TRUNCATE TABLE history`** — the 2016 change deleted every existing
row. It was defensible then, because a `LONGTEXT` column cannot carry the index
being added and the rows could not survive the type change intact. Neither
condition holds now: every column added here is additive, and the old rows
remain exactly as readable as they are today. There is no reason to spend the
data.

## Consequences

- Two tables gain columns; no table changes type or loses a column, so a
  downgrade after any phase but 5 is a code revert.
- `history` rows written after Phase 4 are queryable by subject and type, and
  render in the reader's language rather than the writer's.
- Deleting a host stops silently breaking its login history.
- `userTracking` grows three columns for a fact it already had, which is the
  price of the row outliving its subject. Schema 341 paid it for `taskLog`.
- Nothing here makes `history` trustworthy as an audit record. It still drops
  rows on an invalid actor and it still has no append-only guarantee. That is
  the next ADR's problem, and this one deliberately does not pretend to solve
  it.
- The frame is six keys wide. A future writer that needs a seventh is a signal
  the event is a span, not a point.

## What this constrains, and what it does not

**ADR 0021 (the audit trail).** Three constraints:

1. It cannot use `history` as its store. `logHistory()`'s early return on an
   invalid `$FOGUser` means failed logins — the single most audit-relevant
   event FOG produces — are absent by construction, and lifting that guard
   would let anonymous traffic write the audit log. An audit store needs its
   own write path.
2. If it defines an audit record, it uses this frame. `createdTime`,
   `createdBy`, `ip`, `type`, `subject*`, `text` mean the same things there.
   The addition it will need is integrity — append-only enforcement, and a
   deletion policy that is *never* cascade.
3. Decision 7 (event tables lose their write routes) is a floor, not a
   ceiling. Audit will want more than that.

**ADR 0022 (spans and work items).** Two constraints and one open question:

1. A span is not two events. `imagingLog` has `ilStartTime`/`ilFinishTime`,
   `snapinTasks` has `stCheckinDate`/`stCompleteDate`; do not model either as a
   pair of rows from this ADR's shape. Where a span table adopts the frame,
   `createdTime` means *when the row was created*, and the span's own start and
   end stay separate columns with their own names.
2. `subjectID`/`subjectLabel` applies unchanged. `imagingLog` is already
   halfway — `ilCreatedBy`, `ilImageName` and `ilType` are exactly this pattern
   under different names.
3. Open, and genuinely that ADR's call: `imagingLog` is currently **deleted**
   on host delete while `taskLog` is retained. Decision 4 says the policy must
   be declared; it does not say which one imaging history should get.
   (ADR 0022 Decision 5 answers it: retain-denormalized, with `ilHostName`.)

Neither companion ADR needs this one implemented first. Phase 1 — writing the
frame down — is the only thing they depend on, and it changes no code.

## Alternatives considered

**One physical `eventLog` table.** Rejected on the actor argument above: the
merge's only real gain is a single reader, and there is no reader that wants
all three. It would also make every existing consumer — `History_Report`, two
Login History tabs, Task Management's log pane, four REST resources and any
third-party plugin reading them — a rewrite, in exchange for a table whose rows
are mostly `NULL`.

**A shared base class (`EventController extends FOGController`).** Rejected as
premature rather than wrong. The only behaviour to share is the frame's
defaults, and `save()` already implements the two that exist. A base class
becomes worth it when there is behaviour to put in it — append-only
enforcement, retention, the deletion policy of Decision 4. That is the audit
ADR's decision to make, with three tables already conforming; taking it now
sets the shape of a class before knowing what goes in it.

**Column renames only.** Rejected as the expensive half of the work with none
of the benefit. Renaming `utUserName` to something in the frame requires the
same schema step, the same reader changes and the same rollout as adding a
column, and at the end `history` still has no subject and host deletion still
breaks login history. It also cannot be done cheaply at the friendly-key layer:
`aliasedFields` looks like an alias mechanism from its docblock, but
`save()`'s only use of it is `arrayRemove($this->aliasedFields,
$this->databaseFields)` (`fogcontroller.class.php:635`) — an exclusion list. A
friendly-key rename is a breaking change to the REST payload with no alias seam
to soften it.

**Leave it alone.** The strongest alternative, and the reason the phases are
ordered as they are. Phases 0 and 1 fix real defects and cost nothing; if the
rest is never built, those two still leave the estate better than it is. The
case for going further is Decision 4 — host deletion breaking `userTracking`
today, in the same way it broke `taskLog` before schema 341 — and that case
stands on its own even if nothing else here is accepted.
