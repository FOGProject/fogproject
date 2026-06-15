# CSV Import / Export

FOG can mass‑import and export most management objects (hosts, images,
snapins, groups, printers, users, modules, storage groups and storage nodes)
as CSV files from each object's **Import** / **Export** page.

> **Two ways to map columns:**
> 1. **Headered** — if the file's first row names the columns (which is what
>    Export now produces), FOG maps columns **by name**. Columns may be in any
>    order, and you may include only the columns you care about.
> 2. **Positional (header‑less)** — if there is no header row, FOG maps columns
>    **by position**, in the exact order Export produces.
>
> A header row is **auto‑detected**, and there is also a *"First row is a
> header"* checkbox on the Import page to force it. Either way, the simplest,
> safest workflow is **Export → edit → Import.**

---

## Table of contents

- [Header row vs. positional](#header-row-vs-positional)
- [General format rules](#general-format-rules)
- [The associations column](#the-associations-column)
- [Per‑class column layouts](#per-class-column-layouts)
  - [Host](#host)
  - [Image](#image)
  - [Snapin](#snapin)
  - [Group](#group)
  - [Printer](#printer)
  - [User](#user)
  - [Module](#module)
  - [Storage Group](#storage-group)
  - [Storage Node](#storage-node)
- [Plugin extensibility](#plugin-extensibility)

---

## Header row vs. positional

**Headered (recommended).** If the first row contains the column names, FOG
maps each subsequent row **by name**:

- **Any order** — columns may appear in any order.
- **Partial** — include only the columns you want to set; omitted columns keep
  their defaults. (The identity columns are still required: `name` for every
  class, plus `primac` for hosts.)
- **Auto‑detected** — a first row whose cells are *all* recognised column names
  is treated as a header automatically. The *"First row is a header"* checkbox
  forces header mode (and reports any unrecognised header names as ignored).
- **Names are matched case‑insensitively** (`ProductKey` == `productkey`).
- **Export emits a header row** by default, so an Export file re‑imports
  by name with no editing of column positions.

Valid header names are the column names in the per‑class tables below, plus
`primac` (hosts) and `associations` (where supported).

Example (headered, partial, reordered):

```csv
name,primac,associations
PC-Lab-01,00:11:22:33:44:55,groups:Lab A|Lab B;snapins:7zip
```

**Positional (header‑less).** With no header row, FOG maps columns **by
position**, in the exact order Export produces (the per‑class tables below).

## General format rules

- **Delimiter:** standard comma (`,`). Use normal CSV quoting (wrap a field in
  double quotes) if a value itself contains a comma.
- **Column count.** In positional mode a row may contain *up to* the number of
  columns the class defines (plus the optional trailing associations column).
  In header mode a data row must not have more columns than the header. Either
  way, too many columns rejects the import with *"Invalid data being parsed."*
- **Order matches Export** (positional mode). The per‑class tables list it.
- **Pipe (`|`) is the multi‑value separator** inside a single field (e.g. a
  host's MAC list, and association values). This mirrors how MAC lists have
  always been delimited.
- **Existing items are skipped.** Importing a host whose MAC already exists, or
  an item whose unique name already exists, fails that row (the rest continue).

### A note on sensitive / special fields

| Field | Class | Behaviour |
|-------|-------|-----------|
| `primac` | Host | **Required**, first column. Pipe‑separated MAC list; the first MAC becomes primary, the rest are added as additional MACs. |
| `productKey` | Host | Auto‑detected: accepts plaintext, base64, or the AES‑encrypted form an export produces. |
| `password` | User | Stored encrypted on import. |
| `imageID`, `osID`, `imageTypeID`, … | various | Numeric foreign keys. They are **not** name‑resolved (only the associations column resolves names — see below). Prefer Export→edit so these line up with the target server. |

---

## The associations column

Every class that supports relationships accepts **one optional final column**
named `associations`. It lets a single row carry related objects (a host's
groups/snapins/printers, a group's member hosts, etc.) alongside the object's
own fields.

### Cell format

```
label:value|value|value;label:value
```

- `;` separates association **types**.
- `:` separates a type's **label** from its **values**.
- `|` separates individual **values** within a type.

Example (a host):

```
groups:Lab A|Lab B;snapins:7zip|Chrome;printers:FrontDesk
```

### Resolution rules

- **Id or name.** Each value is resolved by **numeric id first, then by
  (case‑insensitive) name.** Names make a file portable between servers whose
  ids differ — which is the whole point of an export→import migration.
- **Lenient (warn‑and‑skip).** If a value can't be resolved (e.g. a snapin name
  that doesn't exist on this server), the row **still imports**; only that one
  association is skipped and a warning is reported. The import result shows
  *"Import Succeeded With Warnings"* in that case.
- **Export emits names**, not ids, for portability.

### Supported labels per class

| Class | Labels | Resolves against |
|-------|--------|------------------|
| Host | `groups`, `snapins`, `printers`, `modules`, `location`¹ | Group, Snapin, Printer, Module, Location — by name |
| Image | `storagegroups` | Storage Group — by name |
| Snapin | `storagegroups` | Storage Group — by name |
| Group | `hosts` | Host — by name (the group's members) |
| Printer | `hosts` | Host — by name (hosts the printer is assigned to) |
| User, Module, Storage Group, Storage Node | *(none)* | — |

¹ `location` is provided by the **Location** plugin and only appears when that
plugin is installed. A host has a single location, so only the first value is
used.

> **Caveat:** because `;`, `:` and `|` are structural, an object **name** that
> literally contains one of those characters can't currently be expressed in
> the associations cell. Such values are best referenced by **id**.

---

## Per‑class column layouts

Column order below is generated from the live schema and matches Export output
exactly. `associations` is always the optional final column where supported.

### Host

`primac` is required and first; `associations` is optional and last.

| # | Column | # | Column |
|---|--------|---|--------|
| 0 | `primac` (MAC list, `|`) | 16 | `printerLevel` |
| 1 | `name` | 17 | `kernelArgs` |
| 2 | `description` | 18 | `kernel` |
| 3 | `ip` | 19 | `kernelDevice` |
| 4 | `imageID` | 20 | `init` |
| 5 | `building` | 21 | `pending` |
| 6 | `createdTime` | 22 | `pub_key` |
| 7 | `deployed` | 23 | `sec_tok` |
| 8 | `createdBy` | 24 | `sec_time` |
| 9 | `useAD` | 25 | `pingstatus` |
| 10 | `ADDomain` | 26 | `biosexit` |
| 11 | `ADOU` | 27 | `efiexit` |
| 12 | `ADUser` | 28 | `enforce` |
| 13 | `ADPass` | 29 | `token` |
| 14 | `ADPassLegacy` | 30 | `tokenlock` |
| 15 | `productKey` | 31 | `associations` *(optional)* |

Associations: `groups`, `snapins`, `printers`, `modules`, `location`¹.

### Image

`0:name` `1:description` `2:path` `3:createdTime` `4:createdBy` `5:building`
`6:size` `7:imageTypeID` `8:imagePartitionTypeID` `9:osID` `10:deployed`
`11:format` `12:magnet` `13:protected` `14:compress` `15:isEnabled`
`16:toReplicate` `17:srvsize` `18:associations` *(optional → `storagegroups`)*

### Snapin

`0:name` `1:description` `2:file` `3:args` `4:createdTime` `5:createdBy`
`6:reboot` `7:shutdown` `8:runWith` `9:runWithArgs` `10:protected`
`11:isEnabled` `12:toReplicate` `13:hide` `14:timeout` `15:packtype`
`16:hash` `17:size` `18:anon3` `19:associations` *(optional → `storagegroups`)*

### Group

`0:name` `1:description` `2:createdBy` `3:createdTime` `4:building`
`5:kernel` `6:kernelArgs` `7:kernelDevice` `8:init`
`9:associations` *(optional → `hosts`)*

### Printer

`0:name` `1:description` `2:port` `3:file` `4:model` `5:config`
`6:configFile` `7:ip` `8:pAnon2` `9:pAnon3` `10:pAnon4` `11:pAnon5`
`12:associations` *(optional → `hosts`)*

### User

`0:name` `1:password` (stored encrypted) `2:createdTime` `3:createdBy`
`4:type` `5:display` `6:api` `7:token`

*(No associations column.)*

### Module

`0:name` `1:shortName` `2:description` `3:isDefault`

*(No associations column.)*

### Storage Group

`0:name` `1:description`

*(No associations column.)*

### Storage Node

`0:name` `1:description` `2:isMaster` `3:storagegroupID` `4:isEnabled`
`5:isGraphEnabled` `6:path` `7:ftppath` `8:bitrate` `9:helloInterval`
`10:snapinpath` `11:sslpath` `12:ip` `13:maxClients` `14:user` `15:pass`
`16:key` `17:interface` `18:bandwidth` `19:webroot` `20:graphcolor`

*(No associations column.)*

---

## Worked example (host)

The same host — assigned to two groups, three snapins and one printer — shown
both ways.

**Positional** (full column set, header‑less):

```csv
00:11:22:33:44:55|00:11:22:33:44:66,PC-Lab-01,Front lab PC,,4,,,,,0,,,,,,,5,,,,,0,,,,1,0,0,0,,,"groups:Lab A|Lab B;snapins:7zip|Chrome|VLC;printers:FrontDesk"
```

The first MAC is primary, the second is an additional MAC; the trailing quoted
field is the associations column.

**Headered** (only the columns you need, any order):

```csv
primac,name,description,associations
00:11:22:33:44:55|00:11:22:33:44:66,PC-Lab-01,Front lab PC,"groups:Lab A|Lab B;snapins:7zip|Chrome|VLC;printers:FrontDesk"
```

---

## Plugin extensibility

Plugins can register their own association types so they participate in both
import and export, without patching core:

- **`IMPORT_ASSOCIATIONS`** — fired while the per‑class association config is
  built. A hook receives `childClass` and a `config` array (by reference) and
  may add an entry:

  ```php
  $arguments['config']['mylabel'] = [
      'class'     => 'MyClass',    // resolved by id or name
      'namefield' => 'name',
      'get'       => 'myprop',     // item property holding ids (export);
                                   //   or a callable fn($item) returning names
      'apply'     => 'addMyThing', // item method taking an array of ids (import);
                                   //   or a callable fn($item, array $ids)
  ];
  ```

- **`EXPORT_ASSOCIATIONS`** — fired while a row's associations cell is built;
  receives the `parts` array (by reference) for last‑mile tweaks.

The Location plugin (`addlocationimport.hook.php`) is the reference
implementation, registering a single‑valued `location` type for hosts.

See issue [#828](https://github.com/FOGProject/fogproject/issues/828) for the
design discussion and history.
