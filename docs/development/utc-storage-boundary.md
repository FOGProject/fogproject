# Moving stored timestamps to UTC: the boundary, and what happens to what is already there

**Status: proposed, not decided. No code has been written for it.**

This is a scan and a proposal. Every claim below is labeled **VERIFIED** (with
the command that produced it), **INFERRED** (reasoned from something verified),
or **UNKNOWN**. The last section names the claim that would hurt most if it
turned out to be false.

---

## 1. What is actually stored today

### 1.1 The columns

**VERIFIED — 42 datetime-shaped columns across 23 core tables**, plus the
plugin tables.

```
php -r '$m = require "packages/web/commons/schema-expected.php";
foreach ($m["tables"] as $t => $d) { foreach (($d["columns"] ?? []) as $c => $s) {
  if (preg_match("/^(datetime|timestamp|date)\b/i", trim($s), $mm)) { echo "$t.$c  $s\n"; } } }'
```

|  | count | how MySQL treats it |
|---|---|---|
| `timestamp` | 17 | stored as a UTC instant; converted **on read and on write** using the session `time_zone` |
| `datetime` | 24 | stored verbatim; no conversion, ever |
| `date` | 1 | `userTracking.utDate`, a calendar day |

That split is the single most important fact in this document, and it is easy
to miss because both types render identically. **17 of the 42 columns are
already UTC underneath.** What varies for them is not the stored instant but
the string the server hands back, which depends on the session zone at read
time.

### 1.2 The clocks

**VERIFIED — FOG never sets the MySQL session time zone**, so it is `SYSTEM`,
i.e. the database host's OS zone.

```
grep -rn "time_zone\|date_default_timezone_set" packages/ --include=*.php | grep -v vendor
# (no output)
```

**VERIFIED on the lab server** (`php /home/telliott/labs/savedfilters/live_tzprobe.php`):

```
server                : 11.8.8-MariaDB
MySQL session/global  : SYSTEM / SYSTEM
MySQL system_time_zone: CDT
MySQL NOW()           : 2026-08-29 20:28:06
MySQL UTC_TIMESTAMP() : 2026-08-30 01:28:06
host OS zone          : America/Chicago
FOG_TZ_INFO           : [America/Chicago]
```

So a value can reach a column from **three** different clocks:

| clock | reaches | zone it means |
|---|---|---|
| PHP, via `niceDate()`/`storageNow()` | most write sites | `FOG_TZ_INFO` |
| MySQL, via `NOW()` in a hand-written statement | `userPrefs`, `savedFilters`, four plugin managers | the **database host's** OS zone |
| MySQL, via `DEFAULT current_timestamp()` | any INSERT that omits the column — 17 columns declare it | the **database host's** OS zone |

**VERIFIED that clocks 1 and 3 disagree when the database is not on the web
box** (`php /home/telliott/labs/savedfilters/tzprobe.php`, against a container
whose MySQL runs UTC while `FOG_TZ_INFO` says `America/Chicago`):

```
written '2026-08-29 20:27:26' into both:
  TIMESTAMP reads back     : 2026-08-29 20:27:26
  DATETIME  reads back     : 2026-08-29 20:27:26
  DEFAULT current_timestamp: 2026-08-30 01:27:26   <-- DIFFERENT CLOCK
```

**INFERRED:** on a single-box install — which is nearly all of them — clocks 1
and 3 agree, because `FOG_TZ_INFO` is seeded from the host and the database is
the same host. The disagreement is real but it bites remote-database and
containerised installs, not the common case.

**VERIFIED that a TIMESTAMP column's read-back moves with the session zone and
a DATETIME's does not** (same probe):

```
  session +00:00  -> ts=2026-08-29 20:27:26 dt=2026-08-29 20:27:26
  session -05:00  -> ts=2026-08-29 15:27:26 dt=2026-08-29 20:27:26
  session +09:00  -> ts=2026-08-30 05:27:26 dt=2026-08-29 20:27:26
```

### 1.3 A fourth clock, now removed

**VERIFIED, and fixed in #1491 before this proposal was written.** Thirteen
write sites produced their value with `formatTime()`, which converts to the
**viewer's** zone, and `displayTimeZone()` cached its answer before
`FOG_TZ_INFO` was loaded — so the whole request ran in UTC whatever the setting
said. On the lab server that left 676 `auditLog` rows five hours ahead of the
`history` rows beside them:

```
php /home/telliott/labs/savedfilters/live_clockclass.php
  auditLog.alCreatedTime  datetime   676  2026-08-30 01:05:19  UTC
  history.hTime           timestamp 1817  2026-08-29 21:41:24  server local
```

It matters here for one reason: **those 676 rows are already unconvertible.**
No bulk conversion can know which of them were written in UTC and which in
`America/Chicago`, because the answer depends on when the row was written
relative to a deploy. They are the first concrete example of why the boundary
proposal exists at all.

