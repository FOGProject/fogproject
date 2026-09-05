# Activity is a filtered view of one log; retention is a registry, not a page

## Status

accepted

Items 1-4, 6 and 7 of the sequencing table below are implemented on
`working-1.6`, except that item 3 -- the dashboard card -- was removed again on
2026-08-29 at the maintainer's request. Decisions 2 and 3 are withdrawn with it
and marked as such in place; everything else stands. Item 1 (the `report` node split) was signed off separately,
because it narrows an existing grant: a role holding `report.view` loses user
tracking on deploy and is re-granted deliberately. Item 6 landed with ADR
0021's merge 8 rather than separately, since the sweep it needed was the same
sweep. Item 5 landed once ADR 0020's phases 2 to 4 had given `userTracking`
and `taskLog` the frame: both are now sources in the filter, each with a
`summary` column, and the promise this ADR made held -- they are entries in
`_allSources()` and nothing else about the page changed.

Item 5 carried one thing this table does not show, and it is the half worth
reading before changing the page again. `getList` resolves to `activity.view`
by naming convention, so the page's own gate is the only gate unless a source
declares another. Offering `userTracking` under `activity.view` alone would
have re-opened, through a different door, exactly what item 1 closed -- the
permission registry says of the `usertracking` node that "everything that
reads it ... resolves here", and this viewer is a new reader of it. So a
source may declare an extra permission, and `userTracking` requires
`usertracking.view` while `taskLog` requires `task.view`. Neither is a
widening or a narrowing: each keeps the boundary that table already had.

`history` declares none, which is what it has had since the page shipped.
Requiring `report.view` for it as well was put to the maintainer when item 5
landed and **declined** (2026-08-22): `history` is the only source an
`activity.view`-only role can read, so the requirement would silently empty
the page for that role on upgrade, and `activity` exists as a node of its own
precisely so this page carries its own grant. This is settled rather than
pending.

It is also the single null that keeps "this user may read no source"
unreachable, so `tests/activity-sources.test.php` pins it as a value: making
that change anyway fails a test rather than reaching an undefined index that
becomes a class name.

Item 7 is bounded to genuinely new installs, and the test of "new" is the
installer's own: `applyNewInstallDefaults()` runs only when no `.fogsettings`
existed before the run *and* `userTracking` is empty, so an upgrade -- and a
reinstall over live data -- both fall through untouched. An existing install
with no window set gets the dashboard notice instead of a silent deletion,
because some administrators are required to retain these records and the
decision of how long is theirs to make.

## Context

Two requests arrived together — a usable home for activity, and retention for
`userTracking` — and they are the same decision seen from two ends. Both are
downstream of ADR 0020 (log shape) and ADR 0021 (audit trail), and the answer
to "what should I build now that will not be thrown away" comes almost
entirely from those.

### The read gate is wider than the request assumed

`History_Report::getList()` calls `Route::listem('history')` unfiltered
(`history_report.report.php:64-70`), behind `report.view`. That is true, and
it is not confined to `history`. Three log tables map to the **same**
permission node (`authorization.class.php:179, 188, 227`):

```
'history'      => 'report'
'imaginglog'   => 'report'
'usertracking' => 'report'
```

and `report` declares only `['view', 'create']` (`:329`). So a single
`report.view` grant hands over, in one go:

| table | what it discloses |
|---|---|
| `history` | every administrative action on the server |
| `imagingLog` | every image ever deployed, per host |
| `userTracking` | **every named person's login and logout, per host** |

`Hosts_and_Users_Report` renders that third one as User Name / Host Name /
Date (`hosts_and_users.report.php:36-40, 64-70`). The grant that lets a
helpdesk user read an imaging report also gives them a movement log for every
named employee. That is the actual defect behind "anyone who can read any
report reads the whole activity log", and it is an access-control change, so
it is proposed here rather than decided.

### One HARD constraint is already satisfied

"Bound row counts in the query, not in JS" is met today.
`FOGManagerController::limit()` imposes `MAX_ROWS = 10000` on any request that
does not paginate, and on `length=-1` ("All"), annotating the envelope with
`truncated` (`fogmanagercontroller.class.php:37, 288, 312`). Nothing new is
needed for it.

What that does **not** do is bound growth. `MAX_ROWS` caps a page; the tables
still grow forever, which is the other half of the constraint and is what
retention is for.

### There is no retention mechanism anywhere, and the near-miss is not one

Checked before proposing one, as asked. `grep -rn 'retention'` over
`packages/web` returns exactly one hit, and it is a comment. No `DELETE FROM`
and no `deletemass` exists anywhere in `packages/service`, so no daemon prunes
anything.

