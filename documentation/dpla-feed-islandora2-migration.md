# DPLA Feed → Islandora2 Migration Plan

**Branch:** `2026.02.0-DPLAFeed`
**File under change:** `vufind/web/services/API/ArchiveAPI.php`
**Status:** Layers A–C implemented on `2026.02.0-DPLAFeed`; end-to-end validation against a
live Islandora2 index still pending (see §8). Two follow-ups remain: confirm the I2 relation
vocabulary values used for DPLA role comparisons (publisher/owner/donor/acknowledgement), and
index `field_rights_org_statement` so the per-record node fetch can be dropped.

## 1. Scope

`API/ArchiveAPI.php` implements one feature — the **DPLA export feed** consumed by the
state library's harvest — as four members:

| Member | Type | Role |
|---|---|---|
| `getDPLAFeed()` | public | Builds the full DPLA document array for harvest |
| `getDPLACounts()` | public | Returns per-library record counts only |
| `getDPLASearchResults()` | private | Runs the two Solr queries (eligible collections, then eligible objects) |
| `mapFormat()` / `$formatMap` | private | Maps Pika format → DPLA type vocabulary |

Today it runs on the **Islandora 1** stack: `SearchObject_Islandora`, Fedora-backed
`IslandoraDriver`, PID identifiers, MODS/`RELS_EXT` Solr fields. The target is the
**Islandora 2** stack: `SearchObject_Islandora2`, `Islandora2Driver` + `I2Object`,
node-ID identifiers, Drupal/Solr `*_field_*` fields.

The migration has three layers:

- **A** — the two Solr queries in `getDPLASearchResults()`
- **B** — the per-record field/method mapping inside `getDPLAFeed()`
- **C** — the driver methods that must be added to `Islandora2Driver` so layer B has
  something to call

Layers A and B are mechanical once C exists.

## 2. Layer A — `getDPLASearchResults()` query migration

### 2.1 DPLA eligibility

The Islandora2 Solr field `ss_field_pika_dpla` carries the opt-in state, with values
`_none`, `no`, `yes`, `collection`:

- `yes` → **include**
- `no` / `_none` → **exclude**
- `collection` → **include iff a parent collection is `yes`**

`itm_field_member_of` is confirmed to hold the **full ancestor node-ID chain** (not just
the immediate parent), so the legacy two-query `ancestors_ms` pattern maps cleanly and
**no new ancestry field is required**.

**Query 1 — eligible collections:**

```
ss_model:Collection
ss_field_pika_dpla:yes
```

Collect the resulting `its_node_id`s → `$dplaCollectionNids`.

**Query 2 — eligible objects** (`$anc` = `itm_field_member_of:(nid1 OR nid2 OR …)` built
from Query 1):

```
ss_field_pika_dpla:yes
  OR (ss_field_pika_dpla:collection AND (<$anc>))
```

`no` and `_none` are excluded automatically because neither branch matches them. This is
the direct analogue of the legacy `dpla_s:yes OR (!dpla_s:no AND ($ancestors))`, but
tightened — `collection` is now an explicit opt-in rather than "anything not `no`."

### 2.2 Field/filter mapping

| Islandora 1 (current) | Islandora 2 (target) | Notes |
|---|---|---|
| `initSearchObject('Islandora')` | `initSearchObject('Islandora2')` | |
| `!RELS_EXT_isViewableByRole_literal_ms:administrator` | *(drop)* | Covered by `getStandardFilters()` |
| `!...pikaOptions_showInSearchResults_ms:no` | *(drop)* | Covered by `bs_pika_show_in_search:1` |
| `RELS_EXT_hasModel_uri_ms:"...collectionCModel"` | `ss_model:Collection` | Confirm exact term label |
| `...pikaOptions_dpla_s:yes` | `ss_field_pika_dpla` logic (§2.1) | |
| `ancestors_ms:"PID"` | `itm_field_member_of:<nid>` | Full ancestor chain |
| `!PID:person*/event*/organization*/place*` | *(drop)* | I2 entities are separate taxonomy docs; `ss_type:islandoraobject` excludes them |
| `!...:pageCModel` | *(drop)* | `!ss_model:Page` is in standard filters |
| `namespace_ms` (facet) | `ss_library` / `itm_field_library` (facet) | Drives `recordsByLibrary` + `getDPLACounts()` |
| `fgs_lastModifiedDate_dt:[changesSince TO *]` | `ds_changed:[changesSince TO *]` | I2 modified-date field |
| `sort fgs_lastModifiedDate_dt asc` | `sort ds_changed asc` | |
| `addFieldsToReturn([...MODS fields...])` | I2 return-field list (rights, extent, creator, dpla flag, member_of, legacy PID) | Rewrite |

`getDPLACounts()` only reads the facet, so once the facet key is switched it needs no
further change.

## 3. Layer B — `getDPLAFeed()` record-mapping migration

