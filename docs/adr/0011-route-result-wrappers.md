# Route hands results back as values, and raises instead of exiting

## Status

accepted

## Context

Nearly every backend use of the API router is three lines that do one thing:

```php
Route::listem('ouassociation', ['hostID' => $id]);
$assocs = json_decode(Route::getData());
foreach ($assocs->data as $assoc) { ... }
```

`getData()` JSON-encodes `Route::$data` purely so the caller can decode it back.
There are **168 such sites in `packages/web` and 37 in `fog-plugins`**.

The encode/decode is the least interesting part. Two hazards ride on this idiom,
and both fail silently.

### The envelope became caller knowledge

`listem()` leaves a paginated wrapper in `Route::$data`, so the rows sit under
`data`. On 1.6 that wrapper carries eleven members:

```
draw  recordsTotal  recordsFiltered  truncated  data
_lang  recordsReturned  firstUrl  prevUrl  nextUrl  lastUrl
```

The envelope is the DataTables and pagination contract and has to keep
existing. But a caller that iterates the envelope rather than `->data` walks
those eleven scalars. It does not error. It warns `Attempt to read property X
on int` and yields null for every field the loop body asks for.

That mistake has shipped three times:

| Site | Consequence |
|---|---|
| `ou` plugin's check-in hook | `ADOU` never set on any client check-in, one warning per check-in on a client endpoint |
| `route.class.php` group task cancel | cancelling a group's tasks cancelled **nothing** — eleven nulls passed to `getClass('Task', null)->cancel()` while the real tasks kept running |
| `bootitem.hook.php` | the iPXE menu never applied its `fog.local` boot-to-disk label |

All three were verified against a live 1.6 database before being fixed. They are
not near-misses; two of them were silently broken functionality in shipped
releases.

### The helpers end the process

`listem()`, `indiv()` and `active()` report failure through
`Route::sendResponse()`, which reaches `HTTPResponseCodes::breakHead()`, which
ends in `exit`. At the HTTP boundary that is correct — the router is entitled
to end the response.

It is called from well below that boundary:

| Where it runs | Sites | What `exit` means there |
|---|---:|---|
| page / model | 78 | a 406 — ugly, survivable |
| **daemon / service** | **26** | **the daemon process dies**, after writing an HTTP status line to stdout |
| reports / reg-task | 16 | response terminated mid-render |
| router itself | 6 | correct — this *is* the boundary |
| **client endpoint** | **3** | **non-2xx is invisible to the client** and reads as a transport failure |
| hook | 1 | kills whatever request the hook fired inside |

30 of 130 sites are in a context where terminating is the wrong answer.