`tests/tasklog-report-retention.test.php` is not a counter-example, despite
the name: it asserts that a FOS report stays **readable** after its task and
host are deleted — schema 341's denormalized copy — not that anything is aged
out. Retention there means "the report is retained", the opposite sense.

### The dashboard's polling is not a model to copy uncritically

`POLL_SLOW` is 300000ms and the three periodic charts reschedule themselves
with `setTimeout` (`fog.dashboard.js:18, 299, 426, 519`). Two details matter.
It is not a naive interval — `nextSlow()` returns
`POLL_SLOW - ((now - startTime) % POLL_SLOW)`, aligning every client to the
same wall-clock boundary (`:110`). And every one of those chains carries ADR
0012's guard, because `doPageLoad()` cannot cancel a `setTimeout`; an
unguarded self-rescheduling poll outlives its page for the life of the tab.

### The modal pattern does transfer, for a reason worth keeping

`TaskManagement` renders `task-logs-table` and a `task-log-modal` whose body
is filled **client-side from the row the grid already holds**, so opening it
costs no request (`taskmanagement.page.php:536-564`). That is the property
that makes the pattern right for activity, not the visual arrangement: an
activity row's detail is text the grid already fetched.

### Personal data, swept

| holder | field | class |
|---|---|---|
| `userTracking` | `utUserName` | endpoint OS account — a named person, not a FOG account |
| `inventory` | `iPrimaryUser` (`inventory.class.php:41`) | a named person, on a **current-state** row |
| `history` | `hUser`, `hIP` | FOG operator, plus an IP |
| `taskLog` | `createdBy`, `ip` | FOG operator, plus an IP |
| `imagingLog` | `ilCreatedBy` | FOG operator |
| `users` | `uName` | account identity. **No email column** — verified against the manifest |

Two findings worth stating. `inventory.iPrimaryUser` is the one that would
otherwise be found later: it is personal data on a table that is not a log, so
retention does not apply to it and a different control does. And `userCleanup`
has no reader or writer anywhere in `packages/web/lib` — it is an orphan table,
mentioned and left.

## Decision

### 1. Build one log viewer with a source filter, not an activity page

ADR 0020 Decision 1 chose a **record contract over one physical table**: three
tables keep their own storage, and every event row carries the same frame —
`createdTime`, `createdBy`, `ip`, `type`, `subjectType`/`subjectID`/
`subjectLabel`, `text`.

A shared frame is exactly what makes one viewer possible without one table. So
the answer to "one viewer with a source filter, or an activity-specific page"
is the viewer, and it is the thing that does not get thrown away: an
activity-specific page would be rebuilt the moment the second source arrived.

**What to build now, given 0020 is not implemented.** The page ships with the
source concept in place and one source in it. `?node=activity` renders a grid
whose columns are the frame's, with a `source` filter that today offers only
`history`. As 0020's phases land, `userTracking` and `taskLog` become
additional values in that filter and nothing about the page changes. The
column set is the commitment; the number of sources is not.

Before 0020's Phase 4, `history` rows have prose and no structured subject, so
the grid shows `text` and leaves the subject columns empty. That is the
discontinuity 0020 already documents, made visible in one place rather than
hidden.

### 2. The dashboard card does not poll

**Withdrawn 2026-08-29 — the card is removed.** Decisions 2 and 3 described a
card that no longer exists. Nothing about the reasoning below turned out to be
wrong; the card simply did not earn the space it took on the dashboard, and the
maintainer withdrew the request that put it there. The `?node=activity` viewer
is unaffected — it was never fed by the card — and the reasoning is kept rather
than deleted because it is the argument to re-read if a card is ever proposed
again: any such card renders once and schedules nothing.

The rest of this section is the original text.

The periodic charts poll because disk usage changes continuously and
independently of anyone looking at it. Activity does not: it changes when
somebody does something, and on most FOG servers the person looking at the
dashboard is the person doing it.

So the card renders its ten most recent rows once, with an explicit refresh
control, and schedules nothing. This *removes* a class of risk rather than
adding one — ADR 0012 exists because self-rescheduling polls outlive their
page, and the cheapest guard is not having the timer.

If it is ever polled, `POLL_SLOW` and the `nextSlow()` alignment are the right
cadence to adopt, and the ADR 0012 guard is mandatory rather than advisory.

### 3. The card's query is bounded explicitly, not by `MAX_ROWS`

**Withdrawn 2026-08-29 with decision 2** — there is no card query to bound.
The rule it states still binds any unbounded read of these tables.

