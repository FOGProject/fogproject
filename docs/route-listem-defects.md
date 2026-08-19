# Defects found scanning `Route::listem()` and the API read path

**Baseline:** `working-1.6` at `47ddae8b8`. Line numbers from
`packages/web/lib/router/route.class.php` unless stated.
**No fix is proposed for `dev-branch` anywhere in this document** — §4 lists
what would need a decision there, unrated by me.

Severity is mine; the decisions in §3 are yours.

---

## 1. Security — has a working exploit path

### SEC-1 — `GET /<class>/ids/<filter>/<field>` returns any column, secrets included · **critical** · **FIXED**

> **Closed on `working-1.6`.** `ids()` now runs `$getField` through
> `unfilterableFields()` before the existence test. Serving a request it
> answers 400; off-request (the daemons) it returns no rows and logs, because
> `sendResponse()` exits and a daemon that exits is a restart loop. Verified
> under both SAPIs against the lab database, and pinned behaviourally by
> `tests/route-ids-getfield-sensitive.test.php` (41 checks), which fails 20 of
> them when the guard is removed. Landing it required REENTRANCY-1 below.
> The description that follows is the defect as found.


`Route::ids()` (`:4862`) takes the field to SELECT from the URL. It validates
that field against `$classVars['databaseFields']` (`:4917-4941`) and against
nothing else. `unfilterableFields()` — the list that stops the same caller
*filtering* on `sec_tok` — is never consulted, and the emitter cannot help:
`ids()` builds a bare array of scalars with no `_lang` stamp, so
`stripSensitivePayload()` (`:4051`) resolves an empty classname and returns the
payload untouched (`:4074`).

Requires only `<entity>.view`, the same permission as reading the list.

```
GET /fog/host/ids/id=1/sec_tok      -> ["<plaintext host security token>"]
GET /fog/host/ids/id=1/ADPass       -> ["<AD join password>"]
GET /fog/host/ids/id=1/productKey   -> ["<Windows product key>"]
GET /fog/user/ids/id=1/password     -> ["<stored user password value>"]
GET /fog/user/ids/id=1/token        -> ["<that account's API token>"]
```

A host security token is what the fog-client protocol authenticates with —
holding one is impersonating that host. A user token is that account's API
credential.

Route matching **verified** offline (no server, no credentials):

```
php /home/telliott/scripts/background_scripts/probe_ids_getfield_route.php
# /fog/host/ids/id=1/sec_tok  => ids  params={"class":"host","whereItems":"id=1","getField":"sec_tok"}
```

The single-segment form `/fog/host/ids//sec_tok` is already closed — it parses
as a *filter* named `sec_tok` and `_assertNoSensitiveFilter()` answers 400. It
is the two-segment form that is open.

Not confirmed over HTTP: this sandbox blocks the request. The code path above
is complete and each link is cited; a one-line `curl` would settle it, and the
probe script says which.

**Fix shape (one line, no behaviour change for legitimate callers):** run
`$getField` through `unfilterableFields($classname)` in the same loop that
already validates it, and refuse the same way. Nothing in-repo asks `ids()` for
a sensitive field — `getIds()`'s internal callers ask for `id`, `name` and
association columns.

---

## 2. Object scope

### SCOPE-1 — four routes share `<entity>.view` with `list` and apply no site scope · **high**

**FIXED 2026-08-19** (commits 5 and 4). `count()` fell out of pushing the
boundary into the query; `names()`, `ids()` and `unisearch()` now carry it in
their own WHERE, gated on `'cli' !== PHP_SAPI` so the ~90 daemon and core
callers of `getIds()`/`getNames()` are unaffected (DEC-2, option 1).

`unisearch()`'s fragment is parenthesised: its match clause is a chain of ORs
and `AND` binds tighter, so appending the boundary would have scoped the last
arm alone.

DEC-2 parked `ids()` with a non-`id` `getField` as unfilterable. That was true
of post-filtering the rows and is not true of a WHERE, which constrains rows
regardless of the column asked for — so no separate decision was needed.

Verified on the lab, a user entitled to 1 of 86 hosts: all four routes answer 1
when serving a request, and `names`/`ids`/`unisearch` still answer 86 off-request,
which is the daemons.

`_applySiteScope()` is called from `listem()` (`:2545`) and `search()`
(`:2962`). It is not called from:

| Route | Handler | What a site-scoped user gets |
|---|---|---|
| `GET /<class>/names/…` | `names()` `:5954` | id + name of **every** object of that class |
| `GET /<class>/ids/…` | `ids()` `:4862` | id (or any other column) of **every** object |
| `GET /<class>/count/…` | `count()` `:2693` | the **global** count |
| `GET /[search\|unisearch]/<term>` | `unisearch()` `:2751` | id + name of every match, server-wide |

`count()` is a distinct mechanism from the other three and worth stating
separately: it *does* go through `listem()`, but sets `self::$countOnly`, and
`complex()` then returns `'data' => []` (`fogmanagercontroller.class.php:775`)
with `recordsFiltered` computed by SQL over the unscoped set. `_applySiteScope`
early-returns on the empty `data` (`:5670`) and the unscoped count is emitted.

`unisearch()` does check `Authorization::can(resolveApiPermission('list', …))`
per entity (`:2774`) — that is *entity* permission, not *object* scope, and its
route permission is `null` (`authorization.class.php:145`), so any
authenticated api user reaches it.

Consequence: object scope is enforced on the route a scoped user is *shown* and
not on four routes that answer the same question. Enumeration of every host
name and id on the server is one request.

### SCOPE-2 — scope filters the page, not the query, so a scoped user's grid is empty · **high (functional, fail-closed)**

**FIXED 2026-08-19** (commit 5). The boundary is now ANDed into the row
query and both counts as a subquery. Verified on the lab: page 1 of the
site1-scoped user's list returns their host with `recordsTotal` 1, where it
previously returned nothing. This also fixed SCOPE-1's `count()` arm — 86 → 1
for a user entitled to one host — with no code of its own.

`_applySiteScope` (`:5656`) runs after `complex()` has applied the SQL `LIMIT`.
It removes rows from the page and then rewrites `recordsTotal` and
`recordsFiltered` to the size of what is left *of that page*.
`paginate()`/`pageUrl()` build `nextUrl`/`lastUrl` from those same counts.

Measured on the lab server (86 hosts, `site1` holds exactly 1 of them, a user
scoped to `site1`, page size 25):

```
php /home/telliott/scripts/background_scripts/probe_sitescope_pagination.php < /dev/null

acting user: site1 (id 7)
scopedObjectIDs('host') = [105]  (1 host truly visible)

start  rows  returned  recordsTotal  recordsFiltered  nextUrl
0      0     0         0             0                null
25     0     0         0             0                null
50     0     0         0             0                null
75     1     1         1             1                null
```

The one host they may see sits at offset 75. Page 1 returns zero rows,
`recordsTotal: 0` and `nextUrl: null`, so a DataTables grid renders "No
matching records found" and an API client following `nextUrl` stops after one
request. The user sees an empty host list; nothing errors and nothing is
logged.

`SiteScope::allInScopeIDs()`'s docblock (`sitescope.class.php:419-423`)
describes this exact failure and instructs callers not to do it. The one core
caller does it.

This does **not** leak — it hides. But it means nobody can be running the site
feature at fleet scale today, which is itself worth knowing before the
decomposition assumes the current behaviour is load-bearing for somebody.

---

## 3. Correctness and shape — the decisions are yours

### DEC-1 — `_applySiteScope` rewrites `recordsTotal` to a page count

Even once SCOPE-2's filtering moves into the query, there is a real choice
about the counts. Today `recordsTotal` and `recordsFiltered` both become the
kept-row count.

| Option | Gains | Costs |
|---|---|---|
| `recordsTotal` = total in-scope objects, `recordsFiltered` = in-scope matching the filter | DataTables' "Showing 1 to 25 of N" is correct; paging works | changes the number every existing client sees on a scoped server. On an unscoped server (the overwhelming majority) nothing changes. |
| leave both as the unscoped SQL counts | no client-visible change at all | the count answers the question the row-filtering refuses to; a scoped user learns how many hosts exist outside their site |
| keep today's behaviour | nothing | the grid stays broken |

My read: option 1. It is the only one where the envelope describes the payload,
and the client-visible change lands only on scoped servers, where the current
number is already wrong.

**DECIDED 2026-08-19: option 1.** `recordsTotal` becomes the total in-scope
count. Lands with commit 5.

### DEC-2 — should `names`/`ids`/`count`/`unisearch` be scoped, or refused?

Scoping them is the obvious fix, but `getIds()`/`getCount()` are called from ~90
places in core and the services, and the daemons run with no `FOGUser`. A naive
scope in `ids()` would deny-all every daemon.

