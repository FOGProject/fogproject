# Hook and Event subsystem: scan and propose

**Scan and propose only. Do not write implementation code. Do not open a PR.**

Read `docs/refactor-facts.md` first; it outranks this file.

---

## Why this is different from the phase work

The hook system is the plugin ABI. Every bundled plugin and every third-party
plugin registers through `EventManager::register()` and is dispatched through
`HookManager::processEvent()`. ADR 0013 promises plugin authors that code
written against 1.6 keeps working for all of 1.6.

If this goes out wrong, every plugin on every server breaks at once, and the
symptom is silent: a hook that fails to register logs a line and returns
normally. Nobody finds out until a feature quietly stops happening.

So the bar here is higher than "the tests pass." The bar is: **no plugin, core
or third-party, needs editing.** Anything that would require a plugin author to
change a line is not a refactor, it is a breaking change, and it needs to be
identified as one and decided on separately.

---

## Goals, in priority order

1. **Correctness.** Several things below are wrong today, not merely ugly.
2. **The contract stays.** `register()`, `processEvent()`, `notify()`,
   `hasListeners()` keep working for existing callers.
3. **Efficiency, measured not assumed.** I want this lean in memory and code,
   but do not optimize on instinct. Measure what it costs now (how many hook
   and event objects are constructed per request, how much of that a typical
   request ever dispatches) and report it before proposing anything that trades
   clarity for speed. If the current cost is already small, say so and drop
   this goal.

---

## What I have observed

These are observations, not a specification of the fix. Verify each one
independently before relying on it; if any is wrong, say so.

`packages/web/lib/fog/eventmanager.class.php`,
`hookmanager.class.php`, `event.class.php`, `hook.class.php`.

**1. `load()` distinguishes the two managers with two sequential `instanceof`
checks** (`eventmanager.class.php:218-225`). `HookManager extends EventManager`,
so a HookManager satisfies both branches. The correct file extension and
directory are reached only because the second assignment overwrites the first.
Reordering the two blocks changes behavior.

**2. Whether a class is active is decided by a regular expression run over the
file's source text**, line by line (`eventmanager.class.php:248-252`), matching
`$active\s?=\s?true;`. The class is never loaded and the property is never read
at that point. `\s?` is zero-or-one, so spacing variations do not match, and the
scan does not distinguish code from comments.

**3. `register()` branches on `self::shortName($this)`** with a case per
subclass and a `default` arm that throws
(`eventmanager.class.php:71-103`). The parent enumerates its children by name.
The throw is caught by the surrounding handler and written to the log
(`:105-122`), so a failed registration returns normally and the caller cannot
tell. The comment above the switch records that this shape already caused a
total registration failure once during the namespace migration.

**4. `HookManager` inherits `notify()`**, which iterates listeners as objects
(`$element->active`, `$element->onEvent()`) while `HookManager` stores them as
`[$object, 'method']` arrays. Determine whether anything reaches it.

**5. `notify()` treats "no listeners registered" as an exception**
(`eventmanager.class.php:175-177`) that is caught, logged, and returns false.
`processEvent()` handles the same condition with a bare `return`
(`hookmanager.class.php:89-91`). Same situation, two behaviors, one of which
writes a log line on what is probably the common case.

**6. `processEvent()` force-activates any listener whose file path contains the
substring `plugins`** (`hookmanager.class.php:103-105`), regardless of its
`active` property. Note before concluding anything: `FOGBase::fileitems()`
builds `$regex_pgrep` from `self::$pluginsinstalled`
(`fogbase.class.php:3648-3654`), so plugin files appear to be filtered to
installed plugins before reaching this point. Establish whether that makes the
force-activation redundant, or whether there is a path where it is the only
thing making a listener run.

**7. `Hook extends Event`.** Assess whether a hook is genuinely a kind of event,
or whether the two are peers with different dispatch contracts. `processEvent()`
merges arguments and calls a method that can mutate them through references
embedded in the payload; `notify()` calls `onEvent()` and discards the result.

---

## Required before you propose anything

**Inventory the contract.** Every call site of `register()`, `processEvent()`,
`notify()` and `hasListeners()` in `fogproject` AND in `fog-plugins`. What
shapes of listener are actually passed, what payloads are actually mutated. The
contract is what the callers do, not what the docblocks say.

**Characterize current behavior before changing it.** Write tests that pin what
happens today, including the parts that are wrong. Then a behavior change shows
up as a test that has to be edited, deliberately, rather than as a silent
difference.

**For each defect, determine whether anything currently depends on it.** This is
the part I care most about. Two concrete examples:

- If activation stops being a source-text regex and starts reading the property,
  a hook whose property is `false` but whose file contains the literal string
  somewhere (a comment, an example) is active today and would go inactive.
  Search `fog-plugins` and core for any file where the regex result and the real
  property value disagree.
- If the path-substring force-activation is removed, any plugin hook whose
  `$active` is not `true` becomes inactive. Search `fog-plugins` for hooks that
  do not set it true.

Report what you find. Do not assume the answer is zero.

**Say what a third-party plugin cannot see.** `fog-plugins` is the plugins I
ship. Servers run plugins I have never read. Any change whose safety argument
depends on inspecting plugin source is weaker than one that does not, and I need
that distinction stated explicitly for each proposal.

---

## What to bring back

A plan in the same form as the phase plans: `VERIFIED` / `INFERRED` / `UNKNOWN`
throughout, commit-by-commit with the tree green after every commit, a shell
command per claim, alternatives considered and rejected, and the blast radius of
each step for plugin authors.

Plus, and separately from the plan:

1. **The defect list as you found it**, including anything above that turns out
   to be wrong or that you found and I did not.
2. **The decisions that are mine, not yours.** Where fixing a defect would
   change behavior something might rely on, do not pick. Present the options
   and what each costs. Bugs that are load-bearing for somebody are not
   automatically bugs to fix.
3. **The measurement**, per goal 3 above.
4. **Whether this warrants an ADR.** It changes an ABI that ADR 0013 made
   promises about, so my instinct is yes, but argue it.

Ask me questions before planning if the code does not answer something.

**End with: which claim in this plan, if false, would hurt most?**
