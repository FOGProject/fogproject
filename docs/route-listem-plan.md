# Plan: `Route::listem()` and the route layer

**Baseline:** `working-1.6` at `47ddae8b8`, tree green (`sh tests/run-all.sh` →
`72 passed, 0 failed`).
**Reads with:** `docs/route-listem-access-control-map.md` (the map) and
`docs/route-listem-defects.md` (the findings). This document is the sequence.

Every claim is tagged `VERIFIED` (a command in this document proves it),
`INFERRED` (follows from verified facts but is not itself run) or `UNKNOWN`.

The tree is green after every commit. `sh tests/run-all.sh` is the gate and is
stated per commit rather than repeated.

---

## The shape of the answer, up front

`listem()` is not one function that needs splitting. It is **one 1,103-line
`switch` statement wearing a 60-line function around it**:

`VERIFIED` — of the 1,103 lines between `:1512` and `:2614`, the per-field
column loop (`:1645-1981`, 337 lines) and the extra-columns `switch
($classname)` (`:1982-2478`, 497 lines) account for **834**. The actual
pipeline is the remaining **269**.

```
R=packages/web/lib/router/route.class.php
awk 'NR>=1512 && NR<=2614' $R | wc -l    # 1103  (listem)
awk 'NR>=1645 && NR<=1981' $R | wc -l    #  337  (per-field column loop)
awk 'NR>=1982 && NR<=2478' $R | wc -l    #  497  (extra-columns switch)
awk 'NR>=1512 && NR<=2614' $R | grep -c "'prime' =>"   # 22
```

That changes the recommendation. Extracting "request parsing" and "query
building" from the pipeline buys ~260 lines of readability and touches every
guard in the map. Extracting the **column table** buys ~840 lines, touches no
guard, and has a helper already written for it that is currently dead code
(`relColumn()`, `:5065`, zero call sites). Do the cheap, safe, large one first.

---

## What `listem()` actually does — the enumeration

Your expectation was "several functions wearing a trenchcoat: request parsing,
permission resolution, scope filtering, query building, joining, pagination,
sorting, search, field selection, result shaping." Mostly right. Corrections:

| Responsibility | Where | Separable? |
|---|---|---|
| request parsing (body + `?length`/`?start` fold-in) | `:1523-1543` | yes, cleanly |
| expand-clamp | `:1545-1561` | yes — and it is broken (DEAD-2) |
| filter normalisation **and refusal** | `:1567-1581` | yes, but this is guard #1 and #2 |
| **permission resolution** | *not here* | already extracted — `runMatches()` `:1191` |
| column removal (secrets) | `:1614-1633` | yes, but this is guard #3 |
| **column table** | `:1645-2478` | **yes, trivially — 834 of 1,103 lines, zero guards** |
| search suppression | `:2508-2515` | yes, but this is guard #4 |
| query building / joining / sorting / pagination / search | *not here* | already extracted — `_buildSql()` `:5788` + `FOGManagerController::complex()` |
| **scope filtering** | `:2545-2547` | yes, but this is guards #5 and #6, and both are defective |
| result shaping (`_lang`, expand enrichment, `paginate()`) | `:2550-2597` | yes, but `_lang` is load-bearing for guard #7 |

Two things are **not** entangled, contrary to expectation: query building is
already a separate function, and permission resolution never lived here.

One thing genuinely is entangled and must not be separated: **`self::$data` is
static and every helper on the path writes to it.** `getIds()` leaves it an
empty string; `rel()`/`primeRel()`/`scopedObjectIDs()` all issue queries that
can reach it. `listem()` copes by snapshotting into a local (`$payload`,
`$listData`) at `:5670` and `:2569`, and the comment at `:2562-2568` records why.
Any extraction that passes `self::$data` between new methods instead of
returning a value re-opens that. **Return values, never the static.**

---

## Commit 0 — SEC-1, out of band · **DONE**

