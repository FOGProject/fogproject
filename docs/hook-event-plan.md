# HOOK AND EVENT SUBSYSTEM — scan and proposal

**Status: implemented.** Written as a scan and proposal; the five decisions in
§5 were taken on 2026-08-19 and H.0 through H.9 landed as ten commits on
`fix-hook-event-contract`, with `docs/adr/0017-hook-dispatch-contract.md` as
the durable statement of what was decided. H.10 is deliberately not done and
is listed in §4 as its own issue. This document is kept as the reasoning
behind the ADR, not as an outstanding proposal.

Baseline: `working-1.6` @ `9cb646cba`. Facts proved here are recorded as
**F-11 … F-22** in `docs/refactor-facts.md`, which outranks this document.

**Evidence method.** Static reading, plus a running copy of the live 1.6 tree
at `/home/telliott/hookevent-lab` (a `cp -a` of `/var/www/html/fog-1.6`, driven
over `php -S` against the live database; see `_metrics/START.md`). Claims are
`VERIFIED` (I ran it and watched it happen), `INFERRED` (I reasoned it from the
source), `UNKNOWN` (I cannot see it from here).

---

## The headline: three of the seven observations understate the problem

| The brief says | What is actually true |
|---|---|
| "a failed registration returns normally and the caller cannot tell" | Only for *some* failures. Hand `register()` a closure or any non-array object and the `catch` block **itself** throws an `\Error`, which nothing catches — **HTTP 500, zero-byte body, every entry point, until the file is deleted**. F-13 |
| "the hook system is the plugin ABI" | And the authoring guide documents that exact 500 three times, in the §7 examples for the Phase 2 auth seams. Copying the documented example takes the server down. F-14 |
| "a hook whose property is `false` but whose file contains the literal string is active today" | Confirmed, and it is worse in the other direction too: `public $active  = true;` with two spaces is **inactive**, and so is `TRUE`. Six variants characterized. F-15 |
| "several things below are wrong today" | Also: no core hook or event has ever loaded on any FOG server (F-11); two of the six shipped event names are never fired (F-19); `HookManager::notify()` returns `true` having invoked nothing (F-17) |
| goal 3: measure efficiency before optimizing | Measured. The whole subsystem is **1–2 ms of an 11–543 ms page**, and the reflection the force-activation needs costs **14–168 µs**. Drop the goal. F-20 |

The one place the brief *over*states: the plugin path force-activation is not
obviously redundant — it is load-bearing (F-16). But its blast radius is far
smaller than it looks, because `Event::$active` already **defaults to `true`**
(`event.class.php:50`). Removing the force-activation only silences a plugin
hook that *explicitly wrote* `$active = false;`.

---

## 1. The defect list, as found

Ordered by what it costs when it fires, not by where it lives.

### D1 — `register()`'s error handler is itself fatal · `VERIFIED` F-13

`eventmanager.class.php:114` interpolates `$listener[0]` inside the `catch`
meant to swallow the failure. A Closure — or any non-`ArrayAccess` object —
raises `Error: Cannot use object of type X as array`, which `catch (\Exception)`
does not catch. Registration runs inside a hook constructor during
`LoadGlobals`, so it escapes `base.inc.php` and takes the whole application
down, on every entry point, with an empty body.

### D2 — the authoring guide documents D1's shape · `VERIFIED` F-14

`docs/plugin-development.md:588, 622, 636` register a closure against
`API_PLUGIN_ROUTES`, `PAGE_EXEMPT_NODES` and `LOGIN_PAGE_PROVIDERS`. `register()`
has never accepted a closure. Nothing in `fog-plugins` follows the guide (the
real OIDC plugin uses `Hook::registerInstalled()`), so this has never been
caught in-house. It is the section a third-party auth-provider author is most
likely to copy, and copying it is D1.

### D3 — the failures that *are* caught are invisible and cost a database row each · `VERIFIED`

