<?php

/*
 * Pika Discovery Layer
 * Copyright (C) 2026  Marmot Library Network
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace Archive2;

require_once ROOT_DIR . '/services/Archive2/ArchiveObject.php';
require_once ROOT_DIR . '/sys/Islandora2/CollectionObject.php';
require_once ROOT_DIR . '/sys/Islandora2/I2ObjectFactory.php';
require_once ROOT_DIR . '/sys/Islandora2/Functions.php';
require_once ROOT_DIR . '/sys/Archive2/CollectionTimelineData.php';
require_once ROOT_DIR . '/sys/Pager.php';

use Islandora2\CollectionObject;
use Islandora2\I2ObjectFactory;
use Islandora2\Request;

class Collection extends ArchiveObject
{
    /** Maximum number of items rendered into a single scroller carousel. */
    private const SCROLLER_ITEM_LIMIT = 24;

    /** Maximum number of collections a map may aggregate markers from. */
    private const MAX_MAP_COLLECTIONS = 100;

    /**
     * Dispatches to the appropriate collection display template based on the
     * collection's configured display type (basic, timeline, map, or custom).
     */
    public function launch()
    {
        global $interface;

        parent::launch();
        self::assignCollectionDisplayMode();

        /** @var CollectionObject $collection */
        $collection  = $this->mediaObject;
        // ?style= override mirrors the old Archive/Exhibit.php behavior (handy
        // for previewing a display type before configuring it on the node)
        $displayType = $_REQUEST['style'] ?? $collection->getCollectionDisplay() ?? 'basic';
        $nid         = $collection->getNodeId();

        $thumb = $collection->getThumbnail();
        $interface->assign('thumbnail',      $thumb ? $thumb->thumbnailUrl : null);
        $interface->assign('thumbnail_link', $collection->getCollectionThumbnailLink());
        $interface->assign('nid',            $nid);

        // $displayType will be all lower case. In the Islandora admin interface 
        // it is camel case, but the value is all lower. 
        switch ($displayType) {
            case 'timeline':
                $this->loadTimelineData([$nid], true, true);
                return parent::display('collection_timeline.tpl', $collection->getTitle());
            case 'map':
                $this->loadTimelineData([$nid], true, true);
                $this->loadMapData([$nid]);
                return parent::display('collection_map.tpl', $collection->getTitle());
            case 'mapnotimeline':
                $this->loadTimelineData([$nid], true, true);
                $this->loadMapData([$nid]);
                return parent::display('collection_map.tpl', $collection->getTitle());
            case 'custom':
                $this->loadCustomComponents($nid);
                return parent::display('collection_custom.tpl', $collection->getTitle());
            default: // 'basic'
                $this->loadChildrenData($nid);
                return parent::display('collection_basic.tpl', $collection->getTitle());
        }
    }

    /**
     * Loads the initial (unfiltered, page 1 unless requested) state of the
     * filterable child-object grid used by the timeline and map displays.
     * Subsequent filter/place/page changes are AJAX reloads of the same
     * templates via Archive2_AJAX::getCollectionTimelineObjects().
     *
     * @param int[] $collectionNids Node ids whose children populate the grid (one for the
     *                              top-level displays; several for an aggregated custom map).
     * @param bool  $showTimeline   Whether the decade date-filter buttons are shown.
     * @param bool  $groupByYear    Whether the grid groups items under year headings.
     */
    private function loadTimelineData(array $collectionNids, bool $showTimeline, bool $groupByYear = false): void
    {
        global $interface;
        $page = max(1, (int)($_REQUEST['page'] ?? 1));
        $data = CollectionTimelineData::load($collectionNids, null, null, $page);
        CollectionTimelineData::assignToInterface($data, $collectionNids, $showTimeline);
        $interface->assign('groupByYear', $groupByYear);
    }

    /**
     * Fetches a paginated page of child objects for the collection and assigns
     * them — along with pager links and record-count metadata — to the template.
     *
     * @param int $nid Node ID of the parent collection.
     */
    private function loadChildrenData(int $nid): void
    {
        global $interface;
        /** @var CollectionObject $collection */
        $collection = $this->mediaObject;
        $limit      = 24;
        $page       = max(1, (int)($_REQUEST['page'] ?? 1));
        $total      = $collection->getTotalChildCount();
        $children   = $collection->getChildObjectsPaginated($page, $limit);

        $collectionChildren = [];
        foreach ($children as $child) {
            $thumbMedia      = $child->getThumbnail();
            $collectionChildren[] = [
                'nid'         => $child->getNodeId(),
                'title'       => $child->getTitle(),
                'url'         => getObjRelativeUrl($child),
                'thumbnail'   => $thumbMedia ? $thumbMedia->thumbnailUrl : '',
                'date'        => $child->getDateCreated('Y'),
                'description' => strip_tags($child->getDescription() ?? ''),
            ];
        }

        $pager = new \VuFindPager([
            'totalItems' => $total,
            'fileName'   => '/Archive2/Collection/' . $nid . '?page=%d',
            'perPage'    => $limit,
        ]);

        $interface->assign('collectionChildren', $collectionChildren);
        $interface->assign('recordCount',  $total);
        $interface->assign('recordStart',  ($page - 1) * $limit + 1);
        $interface->assign('recordEnd',    min($page * $limit, $total));
        $interface->assign('page',         $page);
        $interface->assign('pageLinks',    $pager->getLinks());
    }

    /**
     * Resolves geolocation data for the places related to one or more collections
     * and assigns mapped/unmapped place lists, bounding-box coordinates, and the
     * configured map zoom level to the template.
     *
     * Collections nested inside the listed ones are expanded first, so a
     * "collection of collections" (e.g. nid 684) draws its markers from the
     * sub-collections' children rather than the marker-less sub-collection
     * nodes themselves.
     *
     * @param int[] $collectionNids Node ids whose children's places/markers populate
     *                              the map (one for the top-level map displays; several
     *                              for a custom `map|a,b,c` option).
     */
    private function loadMapData(array $collectionNids): void
    {
        global $interface, $configArray;
        /** @var CollectionObject $collection */
        $collection = $this->mediaObject;

        $collectionNids = $this->expandWithDescendantCollections($collectionNids);

        // Aggregate related places (deduped by tid, counts summed) and geocoded child
        // markers (deduped by nid) across every listed collection.
        $factory       = new I2ObjectFactory();
        $places        = [];
        $childMarkers  = [];
        $seenMarkerNid = [];
        foreach ($collectionNids as $cnid) {
            $source = ($cnid === $collection->getNodeId())
                ? $collection
                : $factory->fromNodeId((int)$cnid);
            if (!($source instanceof CollectionObject)) {
                continue;
            }
            foreach ($source->getCollectionRelatedPlaces() as $place) {
                $tid = $place['tid'];
                if (isset($places[$tid])) {
                    $places[$tid]['count'] += $place['count'];
                } else {
                    $places[$tid] = $place;
                }
            }
            foreach ($source->getChildMarkers() as $marker) {
                if (!isset($seenMarkerNid[$marker['nid']])) {
                    $seenMarkerNid[$marker['nid']] = true;
                    $childMarkers[] = $marker;
                }
            }
        }
        $places = array_values($places);

        $mappedPlaces   = [];
        $unmappedPlaces = [];
        $latSum = $lngSum = $n = 0;
        $minLat = $maxLat = $minLng = $maxLng = null;

        // Fetch every place's coordinates in one batched JSON:API query keyed by tid,
        // rather than a per-term taxonomy lookup (which was an N+1 over the places).
        $geoByTid = $collection->getPlaceGeolocations(array_column($places, 'tid'));
        foreach ($places as $place) {
            $geo   = $geoByTid[$place['tid']] ?? null;
            $entry = ['tid' => $place['tid'], 'label' => $place['name'], 'url' => $place['url'], 'count' => $place['count']];
            if ($geo && isset($geo['lat'], $geo['lng'])) {
                $entry['latitude']  = $geo['lat'];
                $entry['longitude'] = $geo['lng'];
                $latSum += $geo['lat'];
                $lngSum += $geo['lng'];
                $n++;
                $minLat = min($minLat ?? $geo['lat'], $geo['lat']);
                $maxLat = max($maxLat ?? $geo['lat'], $geo['lat']);
                $minLng = min($minLng ?? $geo['lng'], $geo['lng']);
                $maxLng = max($maxLng ?? $geo['lng'], $geo['lng']);
                $mappedPlaces[$place['tid']] = $entry;
            } else {
                $unmappedPlaces[$place['tid']] = $entry;
            }
        }

        // Extend the bounds/center with the geocoded child markers aggregated above.
        foreach ($childMarkers as $child) {
            $lat = $child['latitude'];
            $lng = $child['longitude'];
            $latSum += $lat;
            $lngSum += $lng;
            $n++;
            $minLat = min($minLat ?? $lat, $lat);
            $maxLat = max($maxLat ?? $lat, $lat);
            $minLng = min($minLng ?? $lng, $lng);
            $maxLng = max($maxLng ?? $lng, $lng);
        }

        $nodeFields = $collection->getNodeWithoutFieldPrefix();
        $mapZoom    = $nodeFields['pika_map_zoom'] ?? 9;
        $mapsKey    = $configArray['Maps']['apiKey'] ?? '';

        $interface->assign('mapsKey',        $mapsKey);
        $interface->assign('mappedPlaces',   $mappedPlaces);
        $interface->assign('unmappedPlaces', $unmappedPlaces);
        $interface->assign('childMarkers',   $childMarkers);
        $interface->assign('mapZoom',        $mapZoom);
        $interface->assign('minLat',         $minLat);
        $interface->assign('maxLat',         $maxLat);
        $interface->assign('minLong',        $minLng);
        $interface->assign('maxLong',        $maxLng);
        if ($n > 0) {
            $interface->assign('mapCenterLat',  $latSum / $n);
            $interface->assign('mapCenterLong', $lngSum / $n);
        }
    }

    /**
     * Iterates over the collection's configured component options (search box,
     * map, browse scroller, random image, etc.) and assigns the data and presence
     * flags each component needs. The components themselves are laid out and
     * {include}d by collection_custom.tpl, so arrangement is controlled in the
     * template rather than by the order of the configured options.
     *
     * @param int $nid Node ID of the collection whose options are being loaded.
     */
    private function loadCustomComponents(int $nid): void
    {
        global $interface;
        /** @var CollectionObject $collection */
        $collection = $this->mediaObject;
        $options    = $collection->getCollectionOptions() ?? [];
        $factory    = new I2ObjectFactory();

        // Layout is owned by collection_custom.tpl. This method only decides which
        // components are present (still driven by the curator's field_pika_coll_options)
        // and gathers their data. Singletons are exposed as boolean flags; repeatable
        // components as arrays the template loops over and {include}s, passing each
        // instance's data as local include variables to avoid collisions on the shared
        // variable names the component templates read.
        $showSearchComponent    = false;
        $showMapComponent       = false;
        $showBrowseAll          = false;
        $scrollerComponents     = [];
        $browseTitleComponents  = [];
        $browseFilterComponents = [];
        $browseByComponents     = [];
        $browseSubjectComponents = [];
        $browseRelatedComponents = [];
        $randomImageComponents  = [];

        foreach ($options as $option) {
            // Tolerate stray whitespace in the stored option (e.g. " scroller|18784").
            $parts = array_map('trim', explode('|', trim($option)));
            $type  = $parts[0];

            if ($type === 'searchCollection') {
                $interface->assign('searchComponentImage',
                    $parts[1] ?? '/interface/themes/responsive/images/search_component.png');
                $showSearchComponent = true;

            } elseif ($type === 'googleMap' || $type === 'map') {
                // The list after the pipe names collections whose children populate
                // the map; no list → the current collection's own children.
                $mapNids = $this->parseCollectionNids($parts[1] ?? '', $nid);
                $this->loadMapData($mapNids);
                // Filterable child grid below the map: marker clicks filter it by
                // place and the decade date-filter buttons filter it by time. It
                // aggregates the same collections as the map, grouped under year
                // headings like the timeline display.
                $this->loadTimelineData($mapNids, true, true);
                $showMapComponent = true;

            } elseif ($type === 'scroller') {
                // "scroller|<nid>" shows that child collection's children; a bare
                // "scroller" shows the current collection's own children.
                $childNid = (int)($parts[1] ?? 0);
                $source   = $childNid > 0 ? $factory->fromNodeId($childNid) : $collection;
                $items    = [];
                $title    = '';
                $srcNid   = 0;
                if ($source instanceof CollectionObject) {
                    $children = $source->getOrderedChildObjects(self::SCROLLER_ITEM_LIMIT);
                    foreach ($children as $obj) {
                        $items[] = [
                            'nid'       => $obj->getNodeId(),
                            'title'     => $obj->getTitle(),
                            'url'       => getObjRelativeUrl($obj),
                            'thumbnail' => $obj->getThumbnailUrl(),
                        ];
                    }
                    $title  = $source->getTitle();
                    $srcNid = $source->getNodeId();
                } elseif ($source !== null) {
                    // A scroller pointed at a single object: show it as one tile.
                    $items  = [[
                        'nid'       => $source->getNodeId(),
                        'title'     => $source->getTitle(),
                        'url'       => getObjRelativeUrl($source),
                        'thumbnail' => $source->getThumbnailUrl(),
                    ]];
                    $srcNid = $source->getNodeId();
                }
                if (!empty($items)) {
                    $scrollerComponents[] = [
                        'title' => $title,
                        'items' => $items,
                        'nid'   => $srcNid,
                    ];
                }

            } elseif ($type === 'browseCollectionByTitle') {
                $childNid = (int)($parts[1] ?? 0);
                if ($childNid > 0) {
                    $childCollection = $factory->fromNodeId($childNid);
                    if ($childCollection instanceof CollectionObject) {
                        // Titles follow the same curator-defined order as the rest of
                        // the collection: field_pika_coll_order first, falling back to
                        // field_weight for any members it doesn't name.
                        $children = $childCollection->getOrderedChildObjects();
                        $childItems = [];
                        foreach ($children as $obj) {
                            $childItems[] = [
                                'nid'       => $obj->getNodeId(),
                                'title'     => $obj->getTitle(),
                                'url'       => getObjRelativeUrl($obj),
                                'thumbnail' => $obj->getThumbnailUrl(),
                            ];
                        }
                        $browseTitleComponents[] = [
                            'title' => $childCollection->getTitle(),
                            'items' => $childItems,
                        ];
                    }
                }

            } elseif ($type === 'randomImage') {
                $sourceNids   = !empty($parts[1])
                    ? array_map('trim', explode(',', $parts[1]))
                    : [$nid];
                $randomObject = self::pickRandomChildImage($sourceNids);
                if ($randomObject !== null) {
                    $randomImageComponents[] = [
                        // Distinguishes multiple randomImage components on one page
                        // (each targets its own placeholder for the reload button).
                        'id'         => $nid . '_' . count($randomImageComponents),
                        'sourceNids' => implode(',', $sourceNids),
                        'object'     => $randomObject,
                    ];
                }

            } elseif ($type === 'browseAllObjects') {
                // Render the collection's children as an inline paginated grid
                // (same data + pager as the basic display) instead of a link out
                // to a search. Paging is driven by ?page= on the collection URL.
                $this->loadChildrenData($nid);
                $showBrowseAll = true;

            } elseif ($type === 'browseFilter' || $type === 'browseEntityFilter') {
                $browseFilterComponents[] = [
                    'facet'  => $parts[1] ?? '',
                    'label'  => $parts[2] ?? '',
                    'image'  => $parts[3] ?? '/interface/themes/responsive/images/search_component.png',
                    'entity' => $type === 'browseEntityFilter',
                ];

            } elseif ($type === 'browseBy') {
                $subtype = strtolower($parts[1] ?? '');
                if ($subtype === 'place') {
                    $rawItems = $collection->getRelatedPlace() ?? [];
                    $urlBase  = '/Archive2/Place/';
                    $title    = $parts[2] ?? 'Browse by Place';
                } elseif ($subtype === 'organization') {
                    $rawItems = $collection->getRelatedOrganization() ?? [];
                    $urlBase  = '/Archive2/Organization/';
                    $title    = $parts[2] ?? 'Browse by Organization';
                } else {
                    continue;
                }
                $items = array_map(fn($item) => [
                    'name' => $item['name'],
                    'url'  => $urlBase . urlencode((string)$item['tid']),
                ], $rawItems);
                $half = (int)ceil(count($items) / 2);
                $browseByComponents[] = [
                    'title' => $title,
                    'col1'  => array_slice($items, 0, $half),
                    'col2'  => array_slice($items, $half),
                ];

            } elseif ($type === 'browseBySubject') {
                // Subjects from the collection and its children, each linking to a
                // subject search scoped to this collection. Format:
                // "browseBySubject|<optional title>|<optional image url>".
                $items = $this->buildFacetBrowseItems($nid, 'sm_subject');
                if (!empty($items)) {
                    $browseSubjectComponents[] = [
                        'title' => $parts[1] ?? 'Browse by Subject',
                        'image' => $parts[2] ?? '/interface/themes/responsive/images/search_component.png',
                        'items' => $items,
                        'id'    => $nid . '_' . count($browseSubjectComponents),
                    ];
                }

            } elseif ($type === 'browseByRelated') {
                // Related entities (person/place/organization/event) from the collection
                // and its children, each linking to a search scoped to this collection.
                // Format: "browseByRelated|<subtype>|<optional title>|<optional image url>".
                $subtype = strtolower($parts[1] ?? '');
                $solrFieldMap = [
                    'person'       => 'sm_related_person',
                    'place'        => 'sm_related_place',
                    'organization' => 'sm_related_organization',
                    'event'        => 'sm_related_event',
                ];
                if (isset($solrFieldMap[$subtype])) {
                    $solrField = $solrFieldMap[$subtype];
                    $items = $this->buildFacetBrowseItems($nid, $solrField);
                    if (!empty($items)) {
                        $browseRelatedComponents[] = [
                            'title' => $parts[2] ?? ('Browse by ' . ucfirst($subtype)),
                            'image' => $parts[3] ?? '/interface/themes/responsive/images/search_component.png',
                            'items' => $items,
                            'id'    => $nid . '_' . count($browseRelatedComponents),
                        ];
                    }
                }
            }
        }

        $interface->assign('showSearchComponent',    $showSearchComponent);
        $interface->assign('showMapComponent',       $showMapComponent);
        $interface->assign('showBrowseAll',          $showBrowseAll);
        $interface->assign('scrollerComponents',     $scrollerComponents);
        $interface->assign('browseTitleComponents',  $browseTitleComponents);
        $interface->assign('browseFilterComponents', $browseFilterComponents);
        $interface->assign('browseByComponents',     $browseByComponents);
        $interface->assign('browseSubjectComponents', $browseSubjectComponents);
        $interface->assign('browseRelatedComponents', $browseRelatedComponents);
        $interface->assign('randomImageComponents',  $randomImageComponents);
    }

    /**
     * Picks a uniformly random child object from across one or more source
     * collections and returns the display data for the "random image" component.
     * Shared by loadCustomComponents() (initial page render) and
     * Archive2_AJAX::getRandomImageComponent() (the reload button), so both pick
     * from the same logic.
     *
     * @param int[] $sourceNids Candidate collection node ids; one is chosen at
     *                          random, then one of its children is chosen at random.
     * @return array{title: string, url: string, thumbnail: string}|null Null if no
     *                          source collection has any children.
     */
    public static function pickRandomChildImage(array $sourceNids): ?array
    {
        if (empty($sourceNids)) {
            return null;
        }
        $factory      = new I2ObjectFactory();
        $randomNid    = (int)$sourceNids[array_rand($sourceNids)];
        $randomSource = $factory->fromNodeId($randomNid);
        if (!($randomSource instanceof CollectionObject)) {
            return null;
        }
        $total = $randomSource->getTotalChildCount();
        if ($total <= 0) {
            return null;
        }
        // number=1 means page N is item N, giving a uniform random pick across all children
        $response = (new Request())->fetchChildren($randomNid, rand(1, $total), 1);
        if (empty($response['children'])) {
            return null;
        }
        $randomObj = $factory->fromNode($response['children'][0]);
        if (!$randomObj) {
            return null;
        }
        $thumb = $randomObj->getThumbnail();
        return [
            'title'     => $randomObj->getTitle(),
            'url'       => getObjRelativeUrl($randomObj),
            'thumbnail' => $thumb ? $thumb->thumbnailUrl : '',
        ];
    }

    /**
     * Builds an Archive2 search-results URL filtered to a single facet value and
     * scoped to the given collection (members of $nid).
     *
     * Embedded double-quotes and backslashes in $value are backslash-escaped so the
     * Solr phrase query stays valid — SearchObject\Islandora2::setFinalFilterQuery()
     * wraps the parsed value in quotes without escaping, so a value like
     * Claude Raymond "Ray" Monson would otherwise produce a broken phrase.
     *
     * @param string $solrField Solr facet field (e.g. 'sm_subject', 'sm_related_place').
     * @param string $value     Raw facet value (term name).
     * @param int    $nid       Collection node id to scope results to.
     * @return string Relative Archive2 Results URL.
     */
    private function buildScopedFilterUrl(string $solrField, string $value, int $nid): string
    {
        $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
        return '/Archive2/Results?filter[]=' . $solrField . ':%22'
            . rawurlencode($escaped) . '%22'
            . '&filter[]=itm_field_member_of:' . $nid;
    }

    /**
     * Builds the browse-box items (name, per-collection object count, scoped search
     * URL) for a Solr facet field. Terms and counts come from a single Solr facet
     * query scoped to this collection's members, using the same search core and
     * standard filters as the Archive2 Results page — so each displayed count equals
     * the number of results its link returns. Terms that match no member object are
     * naturally excluded.
     *
     * @param int    $nid       Collection node id whose members to facet over.
     * @param string $solrField Solr facet field (e.g. 'sm_subject', 'sm_related_place').
     * @return array List of ['name' => string, 'count' => int, 'url' => string], sorted by name.
     */
    private function buildFacetBrowseItems(int $nid, string $solrField): array
    {
        $items = [];
        foreach ($this->getMemberFacetCounts($nid, $solrField) as $name => $count) {
            $items[] = [
                'name'  => $name,
                'count' => $count,
                'url'   => $this->buildScopedFilterUrl($solrField, $name, $nid),
            ];
        }
        return $items;
    }

    /**
     * Runs a facet-only Solr search over the members of a collection and returns
     * [term => count], sorted case-insensitively by term. Mirrors the genre-facet
     * pattern in Archive2\Home so the counts match the live Results page.
     *
     * @param int    $nid       Collection node id (itm_field_member_of value).
     * @param string $solrField Solr facet field to tally.
     * @return array<string,int> Facet term => object count.
     */
    private function getMemberFacetCounts(int $nid, string $solrField): array
    {
        /** @var \SearchObject_Islandora2 $searchObject */
        $searchObject = \SearchObjectFactory::initSearchObject('Islandora2');
        $searchObject->init();
        $searchObject->setDebugging(false, false);
        // Skip the browse box gracefully if Solr is unreachable rather than letting
        // processSearch() raise a fatal PEAR error that takes down the whole collection
        // page (mirrors the availability check in Search\Home).
        if (!$searchObject->pingServer(false)) {
            return [];
        }
        $searchObject->addFilter('itm_field_member_of:' . $nid);
        $searchObject->addFacet($solrField);
        $searchObject->setFacetLimit(-1); // all terms, not just the configured default
        $searchObject->setLimit(0);       // facet data only, no documents

        $response = $searchObject->processSearch(true, true);

        $counts = [];
        if (is_array($response) && empty($response['error'])
            && !empty($response['facet_counts']['facet_fields'][$solrField])) {
            foreach ($response['facet_counts']['facet_fields'][$solrField] as $facetValue) {
                // Each entry is [value, count].
                $counts[(string)$facetValue[0]] = (int)$facetValue[1];
            }
        }
        ksort($counts, SORT_FLAG_CASE | SORT_STRING);
        return $counts;
    }

    /**
     * Expand a list of collection nids with every collection nested inside them,
     * so maps aggregate markers from the nested collections' children too.
     *
     * The Solr index writes a node's full ancestor chain to itm_field_member_of,
     * so a single query scoped to the listed collections finds descendant
     * collections at every depth — matching how the timeline grid under the map
     * already includes all descendant objects. The search applies the standard
     * visibility filters, so collections hidden from search don't contribute
     * markers (consistent with the grid). When Solr is unreachable or errors,
     * the input list is returned unchanged and the map falls back to the
     * direct-children markers.
     *
     * @param int[] $nids Collection node ids.
     * @return int[] Input nids plus descendant collection nids, deduplicated,
     *               capped at MAX_MAP_COLLECTIONS.
     */
    private function expandWithDescendantCollections(array $nids): array
    {
        if (empty($nids)) {
            return $nids;
        }
        /** @var \SearchObject_Islandora2 $searchObject */
        $searchObject = \SearchObjectFactory::initSearchObject('Islandora2');
        $searchObject->init();
        $searchObject->setDebugging(false, false);
        if (!$searchObject->pingServer(false)) {
            return $nids;
        }
        if (count($nids) === 1) {
            $searchObject->addFilter('itm_field_member_of:' . $nids[0]);
        } else {
            // Complex OR filters are passed through raw by Base::parseFilter.
            $searchObject->addFilter('itm_field_member_of:(' . implode(' OR ', $nids) . ')');
        }
        $searchObject->addFilter('ss_model:"Collection"');
        $searchObject->addFieldsToReturn(['its_node_id']);
        $searchObject->setLimit(self::MAX_MAP_COLLECTIONS);

        $response = $searchObject->processSearch(true, false);
        if (\PEAR_Singleton::isError($response) || !empty($response['error'])) {
            return $nids;
        }
        foreach ($response['response']['docs'] ?? [] as $doc) {
            $childNid = (int)($doc['its_node_id'] ?? 0);
            if ($childNid > 0) {
                $nids[] = $childNid;
            }
        }
        return array_slice(array_values(array_unique($nids)), 0, self::MAX_MAP_COLLECTIONS);
    }

    /**
     * Parse a comma-separated list of collection node ids from a map option,
     * falling back to the current collection when the list is empty.
     *
     * Collections nested inside the listed ones are pulled in later by
     * expandWithDescendantCollections(), which loadMapData() applies to
     * this list.
     *
     * @param string $csv        The text after the pipe (e.g. "100,101,120").
     * @param int    $defaultNid Current collection nid, used when no list is given.
     * @return int[] Positive collection node ids.
     */
    private function parseCollectionNids(string $csv, int $defaultNid): array
    {
        $nids = array_values(array_filter(
            array_map('intval', explode(',', $csv)),
            fn($n) => $n > 0
        ));
        return empty($nids) ? [$defaultNid] : $nids;
    }
}