> **Landed.** `ids()` refuses a `$getField` in `unfilterableFields()` -- 400
> when serving a request, no rows plus a log line off-request so a daemon
> cannot be exited by it. Landing it forced REENTRANCY-1 (see the defect list):
> `sensitiveFieldMap()` memoized *after* firing its own event, and
> `HookManager::processEvent()` calls `Route::getIds()`, so the new guard closed
> a cycle that had been one call site away from an OOM since the map was
> written. Both maps now memoize before the event.
>
> Suite 72 -> 73. New file `tests/route-ids-getfield-sensitive.test.php`, 41
> checks, mutation-verified both ways: removing the guard fails 20 checks;
> reverting the memo order exits 255 with an OOM.

Not part of this sequence. `Route::ids()` returns `host.sec_tok`,
`host.ADPass`, `user.password` and `user.token` in plaintext to any caller
holding `<entity>.view`. It is a one-line fix in a function nothing else in
this plan touches, and it should not wait behind a refactor.

`VERIFIED` — the router delivers the field:

```
php /home/telliott/scripts/background_scripts/probe_ids_getfield_route.php
# /fog/host/ids/id=1/sec_tok  => ids  params={"class":"host","whereItems":"id=1","getField":"sec_tok"}
```

`VERIFIED` — the emitter does not save it:

```
Route::stripSensitivePayload(['data' => [['id'=>1,'sec_tok'=>'SECRET']]])
# => {"data":[{"id":1,"sec_tok":"SECRET"}]}     (no _lang stamp, no strip)
```

**Blast radius:** `ids()` only. `getIds()`'s in-repo callers ask for `id`,
`name` and association columns; none asks for a field in
`unfilterableFields()`. `UNKNOWN` — whether any third-party script does.

**Alternative rejected:** stamping `_lang` onto `ids()`'s payload so the
emitter strips it. That changes `ids()`'s response shape (a bare array becomes
an envelope) for every caller, to fix a validation gap. Refuse at the input,
where the same class of check already lives.

---

## Commit 1 — build the net. This is the first commit and it is not optional.

`VERIFIED` — the net does not exist. Eight mutations, full suite each time,
file restored from a scratchpad copy between runs (never `git checkout --`):

| Mutation to `route.class.php` | Suite |
|---|---|
| delete `_applySiteScope($classname)` from `listem()` | 72 passed, 0 failed |
| `_assertNoSensitiveFilter()` → `return;` | 72 passed, 0 failed |
| `unfilterableFields($classname)` → `[]` | 72 passed, 0 failed |
| comment out `stripSensitivePayload()` in `printer()` | 72 passed, 0 failed |
| `_applySettingValueScope()` → `return;` | 72 passed, 0 failed |
| rename `_lang` → `_language` | 72 passed, 0 failed |
| rename envelope key `recordsReturned` | 72 passed, 0 failed |
| change the `/names` route path | 72 passed, 0 failed |

Reproduce:

```
cp packages/web/lib/router/route.class.php /tmp/route.bak
sed -i 's|self::_applySiteScope($classname);|// removed|' packages/web/lib/router/route.class.php
sh tests/run-all.sh | tail -1
cp /tmp/route.bak packages/web/lib/router/route.class.php
```

Why every one of these passes: the existing route tests pin *symbols*, not
*behaviour*. `sensitive-fields-unfilterable.test.php` greps for the strings
`_assertNoSensitiveFilter(`, `HTTP_BAD_REQUEST` and `'nosearch'`; inserting
`return;` above the `HTTP_BAD_REQUEST` line leaves all three present.
`site-scope-lists.test.php` exercises `Authorization::scopedObjectIDs()`
thoroughly and never mentions `Route`. `listem-envelope.test.php` is a
caller-side lint. `openapi-route-coverage.test.php` compares route **names**
only.

### The seams the net can use — all three `VERIFIED`, none needs a database

