# A report is an aggregation over a window, not a grid over a table

## Status

proposed

Decision 4 (the permission model) and decision 5 (the `taskLog` index) were
signed off by the maintainer on 2026-08-29 ahead of the rest, decision 4
because it is an access-control choice and decision 5 because it is a defect
that stands on its own.

Sequencing items 1 to 5 are implemented on `working-1.6`. Item 6 -- the
remaining five subjects in the scope table -- is not started, and whether
this becomes `accepted` depends on the first of them being cheap, which is
the claim the table below makes and the only one still untested.

## Context

FOG has nine files under `lib/reports/`, and every one of them is the same
thing: a `<div class="card">` wrapping a single DataTables grid, fed by
`Route::listem('<entity>')` — the same server-side list protocol the
management pages use for their own grids. `Inventory_Report::getList()` is
four lines and three of them are the `Content-type` header and the exit.

That makes a FOG report a **filtered table dump**. It has one entity, no time
window, no rollup and no comparison, so the only question it can answer is
*"show me the rows"*. The questions an administrator actually arrives with —
how many machines did we image last month, is that better or worse than the
month before, which image fails most often, what hardware is due for refresh
— cannot be asked of any of them.

Run History is the single exception, and only because ADR 0022 decision 4
gave it a date range in order to have a consumer for `ActivityWindow`. It is
still one grid.

**The machinery is not what is missing.** Chart.js 4 is already vendored
(`management/js/Chart/chart.umd.min.js` plus the moment adapter) and the
Dashboard already draws doughnuts and time-series lines from it, with day
range selectors and JSON series endpoints behind them.
`DashboardPage::get30day()` is a correct grouped SQL rollup that emits a
continuous zero-filled series. Every part of a real report exists on that one
page. Reports were never given a window or an aggregation layer; the
dashboard was never given a range you choose, or an export, or a second
question.

**And one piece of knowledge is trapped in a method.** ADR 0022 decision 3
retired `imagingLog` and made `taskLog` the record of what was imaged. But
`taskLog` holds one row per state *transition*, not one row per run, so
counting deployments out of it takes three rules that are not obvious and do
not announce themselves when broken:

- fold a task's rows back down to one, or a task that moved through three
  states is three images;
- exclude the canceled state, because `TaskLog::recordState()` writes
  `logImageName` on every transition of an imaging task including its
  cancellation, so a deploy queued and canceled without ever starting still
  carries an image name;
- attribute a run to `MIN(createTime)`, or a run that starts before midnight
  and finishes after it is counted on two days.

All three live today as a comment inside `get30day()`. Every one of them
fails *silently* — the query still returns a number, and a wrong count is
indistinguishable from a right one by looking at it. The second consumer of
that logic will get it wrong.

## Decision

### 1. A report is a set of panels over a window

A report declares **panels**, not markup. Three kinds:

| Panel | What it is | Answers |
|---|---|---|
| stat tile | one number, optionally against the previous window | "how many, and is that up or down" |
| chart | a series or a distribution from one rollup | "what is the shape over time / across categories" |
| grid | the rows behind the numbers | "which ones, so I can act" |

Every report has a window, and **the window lives in the URL**. That is Run
History's precedent and the reason for it is unchanged: a report pasted into
a ticket has to still show what it showed when it was pasted.

Tabs group panels **within one report** — Throughput, Reliability, By Image,
Rows. Tabs are not how different subjects are grouped; see decision 4.

Every report ends with a grid. A number nobody can drill into is a number
nobody trusts, and the grid is also what makes the existing DataTables
CSV/Excel/print toolbar keep working unchanged.

### 2. The aggregation is a class under `src/`, not the report and not JS

`src/Audit/ActivityWindow.php` is the precedent and it is already the right
shape: a read-only projection, bounded by `MAX_ROWS`, sources validated
against a whitelist, a fixed output column set, and `tests/activity-window.test.php`
pinning the parts that fail silently. What is missing is a sibling for
**rollups** rather than rows — one class per subject area.

Each such class carries, as its own responsibility:

- **the window on FOG's clock, not PHP's.** The columns being compared are
  stamped by `FOGController::save()` through `FOGBase::niceDate()`, which
  uses the configured FOG timezone. A bound built with PHP's default
  timezone does not error — `BETWEEN` matches a shifted window and the report
  quietly answers a question nobody asked. Run History learned this in the
  lab against a server five hours out, where a task created seconds earlier
  did not appear in a window ending "now".
- **a malformed bound dropped rather than passed on.** An unparseable date
  reaching `BETWEEN` matches nothing, which looks exactly like "nothing ran".
- **a bounded query.** A window is a filter, not a limit; a year on a busy
  server is still a large scan.
- **a fixed output shape, zero-filled where it is a series**, so a quiet day
  is a point at zero rather than a gap the chart interpolates across.

Not in the report class, because the same rollup feeds a stat tile, a chart
and an export, and would otherwise be written three times and drift twice.
Not in JS, because the aggregation is a `GROUP BY`: shipping the rows to the
browser to count them there is the grid problem again with extra steps, and
it puts the row cap and the counting rules somewhere neither can be tested.

### 3. `taskLog`'s counting semantics live in exactly one place

The three rules in the Context above become a method on the imaging rollup
class, and `DashboardPage::get30day()` becomes a **caller** of it rather than
the definition of it. Its comment moves with the code.

The test pins them by mutation, not by inspection: remove the `GROUP BY`,
remove the canceled-state exclusion, swap `MIN(createTime)` for the row's
own — each has to turn a fixture's count red. A count assertion that has only
ever been green is evidence about nothing, and this is the specific place
where a broken query still returns a plausible number.

### 4. A report keeps its own permission node; tabs do not carry gates

`Authorization::REPORT_NODES` is extended **per report**. It maps a report's
file name to the node it is really about, defaulting to `report`, and it has
two entries today (`hosts_and_users` → `usertracking`, `run_history` →
`task`).

Reports sharing one `report.view` node is the defect ADR 0023 opens with — a
helpdesk grant for an imaging report also handed over a movement log for
every named employee. Real reports make that worse rather than better,
because the subjects diverge: imaging throughput is `task`-shaped, a hardware
census is `host`-shaped, an audit summary is `audit`-shaped. Putting those on
tabs of one page under one node means granting somebody the imaging tab hands
them the audit tab.

**Per-tab permission filtering is rejected.** It puts the gate in two places
— the page's own resolution and the tab strip — which is how one of them ends
up not being the one that runs; and it renders a half-empty tab strip, which
reads as broken rather than as restricted. A report is one subject; if two
panels want different grants, they are two reports.

This narrows against the default and widens nothing: a role holding
`report.view` today gains no report it does not already have.

### 5. `taskLog.createTime` is indexed

Schema step 379. `taskLog` ships with `PRIMARY KEY (id)` and
`KEY taskID (taskID)` and has never had an index on its time column.

ADR 0022 step 354 indexed the start column of all five **work item** tables
so `ActivityWindow` could find a range without scanning. `taskLog` is not a
work-item table — it is the **event** table, one row per transition — so it
was correctly out of that step's scope, and consequently was never covered by
any step.

This is not a speculative index added ahead of a reader, which is what 354's
own reasoning warns against. `get30day()` already scans the whole table on
every dashboard load, and `taskLog` is the fastest-growing table on a busy
server and the one with the longest retention window under ADR 0023 — so the
scan gets worse exactly where the reports will be used.

Single column rather than composite: the rollups filter further
(`logImageName <> ''`, `taskStateID <> canceled`) but neither is an
equality, so neither is usable as a key part. Finding the range is the job.

## Scope

Six subjects, in the order they are worth building. Imaging is first because
it is the pattern-setter — it exercises every panel kind, the window, the
export and the permission mapping, so building it second would mean
retrofitting the abstraction it validates.

| Report | Node | Substrate |
|---|---|---|
| Imaging | `task` | `taskLog`, `tasks` (`taskTimeElapsed`, `taskBPM`, `taskDataCopied`) |
| Fleet staleness | `host` | `hosts.hostLastDeploy` / `hostCreateDate`, `inventory` absence |
| Hardware census | `host` | `inventory` (33 columns, dumped flat today) |
| Storage and replication | `storagenode` | `images.imageServerSize` / `imageLastDeploy`, `nfsFailures` |
| Snapin outcomes | `snapin` | `snapinTasks.stReturnCode` / `stReturnDetails`, `snapinJobs` |
| Change and access | `audit` | `auditLog` / `auditChange`, `history` |