The remaining shapes — array-of-2 whose `[0]` is not a `Hook`, or whose method
does not exist — log and return normally. `FOGBase::log()` ignores `$logfile`
and `$logbrow` entirely but always calls `logHistory()`, so once an admin is
signed in that is **one `history` row per failed registration per request**,
forever. And the message names neither the class nor the event correctly:

```
[2026-08-18 20:14:58] [2026-08-18 20:14:58] Could not register: Error: Class must extend hook, $s: Event, LAB_SILENT: Class
```

`$s:` is a typo for `%s:` (both here and in `notify()`), the format has six
specifiers for seven arguments so `$listener[0]` — the only field that says
*which* class failed — is dropped, and the timestamp is stamped twice
(`log()` then `logHistory()`).

### D4 — activation is decided by a regex over source text · `VERIFIED` F-15

`eventmanager.class.php:248-252` line-scans each non-plugin file for
`$active\s?=\s?true;`. `\s?` is zero-or-one, the match is case-sensitive, and
the scan does not distinguish code from comments.

| source line | property | loaded today |
|---|---|---|
| `public $active = true;` | true | yes |
| `public $active  = true;` | true | **no** |
| `public $active =  true;` | true | **no** |
| `public $active=true;` | true | yes |
| `public $active = TRUE;` | true | **no** |
| `public $active = false; // set $active = true; to enable` | **false** | **yes** |

The last row is not hypothetical: writing the toggle into a comment, the
obvious way to document it, turns the hook on.

### D5 — dispatch force-activates on a path substring · `VERIFIED` F-16

`hookmanager.class.php:102-105` sets `$function[0]->active = true` whenever the
listener's file path contains the substring `plugins`. Established by
experiment: a plugin hook declaring `$active = false` **still fires**. So the
property is decorative for every plugin hook, and `fileitems()`'s
installed-plugin filter is not what makes this redundant.

`INFERRED`: because it is a bare `stripos` over the whole path, an install
whose base directory contains the string — `/var/www/myplugins/fog` — would
force-activate every core hook too. `Initiator::_isPluginPath()` already exists
and answers the question properly, but it is `private`.

### D6 — `HookManager::notify()` reports success and does nothing · `VERIFIED` F-17

Inherited from `EventManager`. It iterates listeners as objects
(`$element->active`) while `HookManager` stores `[$object, 'method']` arrays.
Under PHP 8 the property read is a warning, yields `null`, the closure returns,
nothing is invoked — and `notify()` returns **`true`**. No core call site and no
bundled plugin reaches it (`VERIFIED` by grep of both trees), so this is a trap
for third-party code only.

### D7 — `Hook extends Event` defeats the type check that separates them · `VERIFIED` F-18

`EventManager::register()` guards with `$listener instanceof Event`, which a
`Hook` satisfies. So a hook can be registered as an event listener, and
`notify()` then calls `Event::onEvent()` — whose default body `printf`s
`"<EVENT> Registered"` straight into the response. Every bundled plugin event
overrides `onEvent()`; the one shipped core event, `hostlist.event.php`, does
**not**, so the inherited printer is reachable from shipped code.

Answering the brief's question directly: **hooks and events are peers, not
kinds of one another.** They share a base class only for `log()` and the
`active`/`logLevel` fields. Their dispatch contracts are genuinely different —
`processEvent()` merges an `event` key into the payload and calls a named method
that mutates arguments through embedded references; `notify()` calls a fixed
method and discards the result. The inheritance buys reuse of four fields and
costs the only type check that keeps the two apart.

### D8 — "nobody is listening" is treated as an exception · `VERIFIED`

`eventmanager.class.php:175-177` throws, catches, logs, returns `false`.
`hookmanager.class.php:89-91` handles the identical condition with a bare
`return`. On this lab server `hasListeners('HOST_CHECKIN')` is `false`, so the
throw path is the *normal* path for one of the five shipped notify sites.

### D9 — `notify()` repeats the GH-707 mistake `processEvent()` was fixed for · `VERIFIED` F-21