`MAX_ROWS` is a 10,000-row backstop for an unpaginated grid. A card showing
ten rows asks for ten, with `LIMIT` in the query. Relying on the backstop to
bound a card would fetch 10,000 rows to display ten.

### 4. Retention is a registry, and it is `FOG_<TABLE>_RETENTION_DAYS`

This is the amendment that flows back into ADR 0021. That ADR scoped its
retention sweep to `auditLog`. Three tables now need the same thing —
`history` and `userTracking` here, `imagingLog` in ADR 0022, which explicitly
defers to 0021's mechanism — so the sweep is generic from the start:

- A **retention registry**: table → setting name → date column. Core
  registers its own; a plugin contributes through a hook, the same shape as
  the permission registry.
- One sweep walks the registry. Adding a fourth table is a registry entry, not
  a second sweep. (Originally in `FOGPluginRunner`; moved to its own daemon,
  `FOGRetentionRunner`, by
  [ADR 0026](0026-retention-runs-in-a-daemon-named-for-retention.md) — the
  registry itself is unchanged.)
- `0` means keep forever, and it is the value every setting takes unless an
  admin chooses otherwise. See Decision 7.

Building this per-table would produce three sweeps that age three tables with
three settings and three bugs.

### 5. Retention is not purge, and only retention ships

The request asked about older-than-N-years, older-than-N-months, and custom
before/after/between, and suspected it was more UI than anyone needs. It is,
and the reason is that those are two different features:

- **Retention** is a policy: one number, applied continuously, no UI beyond
  the setting. This is what comparable admin tooling offers, and the shape is
  near-universal — a single age, not a date-range builder. (Judgment plus
  general practice; I have not verified specific products' defaults and am not
  going to assert numbers for them.)
- **Manual purge** is an administrator deleting a chosen set of records on a
  chosen day. That is a materially more dangerous operation, it is precisely
  what an audit trail exists to record, and it is not needed to stop the
  tables growing.

Ship retention. A date-range purge tool is a separate proposal with its own
justification, and it should have to argue for itself.

### 6. `History_Report` keeps its URL and its endpoint

Removing it breaks bookmarks and anything scripting `getList`. It is also not
in the way: the new page is a different node.

- The **API is untouched.** `Route::listem('history')` is the REST route and
  it keeps working regardless of what any page does.
- `?node=report&sub=file&id=history_report` **redirects** to
  `?node=activity&source=history` once the viewer exists. A redirect keeps a
  bookmark working and teaches the new location, which deprecation text does
  not.
- The report class stays, so its `getList` endpoint keeps answering for
  anything that scripts it.

Deleting it is a later decision, taken when the redirect has been in place for
a release and the logs show whether anything still calls it.

### 7. A retention default is applied to new installs, never silently to upgrades

The request asked whether applying a default on upgrade is "exactly what a
privacy control should do, or a nasty surprise". It is both, and the split is
along install age:

- **New installs default to a bounded value.** Data minimization is the right
  default for data nobody has chosen to keep, and an admin who wants forever
  can say so.
- **Upgrades default to `0`, keep forever**, and surface a dashboard notice
  saying `userTracking` holds login records for named people, that no
  retention is set, and where to set one.

Silently deleting on upgrade is wrong for a specific reason, not a squeamish
one: the administrator never chose to hold this data *or* to delete it, and
some of them are under a legal obligation to retain it. A privacy control that
destroys evidence someone is required to keep is not a privacy win. Making the
choice visible and unavoidable achieves minimization without making it for
them.

The recommended new-install default is **365 days**. It is long enough to
answer "who was on this machine last year", short enough that the table does
not grow unboundedly, and it is a round number an admin can reason about. It
is a judgment, not a derived figure.

### 8. Every retention deletion is audited, and audit gates the whole feature

ADR 0021 Decision 10 says shrinking the audit trail must first be written to
the audit trail. That generalises here, and deleting records about people is
the clearest case for it: each sweep writes one `auditLog` row per table
naming the table, the window and the row count, before the delete.

This makes ADR 0021 a **hard dependency** for Decision 4, and it agrees with
the request's own second HARD constraint from the other direction. That
constraint reasoned that `history` is currently the only record that anything
happened — ADR 0020 found it is weaker than that, because `logHistory()`
returns early on an invalid `$FOGUser` and so records **no logins at all**.
The conclusion is unchanged and slightly strengthened: purging an
already-incomplete trace, through a UI built for the purpose, with nothing
else recording that the purge occurred, is the wrong order to build in.

**So: the card and the page ship without retention. Retention ships after
`auditLog` exists.**

