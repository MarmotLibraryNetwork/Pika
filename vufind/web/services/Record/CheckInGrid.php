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

require_once ROOT_DIR . '/Action.php';

global $configArray;

class CheckInGrid extends Action {
	function launch()
	{
		global $interface;

		$driver = CatalogFactory::getCatalogConnectionInstance();
		$checkInGrid = $driver->getCheckInGrid(strip_tags($_REQUEST['id']), strip_tags($_REQUEST['lookfor']));
		$interface->assign('checkInGrid', $checkInGrid);

		$results = array(
				'title' => 'Check-In Grid',
				'modalBody' => $interface->fetch('Record/checkInGrid.tpl'),
				'modalButtons' => ""
		);
		echo json_encode($results);
	}
}
