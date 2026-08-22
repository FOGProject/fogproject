# Spans and work items are different things; only one of these tables is a span

## Status

accepted, with Decisions 3 and 5 **superseded during review**

Both concerned `imagingLog`, and grilling them established that the table
should not be repaired -- it should be retired. `taskLog` is written at the
same two moments, for the same tasks, in the same methods, and already carries
everything `imagingLog` does except the image name. Adding one denormalized
column to `taskLog` therefore removes the *reason* for Decision 3 rather than
implementing it. The replacement is Decision 3 (rewritten) below.

Decisions 1, 2, 4 and 6 stand as written; they are about the five work-item
tables and the read path, and nothing here touches them. Decision 4's
`ActivityWindow` now unions **five** tables rather than six.

## Context

Six tables record that something ran between two moments, each differently.
The prompt for this ADR grouped them by *how completion is expressed* —
explicit start/end pairs versus completion implied by a state column — and
asked whether a shared span concept is worth having.

Checking the schema moves the line. The grouping by completion mechanism is
real but shallow: **four of the six carry both** a completion timestamp and a
state column, so "two incompatible ways to say this finished" is not a split
between tables, it is a redundancy inside most of them.

| table | start | end | state | other |
|---|---|---|---|---|
| `tasks` | `taskCreateTime` | — | `taskStateID` + `taskStateChangedTime` | `taskCheckIn`, `taskScheduledStartTime` |
| `snapinJobs` | `sjCreateTime` | — | `sjStateID` | |
| `snapinTasks` | `stCheckinDate` | `stCompleteDate` | `stState` | `stReturnCode`, `stReturnDetails` |
| `multicastSessions` | `msStartDateTime` | `msCompleteDateTime` | `msState` | `msSenderPID`, `msSenderNode`, `msSenderStart`, `msPercent` |
| `fileDeleteQueue` | `fdqCreateDate` | `fdqCompletedDate` | `fdqState` | `fdqPathName`, `fdqPathType` |
| `imagingLog` | `ilStartTime` | `ilFinishTime` | **none** | `ilImageName`, `ilType`, `ilCreatedBy` |

Two things fall out of that table, and they are the whole ADR.

### The state vocabulary is already shared, and five tables use it

`TaskState::getQueuedStates()`, `getCheckedInState()`, `getProgressState()`,
`getCompleteState()`, `getCancelledState()` and `getFailedState()` return ids
into `taskStates` (`taskstate.class.php:67-178`), each overridable by a hook.
Twenty files consult them, and the five state-carrying tables above all speak
that vocabulary — including `fileDeleteQueue`, whose daemon sets
`completedTime` and `stateID = getCompleteState()` in the same call
(`lib/service/filedeleter.class.php:313-314`).

So FOG does not lack a shared completion concept. It has one, it is
`taskStates`, and it already spans queue types that have nothing else in
common. What it lacks is a rule about which of the two representations is
authoritative when a row carries both.

**`imagingLog` is the only table that does not speak it.** That is the tell.

### Five of the six are work items. One is a history row.

The tables the prompt suspected might not be logs are not logs, and the
evidence is harder than "they look like queues":

- **`multicastSessions` stores a live OS process id.** `msSenderPID` is
  written from `getPID($this->procRef)` and cleared to `0`
  (`lib/service/multicasttask.class.php:1196, 1244`;
  `lib/service/multicastmanager.class.php:250`), alongside `msSenderNode`,
  `msSenderStart` and `msPercent`. Nothing puts a PID in a history table. This
  is a running-process record.
- **`fileDeleteQueue` is a queue by name and by use.** `Image::destroy()`
  enqueues (`image.class.php:216`); the `FOGFileDeleter` daemon dequeues.
  `fdqPathName` is an instruction, not a record.
- **`snapinTasks`, `snapinJobs` and `tasks`** are the work the client
  collects. `stReturnCode`/`stReturnDetails` are a result, and the row is the
  unit of dispatch.

`imagingLog` is the only one nobody acts on. It has no state because nothing
schedules from it, and it exists — per `TaskingElement` — precisely so a
record survives the task.

So the prompt's instinct is right, and stronger than it was put: a base class
forcing a queue and a history table to behave alike would make both worse. The
concrete harm is not stylistic. A queue row's "end" is a *transition* that
other code polls and acts on; a history row's "end" is a *fact* nobody reads
back. Giving them one setter means every write to a history row looks like a
state transition to whatever is watching.

### `imagingLog` fakes a state column with `finish IS NULL`, and deletes what it cannot express

This is the finding that decides the ADR.

