# Architecture decision records

One decision per file, numbered in the order they were taken. A record is
written when a decision is **hard to reverse**, **surprising without context**,
and **the result of a real trade-off** — if any of the three is missing, the
reasoning belongs in a comment beside the code instead.

This index exists because forty-three records with no way in is forty-three records
nobody reads. GH-1684 asked a question ADR 0040 had already answered and
rejected by name; that is the failure mode a list of titles prevents.

**Amendments are part of the record.** Several ADRs here carry a
`## Amended <date>` section that narrows or reverses something in the body
below it — 0036 and 0040 both do. Read the amendments first; the body is what
was decided originally, not necessarily what stands.

Status is summarized here and stated in full in each file.

| # | Decision | Status |
|---|---|---|
| 0001 | [Group association state is a derived tri-state; modules count only *enabled* hosts](0001-group-association-state.md) | superseded |
| 0002 | [DHCP engine: detect-and-prefer Kea, fall back to ISC, never auto-switch an existing install](0002-kea-dhcp-engine-selection.md) | accepted |
| 0003 | [The settings-cache flush dir is sticky world-writable, not owned by one web user](0003-settings-cache-flush-dir-perms.md) | accepted |
| 0004 | [Server-rendered chrome is refreshed via a second fragment request, not rebuilt in-place](0004-ajax-refresh-server-chrome.md) | accepted |
| 0005 | [Role-based permissions are native; the accesscontrol plugin is retired](0005-native-rbac-retires-accesscontrol-plugin.md) | accepted |
| 0006 | [Per-object scoping is a plugin boundary layered on native RBAC](0006-site-object-scope-boundary.md) | accepted |
| 0007 | [External identity is provenance; authority comes from roles, mapped per directory group](0007-external-identity-provenance-and-directory-group-mapping.md) | accepted |
| 0008 | [The Secure Boot enrollment task type, and the narrow case it is actually for](0008-secure-boot-enrolment-task-type.md) | accepted |
| 0009 | [Plugins become installable artifacts with a lifecycle of their own](0009-plugins-become-installable-artifacts.md) | accepted |
| 0010 | [A single core daemon runs plugin-declared background work](0010-plugin-background-work.md) | accepted |
| 0011 | [Route hands results back as values, and raises instead of exiting](0011-route-result-wrappers.md) | accepted |
| 0012 | [A self-rescheduling poll guards on its own widget](0012-self-rescheduling-polls-guard-on-their-own-widget.md) | accepted |
| 0013 | [A flat `FOG\` namespace, and the reverse alias as the 1.6 plugin ABI](0013-flat-fog-namespace-and-the-reverse-alias-abi.md) | accepted |
| 0014 | [Authentication seams live in core; identity providers are plugins](0014-authentication-seams-in-core-identity-providers-as-plugins.md) | accepted |
| 0015 | [The install settings are independent keys, not one compound value](0015-install-settings-are-independent-keys.md) | accepted |
| 0016 | [iPXE enforces X.509 name constraints, rather than FOG weakening them](0016-ipxe-enforces-x509-name-constraints.md) | accepted |
| 0017 | [The hook dispatch contract](0017-hook-dispatch-contract.md) | accepted |
| 0018 | [Netboot addresses this server by the name in its certificate](0018-netboot-addresses-this-server-by-its-certificate-name.md) | accepted |
| 0019 | [Object scope is a property of the request, and it lives in the query](0019-object-scope-is-a-property-of-the-request-and-lives-in-the-query.md) | accepted |
| 0020 | [Event logs share a record shape, not a table](0020-event-logs-share-a-record-shape-not-a-table.md) | accepted, implemented |
| 0021 | [The audit trail: a header at the authorization seam, changes beside it](0021-the-audit-trail.md) | accepted, implemented |
| 0022 | [Spans and work items are different things; only one of these tables is a span](0022-spans-and-work-items.md) | accepted |
| 0023 | [Activity is a filtered view of one log; retention is a registry, not a page](0023-activity-is-a-view-and-retention-is-a-registry.md) | accepted |
| 0024 | [`.fogsettings` keys are namespaced by the subsystem that owns them](0024-fogsettings-unified-key-model.md) | accepted |
| 0025 | [One boolean encoding in `.fogsettings`, normalized on load](0025-one-boolean-encoding-normalised-on-load.md) | accepted |
| 0026 | [Retention runs in a daemon named for retention](0026-retention-runs-in-a-daemon-named-for-retention.md) | accepted |
| 0027 | [API tokens are a separate, hashed credential](0027-api-tokens-are-a-separate-hashed-credential.md) | proposed |
| 0028 | [A boolean column is `TINYINT(1)`, not `enum('0','1')`](0028-booleans-are-tinyint-not-enum.md) | accepted |
| 0029 | [The Secure Boot ledger is one observed half and one asserted half, and neither is a security control](0029-the-secure-boot-ledger-is-observed-and-asserted-and-neither-is-a-control.md) | accepted |
| 0030 | [A report is an aggregation over a window, not a grid over a table](0030-a-report-is-an-aggregation-over-a-window.md) | accepted |
| 0031 | [Referential integrity is declared in the database, not remembered in PHP](0031-referential-integrity-is-declared-in-the-database.md) | accepted, implemented |
| 0032 | [A saved filter is shared by grant, and a grant is a row per target kind](0032-a-saved-filter-is-shared-by-grant-not-by-visibility.md) | accepted, implemented |
| 0033 | [Impersonation is a second identity, not a replaced one](0033-impersonation-is-a-second-identity-not-a-replaced-one.md) | accepted, implemented |
| 0034 | [One authority decides whether a node exists](0034-one-authority-decides-whether-a-node-exists.md) | accepted, implemented |
| 0035 | [A plugin is laid out like core](0035-a-plugin-is-laid-out-like-core.md) | accepted, implemented |
| 0036 | [The web tier changes PKI through a fixed verb set, never through a path](0036-the-web-tier-changes-pki-through-a-fixed-verb-set.md) | accepted, implemented |
| 0037 | [The PKI tree lives in /etc/fog/pki, reached at its old name by a symlink](0037-the-pki-tree-lives-in-etc.md) | accepted, implemented |
| 0038 | [A group grants snapins and printers; it copies nothing](0038-a-group-grants-it-does-not-copy.md) | accepted, implemented |
| 0039 | [A booting machine is identified by its MAC first and its firmware second, and the firmware ships in log mode](0039-a-booting-machine-is-identified-by-firmware-second.md) | accepted |
| 0040 | [Certificates you bring live in /etc/fog/customizations/pki](0040-certificates-you-bring-live-in-a-customizations-tree.md) | accepted, implemented |
| 0041 | [A boot file is what its bytes say, not what its name says](0041-a-boot-file-is-what-its-bytes-say-not-what-its-name-says.md) | accepted |
| 0042 | [The filesystem is the inventory; the database records judgments about it](0042-the-filesystem-is-the-inventory-the-database-records-judgments.md) | accepted |
| 0043 | [A host proves itself with a key, not a MAC and a shared token](0043-a-host-proves-itself-with-a-key-not-a-mac.md) | proposed |
