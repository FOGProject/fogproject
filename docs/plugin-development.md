# Building a FOG Plugin — Start to Finish

This guide walks you from an empty directory to a working, installable FOG
plugin on the **working‑1.6** framework. It uses a complete, runnable example
plugin — **`helloworld`** — which lives in
[`FOGProject/fog-plugins`](https://github.com/FOGProject/fog-plugins) and
lands at `packages/web/lib/plugins/helloworld/` once the plugins are fetched.
Copy that directory, rename it, and you have a head start.

> The bundled plugins are no longer committed to this repository. A fresh
> clone has no `packages/web/lib/plugins/` at all until `bin/fetch-plugins.sh`
> populates it from the release `FOG_PLUGINS_VERSION` pins — which the
> installer does for you. Run it by hand if you just want the tree.

> **Scope:** this targets the working‑1.6 plugin framework (the `formFields` /
> `makeInput` page helpers, the `addPost`/`editPost` JSON pattern, and the
> non‑destructive `schema()` migration contract). The 1.5.x line renders pages
> differently (raw HTML strings, `*page.class.php` file names) and lacks the
> `schema()` migration mechanism; this guide does not cover it.

---

## 1. What a plugin is

A FOG plugin is just a directory containing PHP classes that FOG
auto‑discovers. There is no build step and no registration list to edit — drop
the directory in, activate the plugin in the UI (**Plugin Management**), and it
works.

There are two places that directory can live, and which one you pick matters:

| Root | For | Survives a FOG upgrade? |
|---|---|---|
| `packages/web/lib/plugins/<name>/` | plugins bundled with FOG itself, sourced from `FOGProject/fog-plugins` | No — the tarball re‑lays this tree |
| `/opt/fog/plugins/<name>/` (`FOG_PLUGIN_DIR`) | **everything third‑party** | Yes |

The installer's `configureHttpd()` does `rm -rf` on the web root before laying
the new one down, so a plugin installed into `lib/plugins/` is deleted by the
next `installfog.sh` run without warning. `FOG_PLUGIN_DIR` sits outside the web
root precisely so that cannot happen. See
[ADR 0009](adr/0009-plugins-become-installable-artifacts.md).

Bundling is not a route third parties take. `lib/plugins/` is filled from a
pinned `fog-plugins` release, so putting a plugin there means opening a PR
against that repository and waiting for FOG to pin a release containing it.
Ship your own archive instead — see §11a.

Discovery, the class autoloader and the routing all treat the two roots
identically; the only difference an external plugin sees is that its `js/`,
`css/` and `images/` are reached through a symlink FOG maintains for it (see
§10).

A typical plugin provides:

- a **model** (one entity / table row),
- a **manager** (the table + its migrations),
- a **page** (the UI and its form POST handlers),
- **hooks** (menu entry, JS injection, API exposure, …),
- **JS** files (one per sub‑page).

The running example, `helloworld`, manages a trivial entity with a `name` and a
`description`, end to end.

---

## 2. Mental model (how the pieces connect)

- **Boot chain.** Every entry point loads `commons/base.inc.php` →
  `commons/init.php` → `LoadGlobals`, which sets the shared singletons
  (`FOGBase::$DB`, `$HookManager`, `$EventManager`, `$currentUser`).
- **Autoloader.** `Initiator` scans `BASEPATH` recursively, builds a
  lowercased‑basename → path map of every `*.{class,page,hook,event,report,task}.php`
  file, and registers that ahead of PHP's default `spl_autoload` (which stays
  registered as a fallback). Lookup **lowercases the class name** to find the
  file. So:

  > **The filename must be `strtolower(ClassName)` + the suffix.**
  > `class HelloWorldManagement` ⇒ `helloworldmanagement.page.php`.
  > `class AddHelloWorldJS` ⇒ `addhelloworldjs.hook.php`.

  (Class names in code are PascalCase; the files on disk are all‑lowercase.)

  **Namespaced spellings work too.** `FOG\Host` resolves to the same class as
  `Host` — the autoloader falls back to the short name and `class_alias`es the
  result. One class entry under two names, so `instanceof`, `new`, Reflection
  and `FOGBase::getClass()` all see a single type.

  Nothing in FOG is namespaced yet; this exists so plugin code written today
  survives the migration when it happens. Either spelling is correct for all of
  1.6, and bare `Host` is the one to use if your plugin must also run on 1.6
  betas before this landed. Two limits worth knowing: only the flat `FOG\<Name>`
  form is bridged (`FOG\Model\Host` deliberately does not resolve), and your own
  namespace is never touched — a `Vendor\Host` in your plugin stays yours and
  will not silently become core's `Host`.

  This does **not** change the filename rule above. `FOG\Host` is found by
  looking up `host`, so the file is still `host.class.php`.
