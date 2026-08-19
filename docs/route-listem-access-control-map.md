# The access-control map of `Route::listem()` and the API read path

**Status:** reference. Written to outlive the decomposition it was written for.
**Baseline:** `working-1.6` at `47ddae8b8`. Every line number is from
`packages/web/lib/router/route.class.php` unless stated otherwise.

This document answers one question: **which lines in the read path remove or
mask a row or a column, and what happens if one of them stops running.**

A decomposition that moves any line listed here into a branch that does not
always execute does not throw, does not fail a type check and does not look
wrong in review. It returns more rows. Check a proposal against this list
before checking it against anything else.

---

## 0. Before `listem()` is entered

These are not in `listem()`, but they are the only thing standing between an
unauthenticated caller and it. A change to `listem()`'s signature or to how it
is routed can move a route out from behind them.

| Line | Mechanism | Failure mode if skipped |
|---|---|---|
| `:571-580` (ctor) | `_testToken()` then `_testAuth()`, unless the path is in `$unauthexact` / `$unauthprefixes` | the whole API is anonymous |
| `:578` | `CSRF::requireForStateChanging()`, session-authed callers only | state-changing routes become CSRF-able from a logged-in browser |
| `:1191-1196` | `Authorization::requireApiPermission(resolveApiPermission($routeName, $class))` | any authenticated api user reads any class |
| `:1200-1203` | `Authorization::requireApiObjectScope($class, $id)` | **inert on every list route** — list routes carry no `id` param, so this is a no-op for `listem()`. Object scope on a list is entirely `_applySiteScope`'s job (§3). |

`resolveApiPermission` maps `list`, `indiv`, `search`, `count`, `names`, `ids`
and `active` to the **same** `<entity>.view` permission
(`authorization.class.php:118-132`). Permission therefore cannot distinguish
these seven routes from one another; only §3 can.

---

## 1. Input side — what a caller may ASK for

Everything here answers **HTTP 400 and exits**. There is no "ignore the bad
filter and carry on" path, deliberately: dropping a filter key broadens the
query, and a caller asking for one host would silently receive all of them.

| Line | Mechanism | Guards |
|---|---|---|
| `:1567` | `handleWhereItems($whereItems, $class)` → `_assertFilterKeys` (`:694`) → `_assertNoSensitiveFilter` (`:781`) | the URL `[*:whereItems]` segment |
| `:1580` | `getsearchbody($class)` (`:3816`) → `_assertNoSensitiveFilter` (`:3844`) | the JSON request body |
| `:754` | `unfilterableFields($class)` — the field list both of the above consult | reads `sensitiveFieldMap()`, so plugin-declared secrets are covered by construction |
| `:828` | `expandSearchWildcards()` — `*`/`+` → `%`, **request-facing entry points only** | applying it in `_buildSql` made `['name' => '*']` compile to `LIKE '%'`; that is how the RBAC lockout guard once believed an admin always remained |

Why filtering is blocked on *both* secret tiers, not just tier 2: a filter is a
question asked of every row at once, and DataTables filters are substring
`LIKE`s. A caller who may not read `host.sec_tok` can otherwise recover it one
character at a time from the row count alone. `sec_tok` and `user.token` are
stored in plaintext and compared exactly at authentication.

**Not covered here:** `Route::ids()`'s `$getField` (`:4917-4941`). It is
request-supplied, it names a column, and it is validated against
`$databaseFields` only — never against `unfilterableFields()`. See defect
**SEC-1**.

---

## 2. Column side — what the query may SELECT

| Line | Mechanism | Note |
|---|---|---|
| `:1614-1632` | `arrayRemove()` drops `user.password`/`token` and the host secret set from `$tmpcolumns` | hard-coded switch, **not** read from `sensitiveFieldMap()` — a second list that must agree with the first |
| `:1640` | `API_REMOVE_COLUMNS` hook, `&$tmpcolumns` | by reference: a plugin can re-add a column removed on the line above |
| `:2480` | `CUSTOMIZE_DT_COLUMNS` hook, `&$columns` | by reference: a plugin can append a column with any `db`, including a sensitive one |
| `:2508-2515` | `unfilterableFields()` → set `nosearch` on matching columns | runs **after** `CUSTOMIZE_DT_COLUMNS` on purpose, so plugin columns are covered. Keys on `dt`, not `db` — a plugin column whose `dt` differs from the friendly field name is not matched |