`TaskingElement::imageLog()` (`lib/reg-task/taskingelement.class.php:307-341`)
opens a span on checkin and closes it on checkout. Opening does this first:

```php
Route::deletemass('imaginglog', [
    'hostID' => self::$Host->get('id'),
    'finish' => null,          // GH-1245: an unfinished log has no finish time
]);
```

**Every unfinished imaging row for that host is destroyed before a new one is
opened.** Closing then finds its row by the same predicate:

```php
$find = ['hostID' => …, 'image' => …, 'finish' => null];
$ilID = self::maxId(Route::getIds('imaginglog', $find));
```

So `ilFinishTime IS NULL` is doing three jobs at once: it means "still
running", it means "died without finishing", and it is the lookup key for
"the row I am about to close". Because it is a lookup key, a second open row
would be ambiguous — which is why the delete exists. And because the delete
exists, **FOG's answer to "this image died halfway" is to erase the evidence
the next time that host images.**

This answers the prompt's question directly. Are "still running" and "died
without finishing" distinguishable today?

- In `imagingLog`: **no**, and worse, the second is not retained at all.
- In the five state-carrying tables: **yes**, and only because of the state
  column — `Failed` (state 6) and `Cancelled` (5) are distinct from
  `In Progress` (3). The timestamps cannot tell them apart in any of them
  either.

That is the argument for keeping state rather than reconciling it away, and it
was invisible from the schema alone.

Two further consequences of the same defect: `imagingLog` is in
`Route::deletemass('host')`'s cascade (`route.class.php:5561`), so deleting a
host destroys its imaging history outright — the opposite of what schema 341
decided for `taskLog` (ADR 0020) — and a host that fails to image ten times
running leaves zero rows behind.

### The prompt's real complaint stands

"There is no single query for everything that ran in the last hour" is
correct, and it is the one problem here worth solving. It is not caused by the
start/end naming: `msStartDateTime`, `ilStartTime` and `stCheckinDate` are
trivially aliased. It is caused by there being no agreement on what "ran"
means when a row carries both a state and a completion time, and by
`imagingLog` not carrying a state at all.

## Decision

### 1. A shared span abstraction is not worth having, and should not be built

Five work-item tables and one history table do not share a concept. They share
a *shape*, and unifying on shape rather than purpose is exactly the failure the
prompt named.

Concretely, what a base class would have to reconcile: a PID, a scheduled
start that has not happened yet (`taskScheduledStartTime`), a checkin that is
also a start (`stCheckinDate`), a return code, a percentage, and a row whose
NULL end is a lookup key. Any base class general enough to hold all of that
constrains nothing, and the one thing it would enforce — a uniform "close the
span" setter — is the thing that would break `imagingLog`, whose close is a
`maxId()` search rather than an assignment.

**This ADR's answer to "is this worth doing at all" is: mostly not.** Three
specific things are worth doing, and none of them needs the abstraction.

### 2. State is authoritative for *what*; timestamps are authoritative for *when*

The redundancy in the four both-carrying tables is not a defect to remove. It
is two different questions answered by two different columns, and the fix is to
say so rather than to delete one:

- **`<x>State` answers "what is this doing now"**, and it is the only column
  that can distinguish finished, cancelled and failed. It is authoritative for
  lifecycle.
- **The timestamps answer "when"**, and they are authoritative for duration
  and for time-range queries. A completion timestamp is set *because* the state
  reached a terminal value, never instead of it.

The invariant, stated once and testable: **a terminal state implies a non-NULL
end; a non-terminal state implies a NULL end.** `FileDeleter` already writes
both in one call and is the model. Where the two disagree today, state wins and
the timestamp is the bug.

Reconciling them into one representation is rejected in both directions.
Dropping state loses the failed/cancelled distinction (see Context). Dropping
the timestamps loses duration, and `taskStateChangedTime` — which the prompt
correctly identifies as a partial one-slot span — cannot substitute, because it
is overwritten by every transition and so records only the last one.

### 3. `imagingLog` is retired; `taskLog` gains the image name

**This supersedes the original Decision 3**, which proposed giving `imagingLog`
an explicit close key, removing its delete, and deriving a state for it. That
was the right fix to the wrong object.

**The two logs record the same events, in the same place.** Verified in
`packages/web/lib/reg-task/taskqueue.class.php`:

```
:240   imageLog(true)     <- checkin
:263   taskLog()          <- same method, 23 lines later
:608   taskLog()          <- complete
:612   imageLog(false)    <- same method, 4 lines later
```

Both are guarded on `$this->imagingTask`. Nothing in `packages/service/`
writes either one. So `imagingLog` is a parallel record of events `taskLog` is
already recording, and it holds exactly one fact `taskLog` does not: the image
name.

