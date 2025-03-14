<?php
/*
 * Pika Discovery Layer
 * Copyright (C) 2025  Marmot Library Network
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

class OnCampusAccessRecord extends SideLoadedRecord {

	public function getRecordActions($isAvailable, $isHoldable, $isBookable, $isHomePickupRecord, $relatedUrls = null, $volumeData = null){
		$actions = [];
		//$title   = "On-Campus Access Only";
		$title   = "Campus Use Only";
		//$title   = translate('externalEcontent_url_action');
		foreach ($relatedUrls as $urlInfo){
			$alt = 'Available online from ' . $urlInfo['source'];
			if (!empty($urlInfo['url'])){
				$actions[] = [
					'url'          => $urlInfo['url'],
					'title'        => $title,
					'requireLogin' => false,
					'alt'          => $alt,
				];
			}
		}

		return $actions;
	}

}