`:1614` is column *removal*; `:2508` is search *suppression*. They are not
alternatives. `productKey` is deliberately left in `$tmpcolumns` because
`product_keys.report.php` calls `listem('host')` and has nothing to report
without it; `stripSensitive()` at the emitter (§4) is what keeps it off the
wire.

---

## 3. Row side — which rows survive

This is the site boundary, and it is two lines.

| Line | Mechanism |
|---|---|
| `:2545` | `_applySiteScope($classname)` (`:5656`) |
| `:2546` | `_applySettingValueScope($classname, $pass_vars)` (`:5728`) |
| `:2962` | `_applySiteScope($classname)` again, in `search()` — idempotent, second application is a no-op |

`_applySiteScope` asks `Authorization::scopedObjectIDs($node)`
(`authorization.class.php:942`), which returns:

- `null` → **no boundary applies**, leave the list alone. Returned for an
  unrestricted user, a server with no sites, a catch-all site member, and an
  unscoped node.
- `[]` → **the user may see nothing.**
- a list of ids → keep only rows whose `id` is in it.

**`null` and `[]` are both falsy.** Collapsing them — `if (!$ids) { return; }` —
shows every host to a user with no sites, silently, with no log line. That is
the single most dangerous edit anyone can make to this file, and
`tests/site-scope-lists.test.php` pins it *on the supplier side only* (see §6).

Two structural properties of `_applySiteScope` that any proposal must address:

1. **It runs after the SQL `LIMIT`.** `FOGManagerController::complex()`
   (`fogmanagercontroller.class.php:703,737`) has already paged the result set
   by the time `:2545` runs, so this filters *the page*, then rewrites
   `recordsTotal` and `recordsFiltered` to the size of the filtered page.
   `SiteScope::allInScopeIDs()`'s own docblock
   (`sitescope.class.php:419-423`) says callers must push the boundary into
   the query "rather than fetching a page and discarding rows afterwards —
   discarding rows leaves the row COUNTS describing objects the user cannot
   see". The one core caller does the thing that docblock forbids. Defect
   **SCOPE-2**; it is fail-closed, so it hides rows rather than leaking them,
   but it makes the site feature unusable.
2. **It early-returns when `data` is empty.** `Route::count()` sets
   `self::$countOnly = true`, and `complex()` then returns `'data' => []` with
   `recordsFiltered` computed by SQL over the *unscoped* set. So `count()`
   returns the global count to a scoped user. Defect **SCOPE-1**.

Routes that reach `_applySiteScope`: `list`, `active`, `pendingmacs`, `search`.
Routes that do **not**, and share the same `<entity>.view` permission:
`names`, `ids`, `count`, `unisearch`. Defect **SCOPE-1**.

---

## 4. Emitter side — what leaves the process

| Line | Mechanism |
|---|---|
| `:2587` | `stripSensitive($classname, $exp)` — **only inside the `?expand` branch** |
| `:2550` | `self::$data['_lang'] = $classname` — not a guard, but the emitter's only way to learn the classname of a list payload |
| `:4005` | `printer()` → `stripSensitivePayload($data)` (`:4051`) — the unconditional one |
| `:4611` | `stripSensitive()` — tier 2 always, tier 1 unless `$alwaysOnly` |
| `:4628` | `maskSensitiveSetting()` — blanks `value` on a credential `globalSettings` row |

The `_lang` stamp is load-bearing and does not look it. `stripSensitivePayload`
resolves the classname from `$data['_lang']`, falling back to
`self::$emitClassname` (set only by `indiv()`, `:2984`), and **returns the
payload untouched when the classname is `''`**. So:

- renaming or dropping `_lang` disables stripping for every list payload;
- any route that builds `self::$data` itself, without a `_lang` stamp and
  without going through `indiv()`, is emitted raw. `names()`, `ids()`,
  `count()` and `unisearch()` all do exactly this. For `names()` and `count()`
  that is harmless (two columns / one integer). For `ids()` it is **SEC-1**.

Masking must stay in `printer()` and must not move into `indiv()`/`getData()`:
the web tier reads through `getData()` and depends on it staying unmasked
(`fogconfigurationpage.page.php` compares `$Setting->value` against the posted
value to decide whether to write). Equally, stripping must not move *into*
`listem()`: the LDAP login path calls `listem('ldap')` and needs `bindPwd` to
bind at all.

---

## 5. The plugin hook surface, treated as frozen

Six hooks fire inside or around this path. Three of them hand a plugin a
reference to something in this map.

| Hook | Line | Handed | Can it weaken a guard? |
|---|---|---|---|
| `API_VALID_CLASSES` / `_TASKING_` / `_ACTIVE_TASK_` | `:590-599` | the class lists | adds routes; the class then resolves through `_pluginEntity()` or is denied |
| `API_REMOVE_COLUMNS` | `:1640` | `&$tmpcolumns` | **yes** — can re-add a column §2 removed |
| `CUSTOMIZE_DT_COLUMNS` | `:2480` | `&$columns` | **yes** — can add a column reading any db field |
| `API_MASSDATA_MAPPING` | `:2531` | `&self::$data` | fires **before** `_applySiteScope`, so rows a plugin adds are still scope-filtered. Keep that order. |
| `API_SENSITIVE_FIELDS` | via `sensitiveFieldMap()` `:4514` | both secret tiers | strengthens only (append) |
| `API_PLUGIN_ROUTES` | `:874` | route declarations | fails closed: bad entry dropped, unknown `auth` → `required`, no permission → denied |

The two `&`-by-reference column hooks are the weakest points in the map and
they are ABI. Nothing may narrow them without a plugin-visible break.

---

## 6. What the tests actually pin

Measured by mutation, on the full suite (`sh tests/run-all.sh`, 72 tests green
at baseline). Each row is one edit to `route.class.php`, suite re-run, file
restored from a scratchpad copy.

| Mutation | Suite result |
|---|---|
| delete `_applySiteScope($classname)` from `listem()` | **72 passed, 0 failed** |
| make `_assertNoSensitiveFilter()` `return;` immediately | **72 passed, 0 failed** |
| replace `unfilterableFields($classname)` with `[]` (no `nosearch`) | **72 passed, 0 failed** |
| comment out `stripSensitivePayload()` in `printer()` | **72 passed, 0 failed** |
| make `_applySettingValueScope()` `return;` immediately | **72 passed, 0 failed** |
| rename `_lang` to `_language` (disables all list stripping) | **72 passed, 0 failed** |
| rename the envelope key `recordsReturned` | **72 passed, 0 failed** |
| change the `/names` route's path | **72 passed, 0 failed** |

**Every access-control mechanism in this document can be deleted with the suite
green.**

The reason is consistent, and it is the failure documented in
`docs/lessons/` as pinning a symbol's *use* instead of its *behaviour*:

- `site-scope-lists.test.php` tests `Authorization::scopedObjectIDs()` — the
  **supplier** of scope ids, exhaustively and well. It contains no reference to
  `Route::_applySiteScope` or `Route::listem`. The call site is untested.
- `sensitive-fields-unfilterable.test.php` is a source-text grep: it asserts
  the string `_assertNoSensitiveFilter(` appears in both callers, the string
  `HTTP_BAD_REQUEST` appears somewhere in that function, and `'nosearch'`
  appears in `listem()`. Inserting `return;` above the `HTTP_BAD_REQUEST` line
  leaves all three strings in place.
- `listem-envelope.test.php` is a *caller-side* lint — nobody may iterate the
  envelope instead of `->data`. It does not pin the envelope's shape.
- `openapi-route-coverage.test.php` compares route **names** served against
  route names described, both directions. Not paths, methods, parameters or
  response bodies.

This is the map's most important line: **the net does not exist yet.** Build it
before decomposing anything.