| Option | Gains | Costs |
|---|---|---|
| scope only when a request is being served (`'cli' !== PHP_SAPI` and a route matched), as `ids()` already does for its error branch | closes the hole, daemons unaffected | a second "am I serving a request" test in the file; that predicate becomes load-bearing |
| scope in the router, not in the handler — one filter applied to `self::$data` for any route whose name resolves to `<entity>.view` | one place, covers routes added later | needs each handler's payload shape to be recognisable; `ids()`'s bare scalar array carries no ids to filter on when `getField != 'id'` |
| refuse these four routes outright for scoped users | trivially correct | breaks the UI's own type-ahead for scoped users |

My read: option 1 for `count()` and `unisearch()` now (both already produce
identifiable payloads), and option 1 for `names()`. `ids()` with a non-`id`
`getField` cannot be filtered by row id at all — that one wants SEC-1's fix
first and then a decision about whether a scoped user may use `ids()` with an
arbitrary field at all.

**DECIDED 2026-08-19: option 1** — scope per-request, daemons unaffected.
`ids()` with a non-`id` field stays open pending its own decision; SEC-1 is now
fixed, so that decision is no longer blocked.

### DEC-3 — `storagegroup`'s `masternode` column depends on column order

**CORRECTED AND FIXED 2026-08-19 (commit 7). The premise above is wrong: it
was not order-dependence, it was state leaking between rows, and it produced
wrong output on every storage-group grid.**

`set('id', …)->load()` on an object that has already loaded a DIFFERENT group
does not clear what it resolved for the previous one. So from the second row
onwards, BOTH columns answered about the FIRST group — not merely if the two
were swapped, but as shipped. On the lab, three groups whose real members are
`[1]`, `[3,2]` and `[]` all reported `enablednodes [1]` and `DefaultMember` as
their master node, verified against `nfsGroupMembers` directly. The wrong
answer is a real node name, which is why nobody noticed.

Each formatter now resolves the row's own group through a per-id memo (the
`$snapinTaskHost` pattern). Same `load()` call — `loadMany()` via `primeRel()`
leaves a group in a state `getMasterStorageNode()` answers differently on, so
priming here trades one wrong answer for another.

This is a **behaviour change and the point of the commit**: the grid now
reports each group's own enabled nodes and master node.

`:2645-2673`. A single `new StorageGroup()` is shared by two formatters: the
`enablednodes` formatter calls `->set('id', $row['ngID'])->load()`, and the
`masternode` formatter then calls `getMasterStorageNode()` on whatever that
left behind. It works only because `dataOutput()`
(`fogmanagercontroller.class.php:212-236`) iterates columns in array order and
`enablednodes` is emitted first. This is the same shape as F-22 — correct by
an ordering accident, and neither PHP nor any test would say so if the two were
swapped. Fixing it changes no output; it is listed here because "make each
formatter self-contained" is a behaviour-preserving change that a reviewer
should be told is deliberate.

### DEC-5 — `listem()`'s catch relabels every failure as HTTP 406

`:2599-2604`. The catch calls `sendResponse(HTTP_NOT_ACCEPTABLE, $e->getMessage())`,
discarding the code the inner failure chose. Over plain HTTP this is invisible:
`_assertNoSensitiveFilter()` calls `sendResponse(HTTP_BAD_REQUEST, …)`, which
exits inside that function and never returns to `listem()`, so the wire status
is a correct 400. Under the ADR 0011 result-wrapper path it is not — there
`sendResponse()` throws instead of exiting, `listem()`'s catch catches it, and
the caller gets 406.

Verified:

```
Route::asValue(function () { Route::listem('host', 'sec_tok=x', true); });
# RuntimeException code=406 msg={"error":"Cannot filter host on: sec_tok"}
#                    ^^^ _assertNoSensitiveFilter chose 400
```

Matters twice over. For consumers: every service and client endpoint that reads
through `asValue()`/`getX()` sees one status for all failures. For the
decomposition: any behavioural test written against the wrapper seam (which is
how the net in the plan is built) will observe 406 where the source says 400,
and a test written to the source rather than to the observed behaviour will
fail for the wrong reason.

Preserving today's wire behaviour while fixing the wrapper means re-raising with
`$e->getCode()` when the code is a real HTTP status and falling back to 406
otherwise. That is a behaviour change for wrapper callers, so it is yours.

**DECIDED 2026-08-19: re-raise the inner code. FIXED 2026-08-19.** All 17
catches in the class now call one helper, `Route::_sendCaught()`, which re-raises
`$e->getCode()` when it is in 400-599 and keeps 406 for everything else. The
range matters in both directions: a `PDOException` carries a SQLSTATE
(`'42S22'`), which casts to a plausible-looking 42, and a hand-thrown
`Exception` defaults to 0 — either reaching `breakHead()` as a status would be
worse than the catch-all it replaced.