`processEvent()` caches the event-name list in `$knownEvents` precisely because
asking the database on every fire cost 2000 round trips on a 1000-host tasking.
`notify()` still does an uncached `NotifyEventManager->exists()` SELECT on every
call: **0.155 ms of the 0.178 ms** a listener-less `notify()` costs.

### D10 — two shipped event names are never fired · `VERIFIED` F-19

`slack`, `ntfy` and `pushbullet` all register `HOST_IMAGE_FAIL` and
`HOST_IMAGEUP_COMPLETE`. Core `notify()`s neither. So "image failed" and
"upload complete" notifications have never worked in 1.6. Conversely
`HOST_CHECKIN` is notified on every task checkin and nothing has ever listened.

### D11 — `load()` distinguishes the managers by an ordering accident · `VERIFIED` F-22

Two sequential `instanceof` checks; `HookManager extends EventManager` satisfies
both, and reaches `.hook.php`/`hooks` only because the second assignment
overwrites the first. Swapping the blocks makes every HookManager load
`.event.php`, and nothing would say so. (The brief's observation 1, confirmed
exactly as stated.)

### D12 — the parent enumerates its children by name · `VERIFIED`

`register()` switches on `self::shortName($this)` with a case per subclass and a
throwing `default`. The comment above it records that this already caused a
total registration failure once, during the namespace migration. Any third-party
subclass of either manager hits `default` and silently registers nothing.

### D13 — no core hook or event has ever loaded · `VERIFIED` F-11

All eleven files under `lib/hooks` and `lib/events` declare
`public $active = false;` and none matches the activation regex. They are
examples an admin opts into. Not a defect in itself — but it means the *entire*
live listener population on every FOG server comes from plugins, which is the
premise every risk assessment below rests on.

---

## 2. The measurement (brief goal 3) — and the recommendation to drop it

`VERIFIED`, twelve authenticated management pages, six plugins installed
(`ldap location ntfy oidc ou windowskey`). Full table in F-20.

| per request | value |
|---|---|
| hook objects constructed | 42 — all from plugins |
| event objects constructed | 5 |
| construction wall time | 0.8–1.6 ms |
| source-text regex scan | 0.40–0.49 ms |
| `processEvent()` calls | 75–246 (37–158 return immediately, no listener) |
| listener callbacks invoked | 24–331 |
| `ReflectionClass` per listener per fire | 24–331, **14–168 µs total** |
| peak memory | 2–4 MiB, flat |
| page wall time | 11–543 ms |

**Recommendation: drop goal 3.** The load path is under 1% of a request, memory
does not move, and the reflection everyone assumes is the expensive part is a
rounding error. The one exception is D9's uncached SELECT, and that is worth
fixing because it is a correctness-of-design wart (the identical bug was fixed
next door), not because 0.155 ms matters.

Nothing below trades clarity for speed. If a proposal here makes the code
slower and clearer, take it.

---

## 3. What a third-party plugin cannot see

Stated per the brief's requirement, because three proposals below have a safety
argument that depends on reading plugin source, and three do not.

`fog-plugins` is 87 hook and event files across 16 plugins. Servers run plugins
neither of us has read, and since ADR 0009 those can live outside the web tree
in `FOG_PLUGIN_DIR`.

| Proposal | Safety argument | Strength |
|---|---|---|
| H.1 fix the fatal catch | Structural — no working plugin can depend on a crash | **Independent of plugin source** |
| H.4 fix `load()` ordering | Structural — no observable change | **Independent** |
| H.6 `notify()` no-listener path | Structural — no core caller reads the return value (`VERIFIED`) | **Independent** |
| H.5 replace the `shortName` switch | Structural for valid callers; a third-party *manager subclass* would start working where it silently did nothing | **Independent**, improvement only |
| H.3 read `$active` instead of regex-matching | Rests on F-12: zero of 87 shipped files diverge. A third-party file under `lib/hooks` could | **Depends on source we can read + a structural argument for the rest** |
| H.8 remove the force-activation | Rests on F-12 **plus** the structural fact that `Event::$active` defaults to `true`, so only an *explicit* `$active = false` is affected | **Mostly structural** |
| H.7 make `HookManager::notify()` honest | Rests on nothing being able to depend on a silent no-op | **Independent**, but it is still a visible change |

