# A boolean column is `TINYINT(1)`, not `enum('0','1')`

## Status

accepted

Prompted by [forum topic 18227](https://forums.fogproject.org/topic/18227/fog-supported-os)
and the fix in #1361, which made the cost of the old encoding visible.

## Context

FOG has spelled its two-state columns `enum('0','1')` since the beginning, and it
has never spelled all of them that way. Before this change the database held
**36** `enum('0','1')` columns and **four** genuine `tinyint(1)` booleans —
`sites`.`siteCatchAll`, `auditLog`.`alRenderable`, `auditChange`.`acRedacted` and
`hosts`.`hostInfoLock`. Two conventions for one idea, with no rule saying which to
reach for.

That alone would be a tidiness problem. The reason it is not is that the older of
the two conventions has a trap in it.

### An integer written to these enums is off by one

An integer written to an `ENUM` is a **member index**, not a value. For
`enum('0','1')` the indices are: 0 = the error value, 1 = the member `'0'`,
2 = the member `'1'`. Measured on MariaDB 11.8 under `STRICT_TRANS_TABLES`:

| bound value | `tinyint(1)` | `enum('0','1')` |
|---|---|---|
| `'0'` | `0` | `'0'` |
| `'1'` | `1` | `'1'` |
| int `0` | `0` | **refused — error 1265** |
| int `1` | `1` | **stores `'0'`** — i.e. FALSE |
| int `2` | `2` | stores `'1'` |
| `''` | refused | refused |

So `->set('isEnabled', 1)` means *disabled* the moment the value reaches the
server as an integer rather than a string. FOG survives that only because
`PDODB::_bind()` binds every parameter as `PDO::PARAM_STR`. That is not a design;
it is a load-bearing accident, and it has already cost real work:

- `PDODB::_bind()` cannot normalise a PHP boolean with `PDO::PARAM_BOOL`, because
  bound as an integer `false` would be index 0 and `true` index 1 — refused and
  inverted respectively. It has to produce the *strings* `'0'`/`'1'` (#1361).
- `Schema::defaultLiteral()` exists because `createTable()` callers pass values
  rather than SQL, and an unquoted `0` against an ENUM is a malformed default.
- `groupmanagement.page.php` carries a comment explaining, at the call site, why
  the enforce value must be passed as a string.

`tinyint(1)` has none of this. `0` is false and `1` is true whether it arrives as
a string or an integer.

### What the ENUM buys, and why it is not enough

An `enum('0','1')` constrains the domain to exactly two values; `tinyint(1)` will
store `2` or `47` without complaint. That is a real property and it is the only
argument for the status quo. It is outweighed because the constraint is enforced
in the one direction that does not matter (a wrong *string* is refused) and
inverted in the direction that does (a wrong *integer* is silently accepted as a
different value). MariaDB 10.2+ `CHECK (col IN (0,1))` is available if the domain
constraint is ever wanted back.

## Decision

Two-state columns are `TINYINT(1) NOT NULL DEFAULT 0` (or `DEFAULT 1` where that
is the existing default). Core columns are converted by schema step 368; the
bundled plugins convert their own, because each plugin owns its schema
([ADR 0009](0009-plugins-become-installable-artifacts.md)).

New two-state columns are declared `TINYINT(1)` from the start. `enum` remains
correct for a genuine enumeration — `lsSearchScope`, `pmAction`, `ttType`,
`alOutcome` and the rest are untouched.

### The migration is three statements per column, and this is not optional

🔴 A direct `ALTER TABLE t MODIFY c TINYINT(1)` converts an ENUM **by index, not
by label**. Measured:

```
before:  '0'  '1'  '0'  '1'
after:    1    2    1    2
```

Every false becomes `1` and every true becomes `2` — both truthy, no error,
nothing logged. On upgrade that would silently switch on every flag in every FOG
database: every host pending, every snapin set to shut down, every user allowed
API access. The safe form goes through a string type, which converts by label:

```sql
ALTER TABLE t MODIFY c VARCHAR(1) NOT NULL DEFAULT '0';
UPDATE t SET c = '0' WHERE c NOT IN ('0','1');
ALTER TABLE t MODIFY c TINYINT(1) NOT NULL DEFAULT 0;
```

The `UPDATE` is not decoration: a row still holding the ENUM error value (the
nine-year legacy of `SET SESSION sql_mode=''`, see GH-1245) arrives at the varchar
stage as `''`, which `tinyint` refuses.

### The REST payload changes, deliberately

`PDODB` runs with `ATTR_EMULATE_PREPARES => false`, so mysqlnd returns native
types. These columns now read back as the integer `1` where they used to read
back as the string `'1'`, and the API payload changes accordingly:

```
{"imageEnabled":"1"}   ->   {"imageEnabled":1}
```

`OpenAPI::_columnSchema()` already maps `tinyint` to `integer`, so the published
document follows the data with no edit.

Every reader inside the tree either tests truthiness or casts with `(string)`
first, and both spellings survive — audited, no call site needed changing. A
downstream consumer that compares the JSON strictly against `"1"` does not, which
is why this lands in a beta rather than a patch line. **`darksidemilk/FogApi`
is the known downstream consumer.**

## Consequences

- One convention. A new boolean column has an obvious type.
- The integer/index trap is gone, so a future `PDO::PARAM_INT` or an unquoted `0`
  in a schema step is no longer a silent data corruption.
- 36 columns changed type: 26 core columns in 15 tables, and 10 plugin columns
  in 3 tables -- `LDAPServers` (4), `OIDCProviders` (5) and `location` (1),
  which ship from `FOGProject/fog-plugins` and so change in their own release.
- `commons/schema-expected.php` regenerated; `SchemaReconciler` brings any
  database that missed the step into line.
- **`dev-branch` does not take this.** 1.5.x is a patches line with the largest
  installed base FOG has; the payload change is not worth it there, and #1362
  already closed the bug that prompted this.
- **Not included, deliberately:** the `char(1)`/`varchar(1)` flags
  (`tasks`.`taskShutdown`, `snapins`.`sReboot`, `hosts`.`hostUseAD`). They look
  like the same family and are not — `hostUseAD` is tri-state, with `''` meaning
  "inherit" as a third value the form renders. That family needs a per-column
  reading, not a sweep.