- **Routing.** The whole UI is driven by `?node=<x>&sub=<y>&id=<n>`. `node` maps
  to a page class (`helloworld` → `HelloWorldManagement`, matched by its
  `public $node = 'helloworld'`), and `sub` maps to a method on it
  (`sub=add` → `add()`, `sub=addPost` → `addPost()`, `sub=list` → the inherited
  DataTables list).
- **ORM.** Models declare `$databaseTable` and `$databaseFields`
  (friendly‑name → column). You then use `get()/set()/save()/load()/destroy()`,
  or `new HelloWorld(42)` to auto‑load by id.
- **Hooks/events.** Cross‑cutting integration is done by registering callbacks
  against named events: `self::$HookManager->register('EVENT', [$this, 'fn'])`
  and firing with `processEvent('EVENT', ['data' => &$data])`.

---

## 3. Directory layout

```
<root>/helloworld/                  # <root> = lib/plugins (bundled) or /opt/fog/plugins
├── config/
│   └── plugin.config.php          # the manifest ($fog_plugin[...])
├── class/
│   ├── helloworld.class.php        # HelloWorld         (model, FOGController)
│   └── helloworldmanager.class.php # HelloWorldManager  (manager + schema())
├── pages/
│   └── helloworldmanagement.page.php  # HelloWorldManagement (FOGPage)
├── hooks/
│   ├── addhelloworldmenuitem.hook.php # menu entry + search/objects
│   ├── addhelloworldjs.hook.php       # JS injection
│   └── addhelloworldapi.hook.php      # REST API exposure
└── js/
    ├── fog.helloworld.list.js
    ├── fog.helloworld.add.js
    └── fog.helloworld.edit.js
```

