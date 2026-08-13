# Server-rendered chrome is refreshed via a second fragment request, not rebuilt in-place

## Status

accepted

## Context

The persistent page chrome — the sidebar menu, its "PLUGIN OPTIONS" section, the
top navigation — is **server-rendered once per full page load** in
`management/other/index.php`. FOG's pjax-style navigation (`.ajax-page-link` →
`?contentOnly=1`) only swaps `#ajaxPageWrapper`; it never re-renders the chrome.
So when an action changes what the chrome should show (the first case: installing,
activating, deactivating, or removing a plugin on the Plugin Management page,
#852), the change is invisible until a manual full-page reload.

Two boot-time facts make this harder than it looks:

- **Menu state is fixed at boot.** Plugin menu items are added by each plugin's
  `add*menuitem.hook.php` via the `MAIN_MENU_DATA` hook, and that hook only
  registers if the plugin is in `self::$pluginsinstalled` — a snapshot taken in
  `FOGCore::setEnv()` at boot, *before* the current request's mutation ran. Hooks
  cannot un-register. So rebuilding the menu **inside the mutating request** sees
  stale state; it cannot reflect the change just made.
- This is **not** a caching problem. `getActivePlugins()` reads plugin state live
  from the DB each request; the settings cache (#849, ADR 0003) does not gate it.
  A plain browser reload already shows the change — the gap is purely that the SPA
  never re-renders the chrome.

## Decision

To refresh server-rendered chrome after an AJAX state change, do **all** of:

1. **Fetch a rebuilt fragment in a separate request** after the mutation
   completes. Reuse the same server-side build path the full page uses
   (e.g. `FOGPage::buildMainMenuItems()`), so the fragment is byte-identical to
   what a reload renders. A fresh request re-runs `setEnv()` and re-registers
   hooks, so state is current.
2. **Swap inner HTML, keep the delegating ancestor.** Replace only the inside of
   the changed container (e.g. `.plugin-options`) and leave the
   `<ul data-widget="tree">` in place. AdminLTE's treeview handler is **delegated**
   on that `<ul>` (`_setUpListeners` → `$(ul).on('click', '.treeview a', …)`), so
   expand/collapse on swapped-in items keeps working with no re-init.
3. **Delegate click bindings that must survive a swap.** `.ajax-page-link` was
   directly bound at `$(document).ready`; injected links lost it and full-reloaded.
   It is now `$(document).on('click', '.ajax-page-link', …)`.

The fragment endpoint pattern: a sub needs **both** an empty `foo()` placeholder
(otherwise the dispatcher's `!method_exists($class, $sub)` check falls back to
`index()`) **and** a `fooAjax()` that emits the markup. The dispatcher appends
`Ajax` for XHR requests (`X-Requested-With: xmlhttprequest`) and gates auth +
CSRF there. A GET fragment is not state-changing, so
`CSRF::requireForStateChanging()` passes.

## Why

- **The second request is not avoidable**, given boot-fixed hook registration and
  the `pluginsinstalled` snapshot. Trying to rebuild in the mutating request is the
  obvious-looking approach and is wrong — it silently renders stale chrome.
- **Reusing the full-page build path** is the correctness guarantee: the fragment
  is defined as "what a reload would show," not a parallel reimplementation that
  can drift.
- **Inner-HTML swap + delegation** avoids re-initializing third-party widgets
  (AdminLTE tree, etc.) and rebinding handlers by hand — the fragile part of any
  in-place SPA update. Replacing the delegating ancestor itself would break it.
- A full `window.location.reload()` was the cheaper alternative and was rejected:
  the project is deliberately AJAX-first, and these are exactly the surfaces where
  a reload flash is most jarring.

## Note

First applied to the plugin sidebar (#852): `pluginmanagement.page.php`
(`sidebar()` + `sidebarAjax()`), `js/fog/plugin/fog.plugin.list.js`
(`refreshSidebar()` on the four state-changing actions, **not** the schema-update
actions, which don't change the menu), `js/fog/fog.common.js` (the delegation
change). This ADR is the convention for any future chrome refresh; the delegation
change in `fog.common.js` is global, so new injected `.ajax-page-link`s already
work without further wiring.
