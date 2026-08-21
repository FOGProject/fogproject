# Spans and work items are different things; only one of these tables is a span

## Status

proposed

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

### 3. `imagingLog` stops using `finish IS NULL` as state, and stops deleting

The three fixes, in dependency order. Each is independently shippable and none
requires the other two to be useful.

**a. Give it an explicit close key.** Store `ilTaskID` so
`TaskingElement::imageLog(false)` finds its row by the task that opened it,
not by `finish IS NULL` + `maxId()`. This is what removes the *reason* for the
delete.

**b. Then remove the delete.** Once the close is unambiguous, a second open row
is no longer a problem, and an image that died halfway is allowed to persist as
what it is: a row with a start, no finish, and a task that is no longer
running. This is the single highest-value change in the ADR — it converts
"FOG has no record of imaging failures" into "FOG has one".

**c. Give it a state, or give it a reason.** With (b) done, `ilFinishTime IS
NULL` still cannot separate "running now" from "died". Two options and the
recommendation is the second:

- Add `ilState` speaking `taskStates`, making it the sixth table in the shared
  vocabulary. Consistent, and it makes the union query in Decision 4 uniform.
- **Derive it.** `imagingLog` already stores the host; with (a) it stores the
  task. "Running" is *this row's task is still in a progress state*; anything
  else with a NULL finish died. No new column, no second source of truth to
  drift, and it keeps `imagingLog` free of lifecycle state it does not own.

The derivation is preferred because `imagingLog`'s whole value is being the
record that outlives the task — and a derived state is only available while
the task exists. That is not a contradiction, it is the honest boundary: while
the task lives, FOG can say why the row is open; afterwards it can only say it
never closed. Storing a state would let it claim more than it knows at the
moment the task is deleted.

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

### 5. `imagingLog` adopts ADR 0020's frame; it does not extend a base class

ADR 0020 decided against a shared base class in favour of a record contract, so
there is no class here to extend and spans sit **beside** that decision rather
than under it. If 0020 is later revisited and grows an `EventController`,
`imagingLog` is a candidate and the five work-item tables are not.

`imagingLog` is already most of the way to 0020's frame under other names:
`ilCreatedBy` is `createdBy`, `ilImageName` and `ilType` are subject
information, `ilHostID` is `subjectID`. It should take the frame's names and,
per 0020 Decision 4, declare its deletion policy explicitly — which means
answering the question 0020 left open for it.

**This ADR answers it: `imagingLog` should be retain-denormalized, not
cascade.** It is currently deleted on host delete, which is indefensible
alongside Decision 3b: there is no point retaining failed imaging runs if
deleting the host erases them anyway, and "which hosts fail to image" is a
fleet-level question whose answer must outlive any individual host. It needs
`ilHostName` for the same reason schema 341 gave `taskLog` `logHostName`.

The five work-item tables keep cascading. A queue row for a deleted host is
not history, it is stale work.

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
- **Retention.** None of these six is pruned by anything, and after Decision 3b
  `imagingLog` grows faster than it does today. That is the audit ADR's
  retention mechanism (ADR 0021 Decision 9) applied to a different table, and
  it should reuse it rather than invent a second sweep.

## Consequences

- FOG gains a record of imaging failures, which it does not have today.
- `imagingLog` grows: rows that used to be deleted on the next attempt now
  persist. On a host in a failure loop that is one row per attempt. See
  retention, above.
- One new class (`ActivityWindow`) and a small number of indexes; no table is
  merged, no vocabulary is renamed, and no writer changes except
  `TaskingElement::imageLog()`.
- The state/timestamp redundancy stays, now with a stated invariant and a test
  rather than as an accident.
- Anyone adding a seventh work-item table is expected to speak `taskStates`.
  Nothing enforces it; the `ActivityWindow` mapping is where the omission
  becomes visible.

## Relationship to the companion ADRs

**ADR 0020 (event log shape).** This ADR consumes 0020's frame for
`imagingLog` only, and resolves the deletion-policy question 0020 explicitly
left open for it (Decision 5). It does not touch `history`, `userTracking` or
`taskLog`. If 0020's Decision 1 is revisited toward a base class, Decision 5
here is where spans re-enter that conversation.

**ADR 0021 (audit trail).** 0021 already states that a span is not an audit
record, and this ADR agrees from the other side: a completed image is an
outcome, and the auditable event is the task that started it, audited at
`host.task`. Two seams meet here and neither should duplicate the other —
`imagingLog` records *what happened to the machine*, `auditLog` records *who
asked for it*. `ilTaskID` from Decision 3a is what lets a reader join the two
without either table knowing about the other.

0021 handed this ADR one open question: which of the `packages/web/service/`
endpoints deserve a machine-provenance audit header, given that those
endpoints are the writers of these tables. **The answer is the state
transitions, not the checkins.** `TaskingElement`'s task-state writes and
`imageLog()`'s open and close are events a person would want to see; the
per-poll checkin traffic is a heartbeat and would swamp the table. That is the
same line Decision 2 draws between state and timestamp, applied to volume.