---

## 2. The decisions

### 2.1 Do not convert what is already stored

**Proposed.** Record a boundary instant; interpret values written after it as
UTC; leave everything before it exactly as it is and label it.

The alternative — one `CONVERT_TZ` sweep per column — was rejected, and this is
the load-bearing rejection, so the reasons are worth stating separately:

- **It cannot be correct.** A sweep must assume one source zone. Section 1.2
  shows three clocks, section 1.3 shows a fourth that ran for an unknown
  window, section 3 shows a fifth on every fog-client check-in, and
  `FOG_TZ_INFO` may have been changed at any point in an install's life with
  no record kept. A sweep would silently shift values that were already
  right.
- **It is one-way.** Once `2024-08-31 10:13:47` becomes `2024-08-31 15:13:47`
  there is nothing left that says which it was.
- **The 17 TIMESTAMP columns would be moved twice.** They already hold a UTC
  instant; converting the *string* MySQL hands back is converting an
  already-converted value.
- **There is no way to test it.** The failure is a value that is wrong by a
  whole-hours offset, which is indistinguishable from a correct value in
  isolation.

### 2.2 Classification is per VALUE, not per row and not per column

**Proposed, and it is what makes the whole thing cheap.** A row can hold a
`created` from before the boundary and a `lastCheckin` from after it — most
host rows on any install older than the migration will. So "is this adjusted"
cannot be a column on the row.

It does not need to be stored at all. It is a pure function:

```
unadjusted(value) := value < boundary + band
```

Nothing is added to any table, nothing has to be backfilled, and a plugin table
created after the migration is handled correctly for free because every value
in it is after the boundary.

**Rejected: a per-column `*_isUtc` flag.** 42 new columns, a backfill that has
to make the same unknowable guess section 2.1 rejects, and it puts the answer
in the database where it can drift from the truth.

### 2.3 The boundary lives in its own table

**Proposed:** a single-row table written once by the schema step that turns the
migration on. Nothing else writes it; there is no model class, no route, and no
page.

**Rejected: `globalSettings`.** **VERIFIED** that the configuration page
renders every row of that table with no filter —
`packages/web/lib/pages/fogconfigurationpage.page.php:3216` selects
`settingKey, settingDesc, settingValue, settingCategory` with no `WHERE` — and
that `setting` is one of `Route::$validClasses`, so the row is editable over
REST whether or not the page chooses to draw it. There is a precedent for
hiding a key from the page (`NODE_API_KEY_SETTING`, same file), but hiding is a
display choice and not a guarantee. A boundary that one careless edit can move
is a boundary that changes the meaning of every timestamp in the install.

**Rejected: a column on `schemaVersion`.** That table's single value is
compared numerically in the installer, the updater and the schema page.
Widening it invites exactly the kind of accident this is trying to avoid.

**Rejected: a file on disk.** A database restored into a different install
would arrive without its boundary, and the values would then be interpreted
under whatever boundary that install had. The boundary has to travel with the
data.

Proposed shape:

| column | holds | why |
|---|---|---|
| `seBoundary` | the instant the migration took effect, in UTC | the comparison point |
| `seZone` | the `FOG_TZ_INFO` in force when it was written | see 2.5 |
| `seDbZone` | the database host's zone at that moment | the second clock, recorded so the band can be justified rather than guessed |
| `seSchema` | the schema step that wrote it | so an install can say which upgrade did this |

### 2.4 One boundary, install-wide

**Proposed.** Per-table boundaries would allow a half-migrated install, which
is the state nobody can reason about, and they buy nothing: classification is
per value already, so a table that only ever held post-boundary values is
already handled.

### 2.5 If `FOG_TZ_INFO` changes afterward, nothing moves

**Proposed, and this is what `seZone` is for.** After the migration
`FOG_TZ_INFO` stops being a storage zone and becomes what it should always have
been — the install's default *display* zone, exactly like the per-user
preference shipped in #1484. Changing it re-labels what is on screen and
changes nothing that is stored.

Pre-boundary values keep being described as "written in `seZone`", because
`seZone` is a record of what was true then rather than a live setting. Today
the opposite is true and it is a real trap: **VERIFIED** that `FOG_TZ_INFO` is
read as the storage zone by `FOGBase::storageTimeZone()`
(`packages/web/src/Base/FOGBase.php`), so changing it today silently re-labels
every existing row.

### 2.6 The band around the boundary

**Proposed: 26 hours, flat.** A value within 26 hours either side of the
boundary is reported as unadjusted rather than guessed at.

Why a band exists at all: the boundary is one instant in UTC, and a
pre-boundary value is a wall-clock reading in some other zone. Comparing them
is comparing two different kinds of thing, so near the boundary the comparison
cannot decide. There is also an operational reason — a request that began
before the schema step and wrote after it produces an old-convention value on
the new side of the line.