No change over plain HTTP, where the inner `sendResponse()` still exits before
the catch runs. Pinned in `tests/route-read-path-guards.test.php`: the sensitive
filter refusal now asserts code 400 (8 checks, all fail if the helper relabels),
and five direct cases pin the range guard (4 fail if it is dropped).

### DEC-4 — `case 'host':` in the column-removal switch has no `break` · **FIXED**

`:1622-1633`. Harmless today (it is the last arm) and a live hazard the moment
anyone appends a case. `break` added 2026-08-19 — the reason for flagging it
rather than fixing it was that the file was otherwise untouched, which stopped
being true.

### REENTRANCY-1 — `sensitiveFieldMap()` memoized after its own event, one call site away from an OOM · **high** · **FIXED**

Found by SEC-1's fix failing to boot. `sensitiveFieldMap()` fires
`API_SENSITIVE_FIELDS` and memoizes the result **after** the event returns.
`HookManager::processEvent()` populates its known-event list by calling
`Route::getIds('hookevent')` (`hookmanager.class.php:172-175`). So firing any
event re-enters `Route`, and any `Route` path that consults the map arrives
back at `sensitiveFieldMap()` with `$_sensitiveMap` still `null` and fires the
event again -- forever:

```
sensitiveFieldMap -> processEvent -> getIds -> ids
  -> unfilterableFields -> sensitiveFieldMap -> ...
```

Before SEC-1's fix this terminated at depth 2 purely by accident: `ids()` was
the one function on that path that did not ask for the map. Adding the guard
closed the cycle and the process exhausted 256 MB during bootstrap, before a
single line of output.

Captured live, instrumenting the map to dump its stack on third entry:

```
RE-ENTRY depth=3
  #0 Route::sensitiveFieldMap()   route.class.php:756
  #1 Route::unfilterableFields()  route.class.php:4967
  #2 Route::ids()                 route.class.php:5176
  #3 Route::getIds()              hookmanager.class.php:174
  #4 HookManager->processEvent()  route.class.php:4532
  #5 Route::sensitiveFieldMap()   route.class.php:756   <- and round again
```

