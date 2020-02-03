<?php
/**
 * Pika Discovery Layer
 * Copyright (C) 2020  Marmot Library Network
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

/**
 * Description goes here
 *
 * @category Pika
 * @author Mark Noble <mark@marmot.org>
 * Date: 12/2/13
 * Time: 11:33 AM
 */

class NovelistData extends DB_DataObject{
	public $id;
	public $groupedRecordPermanentId;
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

	public $__table = 'novelist_data';

	static function doesGroupedWorkHaveCachedSeries($groupedRecordId){
		if (!empty($groupedRecordId)){
			$novelistData                           = new NovelistData();
			$novelistData->groupedRecordPermanentId = $groupedRecordId;
			if ($novelistData->count()){
				return true;
			}
		}
		return false;
	}

}