Why 26: UTC−12 to UTC+14 is the full span of real offsets, so 26 hours is safe
for any install without needing to trust a recorded zone.

**Rejected: a band computed from `seZone` and `seDbZone`** (their offsets, plus
an hour of DST slack), which on the lab server would be six hours rather than
twenty-six. It is more precise and it is not worth it: the entire cost of the
wide band is that a handful of values written in the day around a one-time
upgrade are labeled "unadjusted" when they could have been labeled exactly. The
cost of a band that is too narrow is a timestamp that is silently an hour wrong
and presented as correct. The asymmetry is the whole argument. `seZone` and
`seDbZone` are still recorded, because they are what lets a future maintainer
narrow the band deliberately rather than rediscover this.

### 2.7 What "unadjusted" looks like

**Proposed:**

- **In a grid.** The value renders as it always did, with a small muted marker
  after it and a tooltip naming the zone it was written in — "recorded before
  this server moved to UTC; written in America/Chicago". Not hidden, not
  blanked, not "No Data": the value is real and usually the reader knows
  perfectly well what it means. Sorting and filtering continue to work on the
  raw value, which is the honest behavior because within the pre-boundary era
  the values are mutually consistent.
- **On a detail page.** The same marker plus one line of prose, once per page
  rather than once per field.
- **Over REST.** A sibling field, never a change to the value's type or shape:

  ```json
  { "createdTime": "2024-08-31 10:13:47",
    "createdTimeAdjusted": false }
  ```

  **Rejected: emitting an ISO-8601 string with an offset for post-boundary
  values and a naive one for pre-boundary values.** Every client library parses
  a naive string as local time, so the pre-boundary values would be silently
  reinterpreted by the consumer — the same failure as a bulk conversion, moved
  to somebody else's code where we cannot see it.
  **Rejected: omitting the field or sending null.** That turns "this happened,
  and here is when, roughly" into "this never happened", which is a different
  and worse claim — and it is the exact bug GH-1245 was about.

---

## 3. What this does not answer

- **UNKNOWN: how many installs have ever changed `FOG_TZ_INFO`.** Nothing
  records it. It affects how much pre-boundary data is internally inconsistent,
  not whether the design works.
- **VERIFIED: exactly one stored value comes from a client, and it carries no
  zone.** `packages/web/src/Client/UserTrack.php:72` reads `$_REQUEST['date']`
  off a fog-client check-in and parses it with `niceDate()`, so it is read as
  though it were already in the storage zone. Nothing else in the web tree
  parses a client-supplied date into a stored column.

      grep -rn '_REQUEST\|_POST' packages/web/src/Client | grep -i date

  Two consequences for the boundary. The value is a **fifth clock** -- the
  managed machine's own -- and unlike the other three there is no server-side
  setting that describes it, so a row written this way is unclassifiable by any
  rule this document can state. And it is the one stored date whose correctness
  cannot be improved by changing anything on the server: the client would have
  to start sending an offset. Both belong in the migration's scope, not this
  one.

  **UNKNOWN, and now the largest remaining gap: which zone the client's own
  string is in.** Reading the fog-client source is a separate repository and
  was out of scope here.
- **UNKNOWN: what the six `NOW()` call sites should become.** They are the
  second clock. Unifying them is part of the migration, not of this document.
- **Out of scope by instruction: any code.**

---

## 4. The claim that would hurt most if it is wrong

**"17 of the 42 columns are TIMESTAMP and therefore already hold a UTC
instant."**

Everything in section 2 assumes the migration is about *interpretation* rather
than about *data*. If that split were wrong — if those columns did not already
round-trip through UTC — then the boundary is not enough on its own, because
the same column would hold two genuinely different kinds of value and no
per-value rule could separate them.

It is VERIFIED twice: from the manifest, and by round-tripping a value through
both column types at three different session zones (section 1.2). The way it
could still be wrong in practice is a column whose declared type differs
between the manifest and a real, upgraded install — which is precisely what
`tests/schema-executes.test.php` and the manifest exist to prevent, and which
should be re-checked against a genuinely old install before any of this is
built.

Second most load-bearing: **"the configuration page renders every
`globalSettings` row."** If that were wrong the boundary could live in a
setting, and the dedicated table in 2.3 would be unnecessary machinery.

---

## 5. The admin-facing text

Filed, now that the feature exists: `docs/management/web/unadjusted-timestamps.md`
in `FOGProject/fog-docs` (<https://docs.fogproject.org/unadjusted-timestamps>).
The draft that used to sit here was removed with it -- keeping a second copy in
this repo is how the two drift, and the draft had already gone stale in one
place, promising a REST field beside every unadjusted value that GH-1508
deliberately did not build.

Same split as `FOGSETTINGS.md` and its fog-docs counterpart: what an admin
needs lives there, why it works this way lives here.
