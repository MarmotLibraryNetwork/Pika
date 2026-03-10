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

/**
 * Description goes here
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 12/2/13
 * Time: 11:33 AM
 */

class NovelistData extends DB_DataObject{
	public $__table = 'novelist_data';

	public $id;
	public $groupedWorkPermanentId;
	public $lastUpdate;
	public $groupedRecordHasISBN;
	public $hasNovelistData;
	public $primaryISBN;

	//Series Data
	public $seriesTitle;
	public $seriesNote;
	public $volume;

	//Data calculated at runtime with calls to loadEnrichment
	public $similarTitleCountOwned;
	public $similarTitles;

	static function doesGroupedWorkHaveCachedSeries($groupedRecordId){
		if (!empty($groupedRecordId)){
			$novelistData                         = new NovelistData();
			$novelistData->groupedWorkPermanentId = $groupedRecordId;
			if ($novelistData->count()){
				return true;
			}
		}
		return false;
	}

	static function removeNovelistCachedSeriesEntry($groupedRecordId) :bool{
		if (!empty($groupedRecordId)){
			$novelistData                         = new NovelistData();
			$novelistData->groupedWorkPermanentId = $groupedRecordId;
			$novelistData->limit(1);   // Set a limit so that we only delete a single entry
			$r = $novelistData->delete(); // returns number of deleted rows on success;
			$success = $r !== false;      // only false is a failure; 0 would be no match or no deletion needed
			if ($success){
				// Clear Any memcached entries for the work
				/** @var Memcache $memCache */
				global $memCache;
				global $solrScope;
				foreach (
					[
						"novelist_series_{$groupedRecordId}_{$solrScope}",
						"novelist_enrichment_basic_$groupedRecordId",
						"novelist_enrichment_$groupedRecordId",
						"novelist_similar_titles_$groupedRecordId",
						"novelist_similar_authors_$groupedRecordId",
					] as $memCacheKey){
					$memCache->delete($memCacheKey);
				}
			}
			return $success;
		}
		return false;
	}
}