The directory name **is** the plugin's machine name and routing `node`. Keep it
lowercase and use it consistently (`$fog_plugin['name']`, each hook's
`public $node`, the page's `public $node`).

---

## 4. Step by step

### 4.1 `config/plugin.config.php` — the manifest

`Plugin::readManifest()` `include`s this file during discovery and reads the
`$fog_plugin` array.

```php
$fog_plugin = [];
$fog_plugin['name']        = 'helloworld';           // == directory name
$fog_plugin['description'] = 'Skeleton example plugin …';
$fog_plugin['menuicon']    = 'fa fa-cube fa-fw';     // "fa …" => icon; else <img src>
$fog_plugin['version']     = '1.0.0';                // your plugin's own version
$fog_plugin['fog_min']     = '1.6.0';                // oldest FOG it runs on
$fog_plugin['fog_max']     = '1.7.0';                // newest FOG it runs on
$fog_plugin['requires']    = ['location'];           // other plugins, by directory name
$fog_plugin['author']      = 'Your Name';
$fog_plugin['homepage']    = 'https://example.org/my-plugin';
```

| Key | Required | What it does |
|---|---|---|
| `name` | yes | Machine name and routing `node`. Must equal the directory name, lowercase. |
| `description` | no | Shown in Plugin Management. |
| `menuicon` | no | Defaults to `fa fa-plug fa-fw`. |
| `version` | no | Yours to number. Shown in the grid, stored in `plugins.pVersion`. Nothing compares it to anything. |
| `fog_min` / `fog_max` | no | The FOG range you support. An absent bound is no bound. |
| `requires` | no | Plugins that must be active first. |
| `author`, `homepage` | no | Attribution. |

**Every key except `name` is optional**, so a plugin written before the manifest
existed keeps working untouched.

#### How the compatibility range is enforced

- Activating or installing a plugin outside its range is **refused**, with the
  reason in the error toast. The whole batch is refused, not just the offending
  plugin — a partial success reported as "Plugins activated!" is worse than a
  clean failure.
- If a FOG upgrade moves the server out of the range of a plugin that is
  already active, the next boot **deactivates it** and logs why. `installed` and
  `pSchema` are left alone, so its tables and applied migrations survive and
  re‑activating once you ship a compatible build is one click.
- Only the **numeric core** of a version is compared. `FOG_VERSION` is
  `1.6.0-beta.3318` on a beta and `version_compare()` sorts that *below*
  `1.6.0`, so comparing raw would refuse `fog_min = '1.6.0'` on the entire beta
  branch.
- Plugins turned on in the same batch satisfy each other's `requires`, so the
  order you tick the checkboxes in doesn't matter.

> `menuicon_hover` and `entrypoint` used to appear here. Nothing ever read
> either one — no plugin has ever shipped the `html/run.php` that `entrypoint`
> named, and routing goes through the `node` → page‑class mapping. Both are
> gone; if your manifest still sets them they are simply ignored.

### 4.2 Model — `class/helloworld.class.php`

```php
class HelloWorld extends FOGController
{
    protected $databaseTable = 'helloWorld';
    protected $databaseFields = [
        'id'          => 'hwID',
        'name'        => 'hwName',
        'description' => 'hwDesc',
    ];
    protected $databaseFieldsRequired = ['name'];
}
```

That's the entire ORM contract. `$databaseFields` maps friendly names (used in
code and in the API) to real column names. `$databaseFieldsRequired` is enforced
on `save()`.

### 4.3 Manager + migrations — `class/helloworldmanager.class.php`

The manager owns table creation and **schema evolution**. This is the most
important part to get right, so it gets its own section (§5). The shape:

```php
class HelloWorldManager extends FOGManagerController
{
    public $tablename = 'helloWorld';

    public function createSql() { return Schema::createTable(/* … */); }

    public function schema()
    {
        return [
            $this->createSql(),     // step 0 — create the table
            // append future steps here, never reorder/remove
        ];
    }

    public function install()
    {
        $res = Schema::applyUpdates($this->schema(), 0);
        return $res['error'] === null;
    }
}
```

### 4.4 Page — `pages/helloworldmanagement.page.php`

The page extends `FOGPage`, declares `public $node = 'helloworld'`, and sets the
list columns in its constructor:

```php
public function __construct($name = '')
{
    $this->name = 'Hello World Management';
    parent::__construct($this->name);
    $this->headerData = [_('Name'), _('Description')];
    $this->attributes = [[], []];
}
```

You do **not** write a list/`index()` method — `FOGPage` provides it. The list
page renders a DataTable whose JSON comes from `?node=helloworld&sub=list`; the
columns are produced by the router from your model fields, so the column keys
available to the JS are `mainlink` (the linked name), `id`, and every field by
its **friendly name** (here `description`).

Forms are built with helpers and rendered with `formFields()`:

| Helper | Purpose |
|---|---|
| `self::makeFormTag(...)` | opening `<form>` |
| `self::makeLabel($class, $for, $text)` | a `<label>` |
| `self::makeInput($class, $name, $placeholder, $type, $id, $value, $required)` | an `<input>` |
| `self::makeTextarea(...)` | a `<textarea>` |
| `self::makeButton($id, $text, $class)` | a `<button>` |
| `self::selectForm($name, $items, $selected, ...)` | a `<select>` |
| `self::formFields($fields)` | renders a `[label => field]` array |
| `self::tabFields($tabData, $obj)` | the tabbed edit layout |
| `self::makeTabUpdateURL($tab, $id)` | the POST URL for a tab |