This is already known here. `multicasttask.class.php` carries a hand-rolled
guard for exactly it (#907), and its comment describes the failure precisely:

> `Route::indiv()` answers a missing row with `sendResponse(404)`, which ends in
> `HTTPResponseCodes::breakHead()`'s exit. That is correct for a web request and
> fatal here: it terminates the forked daemon child outright, with nothing
> written to `multicast.log` and no exception for the service loop to catch, so
> multicast stops dead until someone restarts the unit.

That guard had to duplicate `indiv()`'s own validity test to avoid calling it.
It is a workaround for one call site of a hazard that has thirty.

### There is already a precedent for the fix

`Route::getIds()` is this exact wrapper, and its docblock already names the
problem — *"skips the json_encode/json_decode round-trip getData() incurs, for
the common `Route::ids(...); json_decode(Route::getData())` idiom"*. It is used
**232 times in core and 34 in plugins**. The wrapped form is already the
majority idiom; nobody extended it to `listem()` and `indiv()`, which is where
the remaining sites are.

## Decision

**Add `Route::getList()` and `Route::getItem()`, shaped after `getIds()`, and
make `sendResponse()` raise instead of exiting while one of them is on the
stack.**

```php
$assocs = Route::getList('ouassociation', ['hostID' => $id]);   // rows
$ou     = Route::getItem('ou', $ouId);                           // one entity
```

Four properties, each chosen against a specific failure:

**They return rows, so there is no envelope to hold wrongly.** `getList()`
returns `[]` rather than null on an empty result, so a `foreach` is always
safe. The static guard `tests/listem-envelope.test.php` stays as a backstop for
sites that still use the raw form, but the wrapper removes the opportunity
rather than catching the mistake.

**They add no policy.** `listem()` and `indiv()` do the work, so every
permission filter, hook, `expand` and `pluginItems` step runs exactly as before.

**They hand back the object graph `json_decode()` would have produced**, via a
recursive `objectify()` rather than a JSON round-trip: a list stays a list, an
associative array becomes `stdClass`, scalars pass through. This is not
cosmetic. Roughly 190 call sites read `$row->field`, and returning raw arrays
would make migrating them a rewrite rather than a swap — and a `$row->id` left
behind on an array reads null rather than erroring, which is the same silent
shape as the bug being fixed. Equivalence is asserted against
`json_decode(json_encode($x))` over fourteen shapes.

**Failures raise, they do not exit.** A `private static $_rethrowDepth` counter
is incremented by the wrappers; `sendResponse()` consults it and throws a
`\RuntimeException` carrying the message and status code instead of calling
`breakHead()`. When the counter is zero — every existing caller — behavior is
byte-identical to today.

The guard lives in `sendResponse()` rather than in the individual `catch`
blocks because that is the single choke point: 44 call sites in the class
funnel through it, and paths such as `getsearchbody()` end the response without
ever reaching `listem()`'s own `catch`. Putting it in the three obvious catches
was tried first and missed exactly that path.

A counter rather than a boolean because `listem()` fires hooks that may
re-enter `Route`, and the inner call must inherit the outer caller's context.
`$expandDepth`, `$getterDepth` and `$_deleteDepth` are the existing precedent in
this class.

### `getItem()` establishes existence first

`indiv()` answers a missing row with `sendResponse(404)`. Rather than make that
ambiguous, `getItem()` asks a cheap id-only question first and returns null when
there is no such row — or when the caller may not see it. One extra indexed
query is the price of leaving `indiv()`'s HTTP behavior exactly as it is.

### `inputoverride` is true

Internal callers are not answering a DataTables POST. With the default,
`listem()` reads pagination out of `php://input`, so a wrapper called inside a
POST request could have its result silently paged by unrelated request data.
This is a deliberate difference from the three-line idiom it replaces, and the
only one.

## Consequences

Nothing changes for any existing caller. The wrappers are additive, the counter
is zero unless one is on the stack, and `listem()`, `indiv()`, `active()` and
`getData()` are untouched.

Migration is a separate, ordered exercise and is explicitly not part of
adopting this ADR:

1. daemons and services — 26 sites, the only bucket that can kill a process
2. client endpoints and hooks — 4 sites, invisible failures
3. `fog-plugins` — 37 sites, gated on this shipping in a release
4. pages, models, reports — 94 sites, mechanical, least urgent
5. the router's own 6 sites — at the HTTP boundary, where `exit` is correct;
   they may be right to leave alone, but that should be a decision rather than
   an omission

`multicasttask.class.php`'s hand-rolled guard can retire once step 1 reaches it.

### What this does not fix

`listem()` remains a single ~1,000-line method. Nothing here improves that, and
the wrappers deliberately do not restructure it.

The raised exception is a `\RuntimeException` carrying the message and code, not
the original `PDOException` or `ReflectionException`. The message survives,
which is what a daemon needs in order to log; the type does not. Preserving it
would mean routing every one of the 44 call sites individually rather than
guarding the choke point, and the choke point is what makes the guarantee
total.

## Alternatives considered

**Change `getData()` to return decoded data.** Rejected — its output is echoed
straight to the wire by the API's own routes, and it is public surface plugins
call. The wrapper adds a name rather than redefining one.

**Make `listem()` return rows and drop the envelope.** Rejected — the envelope
is consumed by the browser and by scripted API clients. The question was only
whether every *internal* caller should unwrap it by hand.

**Fix the three bugs and stop.** This is the honest baseline, and it is already
done. What the wrappers buy beyond it is removing the opportunity, and the
`exit` hazard, which the bug fixes do not touch at all.

**Return arrays rather than objects.** Rejected — see above; it converts a
mechanical migration into a 190-site rewrite whose mistakes read as null rather
than erroring.

**Swallow errors and return `[]`.** Rejected — it turns a database error into
"no rows", which is the same silent-failure shape this entire ADR is about.

**Fold it into the Phase 0.1 backslash-prefix pass.** Rejected — that PR's
safety argument is that it is mechanically a no-op, proven by token
equivalence. A semantic change riding along would make that proof meaningless.
