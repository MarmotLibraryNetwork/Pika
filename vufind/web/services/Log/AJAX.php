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

require_once ROOT_DIR . '/AJAXHandler.php';

class Log_AJAX extends AJAXHandler {

	protected $methodsThatRespondWithJSONUnstructured = array(
		'getNotes',
	);

	function getNotes(){
		$id                = $_REQUEST['id'];
		$logType          = $_REQUEST['type'];
		$logEntryClassName = $logType . 'LogEntry';
		require_once ROOT_DIR . '/sys/Log/' . $logEntryClassName . '.php';
		/** @var LogEntry $logEntry */
		$logEntry     = new $logEntryClassName();
		$logEntry->id = $id;
		$results      = [
			'title'        => '',
			'modalBody'    => '',
			'modalButtons' => '',
		];
		if ($logEntry->find(true)){
			$results['title'] = "$logType Notes";
			if (strlen(trim($logEntry->notes)) == 0){
				$results['modalBody'] = "No notes have been entered yet";
			}else{
				$results['modalBody'] = "<div class='helpText'>{$logEntry->notes}</div>";
			}
		}else{
			$results['title']     = "Error";
			$results['modalBody'] = "We could not find a $logType log entry with that id.  No notes available.";
		}
		return $results;
	}

}
