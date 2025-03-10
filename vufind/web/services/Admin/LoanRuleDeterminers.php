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

require_once ROOT_DIR . '/services/Admin/ObjectEditor.php';
require_once ROOT_DIR . '/sys/Pika/CirculationSystemDrivers/LoanRuleDeterminer.php';

use Pika\Logger;
class LoanRuleDeterminers extends ObjectEditor {
	function launch(){
		$objectAction = $_REQUEST['objectAction'] ?? null;
		if ($objectAction == 'reloadFromCsv'){
			$this->display('../Admin/importLoanRuleDeterminerData.tpl', 'Reload Loan Rule Determiners');
			die;
		}elseif ($objectAction == 'doLoanRuleDeterminerReload'){
			$loanRuleDeterminerData = $_REQUEST['loanRuleDeterminerData'];
			//Truncate the current data
			$loanRuleDeterminer = new LoanRuleDeterminer();
            $logger = new Logger('LoanRuleDeterminers');
            try {
                $loanRuleDeterminer->query('TRUNCATE table ' . $loanRuleDeterminer->__table);
            } catch (Exception $e) {
                $logger->warning($e->getMessage());
            }

			//Parse the new data
			$data = preg_split('/\\r\\n|\\r|\\n/', $loanRuleDeterminerData);
			foreach ($data as $dataRow){
				if (!empty($dataRow)){
					$dataFields                        = preg_split('/\\t/', $dataRow);
					$loanRuleDeterminerNew             = new LoanRuleDeterminer();
					$loanRuleDeterminerNew->rowNumber  = trim($dataFields[0]);
					$loanRuleDeterminerNew->location   = trim($dataFields[1]);
					$loanRuleDeterminerNew->patronType = trim($dataFields[2]);
					$loanRuleDeterminerNew->itemType   = trim($dataFields[3]);
					$loanRuleDeterminerNew->ageRange   = trim($dataFields[4]);
					$loanRuleDeterminerNew->loanRuleId = trim($dataFields[5]);
					$loanRuleDeterminerNew->active     = strcasecmp(trim($dataFields[6]), 'y') == 0;
					$loanRuleDeterminerNew->insert(false);
				}
			}
			$loanRuleDeterminer->setFullReindexMarker();

			//Show the results
			$_REQUEST['objectAction'] = 'list';
		}
		parent::launch();
	}

	function getObjectType(){
		return 'LoanRuleDeterminer';
	}

	function getToolName(){
		return 'LoanRuleDeterminers';
	}

	function getPageTitle(){
		return 'Loan Rule Determiners';
	}

	function getAllObjects($orderBy = null){
		return parent::getAllObjects($orderBy ?? 'rowNumber');
	}

	function getObjectStructure(){
		return LoanRuleDeterminer::getObjectStructure();
	}

	function getPrimaryKeyColumn(){
		return 'id';
	}

	function getIdKeyColumn(){
		return 'rowNumber';
	}

	function getAllowableRoles(){
		return ['opacAdmin'];
	}

	function customListActions(){
		$actions   = [];
		$actions[] = [
			'label'  => 'Reload From CSV',
			'action' => 'reloadFromCsv',
		];
		return $actions;
	}

	public function canAddNew(){
		return false;
	}
}