The existing nine reports stay as they are. They are the rows behind these
numbers and several are already the right answer to "give me the list" —
Pending MAC List does not want a chart.

## Rejected alternatives

**Bolt a chart onto each existing report.** Cheapest, and it does not fix
anything: a chart of an unaggregated row dump is still a row dump. The
missing thing is the window and the `GROUP BY`, not the picture.

**A generic report builder** — pick a table, pick columns, pick a group-by.
Rejected on three counts. It is a second query language on top of an ORM that
already has one. It cannot be permission-scoped safely: a builder over
arbitrary tables routes around the emitter-level secret stripping and around
ADR 0019's object scope, both of which live in the paths a builder would not
take. And the questions are known — they are the six above, and a builder
ships in order to avoid choosing between them.

**Fold the panels into the activity viewer.** ADR 0022 already answered this
for Run History and the answer holds: that viewer commits to one grid, one
column set and a growing source filter, and it answers "what happened" out of
the event logs. Rollups are not events and do not share a column set.

## Consequences

- **Export needs a second path.** DataTables Buttons exports a table; it
  cannot export a chart. Each chart panel's series must be downloadable as
  CSV — which is what goes in a ticket anyway. A PNG export is reachable via
  Chart.js `toBase64Image()` and a PDF one via the already-vendored pdfmake,
  and neither is in scope until somebody asks.
- **`FOG_BCACHE_VER` must be bumped** with the JS and CSS, or the shared
  panel module is stale on every upgraded install and the feature simply does
  not appear.
- **The OpenAPI document is a hand edit in the same commit** if any rollup
  gets a REST route. These are not the generic CRUD shape, so
  `_fixedPaths()` / `_classPaths()` does not pick them up.
- **ADR 0012 applies to any panel that polls.** `doPageLoad()` cancels
  `setInterval` and cannot cancel `setTimeout`, so a self-rescheduling panel
  guards on its own widget or it outlives the page.
- **The shared panel helper is a new pattern other code will follow**, which
  is the reason this is an ADR rather than a commit.

## Sequencing

| # | Lands | Why here |
|---|---|---|
| 1 | Step 379, `taskLog.createTime` | A defect on its own. Signed off separately; already implemented |
| 2 | Imaging rollup class + its mutation-verified tests | The counting rules have to be right before anything reads them |
| 3 | `get30day()` becomes a caller of it | Proves the class against the one existing consumer before adding new ones |
| 4 | Panel render helper + the shared JS module | The abstraction, validated against exactly one report |
| 5 | Imaging report, with its `REPORT_NODES` entry | The pattern-setter |
| 6 | The remaining five, one rollup and one class each | Cheap by construction, or step 4 was wrong |

Items 4 and 5 landed together, deliberately. This ADR's own context records
that `ActivityWindow` "shipped without a caller, which is how a helper rots";
building the panel helper with nothing consuming it would have repeated that
exactly, so the imaging report is what the helper was shaped against rather
than what it was shaped for.

What item 4 turned out to be, for item 6 to reuse:

| Piece | Where |
|---|---|
| `ReportWindow` | `src/Audit/` — reads `start`/`end` off the URL on FOG's clock, drops a malformed bound, swaps a reversed pair. Each report supplies only its own default range |
| `renderReportWindow()` | `FOGPageRender` — the From/To form, with an `$extra` slot for report-specific controls (Run History's source ticks) |
| `renderStatTiles()` | `FOGPageRender` — the headline row, lifted from `ImageManagement::_archStat()` |
| `renderChartPanel()` | `FOGPageRender` — a card, a container, and the series in a `type="application/json"` block beside it |
| `fog.report.panels.js` | Draws every container on the page. No requests: a report's window is fixed and already in the URL, so re-fetching would re-run the same aggregation and give the chart a chance to disagree with the grid |
| `tabFields($tabData, 0)` | The existing nav-tabs builder. A falsy object skips its entity hooks, so reports needed no second tab implementation |
