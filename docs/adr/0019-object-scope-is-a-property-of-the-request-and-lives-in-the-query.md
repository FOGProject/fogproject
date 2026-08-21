# Object scope is a property of the request, and it lives in the query

## Status

accepted

## Context

ADR 0006 added a generic, default-allow object-scope seam to `Authorization`
and gave it a meaning: a site-scoped user sees only the hosts, users, groups
and user groups that belong to their sites. Sites have since moved from a
plugin into core, but the boundary that ADR describes is a boundary on
*objects*, and it left two questions unanswered that only became visible when
the API read path was audited.

**Which routes are inside it?** ADR 0006's fourth choke point is "list/search
visibility", and that is exactly what was built: `Route::listem()` and
`Route::search()` each call the filter. Four other routes answer the same
question and did not:

| Route | What a site-scoped user got |
|---|---|
| `GET /<class>/names` | id + name of **every** object of the class |
| `GET /<class>/ids` | id — or any other column — of **every** object |
| `GET /<class>/count` | the **global** count |
| `GET /[search\|unisearch]/<term>` | id + name of every match, server-wide |

All seven routes require the same `<entity>.view`. So the split between
"scoped" and "not scoped" was not a decision anyone had made: two functions had
a line added and four did not. Enumerating every host name and id on the server
was one request, and `unisearch()` was reachable by any authenticated api user
because its route permission is `null`.

**Where in the request is it enforced?** On the rows, after
`FOGManagerController::complex()` had already applied the `LIMIT`. So a user
scoped to one site of an 86-host server got an **empty first page** — their
host was at offset 75 — with `recordsTotal` 0 and `nextUrl` null. The grid said
there were no records while the records sat two pages further on. The counts
failed in the other direction: computed in SQL over the unscoped set, they
described objects the user may not see.

`SiteScope::allInScopeIDs()`'s own docblock already said what to do —
"push the boundary into the query that builds a list, rather than fetching a
page and discarding rows afterwards — discarding rows leaves the row COUNTS
describing objects the user cannot see" — and the one core caller did the
opposite.

This is the "shared resource, today's sole user is a snapshot" shape. The
question is not "how do we scope `names`", it is *what does the route layer
promise about object scope, and to which routes does that promise attach* —
because the answer binds every route added after it.

## Decision

**1. A route that answers "which objects of this class exist" is inside the
boundary.** The test is the question the route answers, not the shape of its
payload. `list`, `search`, `names`, `ids`, `count` and `unisearch` all answer
it, so all six are scoped. A route added later that answers it is scoped too,
and the absence of a line in its handler is a defect rather than a design.

**2. The boundary is SQL, ANDed into the query.** Never a filter over rows the
database has already chosen. `Authorization::scopedObjectWhere($node, $idExpr)`
returns the fragment; `listem()` passes it to `complex()` as `$whereAll`, which
was already ANDed into the row query and the filter count and appended to the
total count — the three places the boundary has to hold. `names()`, `ids()` and
`unisearch()` AND it into their own `WHERE`.

A **subquery**, not an id list: one expression whatever the fleet size, one
round trip fewer because the ids are never fetched, and the question of whether
a large site's `IN (…)` outgrows `max_allowed_packet` never arises.

Putting it in the WHERE is also what makes `ids()` fixable at all. That route
returns a bare column — `/host/ids/id=1/name` answers with names and no id — so
there is nothing in the payload to filter on. A WHERE constrains **rows**, so
it is indifferent to which **column** was asked for.

**3. The tri-state is safe by construction.** `null` means no boundary applies
and is the **only** falsy value these functions return. A user who reaches
nothing gets the string `'1=0'`, which is truthy. A caller writing the natural
`if (!$where) { skip }` therefore skips only where skipping is correct.
Returning `''` for deny-all would make that same line show every row on the
server — the null-vs-`[]` trap that `scopedObjectIDs()` already carries one
level up, restated in a form where the dangerous branch cannot be reached by
accident.

**4. The boundary attaches to a REQUEST, not to a process.** `getIds()` and
`getNames()` are called from ~90 places in core and the services, and a daemon
has no `FOGUser`. A userless caller belongs to no site and so reaches nothing —
a correct answer that would stop every replicator, scheduler and multicast
manager on a site-configured server from finding its work.
`Route::_requestScopeWhere()` therefore gates on `'cli' === PHP_SAPI`, the same
predicate `ids()` already used to decide whether it may answer 400 or must
return empty and log.

**5. The membership rule exists once.** `SiteScope::_inScopeSelect()` builds the
SELECT; `allInScopeIDs()` runs it, `inScopeWhere()` embeds it. The decision of
*whether* a boundary applies is shared by `scopedObjectIDs()` and
`scopedObjectWhere()` through `Authorization::_boundedUserID()`. Two copies of a
membership rule in two dialects is a failure this codebase already documents
elsewhere: when they drift nothing fails, the boundary simply stops matching in
one of the two places.

**6. The row filter stays, as a backstop, and says when it fires.**
`Route::_applySiteScope()` still runs on the `listem()` and `search()` paths. It
should now have nothing to remove there, so it logs when it does. It
legitimately still removes rows on `search()`, which runs it a second time after
`API_MASSDATA_MAPPING` — hooks receive `data` by reference, so a plugin
appending an out-of-scope row is exactly what that second call is for.

**7. The envelope's counts describe the caller's scope.** `recordsTotal` and
`recordsFiltered` are the totals within what the caller may see. For an
unrestricted user that is the server; for a site-scoped user it is their sites.
`openapi.class.php` says so.

## Consequences

- **Unscoped servers are untouched.** `scopedObjectWhere()` returns `null` and
  the statements are byte-identical to before. That is the overwhelming
  majority of installs.
- **The counts change meaning for scoped users**, from "a page-sized floor" to
  a real total. That is the documented change; the old value described a page,
  not a result set.
- **`'cli' === PHP_SAPI` is load bearing, and it fails open.** If that ever
  stops separating a request from a daemon, a request-side caller is treated as
  a daemon and gets no boundary. It is stated in the docblock, and it is the
  argument for the boundary living in each route's query rather than in a
  blanket filter over `self::$data` that could be forgotten in one arm.
- **`deletemass()` is deliberately not covered here.** It shares `_buildSql()`
  with `names()` and `ids()`, and the boundary is passed by the callers rather
  than applied inside the builder, so a destructive path is not silently given
  a new WHERE by a read-path change. Whether mass delete should carry the same
  boundary is a real question and its own decision.
- **A new read route is now a decision, not an omission.** If it answers "which
  objects exist", it takes the boundary; if the author believes it should not,
  that belongs in the commit message.

## Verification

Against the lab's live database, a user entitled to 1 of 86 hosts:

```
# pagination, before -> after
start rows recordsTotal        start rows recordsTotal
0     0    0                   0     1    1
75    1    1                   25    0    0

# the four routes, request arm
count 86 -> 1   names 86 -> 1   ids 86 -> 1   unisearch 86 -> 1
# CLI arm (the daemons) unchanged at 86 for names/ids/unisearch
```

`tests/route-read-path-guards.test.php` pins it, including that the fragment
restricts **to** the membership set rather than away from it, that deny-all is
truthy, that no acting user is denied rather than unbounded on both the SQL and
id-list paths, and that `unisearch()`'s fragment is parenthesised — its match
clause is a chain of ORs and `AND` binds tighter, so an unparenthesised
boundary would scope the last arm alone and leave the rest matching
server-wide.