---

## 4. Proposed commit sequence

Tree green after every commit. `sh tests/run-all.sh` is the gate throughout.

### H.0 — Characterization tests · blast radius **none**

One new `tests/hook-event-contract.test.php`, following the standalone-script
convention (`tests/plugin-extension-points.test.php` is the closest model:
`require commons/init.php`, `new Initiator()`, no database, exit 0/1). Pins
today's behavior **including the parts that are wrong**, so every later
behavior change shows up as a test that must be edited on purpose.

Pins: the four `register()` shapes and their outcomes; the six-row activation
table; `Hook` accepted by `EventManager::register()`; `HookManager::notify()`
returning `true` having invoked nothing; `load()`'s extension choice per manager.

`INFERRED` constraint: `EventManager`/`HookManager` extend `FOGBase`, so
construct them with `ReflectionClass::newInstanceWithoutConstructor()` to keep
the test database-free. That covers `register()`, `hasListeners()` and the
extension choice. `notify()` calls `getClass('NotifyEventManager')->exists()`
and therefore needs a stub or a `SKIP`; the runner already supports `SKIP`.

### H.1 — A failed registration must never be fatal · blast radius **none for working plugins**

- Describe the listener safely in the `catch` instead of indexing it
  (`is_array($listener)` → describe `[0]`, else `get_class()`/`gettype()`).
- Fix the format string: `$s` → `%s`, and one specifier per argument, so the
  message actually names the class that failed.

This alone converts D1 from a site-wide outage into a log line. Nothing that
works today changes. What changes is that a plugin currently 500ing the server
now leaves the server up with that one hook not registered — strictly better,
and its population is "installs that are down right now".

### H.2 — `register()` accepts a Closure · blast radius **purely additive**

Decision 1: make the documented shape work rather than deleting it from the
docs. Two listener forms, and the rule is one sentence — **a listener is either
`[Hook, 'method']` or a `Closure`, and its owner is what carries `$active`.**

| form | owner | activation |
|---|---|---|
| `[$hook, 'method']` (unchanged) | `$listener[0]` | `$owner->active` |
| `Closure` written inside a hook | `ReflectionFunction::getClosureThis()` — `VERIFIED` to return the hook | `$owner->active`, identically |
| `static function` / closure with no bound `$this` | none | always active; the registration is the opt-in |

Anything else — a bare function name, `[ClassName::class, 'staticMethod']` —
stays rejected, because an owner is what a listener needs in order to have an
`$active` at all, and the array form's `instanceof Hook` guard is unchanged.

Storage stays byte-identical for existing entries: an array listener is stored
as the array it is today, a closure is stored as itself, and the dispatch loop
branches on `is_array()`. `VERIFIED` that nothing outside the two manager
classes reads `$data`, in core, in `packages/service` or in `fog-plugins`, so
the mixed array is not observable.

Net effect on the guide: `docs/plugin-development.md:588, 622, 636` become
correct as written and need no edit. Worth one added line there anyway noting
that `registerInstalled()` remains the shape to prefer for a hook registering
several callbacks, since it also carries the installed-plugin guard.

Must land with or before H.1 either way — otherwise the documented seam stops
crashing and starts silently doing nothing, which is harder to diagnose than
the crash.

### H.3 — Read `$active` instead of grepping for it · blast radius **nothing shipped; see Decision 2**

Replace the line-by-line regex with
`(new \ReflectionClass($class))->getDefaultProperties()['active']` — the
declared default, which is exactly what the regex was trying to approximate, so
a value set in a constructor is still not consulted and behavior is unchanged
for every shipped file (F-12). Autoloading the class is an include, not a
construction; the class map is already O(1).

### H.4 — Fix `load()`'s manager discrimination · blast radius **none**

`HookManager` first with an `else`, or better a `protected $fileExtension` /
`$fileDir` pair declared on each class so the parent stops branching on its
children's types at all. No observable change; removes a reordering trap.

