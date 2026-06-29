# AdminLTE 2 → 4 / Bootstrap 3 → 5 migration

**Status:** in progress
**Branch:** `feature-adminlte4-migration`

This is the working plan for moving FOG's web UI from AdminLTE 2 / Bootstrap 3 to
**AdminLTE 4 / Bootstrap 5**, with native dark mode, fixing pages in place.

> Lives in tracked `docs/` (not `docs/adr/`, which is gitignored and reserved
> for local-only working notes) so it can travel on the migration branch.

## Decisions (locked)

| Fork | Decision | Consequence |
|---|---|---|
| jQuery | **Keep it loaded** alongside BS5 | Select2, DataTables, `$.apiCall`, ~200 JS files depend on it. We adopt BS5 CSS + native component JS, not a de-jQuery rewrite. |
| Modals (116 `.modal()` calls) | **jQuery `$.fn.modal` shim** → `bootstrap.Modal` | Call sites stay nearly untouched; lowest risk. |
| Date/time picker | **Tempus Dominus 6** | bootstrap-datetimepicker (Eonasdan, BS3-era) has no BS5 version and is abandoned. 4 call sites rewritten; needs Popper. |
| Dark mode | **BS5 native `data-bs-theme="dark"`** + AL4 built-in | Retires the custom dark-mode SCSS hack. |

## Vendored libraries (pinned)

| File | Version | Source |
|---|---|---|
| `css/bootstrap5.min.css`, `js/bootstrap5.bundle.min.js` | Bootstrap 5.3.3 | jsdelivr `bootstrap@5.3.3` (bundle includes Popper) |
| `css/adminlte4.min.css`, `js/adminlte4.min.js` | AdminLTE 4.0.0 | jsdelivr `admin-lte@4.0.0` |
| `css/tempus-dominus.min.css`, `js/tempus-dominus.min.js` | Tempus Dominus 6.9.4 | jsdelivr `@eonasdan/tempus-dominus@6.9.4` |
| `css/select2-bootstrap-5-theme.min.css` | 1.3.0 | jsdelivr `select2-bootstrap-5-theme@1.3.0` |
| `css/datatables.bootstrap5.min.css`, `js/datatables.bootstrap5.min.js` | DataTables BS5 integration (2.0.8) | datatables.net |

The new files use versioned names so they coexist with the BS3/AL2 set until the
foundation flip. Old assets (`bootstrap.min.*`, `AdminLTE.min.css`,
`adminlte.min.js`, `adminlte-skins.min.css`, `bootstrap-datetimepicker.*`) are
removed only when the flip lands and nothing references them.

Notes:
- AL4 v4.0.0 `adminlte.min.js` is self-contained; OverlayScrollbars is optional
  (only used if present) — not vendored unless a need surfaces.
- Tempus Dominus 6 needs Popper. The BS5 *bundle* includes Popper internally but
  does not expose a `Popper` global, so TD's Popper wiring is verified at the
  picker-swap step (vendor `@popperjs/core` standalone if TD's bundle doesn't
  self-contain it).

## Where the work lives (measured)

### Centralized leverage points (fix once, many pages follow)

- **`formFields()`** (`fogpage.class.php:4242`) — horizontal `form-group` row →
  BS5 `row mb-3`; label column class comes from caller's `$labelClass`.
- **`tabFields()`** (`fogpage.class.php:4287`) — AdminLTE `nav-tabs-custom` block
  → BS5/AL4 card-tabs (`nav-item`/`nav-link`, drop `caret`, dropdown restructure).
- **`renderCreateForm()`/`renderAddForm()`/`renderAddModalForm()`**
  (`FOGPageRender.class.php:282/339/392`) — `box-solid`/`box-primary` scaffolds →
  `card`.
- **`makeButton`/`makeSplitButton`/`makeModal`** (`fogpage.class.php:946/992/1058`)
  — button + modal markup (`close`→`btn-close`, `data-*`→`data-bs-*`).
