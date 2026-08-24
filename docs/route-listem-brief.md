# `Route::listem()` and the route layer: scan and propose

**Scan and propose only. Do not write implementation code. Do not open a PR.**

Read `docs/refactor-facts.md` first; it outranks this file.

---

## What this is

`packages/web/lib/router/route.class.php` is 6,470 lines, the largest file in
the tree. `listem()` is 1,087 lines in one function. `deletemass()` is 326,
`getter()` 309, `edit()` 259, `joining()` 168.

Nobody has reviewed `listem()`, because nobody can hold 1,087 lines in their
head at once. Whatever is wrong in there is wrong quietly and has been for
years.

---

## Why this one is dangerous

`listem()` is the read path for the whole API — all 55 entries in
`Route::$validClasses` flow through it — **and** it is where object scope gets
enforced. Everything Phase 1 built in `SiteScope` lands here.

So this is not a readability exercise. A decomposition that moves a filter into
a branch which does not always execute does not throw, does not fail a type
check, and does not look wrong in review. It returns more rows. On a
site-scoped server that means one customer's users seeing another site's hosts,
and the only symptom is a list that looks fine.

**Therefore, before proposing any decomposition:** map every line in `listem()`
that participates in access control — scope filtering, permission checks, field
redaction, anything that removes or masks a row or column. Report that map as
its own artifact. If you cannot say with confidence which lines those are, say
so and stop; a decomposition proposal built on an incomplete map is worse than
no proposal.

---

## Hard constraints

**The API response shape does not change.** Users have scripts, integrations and
bookmarks against these routes. `Route::$validClasses` strings appear in
customers' code. ADR 0011 governs result wrappers. Anything that alters what a
consumer receives is a breaking change, not a refactor, and must be identified
as one and decided separately.

**`dev-branch` is out of scope.** This work is `working-1.6` only. If you find a
defect that also exists on `dev-branch`, do not propose a port and do not shape
the fix to make one easier. Flag it in a separate list at the end with a
severity, and let me decide. The one exception is a security defect with a
working exploit path, which I want told about immediately and separately from
this plan.

**No plugin should need editing.** Plugins mutate the route surface through
`API_VALID_CLASSES` and friends (`route.class.php:451-467`). Establish what that
hook surface actually permits and treat it as frozen.

---

## What I want established, in this order

**1. The access-control map.** Above. This gates everything else.

**2. What `listem()` actually does.** Not a summary — an enumeration of its
responsibilities. My expectation is that it is several functions wearing a
trenchcoat: request parsing, permission resolution, scope filtering, query
building, joining, pagination, sorting, search, field selection, result
shaping. Confirm or correct that, and say which responsibilities are entangled
such that they cannot be separated without changing behaviour.

**3. Whether the existing tests are a net or a comfort.**
`route-filter-fields.test.php`, `route-result-wrappers.test.php`,
`routed-query-string.test.php` and `openapi-route-coverage.test.php` exist. Do
they actually pin the behaviour a refactor could break — scope filtering in
particular — or do they cover the edges and leave the middle open? Answer with
what they assert, not with a count. If they are not a sufficient net, then
building one is the first commit and the decomposition is the second.

**4. The bugs.** A function this size has them. Report them whether or not they
relate to the refactor: early returns that skip later filtering, conditions that
can never be true, `$_REQUEST` reads that bypass validation elsewhere in the
function, silent failure paths, duplicated logic that has drifted between
copies.

**5. Efficiency, measured.** `listem()` is the hot path for every list page and
every API read. Measure what it costs now — query count, peak memory, wall time
on a realistic host count — before proposing anything that trades clarity for
speed. If the current cost is fine, say so and drop this. I would rather have
correct and readable than fast, but I want to know the number before choosing.

**6. `openapi.class.php` coupling.** It is 1,753 lines and derives the published
spec from this layer. Establish what in the route surface it reads, so a change
here cannot silently desynchronise the documented API from the real one.

---

## What to bring back

A plan in the established form: `VERIFIED` / `INFERRED` / `UNKNOWN` throughout,
commit-by-commit with the tree green after every commit, a shell command per
claim, alternatives considered and rejected, blast radius per step.

Separately from the plan:

1. **The access-control map**, as its own document. This outlives the refactor.
2. **The defect list**, with anything that would need a `dev-branch` decision
   split into its own section, severity-rated, no port proposed.
3. **The decisions that are mine.** Where a fix changes observable behaviour,
   present the options and their costs rather than picking. Some of what looks
   wrong in a nine-year-old API is load-bearing for somebody.
4. **The measurement.**
5. **Whether this warrants an ADR.** My instinct is that a decomposition alone
   does not, but any change to what the route layer guarantees about scope does.
   Argue it.

Ask me questions before planning if the code does not answer something.

**End with: which claim in this plan, if false, would hurt most?**

---

## One thing I am not asking for

Do not propose splitting `route.class.php` into multiple files as a first step.
Moving 6,470 lines between files produces an enormous diff that hides behaviour
changes inside apparent relocations, and it is exactly the shape of change I
cannot review. If the end state wants several files, that is the last commit in
the sequence, not the first.
