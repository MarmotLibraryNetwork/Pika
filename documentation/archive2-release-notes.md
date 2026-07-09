# Islandora 2 / Archive2 Integration — Release Notes

**~290 commits across `Islandora2`, `2026.02.0-Islandora2`, and `2026.02.0` (March–July 2026)**

## Key Work Completed

### Taxonomy object model & display pages

- Built the Islandora 2 taxonomy object model with a shared `I2Taxonomy` base class, a `TaxonomyFactory`, a display controller, and shared metadata/related-object panel templates (D-5319)
- **People** (D-5009, D-5012): person taxonomy class and template, genealogy person ID extraction with obituary loading, military-service field handling, person notes display (D-5020), alternate-name arrays
- **Places** (D-5012): Place class, controller, and template with Google Maps API key support and description fallback
- **Events** (D-5011): EventTaxonomy model, controller, and detail template with EDTF-aware date methods
- **Organizations / corporate bodies** (D-5010): CorporateBodyTaxonomy model, controller, and template; library entities now resolve to corporate body terms (D-5371)
- Consolidated related-entity methods (related places, subjects, external links) into the base class with shared panel templates, eliminating duplicated logic in subclasses

### Archive2 framework — built from the ground up

The Islandora 2 integration layer is entirely new work: every class in `sys/Islandora2/` and all of the Archive2 service controllers (excepting the Term redirect controller).

- **Object framework** (`sys/Islandora2/`, ~29 classes) — the `I2Object` abstract base and `I2ObjectFactory`, with a typed object class per content model (Audio, Video, Image, Collection, Compound, DigitalDocument, Newspaper, Page, PagedContent, PublicationIssue, Binary); the `I2Taxonomy` base with vocabulary subclasses and `TaxonomyFactory`; media wrappers and contracts (`I2Media`, `MediaObjectInterface`, `TaxonomyObjectInterface`); and shared helpers (`Functions.php`, caption/transcript traits)
- **API layer** — `AbstractApiClient` base with the `Request` client for Islandora REST endpoints and `JsonApiClient` for server-side filtered JSON:API queries
- **Display services** (`services/Archive2/`) — the `ArchiveObject` base controller plus a controller per content model and taxonomy type (Audio, Video, Image, Collection, Compound, DigitalDocument, PagedContent, Postcard, Book, Magazine, Node, Person, Place, Organization, Event), along with the Home, Results, AJAX, ClaimAuthorship, and RequestCopy services
- Extended `I2Object` over the release cycle with child-object retrieval, parent-collection lookup, thumbnail/absolute URL helpers, creation-date retrieval, curator weight, and magic getter support, with comprehensive PHPDoc throughout
- Centralized display-model mapping into shared constants with URL-map validation, and added mappings for academic papers, documents, compound objects, collections, and pages; refactored Book to extend DigitalDocument

### API client & performance

- Extracted shared API infrastructure into `AbstractApiClient` and added a `JsonApiClient` for server-side filtered JSON:API taxonomy queries (D-5025)
- Added response caching for Islandora 2 API requests, with a follow-up fix to never cache empty bodies or 404s (D-5362); fixed PSR-16 compliance bugs in `Pika\Cache`
- Replaced whole-tree child iteration with paginated API fetches (D-5021), batch geolocation queries (D-5022), generator-based streaming of child nodes, and deferred field-prefix stripping for lower memory use

## Collections & Custom Collections

### Collection display types

Each collection dispatches to one of four curator-configured display types (set on the node in the Islandora admin, with a `?style=` URL parameter for previewing a type before configuring it):

- **Basic** — collection description with a paginated grid of child objects
- **Timeline** (D-5023, D-5024) — children grouped by date with decade filter buttons, powered by EDTF date parsing/humanization and AJAX loading
- **Map / Map-without-timeline** (D-5022, D-5372) — children with coordinates plotted as markers; marker clicks filter the object grid by place, date buttons filter it by time
- **Custom** (D-5025) — a curator-composed page assembled from the component library below

### Core collection functionality

- **API-driven pagination** (D-5021) — child objects load page-by-page through the Islandora JSON:API instead of fetching the whole tree
- **Grid/list view toggle with persistence** (D-5021, D-5398) — patrons switch between tile and list views; the choice is stored in an HTTP cookie and survives page loads and AJAX timeline reloads
- **Curator-controlled ordering** (D-5409, D-5255, D-5026) — child objects sort by curator-assigned weight, with collection-order resolution and item limiting for scrollers
- **Parent-collection breadcrumbs** (D-5334) via a new `getParentCollection()` method
- **Multi-collection aggregation** (D-5372, D-5024) — maps and timeline grids can combine children from several collections into one view (e.g. a custom map option of `map|nid1,nid2,nid3`)
- **Performance** (D-5022, D-5025) — related-entity aggregation moved to server-side filtered JSON:API queries, batch geolocation fetching for place markers, generator-based streaming of child nodes, and per-collection term counts computed in the same queries

