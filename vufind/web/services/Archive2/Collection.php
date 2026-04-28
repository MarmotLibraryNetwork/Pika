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
require_once ROOT_DIR . '/sys/Islandora2/TaxonomyFactory.php';
require_once ROOT_DIR . '/sys/Islandora2/Functions.php';
require_once ROOT_DIR . '/sys/Pager.php';

use Islandora2\CollectionObject;
use Islandora2\I2ObjectFactory;
use Islandora2\TaxonomyFactory;

class Collection extends ArchiveObject
{
    public function launch()
    {
        global $interface;

        parent::launch();

        /** @var CollectionObject $collection */
        $collection  = $this->mediaObject;
        $displayType = $collection->getCollectionDisplay() ?? 'basic';
        $nid         = $collection->getNodeId();

        $thumb = $collection->getThumbnail();
        $interface->assign('thumbnail',      $thumb ? $thumb->thumbnailUrl : null);
        $interface->assign('thumbnail_link', $collection->getCollectionThumbnailLink());
        $interface->assign('nid',            $nid);

        switch ($displayType) {
            case 'timeline':
                $this->loadChildrenData($nid);
                return parent::display('collection_timeline.tpl', $collection->getTitle());
            case 'map':
                $interface->assign('showTimeline', true);
                $this->loadMapData();
                return parent::display('collection_map.tpl', $collection->getTitle());
            case 'mapNoTimeline':
                $interface->assign('showTimeline', false);
                $this->loadMapData();
                return parent::display('collection_map.tpl', $collection->getTitle());
            case 'custom':
                $this->loadCustomComponents($nid);
                return parent::display('collection_custom.tpl', $collection->getTitle());
            default: // 'basic'
                $this->loadChildrenData($nid);
                return parent::display('collection_basic.tpl', $collection->getTitle());
        }
    }

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
                'nid'       => $child->getNodeId(),
                'title'     => $child->getTitle(),
                'url'       => getObjRelativeUrl($child),
                'thumbnail' => $thumbMedia ? $thumbMedia->thumbnailUrl : '',
                'date'      => $child->getDateCreated('Y'),
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

    private function loadMapData(): void
    {
        global $interface;
        /** @var CollectionObject $collection */
        $collection     = $this->mediaObject;
        $places         = $collection->getCollectionRelatedPlaces();
        $mappedPlaces   = [];
        $unmappedPlaces = [];
        $latSum = $lngSum = $n = 0;
        $minLat = $maxLat = $minLng = $maxLng = null;

        foreach ($places as $place) {
            $term = TaxonomyFactory::fromTid($place['tid']);
            if (!$term) {
                continue;
            }
            $geo   = $term->getGeolocation();
            $entry = ['tid' => $place['tid'], 'label' => $place['name'], 'url' => $place['url']];
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

        $nodeFields = $collection->getNodeWithoutFieldPrefix();
        $mapZoom    = $nodeFields['pika_map_zoom'] ?? 9;

        $interface->assign('mappedPlaces',   $mappedPlaces);
        $interface->assign('unmappedPlaces', $unmappedPlaces);
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

    private function loadCustomComponents(int $nid): void
    {
        global $interface;
        /** @var CollectionObject $collection */
        $collection = $this->mediaObject;
        $options    = $collection->getCollectionOptions() ?? [];
        $templates  = [];
        $factory    = new I2ObjectFactory();

        foreach ($options as $option) {
            $parts = explode('|', $option);
            $type  = $parts[0];

            if ($type === 'searchCollection') {
                $interface->assign('searchComponentImage',
                    $parts[1] ?? '/interface/themes/responsive/images/search_component.png');
                $templates[] = $interface->fetch('Archive2/components/search_component.tpl');

            } elseif ($type === 'googleMap') {
                $interface->assign('additionalMapCollections', $parts[1] ?? '');
                $this->loadMapData();
                $templates[] = $interface->fetch('Archive2/components/map_component.tpl');

            } elseif ($type === 'browseCollectionByTitle' || $type === 'scroller') {
                $childNid = (int)($parts[1] ?? 0);
                if ($childNid > 0) {
                    $childCollection = $factory->fromNodeId($childNid);
                    if ($childCollection instanceof CollectionObject) {
                        $childItems = [];
                        foreach ($childCollection->getChildObjects() ?? [] as $obj) {
                            $thumb        = $obj->getThumbnail();
                            $childItems[] = [
                                'nid'       => $obj->getNodeId(),
                                'title'     => $obj->getTitle(),
                                'url'       => getObjRelativeUrl($obj),
                                'thumbnail' => $thumb ? $thumb->thumbnailUrl : '',
                            ];
                        }
                        $interface->assign('browseCollectionTitle', $childCollection->getTitle());
                        $interface->assign('browseCollectionItems', $childItems);
                        $tpl = $type === 'scroller'
                            ? 'Archive2/components/scroller_component.tpl'
                            : 'Archive2/components/browse_titles_component.tpl';
                        $templates[] = $interface->fetch($tpl);
                    }
                }

            } elseif ($type === 'randomImage') {
                $sourceNids = !empty($parts[1])
                    ? array_map('trim', explode(',', $parts[1]))
                    : [$nid];
                $randomNid    = (int)$sourceNids[array_rand($sourceNids)];
                $randomSource = $factory->fromNodeId($randomNid);
                if ($randomSource instanceof CollectionObject) {
                    $rawChildren = $randomSource->getRawChildren() ?? [];
                    if (!empty($rawChildren)) {
                        $pick      = $rawChildren[array_rand($rawChildren)];
                        $randomObj = $factory->fromNodeId((int)($pick['nid'] ?? 0));
                        if ($randomObj) {
                            $thumb = $randomObj->getThumbnail();
                            $interface->assign('randomObject', [
                                'title'     => $randomObj->getTitle(),
                                'url'       => getObjRelativeUrl($randomObj),
                                'thumbnail' => $thumb ? $thumb->thumbnailUrl : '',
                            ]);
                            $templates[] = $interface->fetch('Archive2/components/random_image_component.tpl');
                        }
                    }
                }

            } elseif ($type === 'browseAllObjects') {
                $interface->assign('collectionNid', $nid);
                $templates[] = $interface->fetch('Archive2/components/browse_all_component.tpl');

            } elseif ($type === 'browseFilter' || $type === 'browseEntityFilter') {
                $interface->assign('browseFilterFacetName', $parts[1] ?? '');
                $interface->assign('browseFilterLabel',     $parts[2] ?? '');
                $interface->assign('browseFilterImage',
                    $parts[3] ?? '/interface/themes/responsive/images/search_component.png');
                $tpl = $type === 'browseEntityFilter'
                    ? 'Archive2/components/entity_filter_component.tpl'
                    : 'Archive2/components/browse_filter_component.tpl';
                $templates[] = $interface->fetch($tpl);
            }
        }

        $interface->assign('collectionTemplates', $templates);
    }
}
