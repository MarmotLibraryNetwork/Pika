<?php
/*
 * Pika Discovery Layer
 * Copyright (C) 2023  Marmot Library Network
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

require_once 'Record.php';

class Record_Export extends Record_Record {

	function __construct($recordId = null){
		parent::__construct($recordId);
		$marcRecord = $this->recordDriver->getMarcRecord();
		if ($marcRecord){
			global $interface;
			$interface->assign('marc', $marcRecord);
			$interface->assign('recordLanguage', $this->recordDriver->getLanguage());
			$interface->assign('recordFormat', $this->recordDriver->getFormat());
		}
	}

	function launch(){
		global $interface;

		$tpl = $this->recordDriver->getExport($_GET['style']);
		if (!empty($tpl)){
			$interface->display($tpl);
		}else{
			die(translate('Unsupported export format.'));
		}
	}
}