### H.5 — Each manager validates its own listeners · blast radius **improvement only**

Delete the `shortName()` switch. `EventManager::register()` keeps the
`instanceof Event` path; `HookManager::register()` overrides it, validates the
`[Hook, method]` pair, and stores. The parent stops enumerating its children and
the throwing `default` arm disappears — the shape the comment at `:66-69` says
already broke every registration once.

### H.6 — `notify()` stops treating silence as an error · blast radius **none**

- `!isset($this->data[$event])` → plain `return false;`, matching
  `processEvent()`'s bare return. Keeps the existing return value, so no caller
  can notice; `VERIFIED` that no core call site reads it.
- Cache the notify-event name list in a static, mirroring `$knownEvents`, with
  the same comment explaining why staleness is safe.

### H.7 — `HookManager::notify()` stops claiming success · blast radius **third-party callers only**

Decision 3. Override it in `HookManager` to log and return `false` instead of
inheriting a loop that cannot read its own listeners. Nothing in core,
`packages/service` or `fog-plugins` calls it (`VERIFIED`), so the only code that
notices is third-party code whose listeners have never fired — which now gets a
log line saying why.

### H.8 — Delete the force-activation · blast radius **plugin hooks that explicitly declare `$active = false`**

Decision 4, and it is **one commit, not two.** The earlier plan had a narrowing
step first — make `Initiator::_isPluginPath()` public and use it in place of
`stripos($filename, 'plugins')` — to kill the base-directory misfire. With the
force-activation deleted outright there is nothing left to narrow, so
`_isPluginPath()` stays private and untouched.

What goes with it: `processEvent()`'s `ReflectionClass`, `getFileName()` and
`stripos` exist *only* to feed the substring test, so all three go too. The
dispatch loop becomes: method exists, `$active`, merge, call.

This is the one commit in the sequence that changes what a running server does.
It lands with the ADR (§7).

### H.9 — Stop a `Hook` being registered as an event · blast radius **none in shipped code**

Decision 5. One `instanceof Hook` guard in `EventManager::register()`, plus
`Event::onEvent()`'s default body changing from `printf` to a no-op. Every
bundled plugin event overrides `onEvent()`; the one shipped file that does not,
`lib/events/hostlist.event.php`, is inactive (F-11) and its author plainly did
not intend "print the event name into the page" as behavior.

### H.10 — The dead event names · **split out, not part of this work**

`HOST_IMAGE_FAIL` and `HOST_IMAGEUP_COMPLETE` need a core `notify()` at the
right place. That is making a notification feature work for the first time, not
refactoring a subsystem, and it needs its own issue and its own test. Filed as
**#1202**.

---

## 5. Decisions taken

All five settled 2026-08-19. The reasoning that survives is recorded here; the
reasoning that did not is recorded too, because it was wrong in a way worth not
repeating.

**The release-freeze argument is struck from this document.** Four of the five
recommendations below originally leaned on "ADR 0013 promised the plugin ABI
holds for all of 1.6." That is not what ADR 0013 says. Its §2 makes one
promise — the reverse `class_alias` in each namespaced file is supported for
all of 1.6 and cannot be removed before 1.7 — about *the alias*, not about every
behavior a plugin can observe. And 1.6.0 has not been released: `working-1.6`
stamps `1.6.0-beta.NNNN`, so there is no installed base of third-party 1.6
plugins to keep faith with. Nothing here should be deferred to a "1.7" that is
two releases away from existing. Where a recommendation survived the correction
it is because it had a second, structural reason; where it did not, it changed.

### Decision 1 — `register()` accepts a Closure · **taken: yes**

Originally recommended against, on the freeze. With that struck, the only
remaining objection was that a closure has no `active` and no plugin node — and
that objection dissolves: `ReflectionFunction::getClosureThis()` recovers the
hook a closure was written inside (`VERIFIED`), so `$active` governs a closure
exactly as it governs `[$this, 'method']`. Implementation shape is H.2 above.