1. **Refusals are catchable.** `Route::asValue()` (`:5269`) raises
   `$_rethrowDepth`, which turns `sendResponse()`'s `exit` into a
   `RuntimeException` (ADR 0011). So a test can assert "this filter is refused"
   without the process ending:
   ```
   Route::asValue(function () { Route::listem('host', 'sec_tok=x', true); });
   # RuntimeException code=406 msg={"error":"Cannot filter host on: sec_tok"}
   ```
   Note the 406, not the 400 the source chose — that is DEC-5, now **answered:
   re-raise the inner code**. It is not yet implemented, because a half-applied
   status policy is worse than either and it wants one sweep. Until that sweep,
   write these assertions to the observed 406 and reference DEC-5 — including
   for commit 0's new refusal, which has the same catch above it.

2. **Stripping is a pure function.** `stripSensitivePayload()` and
   `stripSensitive()` are public static and take a payload:
   ```
   Route::stripSensitivePayload(['_lang'=>'host','data'=>[['id'=>1,'sec_tok'=>'S']]])
   # => {"_lang":"host","data":[{"id":1}]}
   ```

3. **Scope filtering is reachable by reflection with a hand-built payload.**
   `_applySiteScope` is private static and reads `Route::$data`; both are
   reachable via `ReflectionMethod`/`ReflectionProperty`. `site-scope-lists
   .test.php` already builds a `FakeDB` and swaps `FOGBase::$DB` and
   `Authorization::$_permCache` to drive `scopedObjectIDs()` DB-free — the same
   fixture drives `_applySiteScope`.

### What the net asserts

`tests/route-read-path-guards.test.php`, one new file, following the suite's
existing conventions (standalone, exit 0/1, no framework, no DB):

1. a URL filter on each tier-1 and tier-2 sensitive field of `host` and `user`
   is refused, and the refusal names the field;
2. the same through the JSON search body (`getsearchbody`);
3. `stripSensitivePayload` removes both tiers from a `_lang`-stamped list
   payload, and only tier 2 from an unstamped single-entity payload;
4. an unstamped payload is returned **unchanged** — pinning today's behaviour
   so SEC-1's fix is understood as an input-side fix, not an emitter change;
5. `_applySiteScope` with `scopedObjectIDs` → `null` leaves the payload
   byte-identical (`===`), with `[]` empties it, with `[a,b]` keeps exactly
   those rows — the `null`-vs-`[]` distinction asserted by identity;
6. `_applySettingValueScope` drops a sensitive setting matched only on `value`,
   keeps it when matched on `name`, and does nothing with no search term;
7. `unfilterableFields('host')` covers every entry of both tiers plus anything
   `API_SENSITIVE_FIELDS` declared;
8. the envelope carries `draw`, `recordsTotal`, `recordsFiltered`, `truncated`,
   `data`, `_lang`, `recordsReturned` and the four page URLs — pinned as a
   literal key list, because that list is the API contract (ADR 0011) and
   nothing pins it today;
9. `listem()` calls `_applySiteScope` — a source-level assertion, kept
   **alongside** the behavioural ones and not instead of them, because a
   decomposition can preserve the behaviour of a method nobody calls.

**Each assertion must be mutation-verified as it is written.** The mutation
table above is the acceptance criterion: re-run all eight with the net in
place and every one must fail. A net that does not turn that table red has not
been built, whatever its assertion count says.

**Blast radius:** none. New file only.
**Alternative rejected:** waiting to write tests until after the extraction, on
the grounds that the extraction gives better seams. That is the argument for
having no net during the one change that needs it most.

---

## Commit 2 — `relColumn()` adoption

`VERIFIED` — `relColumn()` (`:5065`) has zero call sites anywhere:

```
grep -rn 'relColumn(' --include=*.php packages/ /home/telliott/fog-plugins | grep -v 'function relColumn'
```

Its docblock says it exists so "a formatter that reaches for a relation without
a primer" cannot happen. `listem()` hand-rolls that pair 22 times and three
have drifted (PERF-2).

Convert all 22 `['db'=>…, 'dt'=>…, 'prime'=>fn, 'formatter'=>fn]` literals to
`self::relColumn(…)`. Mechanical; ~300 lines removed; **no guard touched** —
none of the 22 is in the map.