`taskLog` already carries the rest, denormalized so it survives deletion:
`logHostID`/`logHostName` (schema 341), `taskStateID`, `createTime`,
`createdBy`, `ip`, `logTaskTypeName`. **So the whole of the original Decision 3
dissolves:**

| original decision | with `logImageName` on `taskLog` |
|---|---|
| 3a: an explicit close key, so the close is unambiguous | unnecessary -- `taskLog` rows are keyed by task |
| 3b: remove the delete, so failed runs persist | unnecessary -- nothing deletes `taskLog` rows |
| 3c: derive a state, since `finish IS NULL` cannot separate running from dead | moot -- `taskStateID` is a real state column |

3a and this decision want the same thing from opposite ends: denormalize one
field to link two records. Only this direction lets a table be deleted.

**Correction to the original 3b.** It claimed to convert "FOG has no record of
imaging failures" into "FOG has one". That claim was wrong when it was written.
`taskLog` already types its rows (`TaskLog::TYPE_ERROR`) and already records
failures -- measured on a live install: 3 `logType='error'` rows alongside 53
`logType='state'`. The FOS failure-reporting work had already delivered it.

**What `imagingLog` was thought to be for, it is not.** "When was this image
last captured / last deployed" is answered by dedicated columns --
`images.imageDateTime`, `images.imageLastDeploy`, `hosts.hostLastDeploy` -- not
by this table and not by `taskLog`, which has no image column at all. Nor could
`taskLog` reach one by joining: the only route is
`taskLog.taskID -> tasks.taskImageID -> images.imageName`, and on the install
this was measured against **9 of 56 `taskLog` rows already have no surviving
task**. That is the same failure that made schema 341 denormalize the host
name, and it is why the new column stores the name rather than an id.

**The work:**

- `taskLog` gains `logImageName`, denormalized, written at the same two
  moments `imageLog()` was called.
- `logTaskTypeName` starts being written on `logType='state'` rows too. Schema
  341 deliberately excluded them, so capture-versus-deploy is currently absent
  from exactly the rows a per-event count would read.
- The dashboard's images-per-day chart reads `taskLog` instead. It becomes
  `COUNT(DISTINCT taskID)` with a state filter, not `COUNT(*)`: `imagingLog`
  is one row per event, `taskLog` one per transition.
- `imagingLog` goes entirely -- table, model, manager, report, REST class,
  permission mapping, retention entry and activity-viewer source.
- Existing rows are **dropped, not migrated**. Backfilling them into `taskLog`
  needs a task id `imagingLog` does not store, which is precisely the defect
  the original 3a existed to add. Building that in order to throw the table
  away afterwards is work for nothing. The cost is the history on installs
  that have it, and the images-per-day chart reading empty for the window
  predating the change.