The residual cost is honest and small: the dispatch loop gains one `is_array()`
branch, and a `static function` listener has no owner and is therefore always
active. That last is a rule to document, not a hole — the alternative is
refusing a listener form the guide has been recommending for the whole of the
Phase 2 seam work.

### Decision 2 — read `$active` from the declaration, not from a regex · **taken: yes**

H.3 as written. Zero shipped files change (F-12). Two invisible populations
change, in opposite directions, and both changes deliver what the file says:
a spacing or `TRUE` variant is inactive today and starts working; a `false`
declaration with `= true;` in a comment is active today and stops.

### Decision 3 — `HookManager::notify()` · **taken: log and return `false`**

The recommendation stands but its reason does not. "Delegating to
`processEvent()` would start running code that has never run on any server" was
an upgrade-safety argument, and with 1.6.0 unreleased there is no upgrade path
to protect — that argument is void.

What replaces it: Decision 5 sharpens the boundary between hooks and events by
refusing a `Hook` where an event listener is expected. Making
`HookManager::notify()` quietly behave like `processEvent()` blurs the same
boundary in the same release. `notify()` and `processEvent()` have genuinely
different calling conventions — one merges an `event` key and mutates arguments
through references, the other passes by value and discards the result — so
`notify()` on a HookManager is a category error, and the useful thing to do
with a category error is say so.

### Decision 4 — remove the plugin path force-activation · **taken: yes, delete it**

H.8, one commit. The safety net is understood, not merely tolerated: it dates
from when `capone` was the only plugin and hooks were adopted into the plugin
system by copying core's example hooks, which are all `$active = false`. Force-
truthing every plugin-path hook made a copied example work without the author
having to notice the flag. Plugins now ship their own hooks and all 87 of them
set `$active = true` (F-12), so the net has nothing left to catch.

`$active` is an intended flag: set it false and the hook does not run, wherever
the file lives. Removing the force-activation is what makes that sentence true.

### Decision 5 — reject a `Hook` passed to `EventManager::register()` · **taken: yes**

Plus changing `Event::onEvent()`'s default from `printf` to a no-op.

Making `Hook` extend `FOGBase` directly — so hooks and events are genuine peers
rather than one being a kind of the other — is no longer deferred on the freeze
argument, but it is still not part of this work: it changes the answer to
`$obj instanceof Event` for every hook, and the defect it would close (F-18) is
already closed by the one-line guard. It belongs in its own issue with its own
blast-radius argument, not folded into a bug-fix sequence. Filed as **#1203**.

### A refinement that falls out of Decisions 2 and 4

With the force-activation gone, `processEvent()` no longer needs
`ReflectionClass`, `getFileName()` or the `stripos` — those three lines exist
only to feed the substring test. That removes the whole measured reflection
cost (F-20) by deletion rather than by optimization.

**Keep the dispatch-time `$active` read; do not also gate plugin hook
construction on it.** Gating construction would make `$active` mean one thing
everywhere and delete one more branch, but it would break a hook that computes
`$active` in its constructor — `$this->active = self::getSetting('FOO');` is the
natural way to write "on only when this setting is enabled", and for plugin
hooks it works today. `VERIFIED` that no core or bundled-plugin code does it, so
this is protecting third-party code only; the cost of protecting it is one
property read per listener per fire, which the measurement says is free.

## 6. Alternatives considered and rejected

**Rewrite the two managers as one dispatcher with two listener kinds.** It is
the right shape — `load()`, `register()` and dispatch all branch on which
manager they are, which is a class doing two jobs. Rejected here, not deferred
to a release: it moves every line a plugin can observe at once, and every defect
on the list above is fixable without it. Deliberately **not** filed as an issue
-- nobody is currently making that argument, and an open issue for a rejected
alternative reads as agreed work. Noted in #1203 instead, next to the hierarchy
change it would subsume.

**Make activation a database setting rather than a property.** Would let an
admin toggle a core example hook without editing a file, and would kill D4
outright. Rejected: it puts a database read in the boot path before
`LoadGlobals` has finished, and core hooks are examples — the audience for the
toggle is someone already editing the file.