**Blast radius:** the grid column table for every class. The `prime` closures
become identical by construction, which is the point. `CUSTOMIZE_DT_COLUMNS`
receives the same `$columns` array shape it does today (`relColumn` returns the
same four keys), so no plugin sees a change. `INFERRED`, and commit 1's
assertion 8 plus a per-class column-name diff should prove it — see the gate
below.

**Gate:** capture `array_keys($row)` for one row of each of the 55 classes
before and after, and require them identical. This is worth writing as a
throwaway harness rather than a test, since it needs the live DB:
`/home/telliott/scripts/background_scripts/profile_route_listem.php` already
has the driving code.

---

## Commit 3 — extract the column table

`VERIFIED` (from the line counts above) — this is 834 of 1,103 lines.

Move the per-field loop and the extra-columns `switch` into
`_gridColumns($classname, $tmpcolumns, $classman)`, returning `$columns`. `listem()` keeps the two hook
fires (`API_REMOVE_COLUMNS` before, `CUSTOMIZE_DT_COLUMNS` after) so the hook
surface does not move, and keeps the `nosearch` pass — that one *is* a guard
and stays where the map says it is.

After this commit `listem()` is ~260 lines and can be read.

**Blast radius:** as commit 2. Same gate.
**Alternative rejected:** one method per class (`_hostColumns()`,
`_taskColumns()`, …). 20-plus new methods, each called from exactly one place,
to replace a `switch` that is already a dispatch on one variable. That is
abstraction for its own sake and the CLAUDE.md rule forbids it.

---

## Commit 4 — SCOPE-1: the four unscoped routes

**DEC-2 answered: scope per-request, daemons unaffected.** Unblocked.

`count()` first regardless of which option DEC-2 takes: it already runs through
`listem()`, so the fix is to compute the scoped count rather than to add a new
enforcement point. `INFERRED` — the cheapest correct form is to stop
short-circuiting `data` when a scope applies, which trades GH-707's saving back
on scoped servers only.

**Blast radius:** every list page's row counter and every `getCount()` caller,
on scoped servers. Unscoped servers take `scopedObjectIDs() === null` and are
untouched — `VERIFIED` by the map §3 and by
`tests/site-scope-lists.test.php`'s first four cases.

---

## Commit 5 — SCOPE-2: push the boundary into the query

`VERIFIED` end to end:

```
php /home/telliott/scripts/background_scripts/probe_sitescope_pagination.php < /dev/null
# page 1 of a site1-scoped user's 86-host list: 0 rows, recordsTotal 0, nextUrl null
# the one host they may see is at offset 75
```

Pass the scope ids into `complex()` as `$whereAll` — the parameter is already
there (`fogmanagercontroller.class.php:685,713-718`), already ANDed into both
the row query and the filter count, and already feeds `$whereAllSql` into the
total count. That is what it was built for; nothing new is invented.

`_applySiteScope`'s row loop then becomes a **defence in depth** assertion
rather than the enforcement — keep it, and have it log if it ever removes a row
the SQL should already have excluded.

**DEC-1 answered: `recordsTotal` becomes the total in-scope count**, so the
envelope describes the payload. Unscoped servers are untouched.

**Blast radius:** scoped servers only, and it is the difference between the
grid working and not. `UNKNOWN` — whether the id list can exceed MySQL's
`max_allowed_packet` as an `IN (…)` on a large fleet; a site with 50,000 hosts
produces a very long literal. Worth measuring before this commit, and it may
argue for a subquery against `siteHostMembers` instead of an id list — which
`SiteScope::allInScopeIDs()` already builds as SQL for the `task` case
(`sitescope.class.php:443-455`) and could expose.

---

## Commit 6 — PERF-1: prime the `?expand` branch

`VERIFIED`, measured:

| rows | plain queries | `?expand=all` queries | expand wall |
|---|---|---|---|
| 1 | 4 | 30 | 10 ms |
| 10 | 4 | 201 | 50 ms |
| 25 | 4 | 485 | 107 ms |
| 50 | 4 | 1008 | 325 ms |

`php /home/telliott/scripts/background_scripts/profile_route_listem.php`

