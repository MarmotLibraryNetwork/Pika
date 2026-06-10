<?php
/*
 * Pika Discovery Layer
 * Copyright (C) 2026  Marmot Library Network
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

require_once ROOT_DIR . '/RecordDrivers/Islandora2Driver.php';
require_once ROOT_DIR . '/sys/SearchObject/Islandora2.php';
require_once ROOT_DIR . '/sys/Archive2/EdtfDateHelper.php';

/**
 * Loads a filterable, paginated page of a collection's child objects from
 * Solr for the timeline/map collection displays, along with decade date-facet
 * buckets built from the its_edtf_year index field.
 *
 * Shared by the Collection controller (initial server render) and the
 * Archive2 AJAX handler (filter/pagination reloads) so both use one code path.
 */
class CollectionTimelineData {

	const PAGE_SIZE = 24;

	/**
	 * Run the child-object search for a collection.
	 *
	 * @param int         $nid        Node ID of the parent collection.
	 * @param string|null $placeName  Optional place term name to restrict results to (sm_related_place).
	 * @param string|null $dateFilter Optional decade start year (e.g. '1920') or 'unknown'.
	 * @param int         $page       1-indexed result page.
	 * @return array{items: array, total: int, startRecord: int, endRecord: int,
	 *               page: int, pageCount: int, dateFacetInfo: array|null, unknownCount: int}
	 *         dateFacetInfo is only populated when no date filter is applied
	 *         (the filter buttons are only re-rendered then); null otherwise.
	 */
	public static function load(int $nid, ?string $placeName = null, ?string $dateFilter = null, int $page = 1): array {
		$page = max(1, $page);

		/** @var \SearchObject_Islandora2 $searchObject */
		$searchObject = \SearchObjectFactory::initSearchObject('Islandora2');
		$searchObject->init();
		$searchObject->addFilter('itm_field_member_of:' . $nid);
		if (!empty($placeName)){
			$searchObject->addFilter('sm_related_place:"' . str_replace('"', '\\"', $placeName) . '"');
		}

		$searchObject->clearFacets();
		if (empty($dateFilter) || $dateFilter === 'all'){
			$dateFilter = null;
			// Facet on the per-object year so the decade filter buttons can be built
			$searchObject->addFacet('its_edtf_year');
			$searchObject->setFacetLimit(-1);
			$searchObject->setFacetSortOrder('index');
		}elseif ($dateFilter === 'unknown'){
			$searchObject->addFilter('-its_edtf_year:[* TO *]');
		}elseif (ctype_digit($dateFilter)){
			$decadeStart = (int)$dateFilter;
			$searchObject->addFilter('its_edtf_year:[' . $decadeStart . ' TO ' . ($decadeStart + 9) . ']');
		}

		// Chronological order with undated objects grouped at the end.
		// (Comma-free function sort: Search\Solr::search() splits the sort
		// string on commas, so e.g. def(field,9999) would be mangled.)
		$searchObject->setSort('exists(its_edtf_year) desc,its_edtf_year asc');
		$searchObject->setLimit(self::PAGE_SIZE);
		$searchObject->setPage($page);

		$result = $searchObject->processSearch(true, false);
		if (\PEAR_Singleton::isError($result)){
			return [
				'items'         => [],
				'total'         => 0,
				'startRecord'   => 0,
				'endRecord'     => 0,
				'page'          => $page,
				'pageCount'     => 0,
				'dateFacetInfo' => $dateFilter === null ? [] : null,
				'unknownCount'  => 0,
			];
		}
		$summary = $searchObject->getResultSummary();
		$total   = (int)($result['response']['numFound'] ?? 0);

		$dateFacetInfo = null;
		$unknownCount  = 0;
		if ($dateFilter === null){
			$yearCounts = [];
			$datedCount = 0;
			foreach ($result['facet_counts']['facet_fields']['its_edtf_year'] ?? [] as $facetPair){
				$yearCounts[$facetPair[0]] = $facetPair[1];
				$datedCount               += $facetPair[1];
			}
			$dateFacetInfo = EdtfDateHelper::bucketYearsByDecade($yearCounts);
			$unknownCount  = max(0, $total - $datedCount);
		}

		$items = [];
		foreach ($result['response']['docs'] ?? [] as $doc){
			/** @var \Islandora2Driver $driver */
			$driver = \RecordDriverFactory::initRecordDriver($doc);
			if (\PEAR_Singleton::isError($driver)){
				continue;
			}
			$edtfDate = $doc['sm_field_edtf_date_created'][0] ?? '';
			$items[]  = [
				'nid'         => $driver->getUniqueID(),
				'title'       => $driver->getTitle(),
				'url'         => $driver->getRecordUrl(),
				'thumbnail'   => $driver->getBookcoverUrl('medium'),
				'description' => strip_tags($driver->getDescription() ?? ''),
				'date'        => EdtfDateHelper::humanize($edtfDate),
				'year'        => $doc['its_edtf_year'] ?? EdtfDateHelper::parseYear($edtfDate),
			];
		}

		return [
			'items'         => $items,
			'total'         => $total,
			'startRecord'   => $summary['startRecord'] ?? 0,
			'endRecord'     => $summary['endRecord'] ?? 0,
			'page'          => $page,
			'pageCount'     => (int)ceil($total / self::PAGE_SIZE),
			'dateFacetInfo' => $dateFacetInfo,
			'unknownCount'  => $unknownCount,
		];
	}

	/**
	 * Assign a load() result to the Smarty interface using the variable names
	 * the timeline component templates expect.
	 *
	 * @param array       $data        Result of load().
	 * @param int         $nid         Node ID of the parent collection.
	 * @param bool        $showTimeline Whether the decade date-filter buttons are shown.
	 * @param string|null $placeName   Currently selected place name, if any.
	 * @param string|null $dateFilter  Currently selected date filter, if any.
	 */
	public static function assignToInterface(array $data, int $nid, bool $showTimeline, ?string $placeName = null, ?string $dateFilter = null): void {
		global $interface;
		$interface->assign('nid',                 $nid);
		$interface->assign('showTimeline',        $showTimeline);
		$interface->assign('timelineItems',       $data['items']);
		$interface->assign('recordCount',         $data['total']);
		$interface->assign('recordStart',         $data['startRecord']);
		$interface->assign('recordEnd',           $data['endRecord']);
		$interface->assign('page',                $data['page']);
		$interface->assign('pageCount',           $data['pageCount']);
		$interface->assign('dateFacetInfo',       $data['dateFacetInfo']);
		$interface->assign('unknownDateCount',    $data['unknownCount']);
		$interface->assign('selectedPlaceName',   $placeName);
		$interface->assign('selectedDateFilter',  $dateFilter ?? '');
		// Item tiles show the humanized EDTF date (basic display omits it
		// since its 'date' is the Drupal node creation year)
		$interface->assign('showItemDates',       true);
	}
}
