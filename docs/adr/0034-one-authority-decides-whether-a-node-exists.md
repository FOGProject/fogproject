# One authority decides whether a node exists

## Status

accepted, and implemented on `working-1.6` by deleting the known-node guard
from `FOGPage::buildMainMenuItems()`.

## Context

`?node=impersonate` rendered its page and then landed the user back on the
dashboard. No status code, no message, nothing in any log. On screen it was
indistinguishable from a link that does nothing.

Three things in the request path claimed to know which nodes exist:

| where | how it knew | used for |
|---|---|---|
| `FOGPageManager::loadPageClasses()` | a `*.page.php` declares `public $node` | dispatch |
| `FOGPage::buildMainMenuItems()` | sidebar keys + a hardcoded escape list | redirecting |
| `Authorization::EXEMPT_NODES` | a hand-written constant | which permission applies |

The first is the only real one: a node exists precisely when a page class
declares it. The second was a hand-maintained approximation of the first, and
its escape list was

    ['home', 'logout', 'hwinfo', 'client', 'schema', 'ipxe']

Five of those six are also `EXEMPT_NODES` entries, written out a second time.
`login` was already missing from the copy. `impersonate` was the seventh
exempt node, has no sidebar entry by design — it lives under the logout
control, exactly as `logout` does — and so was missing too.

**The failure mode is what makes this worth an ADR rather than a one-line
fix.** The guard was written when the menu was built from
`Page::__construct()`, i.e. *before* dispatch. The menu build later moved to
page-render time so that AJAX requests stop building a menu they discard,
which put the guard *after* dispatch. A guard that runs after the page has
already echoed itself into the output buffer cannot prevent anything — it can
only throw away a page that dispatch had accepted. It stopped being a guard
and became a way to lose a working page silently.

`FOGPageManager::render()` already says this in a comment: *"Dispatch owns the
unknown-node case."* The handling was moved there; the old guard was left
behind.

## Decision

**Delete the guard.** `FOGPageManager::render()` redirects a node with no
registered page class, and `FOGBase::redirect()` ends in `exit`, so nothing
unknown ever reaches the menu builder. The second check was unreachable for
the case it was written for and harmful for every other.

**A node exists if and only if a page class declares it.** Nothing else may
answer that question. The sidebar decides what is *offered*; permissions
decide what is *allowed*; neither decides what *exists*.

Ordering, which is what makes the above true rather than aspirational —
`management/index.php`:

```php
$Page->startBody();
$FOGPageManager->render();          // dispatch: unknown node -> redirect + exit
$Page->...->endBody()->render();    // menu build, necessarily after
```

and the AJAX arm never builds a menu at all.

## Consequences

**A node with no sidebar entry now works.** That was always meant to be a
supported shape — `logout` is the long-standing example, and `hwinfo`,
`client` and `schema` are others. It needed a name in two places and nothing
said so.

**`PluginManagement::sidebarAjax()` can no longer redirect.** It calls
`buildMainMenuItems()` directly to refresh the sidebar after a plugin change.
Redirecting an XHR to the dashboard was never right there; it merely never
fired, because that page's own node is always known.

**Rejected: derive the escape list from `Authorization::exemptNodes()`.** This
was built first and it works — it fixes the bug and removes the duplication.
It was thrown away because it keeps a second authority alive and merely makes
it agree today. The guard would still run after dispatch, still be able to
discard a rendered page, and still need to be right about something dispatch
already knows. Removing the check is strictly smaller than making it correct.

**Rejected: keep the guard and move the menu build back before dispatch.**
That restores the ordering the guard needs, at the cost of the change that put
it here: every AJAX request would again build a full main menu — firing
`MAIN_MENU_DATA` and `DELETE_MENU_DATA` across fifteen listeners — and discard
it.

`tests/one-authority-for-which-nodes-exist.test.php` pins ownership rather
than the list: dispatch has the unknown-node arm, and nothing in `FOGPage.php`
turns a node away.