The plain path is flat at 4 queries from 1 to 86 rows. The expand loop
(`:2560-2593`) resolves per row with no priming, so `EXPAND_MAX_ITEMS` = 2500
allows ~50,000 statements. The clamp's own comment says it bounds *memory*;
memory is ~25 KiB/row, so 2500 rows is ~62 MiB and is not the binding
constraint.

Fix: `loadMany()` the page's ids once before the loop, as `primeRel()` already
does, and have `expandRelations()` read from `$relCache`.

**Blast radius:** `?expand` responses only. The response *shape* does not
change — the same relations are inlined, resolved from a cache instead of one
at a time.
**Alternative rejected:** lowering `EXPAND_MAX_ITEMS`. It caps the damage
without fixing it and silently truncates pages that work today.

---

## Commit 7 — PERF-2: the three unprimed classes

`VERIFIED`, plain `listem()`, marginal queries per row:

| class | rows | queries | q/row | wall |
|---|---|---|---|---|
| `storagegroup` | 3 | 112 | 36.3 | 284 ms |
| `storagenode` | 4 | 54 | 12.8 | 12 ms |
| `imaginglog` | 11 | 16 | 1.2 | 13 ms |
| `snapintask` | 44 | 7 | 0.09 | 28 ms |
| `macaddressassociation` | 87 | 6 | 0.03 | 32 ms |

Three classes never got GH-707's treatment. `storagegroup` costs 284 ms for
three rows. Fix each with `relColumn()`, which commit 2 has already made the
house shape. Includes DEC-3 (the shared `StorageGroup` object threaded between
two formatters by column order).

**Blast radius:** the storage group, storage node and imaging log grids.
Formatter output is unchanged; only where the object comes from changes.

---

## Commit 8 — extract the pipeline phases

Only now, and only if commits 1–7 have left something worth extracting. After
commit 3, `listem()` is ~260 lines: parse request → normalise filters →
build columns → query → hooks → scope → shape. Each phase is a private method
returning a value.

**Every guard in the map is in this commit's diff.** It is the one that needs
the net, and by here the net has been mutation-verified twice.

**Alternative rejected — and this is your constraint, restated so a later
reader does not re-litigate it:** splitting `route.class.php` into several
files. If the end state wants that, it is the commit after this one, never
before. A 6,470-line move hides behaviour changes inside apparent relocations.

---

## `openapi.class.php` coupling

`VERIFIED` — the document reads exactly six things from the route layer:

```
grep -nE 'Route::(\$?[A-Za-z_]+)' packages/web/lib/fog/openapi.class.php
# :295  Route::webrootbase()
# :397  Route::$validClasses
# :619  Route::serverOwnedFields()
# :713  Route::sensitiveFieldMap()
# :799  Route::$validTaskingClasses
# :800  Route::$validActiveTasks
# :1104 Authorization::resolveApiPermission()
```

The important consequence: **it does not read the route table.** `defineRoutes()`
is named in its docblock but never called; the path shapes in `_paths()`,
`_classPaths()` and `_fixedPaths()` are hand-written mirrors. So a change to a
path pattern, a method, a parameter or a response body desynchronises silently.
`openapi-route-coverage.test.php` catches only route **names**, in both
directions — `VERIFIED` by mutation: changing the `/names` route's *path* while
keeping its name leaves the suite green.

For this plan that cuts a favourable way: **nothing in `listem()` is described
by the document at all.** `_entitySchema()` reflects `$databaseFields`, so the
grid's own `dt` columns — `mainlink`, `imagename`, `primac`, `primac_vendor`,
`hostLink`, `taskstateicon`, `diff`, `members`, `hostcount` — are undocumented
today. Commits 2, 3 and 7 therefore cannot desync a spec that never described
them.

They also cannot be caught by it. Those names *are* the contract that
`fog.host.list.js` and every DataTables binding depend on, and the comment at
`:2010-2018` records a case where renaming one to something tidier would have
left a column silently blank. That is why commit 2's gate is a before/after
`array_keys()` diff and not a spec check.