**The POST pattern.** `addPost()` and `editPost()` return JSON and follow the
same skeleton every time:

```php
public function addPost()
{
    self::checkAuthAndCSRF();                 // ALWAYS first
    header('Content-type: application/json');
    $name = trim(filter_input(INPUT_POST, 'name'));   // never raw $_POST

    $serverFault = false;
    try {
        // validate, then build + save the model …
        if (!$obj->save()) { $serverFault = true; throw new Exception(_('…')); }
        $code = HTTPResponseCodes::HTTP_CREATED;
        $msg  = json_encode(['msg' => _('…'), 'title' => _('…')]);
    } catch (Exception $e) {
        $code = $serverFault
            ? HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR   // 500 = our fault
            : HTTPResponseCodes::HTTP_BAD_REQUEST;            // 400 = bad input
        $msg  = json_encode(['error' => $e->getMessage(), 'title' => _('…')]);
    }
    http_response_code($code);
    echo $msg;
    exit;
}
```

> Set `$serverFault = true` **only** when the failure is server‑side (a failed
> `save()`), so genuine failures return `500` and validation errors return
> `400`. Getting this backwards is a real bug we've fixed before.

The **edit** page uses tabs. `edit()` builds `$tabData` and calls
`tabFields()`; each tab has a `generator` closure that renders its body
(`helloworldGeneral()`), and `editPost()` dispatches on the global `$tab` to the
matching `*GeneralPost()` that mutates `$this->obj` before the shared `save()`.

### 4.5 Hooks — `hooks/*.hook.php`

Each hook is a small class extending `Hook`, with `public $node`, that registers
callbacks **in its constructor**. Use `registerInstalled()` — it applies the
"only when this plugin is installed" guard for you and takes an ordered list of
`[event, method]` pairs:

```php
public function __construct()
{
    parent::__construct();
    $this->registerInstalled([
        ['MAIN_MENU_DATA', 'menuData'],
        ['PERMISSION_REGISTRY_DATA', 'permData'],
    ]);
}
```

The example ships three hooks:

- **Menu** (`AddHelloWorldMenuItem`) — `MAIN_MENU_DATA` adds the sidebar entry;
  `SEARCH_PAGES` makes it searchable; `PAGES_WITH_OBJECTS` enables the
  edit/delete object flow. (`SUB_MENULINK_DATA` would add extra sub‑links such
  as Export/Import — omitted here.)
- **JS** (`AddHelloWorldJS`) — `PAGE_JS_FILES` injects `fog.<node>.<sub>.js` for
  the current sub‑page.
- **API** (`AddHelloWorldAPI`) — `API_VALID_CLASSES` exposes the node over REST
  so `/fog/helloworld` reuses the same ORM as the UI.

> **Name API classes after your permission node.** Access to a REST class is
> resolved to `<node>.<action>` by matching the class name against the nodes
> registered through `PERMISSION_REGISTRY_DATA`. A class is claimed by a node
> when it either *is* the node (`site`) or *starts with* the node and ends in
> `association` (`sitehostassociation`) — the same shape core uses for
> `groupassociation` → `group`. Longest match wins.
>
> A class no node claims is restricted to administrators and logs a line
> naming it. That is deliberate: an unmapped class used to be readable and
> writable by **any** authenticated user regardless of role. If your endpoint
> is admin-only when you did not intend it to be, check the log and rename the
> class (or register the node) rather than working around it.

> **Register your node, or your page is admin-only.** The same stance applies
> to the management page, not just the REST classes: a node absent from the
> permission registry resolves to `unmapped.<node>`, which no role can be
> granted, so only a holder of `*` can reach it — and its sidebar entry is
> hidden from everyone else. It logs one line per node per request naming
> what to register. Firing `PERMISSION_REGISTRY_DATA` is therefore not
> optional; a plugin that skips it is not "ungated", it is unreachable.
>
> ```php
> public function permData($arguments)
> {
>     $arguments['registry'][$this->node] = ['view', 'create', 'edit', 'delete'];
> }
> ```

