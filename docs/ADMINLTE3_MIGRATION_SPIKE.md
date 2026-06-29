# Bootstrap 3→4 / AdminLTE 2→3 migration — spike scope

**Status:** proposed (spike scope — not yet executed or decided)
**Branch:** `feature-adminlte3-migration-spike`

This document is the **scope** for a throwaway spike. It records what the
upgrade would touch, the measured blast radius, and the smallest proof-of-concept
that would tell us the true cost before committing to the full migration. No
production code is changed by this document.

> Lives in tracked `docs/` (not `docs/adr/`, which is gitignored and reserved
> for local-only working notes) so it can travel on the spike branch.

## Context

### Where the frontend stack actually sits

| Library | Vendored | Latest | Gap |
|---|---|---|---|
| jQuery | **3.7.1** | 3.7.1 | none — already current |
| DataTables | **2.0.8** + Scroller 2.4.3 | 2.x | effectively current |
| Select2 | 4.0.13 | 4.1.0 | minor |
| Bootstrap | **3.3.7** | 5.3 | two majors |
| AdminLTE | **2.4.0** | 4.0 | two majors |

jQuery and DataTables — usually the riskiest parts of a frontend
modernization — are already current. **The entire cost is concentrated in
Bootstrap 3→4, which is what AdminLTE 3 is built on.** (jQuery 1.x banner
strings show up in a couple of old vendored plugins; core jQuery is 3.7.1.)

AdminLTE 3 / Bootstrap 4 is the pragmatic target precisely because it keeps
jQuery. AdminLTE 4 / Bootstrap 5 drops jQuery entirely, which would turn this
into a JS rewrite across ~200 jQuery-coupled files — out of scope here.

### Surprising finding #1 — the field helpers are class-agnostic

`makeInput`, `makeLabel`, `makeTextarea`, `makeFormTag`, `makeInfoTooltip`
(in `lib/fog/fogpage.class.php`) take the CSS class as the **caller's** first
argument. They emit no hard-coded Bootstrap class. So `makeInput('form-control',
…)` carries the BS class at the call site, not in the helper. **These helpers
need essentially zero change for BS4.**

### Surprising finding #2 — box chrome is hand-rolled, not centralized

FOG uses AdminLTE **`box`** markup (not Bootstrap `panel`). In AdminLTE 3 every
`box*` class is renamed to `card*` (`box`→`card`, `box-header`→`card-header`,
`box-body`→`card-body`, `box-footer`→`card-footer`, `box-title`→`card-title`,
`box-tools`→`card-tools`, `with-border` dropped). Measured occurrences:

| marker | files | occurrences |
|---|---|---|
| `box box-` | 57 | 138 |
| `box-body` | 62 | 151 |
| `with-border` | 57 | 178 |
| `box-header` | 40 | 98 |
| `box-footer` | 41 | 85 |
| `box-primary` | 15 | 49 |

Where that markup lives:

| location | `box box-` count |
|---|---|
| `lib/pages/` (page classes) | 85 |
| `lib/plugins/` | 29 |
| `lib/fog/` (helpers) | 8 |
| `management/` (JS) | 4 |

**So box chrome is overwhelmingly hand-rolled in the individual page classes.**
The "fix the helpers and most pages follow" hypothesis is only half-true: it
holds for *forms and create-scaffolds*, not for the box containers on edit
pages, dashboards, and reports.

### What IS centralized (the real leverage points)

These methods, if migrated, fix a large slice of pages at once:

- **`formFields()`** (`fogpage.class.php:4242`) — wraps each label⇒field pair as
  `<div class="form-group"> … <div class="col-sm-9">`. BS4 needs
  `form-group row`. One-line structural change; the label column class is the
  caller's `$labelClass` (see below).
- **`tabFields()`** (`fogpage.class.php:4287`) — emits the AdminLTE
  `nav-tabs-custom` tab block. BS4 nav changes are real and centralized here:
  `.active`/`li` → `.nav-link.active`/`a`, items need `nav-item`/`nav-link`,
  `caret` removed, dropdown structure changes. One method.
- **`renderCreateForm()`** (`FOGPageRender.class.php:282`) — the
  `box-solid` + `box-primary` create-form scaffold. `box*`→`card*`. One method,
  covers every `add()` page.
- **`makeButton` / `makeSplitButton` / `makeModal`** — button + modal markup.
- **`stripedTable()`** — minor table markup.

### Surprising finding #3 — the long tail is class-literals at call sites

The wide, un-centralized work is find-and-replace of BS3 class literals scattered
across pages/plugins/JS:

| BS3 literal | BS4 replacement | files | occurrences |
|---|---|---|---|
| `control-label` | `col-form-label` | 40 | 99 |
| `pull-right` | `float-right` | 46 | 146 |
| `pull-left` | `float-left` | 30 | 75 |
| `col-sm-*` (grid in forms) | mostly unchanged in BS4 | 43 | 125 |
| `help-block` | `form-text text-muted` | 11 | 39 |
| `btn-default` | `btn-secondary` | 8 | 21 |
| `input-group-addon` | `input-group-text` (+ prepend/append wrap) | 5 | 8 |

`col-xs-*` is nearly unused (3 files / 5 occurrences) — trivial.

The label-column class (`'col-sm-3 control-label'`) is passed in as a
`$labelClass` variable at ~40 sites; most pages set it once near the top, so the
99 `control-label` hits collapse to far fewer edits in practice.

### Out of scope (do not touch in the spike)

- jQuery, DataTables/Scroller (already current).
- The dark-mode SCSS and F1–F4 visual passes — they are authored against
  BS3/AdminLTE-2 variables and class names and would be **re-authored** after the
  markup migration lands, not during the spike.
- Any move toward Bootstrap 5 / AdminLTE 4 (jQuery removal).
- FOG 2.0 (`fog-node`) is a separate rewrite; this migration is for 1.6 only.

## Decision (proposed spike)

Run a **time-boxed, throwaway** proof-of-concept on
`feature-adminlte3-migration-spike` in this order, stopping after step 4 to
measure before committing to the long tail:

1. **Swap vendored assets** — drop in Bootstrap 4 CSS/JS, AdminLTE 3, and the
   Select2 BS4 theme; bump `FOG_BCACHE_VER`. Everything will look broken; that's
   expected and is the baseline.
2. **Migrate the centralized helpers** — `formFields`, `tabFields`,
   `renderCreateForm`, `makeButton`/`makeSplitButton`/`makeModal`,
   `stripedTable`. Field helpers (`makeInput`/`makeLabel`/…) need no change.
3. **Add a compatibility shim (decide during spike)** — a small CSS shim mapping
   surviving `box*`/`pull-*`/`control-label` to their BS4 equivalents lets pages
   render acceptably *before* their literals are migrated, so the long tail can
   be done page-by-page instead of big-bang. Whether the shim is worth keeping is
   itself a spike finding.
4. **Smoke-test one page of each archetype** and record what breaks that the
   helpers + shim did *not* cover. Archetypes:
   - List/table page — Host list (DataTables + box wrapper)
   - Tabbed edit page — Host edit (`tabFields`, multiple `box-primary`, forms)
   - Create/add page — Image add (`renderCreateForm` + `formFields`)
   - Heavy config page — FOG Configuration (hand-rolled boxes/forms/tabs)
   - Dashboard — info-boxes + charts
   - Report page — tables + boxes
   - One plugin page — Location (confirms plugin parity)

Deliverable of the spike: a true per-archetype defect count and a go/no-go
estimate for the full migration (the long-tail page/plugin sweep + dark-mode
re-author + QA pass).

## Why

- **Measure before committing.** The expensive part (steps beyond 4 — the
  ~114 hand-rolled box sites across pages+plugins, the class-literal sweep, and
  re-authoring the custom CSS/dark-mode) is broad and un-automatable. The spike
  buys a real defect count from the archetypes cheaply, so the weeks-long
  decision is made on data, not a guess.
- **The helpers are the cheap, high-leverage start.** Forms and create-scaffolds
  are genuinely centralized; field helpers are class-agnostic. Proving those out
  first front-loads the easy wins and isolates the true cost (the hand-rolled
  box chrome) for honest estimation.
- **Throwaway by design.** This branch is not intended to merge as-is; it exists
  to produce the estimate. The real migration, if greenlit, would be sequenced
  page-by-page on its own branch with QA on every screen.

## Open questions for the spike to answer

1. Is the CSS compatibility shim (step 3) good enough to defer the box-literal
   sweep, or does AL2→AL3 box structure differ enough that pages must be touched
   directly?
2. Does Select2 4.0.13 ride the BS4 theme cleanly, or is a bump to 4.1.0 needed?
3. How much of the dark-mode SCSS survives vs. needs re-authoring against BS4
   variables?
4. Do the AdminLTE 3 sidebar/treeview and the AJAX server-chrome refresh
   (ADR 0004) still cooperate, or does the nav rewrite break delegation?