| Current call | Islandora 2 source | Status |
|---|---|---|
| `getArchiveObject()` null-check (auth guard) | `Islandora2Driver::getNodeData()` returns null | exists |
| `getUniqueID()` (PID) | new node-based `identifier` + labeled legacy PID (§4.2) | decision closed |
| `getTitle()` / `getDescription()` / `getFormat()` / `getBookcoverUrl()` / `getDateCreated()` | driver methods | exist |
| `getModsValue('languageTerm')` | `I2Object::getLanguage()` | exists on I2Object |
| `getSubTitle()` | `I2Object->subtitle` (magic prop) | needs driver passthrough |
| `getContributingLibrary()` → `[libraryName, baseUrl, pid]` | `I2Object::getLibraryOrganization()` + Pika `Library` row (§4.3) | shape differs — new mapping |
| `getBrandingInformation()` (partner orgs w/ roles) | `I2Object::getRelatedOrganization()` filtered by `relation` | needs mapping |
| `getRelatedCollections()` | `getParentCollection()` / `member_of` resolution | needs mapping |
| `getRelatedPlaces()` | `I2Object::getRelatedPlace()` | exists |
| `getRelatedPeople()` | `I2Object::getRelatedPerson()` | exists |
| `getRelatedOrganizations()` | `I2Object::getRelatedOrganization()` | exists |
| `getRelatedEvents()` | `I2Object::getRelatedEvent()` | exists |
| `getAllSubjectHeadings(false,0)` | `I2Object::getSubjects()` (reshape to name list) | exists |
| `initIslandoraDriverFromPid($pid)` (org/collection lookups) | `TaxonomyFactory::fromTid()` / `I2ObjectFactory::fromNodeId()` | swap factories |
| Rights: `mods_accessCondition_marmot_rightsStatementOrg_t` + parent-collection fallback | `field_rights_org_statement.uri` + default (§4.1) | fallback chain dropped |
| `mods_accessCondition_rightsHolder_entityTitle_ms` | `nodeData['rights_holder']` term name(s) | needs mapping |
| `mods_physicalDescription_extent_s` | `I2Object->extent` | exists |
| `mods_extension_marmotLocal_hasCreator_entityTitle_ms` | `nodeData['rights_creator']` / linked agents | needs mapping |

## 4. Layer C — new code required

### 4.1 Rights statement

Source is the node field `field_rights_org_statement.uri` (read from the `I2Object`,
since the feed already fetches the full node for related-entity data). Example raw shape:

```json
"field_rights_org_statement": {
  "uri": "http://rightsstatements.org/page/CNE/1.0/?language=en",
  "title": "http://rightsstatements.org/page/CNE/1.0/?language=en",
  "options": []
}
```

The legacy MODS-plus-parent-collection fallback is **dropped**. When the field is
absent/empty, emit the default `http://rightsstatements.org/page/CNE/1.0/`. Continue
stripping the `?language=en` param as today.

> **Index note (reindexer team):** `field_rights_org_statement` is **not yet a Solr
> field**. It should be indexed (e.g. `ss_rights_org_statement`) so a future version can
> avoid the per-record node fetch. For now the plan reads it from the node payload.

### 4.2 Identifier

The migration breaks PID continuity, so:

- `identifier` → the new Islandora2-based ID (node-based).
- Add a **separate, explicitly labeled** field carrying the legacy PID from
  `ss_legacy_pid` (e.g. `legacyIdentifier`) so harvest history stays traceable.

### 4.3 Contributing library

Legacy `getContributingLibrary()` keyed the Pika `Library` row by `archiveNamespace`
(PID prefix). That path is gone in I2. New derivation:
`I2Object::getLibraryOrganization()` (Corporate Body term → `name`, `tid`, `url`) joined
to the Pika `Library` row via `libraryTid` / `corporateBodyTid` to recover `baseUrl` for
`isShownAt`. The `getBrandingInformation()` partner-organization logic maps to
`getRelatedOrganization()` filtered by `relation`.

### 4.4 New `Islandora2Driver` methods

Thin passthroughs so `ArchiveAPI` stays driver-facing rather than reaching into
`I2Object`, each normalizing to the flat `['label' => …]` shape the DPLA loop expects:

- `getSubTitle()`, `getLanguage()`, `getSubjects()`
- `getRelatedPlaces()`, `getRelatedPeople()`, `getRelatedOrganizations()`,
  `getRelatedEvents()`, `getRelatedCollections()`
- a public contributing-library accessor returning `[libraryName, baseUrl, pid]`
- a public DPLA rights-statement resolver (node field → default)

## 5. Gap status

| Gap | Status |
|---|---|
| DPLA opt-in flag | Resolved — `ss_field_pika_dpla` (`_none`/`no`/`yes`/`collection`) |
| Collection ancestry | Resolved — `itm_field_member_of` is the full ancestor chain |
| Rights-statement Solr field | Read from node now; recommend indexing `field_rights_org_statement` later |
| Library base URL | Unchanged — Pika `Library` table via `libraryTid` |

## 6. Decisions closed

- **Scope:** in scope — migrate the feed.
- **Identifier:** new node-based `identifier` **plus** a labeled legacy-PID field from
  `ss_legacy_pid`.
- **Rights:** single node-field read with a default; no parent-collection walk.

## 7. Sequencing

1. Add the Layer C driver methods (spot-check against a known node).
2. Migrate `getDPLASearchResults()` (Layer A) — validate via `getDPLACounts()` first
   (smaller surface).
3. Migrate the `getDPLAFeed()` loop (Layer B) field-by-field, diffing output JSON against
   a current Islandora 1 sample for schema parity.
4. End-to-end validate against the DPLA schema the harvester expects.

## 8. Testing / validation notes

- Compare `getDPLACounts()` per-library totals before/after where an overlap window
  exists.
- Diff a sample `getDPLAFeed()` document against the legacy output; confirm the DPLA
  schema keys (`identifier`, `dataProvider`, `isShownAt`, `rights`, `subject`, etc.) are
  all still present and correctly shaped.
- Verify the `collection` opt-in path: an object flagged `collection` under a `yes`
  collection is included; the same object under a non-`yes` collection is excluded.
