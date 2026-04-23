# Archive2 Display Maps

How `ISLANDORA2_DISPLAY_MODEL_URL_MAP`, `ISLANDORA2_VOCAB_URL_MAP`, and `MODEL_VIEWER_MAP`
work together to determine the URL and viewer for an Archive object.

---

## 1. Building the URL — `ISLANDORA2_DISPLAY_MODEL_URL_MAP`

Defined in [sys/Islandora2/Functions.php](../vufind/web/sys/Islandora2/Functions.php) as a
constant array that translates an object's Drupal **display model** string into an Archive2
URL segment.

`getObjRelativeUrl()` does the work:

```
displayModel = strtolower($obj->getDisplayModel())     → e.g. "paged content"
lookup in map                                           → "PagedContent"
return "/Archive2/PagedContent/1234"
```

The resulting URL routes to `web/services/Archive2/PagedContent.php` via the standard
`module=Archive2&action=PagedContent&id=1234` dispatcher. Each map value corresponds to a
controller class under `web/services/Archive2/`.

---

## 2. Building Taxonomy URLs — `ISLANDORA2_VOCAB_URL_MAP`

Also in `Functions.php`, this map handles taxonomy terms (people, places, organizations,
events) rather than objects.

`getTaxonomyRelativeUrl()` uses it:

```
vocab = "geo_location"  →  segment = "Place"
return "/Archive2/Place?tid=456"
```

These link to browse/facet pages for taxonomy terms rather than object detail pages (note
the `?tid=` query param rather than a path segment).

---

## 3. Choosing the Viewer — `MODEL_VIEWER_MAP`

Defined in [services/Archive2/ArchiveObject.php](../vufind/web/services/Archive2/ArchiveObject.php),
this maps the **same display model strings** to a viewer type string, used by
`getViewerForModel()`:

| Display Model    | Viewer               |
|------------------|----------------------|
| `image`          | `open_seadragon`     |
| `book`           | `mirador`            |
| `paged content`  | `mirador`            |
| `digital document` | `pdfjs`            |
| `audio`          | `audio`              |
| `video`          | `video`              |
| `postcard`       | `open_seadragon_multi` |
| `compound object` | `compound`          |

The viewer string is passed to the Smarty template so it can render the correct embed
(OpenSeadragon, Mirador IIIF, PDF.js, etc.).

---

## The Full Flow

```
HTTP request: /Archive2/PagedContent/1234
       ↓
index.php dispatches → Archive2\PagedContent controller
       ↓
ArchiveObject::__construct() fetches node via I2ObjectFactory
       ↓
launch() calls getViewerForModel($obj->getDisplayModel())
         → MODEL_VIEWER_MAP → "mirador"
       ↓
Template renders Mirador IIIF viewer
```

Links within a page are built the other direction — the template calls
`getObjRelativeUrl($obj)`, which consults `ISLANDORA2_DISPLAY_MODEL_URL_MAP` to produce
`/Archive2/PagedContent/1234`, closing the loop.

---