### Custom collection component library

Curators compose custom collection pages by adding pipe-delimited options (`field_pika_coll_options`) on the collection node; the page layout itself is template-driven (`collection_custom.tpl`), so arrangement is controlled per-theme rather than by option order. Components shipped:

| Component | Option syntax | What it does |
|---|---|---|
| Search box | `searchCollection\|<image>` | Search scoped to the collection, with optional feature image |
| Map | `map\|<nid,nid,…>` | Google or Leaflet map of child markers; no list = this collection's own children; a list aggregates multiple collections. Includes the filterable object grid beneath |
| Scroller | `scroller\|<nid>` | Horizontal scroller of a child collection's items (or the current collection's if bare), limited and weight-ordered |
| Browse titles | `browseCollectionByTitle\|<nid>` | A child collection's items listed by title, ordered by per-item curator weight |
| Random image | `randomImage\|<nid,nid,…>` | A uniformly random featured object drawn from one of the listed collections |
| Browse all | `browseAllObjects` | The full children grid rendered inline with a pager (D-5385), replacing the old link-out-to-search |
| Browse by subject | `browseBySubject\|<title>\|<image>` | Deduplicated subjects from the collection and its children with per-collection object counts, each linking to a subject search scoped to the collection (D-5384) |
| Browse by related entity | `browseByRelated\|<person\|place\|organization\|event>\|<title>\|<image>` | Facet-driven browse boxes for related people, places, organizations, or events (D-5375) |

Browse-component counts come from a single Solr facet query using the same core and filters as the Archive2 Results page, so every displayed count matches the number of results its link returns; facet values containing quotes are escaped so names like `Claude Raymond "Ray" Monson` produce valid searches.

### Timelines & maps

- EDTF date parsing and humanization via the `professional-wiki/edtf` library (D-5024, D-5023)
- Reusable `CollectionTimelineData` loader, timeline component templates, and AJAX state management for a filterable, date-grouped object grid with dates shown on tiles and list rows
- Google Maps upgraded to v3 Advanced Markers, plus a new Leaflet map component as an alternative (D-5372)
- Collection maps render child-object markers from coordinate data, integrate with timeline filtering, and support custom map options over aggregated collections (D-5022)

## Media Support & Object Display

Every Archive2 object page routes through a shared wrapper that dispatches to a viewer template based on the object's content model, with viewing restrictions enforced before any viewer or media URL is rendered. Content models map to display services in `sys/Islandora2/Functions.php` and to viewers via `MODEL_VIEWER_MAP` in `services/Archive2/ArchiveObject.php`.

### Digital Document

- Renders PDFs in-browser with a **locally hosted PDF.js viewer** (no CDN dependency), embedded via iframe
- The PDF is never linked directly — it streams through an AJAX proxy (`fetchPDFFile`) that **enforces viewing restrictions** before serving the file (D-5030)
- Prefers the original media file, falling back to any PDF media on the node when the original is missing
- The `digital document`, `academic paper`, `document`, and `page` content types all route here; Book was refactored to extend DigitalDocument

### Paged Content

- Displays in the **Mirador viewer** for multi-page navigation
- The IIIF manifest is served through an AJAX endpoint (`fetchManifest`) that enforces viewing restrictions before returning it
- Viewer sizing/height mismatch corrected for proper embedding

### Image

- Displays in **OpenSeadragon** for deep-zoom viewing, backed by the **Cantaloupe IIIF image server**
- Security-conscious URL construction: because Cantaloupe has no access control of its own, the IIIF `info.json` URL is only built after the patron passes Pika's viewing-restriction check — restricted image URLs never reach the page
- Viewer sizing improvements: removed hardcoded zoom levels, responsive resize, larger image presentation (D-5025)
- Postcards get a dedicated **multi-image OpenSeadragon** viewer (front/back)

### Compound Objects

The Compound service inspects all children and picks a display strategy:

- **Compound Audio** — if every child is audio (and there's more than one): a single shared player with a **track-list playlist**. Selecting a track swaps the source, preserves playback position, and rebuilds that child's caption tracks from per-track JSON data. A shared on-page caption readout displays live VTT cues
- **Compound Video** — if every child is video: the same playlist pattern with per-child poster images and per-child caption tracks
- **Compound Image / mixed compounds** — anything else renders each child inline with its own viewer, dispatched per child's content model through helper templates: images → OpenSeadragon, video → video player, audio → audio player, documents → PDF.js, paged content/book → Mirador, with a visible notice for unmapped types

Supporting fixes: broken child-object retrieval repaired, null-safe media access, and child-access methods renamed and consolidated in the base `I2Object` class.

### Video (D-5379, D-5380)

- Full-width HTML5 player with download controls suppressed (`controlslist="nodownload"`)
- **Plays the service file by default** rather than the original (D-5379) — smaller derivative files for streaming
- Poster image unified through `getThumbnail()`, replacing the separate `getVideoPoster` path (D-5380)
- VTT captions attach as native `<track kind="captions">` elements with language labels; transcripts (text or PDF) are loaded alongside
- Launch-order reorganization and null-safe media handling when a video file is missing

### Audio (D-5027)

- HTML5 audio player with a **poster/cover image** above it (object-fit styling, proper sizing, alt text)
- Because `<audio>` elements can't render caption tracks natively, a custom player script displays **live VTT caption text on the page**, updating on cue changes — with detection for the patron toggling captions off via the native controls
- Caption files load through the `fetchVtt` AJAX proxy so viewing restrictions apply
- Download controls suppressed; obsolete download button removed

### VTT Caption & Transcript Support

- A shared `CaptionAndTranscriptTraits` trait gives all media objects:
  - `getCaptions()` — returns media entries flagged **Caption or Subtitle** with MIME `text/vtt`
  - `getTranscripts()` — returns media entries flagged **Transcript** as plain text or PDF
- Captions come from the VTT files catalogers attach as media on the Islandora node — multiple language tracks are supported, each labeled in the player
- The `fetchVtt` AJAX endpoint retrieves VTT files server-side via cURL and **enforces per-object viewing restrictions** before serving them
- Caption support spans all four A/V displays: single video, single audio, compound video, and compound audio (per-child tracks)

### Downloads (cross-cutting)

- Single download permission replaced with **granular per-file flags**: "Download Original File" and "Download Intermediate File" buttons render independently based on the object's permissions (D-5340)
- Corrected small-file download delivery (D-5336); original-file download temporarily disabled pending policy; all A/V players marked no-download

## Migration & Administration

- Legacy Islandora 1 → Islandora 2 redirect service so old archive URLs keep working (D-5322)
- Staff-only admin panel on Archive2 metadata display and a `debugArchiveObject` default (D-5362)
- Standardized Archive2 URL generation utilities and archive request permission checks (D-5340)

## Improved Functionality Highlights

- **Dedicated pages for people, places, events, and organizations** — archive taxonomy terms are now first-class destinations with metadata, related objects, and (for people) obituaries pulled from genealogy records
- **Four ways to present a collection** — simple grid, date-grouped timeline, interactive map, or a fully custom curated page
- **Curator-composed custom collections** — pages built from reusable components (search, maps, scrollers, browse-by boxes, random featured images) without any code changes
- **Accurate browse boxes** — browse-by-subject and browse-by-people/places/organizations components show object counts that match their linked search results
- **Interactive timelines** — collection objects can be explored by date with human-readable EDTF dates, filterable groupings, and AJAX loading
- **Modern maps** — collections plot their objects geographically using Google Advanced Markers or Leaflet, with markers linked to timeline filters
- **Combined collection views** — related collections can be presented together in one aggregated timeline, map, or grid
- **Remembered preferences** — grid/list display choice persists across pages and reloads
- **Rich media viewing** — in-browser PDF.js documents, deep-zoom IIIF images, Mirador paged content, and HTML5 audio/video players with poster images
- **Compound object playlists** — multi-track audio and video objects play in a single player with track switching that preserves playback position
- **Accessible A/V** — VTT captions and subtitles in multiple languages across all audio and video displays, live on-page caption text for audio, and text/PDF transcripts
- **Protected media delivery** — images, PDFs, manifests, and caption files are all served through viewing-restriction checks; downloads are governed by per-file permissions
- **Faster page loads** — API response caching, batched and filtered JSON:API queries, and streaming pagination dramatically cut request counts and memory use on large collections
- **Seamless migration** — links to the old Islandora 1 archive automatically redirect to their Islandora 2 equivalents
- **Staff tooling** — an admin metadata panel on archive objects aids cataloging and troubleshooting