Frames #10-#13 of the same trace enter the cycle from `getsearchbody() ->
_assertNoSensitiveFilter()` instead, so this was never specific to `ids()`.

**Fixed** by memoizing the core tiers *before* firing the event and again with
the plugin-augmented ones after. A re-entrant caller sees the core tiers --
never a smaller set, so a core field can never be un-protected; it can only
miss a *plugin*-declared field for the duration of one nested call, and the
only re-entrant caller is the hookevent name lookup, whose class declares no
secrets. `processEvent()` already carries the identical
mark-before-you-recurse pattern for `$knownEvents`, with a comment saying why.

`serverOwnedFields()` has the same construction and is not currently reachable
re-entrantly. Fixed the same way rather than left as the next accident.

Pinned by check 4 of `tests/route-ids-getfield-sensitive.test.php`: reverting
the memo order exits 255 with an OOM rather than failing an assertion, which is
loud enough.

---

## 4. Efficiency, measured

Method: `/home/telliott/listem-lab` (a copy of `/var/www/html/fog-1.6`) with a
statement-counting shim added to `PDODB::query()` and
`FOGManagerController::sqlexec()`; reads the live `fog` database, SELECTs only.
Harness: `/home/telliott/scripts/background_scripts/profile_route_listem.php`.

### The plain path is flat. GH-707's fix is holding.

`listem('host')` with the row count controlled by an `id=` filter:

| rows | queries | wall | peak Δ |
|---|---|---|---|
| 1 | 4 | 1.0 ms | 87 KiB |
| 10 | 4 | 1.2 ms | 97 KiB |
| 25 | 4 | 1.6 ms | 160 KiB |
| 50 | 4 | 2.3 ms | 306 KiB |
| 86 | 4 | 3.8 ms | 507 KiB |

Four queries regardless of row count, ~6 KiB and ~44 µs per row.
`rel()`/`primeRel()` are doing their job. **There is no efficiency argument for
touching the plain path.**

### PERF-1 — `?expand` is ~20 queries per row and is bounded on the wrong resource · **high**

Same class, same rows, `?expand=all`:

| rows | queries | wall |
|---|---|---|
| 1 | 30 | 10 ms |
| 10 | 201 | 50 ms |
| 25 | 485 | 107 ms |
| 50 | 1008 | 325 ms |

The expand loop (`:2560-2593`) calls `getClass($class, $rid)` per row and
`expandRelations()` per row with no priming at all — the `$relCache` that bounds
the plain path is not used here. `EXPAND_MAX_ITEMS` (2500) clamps the page size,
and the comment at `:1551` says it does so to "bound memory". Memory is not the
binding constraint: at ~25 KiB/row, 2500 rows is ~62 MiB, while the query count
at 2500 rows is **~50,000 statements** and the wall-time curve is worse than
linear (5× the rows costs 6.5× the time from 10 to 50).

This is GH-707 in the branch that was written after GH-707 was fixed.

### PERF-2 — three classes never got the priming treatment · **medium**

Plain `listem()`, whole class, marginal queries per row after the fixed three:

| class | rows | queries | q/row |
|---|---|---|---|
| `storagegroup` | 3 | 112 | **36.3** |
| `storagenode` | 4 | 54 | **12.8** |
| `imaginglog` | 11 | 16 | **1.2** |
| `snapintask` | 44 | 7 | 0.09 |
| `macaddressassociation` | 87 | 6 | 0.03 |
| `usertracking` | 12 | 5 | 0.17 |

`storagegroup` costs 284 ms for **three rows**: `new StorageGroup()` is
re-`load()`ed per row, `getMasterStorageNode()` runs per row, and the result is
passed through a full `getter('storagenode', …)` serialization per row
(`:2645-2673`). `storagenode`'s `clientload` column primes the object and then
calls `getClientLoad()` on it, which queries per call (`:2385-2399`).
`imaginglog`'s `image` column primes with `primeRel('Image', <image NAMES>)` —
`primeRel` skips anything with `(int)$id < 1` (`:5032`), so the prime is a
complete no-op and the formatter runs `->load('name')` per row (`:1818-1836`).

### PERF-3 — two byte-identical `COUNT` queries per list request · **low**

`complex()` runs `$filter_query` (`fogmanagercontroller.class.php:739`) and
`$total_query` (`:748`). With no `WHERE` — the common case for a grid's first
load — they are the same statement. On `host` that statement carries two
`LEFT JOIN`s. Confirmed in the statement log: `x2 SELECT COUNT(\`hostID\`) FROM
\`hosts\` LEFT OUTER JOIN \`images\` …`.

---

## 5. Dead and near-dead code

### DEAD-1 — `relColumn()` is never called

`:5065`. Written specifically so "a formatter that reaches for a relation
without a primer" cannot happen, per its own docblock. Zero call sites in
`packages/`, `packages/service/` or `fog-plugins`. `listem()` hand-rolls the
same `prime` + `formatter` pair **22 times**, and three of those hand-rolled
pairs have drifted — which is precisely what this helper exists to prevent
(PERF-2).

This is the single best decomposition lever in the function: 22 literal column
definitions collapse onto a helper that already exists and is already the
intended shape, with no new abstraction invented.

**FIXED 2026-08-19** (commit 2 of the plan). 19 of the 22 converted; 96 lines
net removed. The other three are not this shape and were left as they are:

| Site | Why it stays |
|---|---|
| `primac_vendor`, `mac_vendor` | prime `MACAddress::primeVendors()`, not `primeRel()` — a different cache entirely. `relColumn()` would need a second primer parameter used twice, which is an abstraction invented for two call sites. |
| `scheduledtask` → `hostLink` | primes **two** classes off one column (`Group` and `Host`, because `stGroupHostID` means either depending on `stIsGroup`). One column, two relations; `relColumn()` models one. |

Gated by `tests/route-column-contract.test.php`, which captures the whole
column table for all 52 classes at the point plugins receive it and — for the
56 columns carrying a primer — **runs** each primer and records which classes
it warms. Byte-identical before and after. PERF-2's three drifted pairs are
untouched by this commit; the conversion makes them visible, it does not
change them.

### DEAD-2 — the `?expand` clamp cannot fire for its own callers

`:1547-1561`. Guarded on `isset($pass_vars)`, which is only true when
`$inputoverride` is falsy. `$inputoverride` is a *boolean flag*, not a value —
`listem()` never reads pagination out of it — so an internal caller that passes
`true` skips the clamp entirely. Not currently reachable in a harmful way (the
HTTP route passes only two arguments, so `$inputoverride` is false on every
request), but it is a guard that does not guard, and the `isset($pass_vars)`
idiom appears four more times (`:2519`, `:2547`, `:2594`).

---

## 6. `dev-branch` — flagged only, no port proposed

| Item | State on `dev-branch` | Severity there |
|---|---|---|
| SEC-1 (`ids()` `$getField`) | same route, same validation against `$databaseFields` only (`route.class.php:2140-2208` on that branch) | moot in isolation — `dev-branch` has **no `stripSensitive` at all** (`grep -c stripSensitive` = 0), so the whole list path returns sensitive columns regardless |
| SCOPE-1 / SCOPE-2 | not applicable — `SiteScope` does not exist on `dev-branch` | n/a |
| PERF-1 | not applicable — no `?expand` | n/a |
| PERF-2 / PERF-3 | present | unmeasured there |

The `dev-branch` question is therefore not "port SEC-1's fix" but "does the
1.5.x line get sensitive-field stripping at all". That is a much larger
decision than this scan, it is a shipped-behaviour change on a patches line,
and it is entirely yours. I have not shaped anything in §1–§5 to make it
easier.

### The 1.5 `site` plugin: investigated 2026-08-19, and it is a different defect

Checked because SCOPE-1 had a working exploit path and the brief scoped
`dev-branch` in for exactly that case. `dev-branch` at `b7b748da4`,
read-only in `/home/telliott/fog-worktrees/hook-dev`.

**1.5 does not have SCOPE-1.** SCOPE-1 is "six routes carry the object
boundary and four forgot it". On 1.5 *no* API route carries one, because
`Route` on that branch never consults the site plugin at all — `listem()`
(`:625`), `search()` (`:722`), `names()` (`:2093`) and `ids()` (`:2140`)
contain no reference to `Site`, `scope` or `FOGUser` between them.

**What 1.5 has instead is a boundary that exists in the UI and not in the
API.** The site plugin's only filtering hook is
`plugins/site/hooks/addsitefiltersearch.hook.php`, registered on `HOST_DATA`
and `GROUP_DATA`. Both handlers open with `global $node; global $sub;` and
switch on them — `host`/`list` and `host`/`search`. Those events are fired by
the management pages; nothing in `api/` fires them. The plugin's *other* hook,
`addsiteapi.hook.php`, is registered on `API_VALID_CLASSES`, `API_GETTER`,
`API_INDIVDATA_MAPPING` and `API_MASSDATA_MAPPING` — it *adds* `site` and
`sitehostassociation` as API classes and decorates host payloads with their
`siteID`. It filters nothing.

So a site-restricted user sees their site's hosts in the grid and every host
on the server through the API, on the same credentials.

**Reachability.** Two ways in, both without an administrator:

- `Route::__construct()` skips `_testToken()`/`_testAuth()` entirely when
  `self::$FOGUser->isValid()` (`:252`) — i.e. when a normal web-UI session
  cookie is present. A site-restricted user already logged into the UI can
  navigate to `/fog/host/list` and get the unfiltered set. No API token, no
  `uAllowAPI`, no second credential.
- Or with API credentials: `_requireAuthorized()` (`:489`) allows a non-admin
  `list`, `listdetails`, `search`, `names`, `ids`, `indiv`, `active` on
  `host` and `group`, among others.

Precondition for both: `FOG_API_ENABLED` (`:218`), which is off by default.

**FIXED on `dev-branch` 2026-08-19** — #1229, merged. Not a port: 1.5 has no
`SiteScope` and no `Authorization::scopedObjectWhere()`, so the boundary had to
be built there. Core gained one seam, `API_SCOPE_IDS`, and stays ignorant of
sites; the site plugin answers it, and the membership rule moved to `Site` as
static methods that BOTH hooks now call. `listem()`/`search()` filter the rows
(neither has a `LIMIT`, so that is exact), `names()`/`ids()` fold it into the
`WHERE`, and `runMatches()` gates `indiv`/`update`/`delete`/`task`/`cancel` in
one place. Same tri-state as 1.6 — `null` no boundary, array narrows, empty
array denies — and `_buildWhere()` already compiled an empty `IN` to `WHERE
1=0`, so deny-all was safe by construction on that branch.

Verified on the 1.5 lab, 2079 hosts and a user entitled to 2: every read route
went from 126/68/2079/2079 to 2, the administrator unchanged, a restricted user
with no site gets 0 rather than 2079, and with nobody logged in — the daemons —
`ids('host')` still answers 2079. Pinned by `tests/site-api-scope.test.php`
(28 checks, 8 mutations caught).

