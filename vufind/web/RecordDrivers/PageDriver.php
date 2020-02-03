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
 * Record Driver for display of LargeImages from Islandora
 *
 * @category VuFind-Plus-2014
 * @author Mark Noble <mark@marmot.org>
 * Date: 12/9/2015
 * Time: 1:47 PM
 */
require_once ROOT_DIR . '/RecordDrivers/IslandoraDriver.php';
class PageDriver extends IslandoraDriver {

	public function getViewAction() {
		return 'Page';
	}

	public function getFormat(){
		return 'Page';
	}

	function getRecordUrl($absolutePath = false){
		global $configArray;
		$recordId = $this->getUniqueID();
		//For Pages we do things a little differently since we want to link to the page within the book so we get context.
		$parentObject = $this->getParentObject();
		$parentDriver = RecordDriverFactory::initIslandoraDriverFromObject($parentObject);
		if ($parentDriver != null && $parentDriver instanceof BookDriver){
			return $parentDriver->getRecordUrl($absolutePath) . '?pagePid=' . urlencode($recordId);
		}elseif ($absolutePath){
			return $configArray['Site']['url'] . '/Archive/' . urlencode($recordId) . '/' . $this->getViewAction();
		}else{
			return '/Archive/' . urlencode($recordId) . '/' . $this->getViewAction();
		}
	}
}
