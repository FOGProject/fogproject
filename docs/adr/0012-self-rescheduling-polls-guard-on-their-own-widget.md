# A self-rescheduling poll guards on its own widget

## Status

accepted

## Context

FOG's UI navigates over AJAX. `doPageLoad()` in `fog.common.js` fetches the new
page, replaces the body with `ajaxPageWrapper.empty().html(data)`, and tears down
what the old page left running. The teardown is `clearAllIntervals()`, which
works because `fog.common.js` wraps `window.setInterval` and pushes every id it
hands out into an `intervals[]` array.

**Nothing does the equivalent for `setTimeout`, and nothing can.** `setTimeout`
is not only how polls are written; it is also how debounces, next-tick
deferrals and one-shot delays are written. There are 20-odd calls in
`management/js/fog` and the large majority are exactly those. A central
`clearAllTimeouts()` in `doPageLoad()` would cancel a debounce mid-flight and a
deferred layout pass before it ran, and the resulting breakage would be
intermittent and invisible.

So a widget that polls by rescheduling itself from an ajax `complete` handler
**outlives the page that started it**, and every visit starts another chain that
runs for the life of the tab. Nothing errors. The requests answer 200. The chart
they were fetching for is not in the document, so the draw is a no-op against a
selector that matches nothing.

### What it cost

Measured on the 1.6 lab: one visit to the dashboard, then five and a half
minutes sitting on Host Management, produced **122 requests**. The bandwidth
chart is the expensive one — it polls every 2.5s, so roughly 24 requests a
minute, per dashboard visit, indefinitely. Ten dashboard visits in a working day
leaves ten chains running.

The only visible symptom was `?node=home&sub=diskusage` returning **400** once
every five minutes. `$('.nodeid')` is not on the page either, so jQuery drops the
`undefined` id, and `DashboardPage::diskusage()` reports a missing node id with
the same `"Node is unavailable"` message it uses for an offline node. The console
blamed a storage node for a poll that should never have been running.

That is the general shape: the wasted traffic is silent, and the one error you
do see points somewhere else entirely.

### It had already been solved four times

By the time this was written down, four of the five poll chains in the tree
carried the guard, each discovered independently and each spelled differently:

| Chain | Cadence | Guard as written |
|---|---|---|
| `dashboard/fog.dashboard.js` (×4 charts) | 2.5s / 5m | `alive(SEL)` → `document.querySelector(sel)` |
| `task/fog.task.list.js` | 5s | `document.body.contains(initialTabEl)` |
| `image/fog.image.multicast.js` | 5s | `document.body.contains(sessionTable[0])` |
| `about/fog.about.logviewer.js` | 10s | `document.body.contains(logsGoHere[0])` |

Three of the four comments independently explain that `clearAllIntervals()` only
covers intervals. Four authors rediscovering the same platform behavior, and one
of them only after measuring 122 stray requests, is the argument for the rule
being stated once rather than re-derived.

The fifth chain, `schema/fog.schema.js`, has no guard **and does not need one**:
it polls `status/dbrunning.php` only while the database is down, aborts and
clears itself the moment the database answers, and while the database is down
there is no working UI to navigate away to. Left as it is.

## Decision

**Any `setTimeout` chain that repeats must check that its own widget is still in
the document, and return without rescheduling when it is not.**

The check goes at the top of the polling function, before the request:

```js
function poll() {
    if (!alive(SEL)) {
        return;
    }
    $.ajax({
        // ...
        complete: function() {
            timer = setTimeout(poll, POLL_MS);
        }
    });
}
```

Returning early suppresses the request **and** ends the chain, because the
reschedule lives in the `complete` handler that then never runs. Do not write
the reschedule before the guard.

Either spelling is fine — `document.querySelector(sel)` when the widget is
identified by a selector, `document.body.contains(el)` when a jQuery object or
element reference is already in scope. Both answer the same question. Consistency
inside one file matters; consistency across files does not.

The guard is checked **per widget, not centrally**. A page with four charts gets
four guards on four selectors.

## Consequences

Chains are self-healing. A chain ends the first time it wakes up on a page that
no longer has its widget, so there is no teardown to register, nothing to
unregister, and no way to leak a registration.

Chains restart normally on revisit. The page script re-executes on every AJAX
navigation — see the load-once bucket in `Page::$onceJavascripts`, and note that
re-execution is deliberate — so returning to the dashboard starts fresh chains
against the fresh DOM.

There is a one-cycle tail. A chain already in flight when you navigate away will
complete that request and draw into nothing before the next wake-up stops it.
That is one request, not an unbounded stream, and eliminating it would require
the central cancellation this ADR rejects.

`setInterval` needs none of this. It is already tracked and canceled. This ADR
is not a reason to convert intervals to timeout chains, and if a poll can be
written as an interval, prefer it.

## Alternatives considered

**Track timeouts centrally, like intervals.** Wrap `window.setTimeout` the way
`setInterval` is wrapped and clear the lot in `doPageLoad()`. Rejected: the
wrapper cannot distinguish a poll from a debounce or a deferral, so it would
cancel legitimate one-shots that are supposed to survive the tick they were
scheduled in. The failure would be intermittent, silent, and spread across every
page in the product — a strictly worse bug than the one being fixed.

**Give `doPageLoad()` a teardown registry that pages opt into.** Each page
registers a cleanup callback; the navigation runs them. Rejected as more
machinery for no more safety: a page that forgets to register is exactly the
page that forgets the guard, and the registry adds a way to leak the
registration itself. The guard has no bookkeeping to get wrong.

**Cancel the chain from the navigation by convention** — expose the timer id and
have `doPageLoad()` clear known ones. Rejected: it puts knowledge of every
widget's internals into the navigation code, and gets stale silently the moment
a widget is added.