### 4.6 JavaScript — `js/fog.helloworld.*.js`

One file per sub‑page (`list`, `add`, `edit`), each an IIFE. The **list** file
registers the server‑side DataTable and the create modal; its `columns[].data`
keys must match the list endpoint (`mainlink`, then your field names) and their
order must match `$headerData`. The **add**/**edit** files wire the form buttons
to `processForm()` (which POSTs and shows notifications) and, on edit, the delete
confirm modal to `$.apiCall(... &sub=delete ...)`.

Shared helpers you'll use: `Common.node`, `Common.id`, `Common.search`,
`$.apiCall()`, `$.deleteSelected()`, `<form>.processForm()`,
`$('#dataTable').registerTable()`.

---

## 5. Database & migrations (the important part)

FOG has **no automatic per‑column migration**. `Schema::createTable()` emits
`CREATE TABLE IF NOT EXISTS`, which does nothing on a table that already
exists — so simply adding a column to `createSql()` will **not** reach existing
installs. Use the **`schema()` contract** instead.

**`schema()` returns an ordered, append‑only list of steps.** Each step is a SQL
string (or a closure returning SQL). On install/upgrade the framework
(`Plugin::installdb()`) calls:

```php
Schema::applyUpdates($manager->schema(), $applied);
```

where `$applied` is the count stored in the plugin's `pSchema` column. Only
steps from index `$applied` onward run, and the new count is saved back. So:

> **To add a column later, append a new step. Never reorder or delete existing
> steps** — the applied count is positional.

```php
public function schema()
{
    return [
        // 0 — create the table
        $this->createSql(),
        // 1 — added later; runs once on upgrade, skipped thereafter
        "ALTER TABLE `helloWorld` ADD COLUMN `hwColor` VARCHAR(255) NULL",
    ];
}
```

`applyUpdates()` is defensive: it ignores "already exists / duplicate column /
duplicate key / unknown column / duplicate entry" errors, so re‑running is
safe. A closure step may return a string to signal a hard error and stop.

Seed data (e.g. default `globalSettings` rows) is just another step — return the
`INSERT` SQL, or a closure for anything that needs runtime values (see
`accesscontrolmanager`'s `schema()` for the closure pattern).

> **Legacy note.** Older plugins implement a destructive `install()` that calls
> `uninstall()` (drop) then recreates. New plugins should implement `schema()`
> (the framework prefers it and falls back to `install()` only when `schema()`
> is absent). The example provides both; its `install()` just applies the schema
> from `0`.

---

## 6. Lifecycle

1. **Discovery.** `Plugin::getPlugins()` scans **both** plugin roots, reads each
   manifest, and upserts a row in the `plugins` table. It also maintains the
   asset symlink for external plugins and deactivates any active plugin that
   this FOG version has moved out of range.
2. **Activation.** An admin enables the plugin in **Plugin Management**. Its
   `node` is added to `FOGBase::$pluginsinstalled`, which is what every hook
   constructor checks before registering. Refused if `fog_min`/`fog_max`/
   `requires` say it cannot run here.
3. **Install / upgrade.** `Plugin::installdb()` runs `schema()` via
   `applyUpdates()` and tracks `pSchema`. This is idempotent and
   non‑destructive — safe to run on every upgrade.
4. **Uninstall.** Inherited `uninstall()` drops the table; override it if you
   need to clean up settings, associations, or users you created.
5. **Code removed.** Deleting the plugin directory does **not** delete its row.
   Discovery only ever walks directories that exist, so nothing would visit
   the row again to clean it up — and absence is not reliably permanent (an
   unmounted external root, or the web tree mid-upgrade, makes every plugin
   vanish at once). The row keeps its state and its `pSchema` count, so
   putting the code back resumes exactly where it left off.

   Plugin Management badges such a row **Missing**, refuses to activate or
   install it, and offers **Forget selected** to delete the row deliberately.
   Forget leaves the plugin's tables behind and says so: what to drop is
   described by `schema()`, which is part of the code that has gone.

---

## 7. Settings

Global configuration lives in the `globalSettings` table.

- Read: `FOGBase::getSetting('FOG_PLUGIN_HELLOWORLD_FOO')`
- Write: `FOGBase::setSetting('FOG_PLUGIN_HELLOWORLD_FOO', $value)`
- Naming: `ALL_CAPS_WITH_UNDERSCORES`, prefixed `FOG_PLUGIN_<NAME>_…`.
- Create defaults as a `schema()` seed step (an `INSERT` into `globalSettings`).

---

## 7a. Class names and the `FOG\` namespace

Since 1.6 beta, FOG's own classes are declared in the **`FOG\`** namespace —
`FOG\Host`, `FOG\FOGController`, `FOG\Hook`. Every one of them is also aliased
back into the global namespace, so **your plugin keeps working exactly as it is**
(ADR 0013).

`class MyHook extends Hook`, `new Host($id)`, `$obj instanceof Host`,
`self::getClass('HelloWorldManager')`, `is_subclass_of($c, 'PluginTask')` — all
of these resolve through the alias, unchanged, on every version of 1.6. The
aliases are the 1.6 plugin ABI and are supported for the whole of 1.6.

Two things to know:

- **`FOG\Foo` is the forward-compatible spelling.** Prefer it in new code. Bare
  `Foo` works for all of 1.6 and is what to write if you also support earlier
  1.6 betas.

- **⚠️ `get_class($this)` now returns `FOG\Foo`, not `Foo`.** If your plugin
  *produces* a class name and then uses it as data — compares it to a literal,
  builds a database column name or an array key from it, puts it in a filename
  or a log line — it must be updated. Use `FOGBase::shortName()`:

  ```php
  // Before: 'FOG\HelloWorld' on 1.6, and the comparison silently fails.
  if (get_class($this) === 'HelloWorld') { /* ... */ }

  // After: 'HelloWorld' on every version, namespaced or not.
  if (self::shortName($this) === 'HelloWorld') { /* ... */ }
  ```

  `shortName()` accepts an object or a class-name string, strips any namespace
  prefix, and is a no-op on a name that has none — so the same code is correct
  before and after.

  This is the **only** source-level change 1.6 asks of a plugin, and it affects
  only plugins that *produce* a class name. Consuming one — which is what nearly
  all plugin code does — needs no change at all.

**Deprecation window.** The global aliases are supported for all of 1.6. The
earliest they could be reviewed for removal is 1.7, with at least one minor
release of notice before it happens. Adopting `FOG\` names now costs nothing and
removes the question later.

## 8. Security & output conventions

- **Output:** wrap every user‑controlled value with `Initiator::e($value)` when
  echoing into HTML. All output also passes through the global
  `sanitizeOutput` buffer.
- **Input:** use `filter_input(INPUT_POST, 'key')` (or the already‑sanitized
  superglobals) — never raw `$_POST`/`$_GET`.
- **CSRF/auth:** call `self::checkAuthAndCSRF()` at the top of every state‑
  changing POST handler.
- **Instantiation:** prefer `self::getClass('HelloWorld')` /
  `self::getClass('HelloWorldManager')` over `new`.
- **Translation:** wrap UI strings in `_('…')`.
- **Secrets in your table:** if a column holds a credential — an API token, a
  webhook URL, a bind password — declare it through `API_SENSITIVE_FIELDS` or
  it is emitted in REST payloads and by the unauthenticated boot endpoint.
  Two tiers:

  | Tier | Stripped from | Use when |
  |---|---|---|
  | `fields` | lists and expanded relations | a client legitimately reads it back on a direct single GET (as fog-client does with `host.ADPass`) |
  | `always` | everything, single GET included | nothing outside the web tier ever needs it |

  ```php
  public function declareSensitiveFields($arguments)
  {
      $arguments['always'][$this->node][] = 'bindPwd';
  }
  ```

  Prefer `always` unless you can name the consumer that reads the field back.

- **Columns only your code should write:** `Route::edit()` and `Route::create()`
  copy a JSON body straight into your model's `databaseFields`, so by default
  anyone who can reach the API can set any column you declare. Declare the ones
  your server-side code maintains — a token you issue, a counter, a timestamp
  you stamp — through `API_SERVER_OWNED_FIELDS`:

  ```php
  public function declareServerOwnedFields($arguments)
  {
      $arguments['fields'][$this->node][] = 'apiToken';
  }
  ```

  A request that tries to **change** one gets a 400. A request that carries the
  same value it already had is let through, so a client that GETs your object
  and PUTs the whole thing back keeps working — don't "fix" that by rejecting
  the field's mere presence.

  This is a different question from `API_SENSITIVE_FIELDS`, which is about what
  leaves the server. A field can be one, the other, or both: `host.ADPass` is
  sensitive but genuinely settable over the API, so it is only in the first list.

---

## 9. Common hook events

| Event | Purpose |
|---|---|
| `MAIN_MENU_DATA` | add the top‑level sidebar entry (`hook_main[node] = [label, icon]`) |
| `SUB_MENULINK_DATA` | add sub‑links (Export/Import/…) under the node |
| `SEARCH_PAGES` | make the node searchable |
| `PAGES_WITH_OBJECTS` | enable the object (edit/delete) flow for the node |
| `PAGE_JS_FILES` | inject JS files for the current page |
| `PERMISSION_REGISTRY_DATA` | register the node and its actions — **required**, see §4.5 |
| `API_VALID_CLASSES` | expose the node over the REST API (name classes after your permission node — see §4.5) |
| `API_SENSITIVE_FIELDS` | keep credential columns out of API and boot-endpoint output — see §8 |
| `API_SERVER_OWNED_FIELDS` | refuse API writes to columns your own code maintains — see §8 |
| `<NODE>_ADD_FIELDS` / `_GENERAL_FIELDS` | let others extend your forms |
| `<NODE>_ADD_POST` / `_EDIT_POST` / `_ADD_SUCCESS` / `_ADD_FAIL` | extension points around your saves |

Fire your own events with `&`‑by‑reference args so listeners can mutate them
(see the example's `HELLOWORLD_*` events).

---

## 10. Gotchas (learned the hard way)

- **`CREATE TABLE IF NOT EXISTS` never alters a live table.** Add columns via a
  new `schema()` step, not by editing `createSql()`.
- **Filename = `strtolower(ClassName)` + suffix.** A mismatch means the class
  won't autoload. Silently, for most classes — but not for your manager:
  install refuses outright if `class/<name>manager.class.php` exists and does
  not declare `<Name>Manager`, because the fallback used to make the install
  report success having created nothing.
- **`menuicon`** beginning with `fa` is rendered as a font‑awesome icon;
  anything else is treated as an `<img>` `src`.
- **`$serverFault`** must be `true` only for server‑side failures, so HTTP
  status codes are honest (`500` vs `400`).
- **Hook constructors must early‑return** when the node isn't in
  `$pluginsinstalled`, or your hooks run for a plugin that isn't enabled.
- **List columns** in the JS must match `$headerData` order and the keys the
  router emits (`mainlink`, `id`, friendly field names).
- **An external plugin's assets are served through a symlink FOG maintains for
  you.** `/opt/fog/` is outside the document root, so the browser cannot reach
  it; every discovery pass (re)creates `lib/plugins/<name>` →
  `/opt/fog/plugins/<name>` so `../lib/plugins/<name>/js/…` resolves for a
  bundled and an external plugin alike. You do nothing — reference your assets
  the normal way and `Hook::injectPluginJS()` emits the right URL. Apache needs
  `Options +FollowSymLinks` (the installer sets it); nginx follows regardless.

---

## 11. Install & test your plugin

1. Copy `helloworld/` to `/opt/fog/plugins/<yourname>/` (or, for a plugin you
   intend to bundle with FOG itself, `packages/web/lib/plugins/<yourname>/`) and
   rename the directory, the classes, the files (lowercased), every `$node`, and
   the `$fog_plugin['name']`.
2. Deploy to the web root (e.g. `copybacktrunk.sh "" "" "1.6"`) — only needed
   for a bundled plugin; `/opt/fog/plugins/` is already live.
3. In the UI: **Plugin System → Plugin Management → install/activate** your
   plugin.
4. Confirm: the sidebar entry appears, **Create New** saves a row (check the
   table exists and `pSchema` advanced), **list** shows it, **edit** updates it,
   **delete** removes it.
5. Quick static checks while developing:
   `php -l <file>` on each PHP file and `node --check <file>` on each JS file.

---

## 11a. Shipping your plugin to other people

Package it as a `.tar.gz` containing **one directory, named for the plugin**:

```
tar czf myplugin-1.0.0.tar.gz myplugin/
```

Publish the archive's `sha256sum` alongside it — Plugin Management shows the
checksum of what was uploaded so an admin can compare the two before
installing.

Admins have two routes:

- **`git clone` or untar into `/opt/fog/plugins/`** as root. Always available,
  nothing to switch on.
- **Plugin Management → Upload plugin.** Off by default; see below.

### The archive must survive validation

FOG unpacks the archive somewhere the autoloader does not look, reads the
manifest, and shows the admin what it found *before* anything is installed. It
is refused outright if:

| | |
|---|---|
| it isn't a readable `.tar.gz` | opened as a tar regardless of the file name |
| it holds anything other than exactly one top-level directory | including a stray file at the archive root |
| an entry path is absolute or contains `..` | an interior `..` gets past PharData; FOG checks anyway |
| there is no `<name>/config/plugin.config.php` | |
| the manifest's `name` isn't the directory name | |
| the plugin is outside its own `fog_min`/`fog_max` range | |
| a **bundled** plugin already has that name | a bundled plugin always wins the collision, so the upload could never load |
| it is over 64 MB | `post_max_size` usually bites first |

Symlinks need no rule: `PharData` writes them out as empty regular files, so
they cannot escape — but it also means **a plugin that relies on a symlink will
install subtly broken.** Don't ship one.

Uploading a plugin that is already in `/opt/fog/plugins` is an upgrade; the
admin is warned that files will be replaced, and the old copy is only deleted
once the new one is in place. Installing the files does **not** install or
activate the plugin — the admin still does that from the same page, so "the
files are here" and "this code is running" stay separate decisions.

### Turning uploads on (admins)

Two independent switches, both required:

1. `FOG_PLUGIN_UI_INSTALL_ENABLED` in **FOG Configuration → FOG Settings →
   Plugin System**.
2. `sudo bin/fog-plugin-uploads.sh enable`, which makes `/opt/fog/plugins`
   writable by the web server (and relabels it for SELinux). `disable` and
   `status` do what they say.

The upload route also needs the **`plugin.install`** permission, which is
deliberately *not* part of `plugin.edit`: activating a plugin that is already
on disk and adding new executable code to the server are different authorities.

> **Understand what you are turning on.** A plugin is PHP that FOG autoloads
> and runs as the web user. Making its directory web-writable means any
> file-write bug anywhere in FOG can put executable code on the server. That is
> why step 2 is a root command rather than something the settings page can do
> for itself — and why leaving uploads off and using `git clone` is a perfectly
> good answer.

---

## 12. Reference plugins

- **`helloworld`** — this guide's minimal, complete CRUD example.
- **`subnetgroup`** — a clean real CRUD plugin (model→class relationship,
  Export/Import, `schema()`).
- **`site`** — a five‑table plugin, and the reference for object scoping via
  `OBJECT_SCOPE_CHECK`. Its `schema()` shows how to retire a table you shipped
  (steps are immutable: step 3 creates it, step 4 drops it).
- **`persistentgroups`** — a plugin that is nothing but a `schema()` closure
  step (it installs a MySQL trigger). No page, no model, no hooks: proof that
  none of those are mandatory.
- **`ldap`** — authentication/integration plugin (custom hooks beyond CRUD).

When in doubt, copy the closest existing plugin and adapt it — the conventions
above are followed consistently across all of them.
