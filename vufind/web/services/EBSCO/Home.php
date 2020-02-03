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
 * Displays full record information
 *
 * @category Pika
 * @author Mark Noble <mark@marmot.org>
 * Date: 3/2/2016
 * Time: 10:31 AM
 */

require_once ROOT_DIR . '/RecordDrivers/EbscoRecordDriver.php';
class EBSCO_Home extends Action{

	function launch() {
		global $interface;
		$id = urldecode($_REQUEST['id']);

		$recordDriver = new EbscoRecordDriver($id);
		$interface->assign('recordDriver', $recordDriver);

		$exploreMoreInfo = $recordDriver->getExploreMoreInfo();
		$interface->assign('exploreMoreInfo', $exploreMoreInfo);

		// Display Page
		global $configArray;
		if ($configArray['Catalog']['showExploreMoreForFullRecords']) {
			$interface->assign('showExploreMore', true);
		}

		$this->display('view.tpl', $recordDriver->getTitle());
	}
}