- **`stripedTable()`** (`fogpage.class.php:4263`) — table markup.
- **Field helpers** `makeInput`/`makeLabel`/`makeTextarea`/`makeFormTag`/
  `makeInfoTooltip` are **class-agnostic** (CSS class is the caller's argument) —
  near-zero change.

### App shell (global, must precede pages)

- **`management/other/index.php`** — full HTML shell (body class, header,
  content-wrapper). AL4 restructured the layout.
- **`fogpage.class.php::_buildMenuStructure`** (~line 542) — sidebar treeview.
  AL4 renames sidebar classes (`treeview`→`nav-item has-treeview`,
  `treeview-menu`→`nav nav-treeview`, `sidebar-menu`→`nav nav-pills nav-sidebar`,
  `pull-right`→`float-end`/`nav-icon`). Must keep the AJAX server-chrome refresh
  (ADR 0004) treeview delegation working.
- **`page.class.php`** — the `$javascripts`/`addCSS()` asset list (lines ~97-148)
  and the AJAX content branch (case 1, `content-header`/`content`).

### JS coupling to BS5 (the new cost vs BS4)

| Marker | Count (FOG source) | Action |
|---|---|---|
| `.modal(` jQuery calls | 116 | shim — sites unchanged |
| `.tooltip(` / `.tab(` | 6 / 1 | shim |
| `data-toggle` | 48 (13 files) | → `data-bs-toggle` |
| `data-dismiss` | 30 (12 files) | → `data-bs-dismiss` |
| `data-target` | 8 | → `data-bs-target` |
| bootstrap-datetimepicker | 4 files | → Tempus Dominus 6 |

### Markup long tail (per page/plugin)

| BS3/AL2 literal | BS5/AL4 | files | occurrences |
|---|---|---|---|
| `box box-*` chrome | `card` | 85 pages + 29 plugins + 8 helpers | ~138 |
| `control-label` | `col-form-label` | 40 | 99 |
| `pull-right` | `float-end` | 46 | 142 |
| `pull-left` | `float-start` | 30 | 74 |
| `form-group` (removed) | `mb-3` (+ `row`) | — | 14 |
| `input-group-addon` | `input-group-text` | 5 | 7 |
| `btn-default` | `btn-secondary` | — | 8 |
| `close` (modal) | `btn-close` | — | 37 |
| `help-block` | `form-text text-muted` | 11 | 39 |

(`badge-*`→`badge bg-*`, `sr-only`→`visually-hidden`, `ml-*/mr-*`→`ms-*/me-*`
where they appear.)

## Sequencing

BS5 breaks shared infrastructure (modals, picker, `data-bs-*`, the AL4
shell/sidebar), so this is **foundation-first, then pages** — it cannot be done
purely page-by-page.

1. **Vendor libraries** (done) — additive, versioned filenames.
2. **Foundation flip** (one coordinated change; UI broken until done, then runs
   on AL4 with rough pages):
   - swap asset list in `page.class.php`; bump `FOG_BCACHE_VER`
   - rebuild shell (`other/index.php`) + sidebar (`_buildMenuStructure`) for AL4
   - add modal shim
   - swap datetimepicker → Tempus Dominus 6
   - native dark mode (`data-bs-theme`), retire custom dark-mode SCSS
   - `data-*` → `data-bs-*` sweep
   - migrate centralized helpers (`formFields`/`tabFields`/`renderCreateForm`/
     `makeButton`/`makeModal`/`stripedTable`)
3. **Pages inline** — box→card + class-literal sweep, page by page, with a smoke
   test per archetype:
   - List/table — Host list
   - Tabbed edit — Host edit
   - Create/add — Image add
   - Heavy config — FOG Configuration
   - Dashboard — info-boxes + charts
   - Report — tables + boxes
   - Plugin — Location

## Operational notes

- Multi-session work; commit each chunk on the branch.
- The checkout is shared across Claude instances — the tree is half-migrated for
  a while. A dedicated git worktree (or pausing other instances) avoids conflicts.
- Deploys are user-triggered (`copybacktrunk.sh`); do not run them here.
- `FOG_BCACHE_VER` must bump whenever vendored CSS/JS or `fog-default-ui` change.