**Cache the discovered listener set.** The obvious "efficiency" move, and the
measurement (F-20) says there is nothing to win: 1–2 ms, and a cache keyed on
the file list is a new invalidation bug in exchange.

**Delete the eleven inactive core hooks.** Tempting after F-11 — they have never
run on any server. Rejected: they are the worked examples the authoring guide
points at, and deleting them is a docs decision, not a refactor.

---

## 7. Does this warrant an ADR?

**Yes — one, and narrower than it first looks.**

The argument for: Decisions 1 through 5 change what a plugin author can rely
on, and four of them leave no trace in the code. A future maintainer looking at
`hookmanager.class.php` needs to know the missing force-activation is a decision
and not an omission — that is exactly what an ADR is for, and it is the same
argument that produced ADR 0012 for a two-line polling guard. The
force-activation in particular has a *reason* behind it (Decision 4: copied
core example hooks, back when `capone` was the only plugin), and a reason that
has expired is worth writing down precisely so nobody re-adds the net.

Note this is **not** an ADR 0013 question. That ADR's promise is about the
reverse `class_alias` specifically, and 1.6.0 is unreleased — there is no
shipped ABI here to break, which is why none of the five decisions needed a
deprecation window.

The argument against, which I do not find sufficient: most of the list (H.1,
H.4, H.5, H.6) is bug fixing with no observable contract change, and an ADR per
bug fix is noise.

**Proposal:** one ADR — *"The hook dispatch contract: a listener is
`[Hook, 'method']` or a Closure, and `$active` is what decides whether it
runs"* — stating the five settled points: the two listener forms and how a
closure gets an owner; that activation is read from the declaration at load and
from the property at dispatch, and from nothing else; that a plugin's file path
no longer overrides it, and why the net existed; that hooks and events are peers
sharing a base class for historical reasons, so a `Hook` is not an event
listener; and that `notify()` on a HookManager is an error rather than a no-op.

Written with H.8, because that is the commit that changes what a running server
does. It stands alongside ADR 0013 rather than amending it.

---

## 8. UNKNOWN

1. **Third-party plugin hooks that explicitly set `$active = false`.** Decision
   4's entire residual risk. Unknowable without a survey of installs.
2. **Third-party hook files dropped into `lib/hooks`** — Decision 2's exposure.
   Narrowed but not closed by `configureHttpd()` wiping the web tree.
3. **Whether any third-party code calls `$HookManager->notify()`.** If it does,
   it has never worked, so this is a question about who gets a new log line.
4. **`dev-branch`.** Not examined. Every finding above is stated for
   `working-1.6` only. D1's fatal catch and D4's regex look old enough to be
   present on 1.5 as well, but that is `INFERRED` from age, not checked — and
   1.5's plugin population is much larger, so the port is a separate decision.
5. **Whether the `stripos(path, 'plugins')` misfire has ever fired in the
   field.** It needs a base directory containing the string; the failure would
   look like core example hooks mysteriously being active.

---

## 9. Which claim here, if false, would hurt most?

**F-12 — that all 87 bundled plugin hooks and events set `$active = true`, and
that the source-text regex and the real property agree in every one.**

Decisions 2 and 4 both rest on it. If it is wrong — if even one bundled plugin
hook has a spacing variant, or relies on the force-activation because it
declares `false` — then H.3 and H.8 stop being no-ops on code we ship and become
changes that break a plugin FOG itself distributes, which is precisely the
"needs editing = breaking change" line the brief draws.

It is a single `find | grep -L` and it is written down in the facts ledger. It
is also the claim most likely to rot: the next plugin someone writes is the one
that breaks it. That is the argument for putting the check in `tests/` as part
of H.0, not only in a document.

The runner-up is F-16 — that the force-activation is load-bearing. If it were
*not* (if `fileitems()`'s filter really did make it redundant), Decision 4 would
collapse into an obvious deletion with no risk at all. I proved it by experiment
rather than by reading, which is why it is stated as a positive result rather
than an absence.
