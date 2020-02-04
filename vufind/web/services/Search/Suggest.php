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

class Suggest extends Action {

	function launch()
	{
		global $configArray;

		//header('Content-type: application/x-suggestions+json');
		header('Content-type: application/json');

		// Setup Search Engine Connection
		$class = $configArray['Index']['engine'];
		$db = new $class($configArray['Index']['url']);

		$results = $db->getSuggestion(strtolower(strip_tags($_GET['lookfor'])), 'title_sort', 10);
		echo json_encode($results);
	}
}