Commits 4 and 5 do touch the document's inputs indirectly — if DEC-1 changes
what `recordsTotal` means, the prose on the list operation in `_classPaths()`
must change in the same commit. Per CLAUDE.md's standing rule, if a commit here
touches `route.class.php` and not `openapi.class.php`, say in the message why
not.

---

## Does this warrant an ADR?

Your instinct is right and I would split it in two.

**The decomposition: no ADR.** Commits 2, 3, 7 and 8 change no guarantee, no
response shape and no extension point. An ADR recording "we made a long
function shorter" is documentation of an activity, not of a decision, and it
would dilute a directory whose value is that every entry is load-bearing.

**The scope guarantee: yes, an ADR, and it should be written before commit 4.**
Commits 4 and 5 answer a question `docs/adr/` does not currently contain:
*what does the route layer promise about object scope, and on which routes?*
Today the answer is an accident — `list` and `search` are scoped because
somebody added a line to two functions, and `names`/`ids`/`count`/`unisearch`
are not because nobody added it to four more. The permission table maps all
seven to the same `<entity>.view`, so there is no principle anywhere that says
which routes are inside the boundary.

That is exactly the "shared resource, today's sole user is a snapshot" shape:
the next route added to `defineRoutes()` will be scoped or not depending on
whether its author remembered, and nothing will tell them. The ADR's job is to
make the boundary a property of the route layer — every route resolving to
`<entity>.view` is scoped, enforced in one place — rather than a habit.

It should also record DEC-1, because "what `recordsTotal` counts on a scoped
server" is a promise to API consumers, and the reason `_applySiteScope` was
originally written as a post-filter rather than a query filter (`UNKNOWN` — the
code carries no comment saying, and `SiteScope`'s docblock argues against it,
so this may simply have been an oversight rather than a decision).

---

## Which claim in this plan, if false, would hurt most?

**That `_applySiteScope`'s post-filter is fail-closed — that it can only ever
remove rows a user should not see, never leave one in.**

Everything else in this plan is arranged around that. It is why SCOPE-2 is
rated a *functional* defect and not a disclosure, why commit 5 is sequenced
sixth instead of first, and why I was willing to keep the row loop as a defence
in depth rather than treating it as the hole.

It rests on one line, `:5676`:

```php
$id = (int)(is_array($row) ? ($row['id'] ?? 0) : ($row->id ?? 0));
if (isset($allowed[$id])) { $kept[] = $row; }
```

A row whose `id` key is missing, or is not the object id the scope list is
about, casts to `0`, misses `$allowed`, and is dropped. That is the safe
direction. But the filter is only as correct as the assumption that **`$row['id']`
is the id `scopedObjectIDs($node)` enumerated** — and `listem()`'s own column
table is what puts `id` in the row. `:1642-1657` sets it from `$tmpcolumns['id']`
for every class, so today it holds. Commits 2 and 3 rewrite exactly that code.

If a class ever emits an `id` that is not its own primary key — a join column, a
plugin column added through `CUSTOMIZE_DT_COLUMNS` with `'dt' => 'id'`, or a
future class whose `$databaseFields` has no `id` at all — then the filter stops
being a filter. It does not throw. It does not warn. It keeps or drops rows
against the wrong list, and on a site-scoped server that is one customer's users
seeing another site's hosts, with a list that looks entirely fine.

`INFERRED`, not `VERIFIED`: I have confirmed the current column table always
emits the primary key as `id`, and that none of the six bundled plugins
listening on `CUSTOMIZE_DT_COLUMNS` adds a column with `'dt' => 'id'`
(`grep -rn "'dt'\s*=>\s*'id'" /home/telliott/fog-plugins` → nothing). I have not confirmed that no third-party plugin does, and
`CUSTOMIZE_DT_COLUMNS` hands `&$columns` to anything installed. Commit 1 should
assert this directly — `_applySiteScope` against a payload whose `id` is absent,
and against one whose `id` has been overwritten — because it is the assumption
the rest of the plan is standing on.