**The REST class is removed outright, with no compatibility shim.** `imaginglog`
is in `Route::$validClasses`, so `/api/imaginglog` exists today. No 1.6 release
has ever shipped (see ADR 0021's status), so there is no released 1.6 API
contract to break, and a view over a table that no longer exists is a permanent
cost paid against a promise never made. Consumers tracking `working-1.6` builds
get a 404 with no deprecation window; that is the accepted cost. FogApi
hardcodes the 1.6 class list rather than reading `system/openapi`, so its copy
needs syncing by hand.

**One live defect found while establishing the above, and fixed with this
work.** On deploy completion FOG sets `hosts.hostLastDeploy`
(`taskqueue.class.php:576-579`) but **nothing anywhere sets
`images.imageLastDeploy`** -- the `Image` model maps `'deployed' =>
'imageLastDeploy'` and no code writes it. Measured: 3 of 29 images carry a
value. It matters more once `imagingLog` is gone, because that column is what
someone reaches for to answer "when did this image last go out".

### 4. "Everything that ran in the last hour" is one read path, not one table

The prompt's actual complaint is answered by a query, not a schema. A single
`ActivityWindow` helper unions the six tables behind one column set —
`source`, `subjectID`, `startedAt`, `endedAt`, `state`, `label` — mapping each
table's own names into it.

This is deliberately a *read* projection with no write side. It costs one class
and no migration, every table keeps its own vocabulary and its own writers, and
nothing about it constrains what a seventh table may look like. It is also the
only part of this ADR that delivers the thing the prompt actually wanted.

Order the union by `startedAt` and bound it by a time range; `tasks` has an
index on `taskCheckIn` but none of the six has one on its start column, so a
window query is a scan per table today. Add the indexes with the helper, not
before.

### 5. Superseded: there is no `imagingLog` left to give a frame to

**The original Decision 5** had `imagingLog` take ADR 0020's frame names and
declare a retain-denormalized deletion policy, adding `ilHostName` so a row
outlived its host.

Decision 3 above retires the table, so all of that lands on `taskLog` instead
-- where it is **already true**. Schema 341 gave `taskLog` `logHostID` and
`logHostName` for exactly the reason the original Decision 5 wanted `ilHostName`,
and nothing deletes `taskLog` rows, which is retain-denormalized by
construction rather than by declaration.

What survives from it is one sentence, and it is about the other five tables:
the work-item tables keep cascading on host delete. A queue row for a deleted
host is not history, it is stale work.

ADR 0020's open question about `imagingLog`'s deletion policy is answered by
the table ceasing to exist.

### 6. Naming: these are work items, and the word "span" is reserved

The prompt borrowed "span" from observability, and the borrowing is accurate
for `imagingLog` — a start, an end, a status and attributes is an
OpenTelemetry span, and the analogy holds down to the nullable end.

It is inaccurate for the other five, and the standard pattern for those has a
different name: a **work item** or **job** with a state machine. That is what
`taskStates` already is. The distinction matters because the two carry
different obligations — a span's end is written once and never read back by
the system; a work item's state is polled, transitioned and acted on. Calling
both "spans" is what would license the base class Decision 1 rejects.

So: `imagingLog` is a span. The other five are work items. FOG already has a
name for the second thing and should keep using it.

## What is not decided here

- **Whether `tasks` and `snapinJobs` should gain completion timestamps.** They
  are the two with state and no end column, so duration is unavailable for
  both. `taskStateChangedTime` is close enough that the gap is small, and
  closing it is a schema change to the hottest table FOG has. Worth a separate
  look, not worth bundling.
- **`msAnon5`.** Another dead `anonN` column, same family as `utAnon3` and
  `sAnon3` (ADR 0020). Leave it.
- **Retention.** None of these five is pruned by anything. That is the audit
  ADR's retention mechanism (ADR 0021 Decision 9) applied to different tables,
  and it should reuse it rather than invent a second sweep. `imagingLog`'s own
  entry in the registry goes with the table; `taskLog` inherits the question,
  and it grows faster than `imagingLog` ever did.

## Consequences

- One fewer table, and one fewer REST class, model, manager, report,
  permission mapping and retention entry with it.
- FOG keeps its record of imaging failures. It already had one, in `taskLog`;
  what changes is that the record now names the image.
- `/api/imaginglog` returns 404. No 1.6 release shipped it, and FogApi's
  hardcoded class list needs syncing by hand.
- Existing `imagingLog` rows are lost, and the dashboard's images-per-day chart
  reads empty for the window predating the change.
- The dashboard chart counts distinct tasks rather than rows, because `taskLog`
  is per-transition. That query is worth writing carefully.
- `images.imageLastDeploy` starts being populated, having never been written.
- One new class (`ActivityWindow`) and a small number of indexes; no table is
  merged and no vocabulary is renamed.
- The state/timestamp redundancy stays, now with a stated invariant and a test
  rather than as an accident.
- Anyone adding a seventh work-item table is expected to speak `taskStates`.
  Nothing enforces it; the `ActivityWindow` mapping is where the omission
  becomes visible.

## Relationship to the companion ADRs

**ADR 0020 (event log shape).** This ADR consumes 0020's frame for
`imagingLog` only, and resolves the deletion-policy question 0020 explicitly
left open for it -- by retiring the table (Decision 5). It does touch
`taskLog`, which gains `logImageName`; it does not touch `history` or
`userTracking`. With `imagingLog` gone there is no span table left, so nothing
here re-enters 0020's base-class conversation.

**ADR 0021 (audit trail).** 0021 already states that a span is not an audit
record, and this ADR agrees from the other side: a completed image is an
outcome, and the auditable event is the task that started it, audited at
`host.task`. Two seams meet here and neither should duplicate the other —
`taskLog` records *what happened to the machine*, `auditLog` records *who
asked for it*. The task id both already carry is what lets a reader join them
without either table knowing about the other; that it is already there, on
`taskLog`, is part of why the separate span table was not worth keeping.

0021 handed this ADR one open question: which of the `packages/web/service/`
endpoints deserve a machine-provenance audit header, given that those
endpoints are the writers of these tables. **The answer is the state
transitions, not the checkins.** `TaskingElement`'s task-state writes are
events a person would want to see; the per-poll checkin traffic is a heartbeat
and would swamp the table. That is the
same line Decision 2 draws between state and timestamp, applied to volume.
