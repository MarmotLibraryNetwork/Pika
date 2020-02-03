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

require_once ROOT_DIR . '/sys/Genealogy/Person.php';
require_once ROOT_DIR . '/Action.php';

/*class Reindex extends Action{
	function launch(){
		global $timer;

		$timer->logTime("Starting to reindex person");
		$recordId = $_REQUEST['id'];
		$quick = isset($_REQUEST['quick']) ? true : false;
		$person = new Person();
		$person->personId = $recordId;
		if ($person->find(true)){
			$ret = $person->saveToSolr($quick);
			if ($ret){
				echo(json_encode(array("success" => true)));
			}else{
				echo(json_encode(array("success" => false, "error" => "Could not update solr")));
			}
		}else{
			echo(json_encode(array("success" => false, "error" => "Could not find a record with that id")));
		}

	}

}*/