### 9. `inventory.iPrimaryUser` gets a different control, and it is out of scope here

It is personal data, so it belongs in the sweep above — but `inventory` is a
current-state table with `UNIQUE KEY (iHostID)`, one row per host. Aging it
out by date would delete the inventory, not the person's name.

The control it needs is the ability to clear the field, and a decision about
whether FOG should collect it at all. Both are real and neither is retention.
Named and deferred rather than bolted onto a mechanism that does not fit it.

## What this changes in the other ADRs

**ADR 0021 — one amendment, made.** Decision 9's retention sweep becomes
generic: a registry of table → setting → date column, walked by one sweep,
rather than a mechanism that knows only about `auditLog`. Nothing else in 0021
moved at the time; `audit.manage` is still the right gate. The home did move
later — see [ADR 0026](0026-retention-runs-in-a-daemon-named-for-retention.md),
which takes the sweep out of `FOGPluginRunner` and gives it a daemon named for
what it does.

**ADR 0020 — no change.** Its Decision 1 is what makes Decision 1 here
possible, and this ADR is a consumer of the frame, not a constraint on it. The
`report.view` finding above is about the *read* gate and is orthogonal to
0020's Decision 7, which concerns write routes.

**ADR 0022 — no change.** It already defers `imagingLog` retention to 0021's
mechanism, and Decision 4 here is that mechanism arriving in a form that can
take a third table.

## Sequencing

| # | Merge | Depends on |
|---|---|---|
| 1 | Split the `report` node so `usertracking` is not readable under the same grant as an imaging report | nothing — **access-control change, needs signoff** |
| 2 | `?node=activity` viewer: frame columns, `source` filter with one value, explicit `LIMIT`, grid + client-filled modal | nothing |
| 3 | ~~Dashboard card, ten rows, no timer~~ — shipped, then **removed 2026-08-29** (decisions 2 and 3 withdrawn) | 2 |
| 4 | `History_Report` redirects to the viewer; class and endpoint stay | 2 |
| 5 | Additional sources appear in the filter | ADR 0020 phases 2–4 — **shipped** |
| 6 | Retention registry + sweep + per-table settings | **ADR 0021 `auditLog` merged** |
| 7 | New-install default of 365 days on `userTracking`; upgrade notice | 6 |

1 through 4 deliver the usable home with no schema change and no dependency on
either companion ADR. 6 and 7 are gated, deliberately.

## Consequences

- One page answers "what happened", for every source, instead of one report
  per table.
- ~~The dashboard gains a card and no new timer.~~ The card was removed on
  2026-08-29; the dashboard is unchanged by this ADR.
- A `report.view` holder stops getting a movement log for named employees as a
  side effect. Signed off and shipped as item 1; a role holding `report.view`
  loses user tracking on deploy and is re-granted deliberately.
- Retention is one mechanism for five tables rather than five mechanisms.
  (Four when this was written. `hostUserSession` joined in schema 422 --
  design 0008's agent-reported user sessions -- which is the registry
  working as intended: a new table that accumulates rows per host declares
  its own setting and date column rather than growing unbounded while
  somebody notices later.)
- Upgraded servers keep every existing `userTracking` row until an
  administrator decides otherwise, and are told that they hold them.
- `history`'s existing prose rows render in the new viewer with empty subject
  columns until ADR 0020 Phase 4, which is the discontinuity that ADR already
  accepts.

## Alternatives considered

**An activity-specific page now, a unified viewer later.** Faster to the first
screen, and thrown away. The column set is the expensive part of the decision
and it is the same either way, so there is nothing to gain by committing to
the narrower one.

**Extend `History_Report` in place.** It is a `ReportManagement` subclass, so
it inherits the report node's permission and the report menu's location —
which is where the disclosure problem in Context comes from. A new node is how
the gate gets to be different.

**Poll the card at `POLL_SLOW`.** Consistent with the charts, and consistency
is the only argument for it. It costs a request every five minutes per open
dashboard, for data that is usually stale by zero seconds because the viewer
caused it, and it re-introduces exactly the self-rescheduling-timeout shape
ADR 0012 was written about.

**Retention as a date-range purge UI.** Rejected in Decision 5: it is a
different, more dangerous feature wearing retention's name, and it is not
needed to bound growth.

**Apply the retention default on upgrade.** Rejected in Decision 7. The
strongest version of the case for it is that a privacy control which requires
opt-in mostly does not happen — which is true, and is why the upgrade path
carries a notice rather than silence. Deleting data an administrator may be
legally required to hold, without asking, is not a defensible default.
