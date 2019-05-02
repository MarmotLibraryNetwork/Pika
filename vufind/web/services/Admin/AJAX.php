<?php
/**
 *
 * Copyright (C) Villanova University 2007.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 */

require_once ROOT_DIR . '/Action.php';

class Admin_AJAX extends Action {


	function launch() {
		global $timer;
		$method = (isset($_GET['method']) && !is_array($_GET['method'])) ? $_GET['method'] : '';
		if (method_exists($this, $method)) {
			$timer->logTime("Starting method $method");
			if (in_array($method, array(
				'getReindexNotes', 'getReindexProcessNotes', 'getCronNotes', 'getCronProcessNotes', 'getAddToWidgetForm', 'getRecordGroupingNotes', 'getHooplaExportNotes', 'getSierraExportNotes',
				'markProfileForRegrouping', 'markProfileForReindexing',
				'copyHooplaSettingsFromLibrary', 'clearLocationHooplaSettings', 'clearLibraryHooplaSettings',
			))){
				//JSON Responses
				header('Content-type: application/json');
				header('Cache-Control: no-cache, must-revalidate'); // HTTP/1.1
				header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
				echo $this->$method();
			} else if (in_array($method, array('getOverDriveExtractNotes'))) {
				//HTML responses
				header('Content-type: text/html');
				header('Cache-Control: no-cache, must-revalidate'); // HTTP/1.1
				header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
				echo $this->$method();
			} else {
				//XML responses
				header('Content-type: text/xml');
				header('Cache-Control: no-cache, must-revalidate'); // HTTP/1.1
				header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
				$xml = '<?xml version="1.0" encoding="UTF-8"?' . ">\n" .
						"<AJAXResponse>\n";
				$xml .= $this->$_GET['method']();
				$xml .= '</AJAXResponse>';

				echo $xml;
			}
		}else {
			echo json_encode(array('error'=>'invalid_method'));
		}
	}

	function getReindexNotes(){
		$id = $_REQUEST['id'];
		$reindexProcess = new ReindexLogEntry();
		$reindexProcess->id = $id;
		$results = array(
				'title' => '',
				'modalBody' => '',
				'modalButtons' => ''
		);
		if ($reindexProcess->find(true)){
			$results['title'] = "Reindex Notes";
			if (strlen(trim($reindexProcess->notes)) == 0){
				$results['modalBody'] = "No notes have been entered yet";
			}else{
				$results['modalBody'] = "<div class='helpText'>{$reindexProcess->notes}</div>";
			}
		}else{
			$results['title'] = "Error";
			$results['modalBody'] = "We could not find a reindex entry with that id.  No notes available.";
		}
		return json_encode($results);
	}

	function getRecordGroupingNotes(){
		$id = $_REQUEST['id'];
		$recordGroupingProcess = new RecordGroupingLogEntry();
		$recordGroupingProcess->id = $id;
		$results = array(
				'title' => '',
				'modalBody' => '',
				'modalButtons' => ''
		);
		if ($recordGroupingProcess->find(true)){
			$results['title'] = "Record Grouping Notes";
			if (strlen(trim($recordGroupingProcess->notes)) == 0){
				$results['modalBody'] = "No notes have been entered yet";
			}else{
				$results['modalBody'] = "<div class='helpText'>{$recordGroupingProcess->notes}</div>";
			}
		}else{
			$results['title'] = "Error";
			$results['modalBody'] = "We could not find a record grouping log entry with that id.  No notes available.";
		}
		return json_encode($results);
	}

	function getHooplaExportNotes(){
		$id = $_REQUEST['id'];
		$hooplaExportProcess = new HooplaExportLogEntry();
		$hooplaExportProcess->id = $id;
		$results = array(
				'title' => '',
				'modalBody' => '',
				'modalButtons' => ''
		);
		if ($hooplaExportProcess->find(true)){
			$results['title'] = "Hoopla Export Notes";
			if (strlen(trim($hooplaExportProcess->notes)) == 0){
				$results['modalBody'] = "No notes have been entered yet";
			}else{
				$results['modalBody'] = "<div class='helpText'>{$hooplaExportProcess->notes}</div>";
			}
		}else{
			$results['title'] = "Error";
			$results['modalBody'] = "We could not find a hoopla extract log entry with that id.  No notes available.";
		}
		return json_encode($results);
	}

	function getSierraExportNotes(){
		$id = $_REQUEST['id'];
		$sierraExportProcess = new SierraExportLogEntry();
		$sierraExportProcess->id = $id;
		$results = array(
				'title' => '',
				'modalBody' => '',
				'modalButtons' => ''
		);
		if ($sierraExportProcess->find(true)){
			$results['title'] = "Sierra Export Notes";
			if (strlen(trim($sierraExportProcess->notes)) == 0){
				$results['modalBody'] = "No notes have been entered yet";
			}else{
				$results['modalBody'] = "<div class='helpText'>{$sierraExportProcess->notes}</div>";
			}
		}else{
			$results['title'] = "Error";
			$results['modalBody'] = "We could not find a sierra extract log entry with that id.  No notes available.";
		}
		return json_encode($results);
	}


	function getCronProcessNotes(){
		$id = $_REQUEST['id'];
		$cronProcess = new CronProcessLogEntry();
		$cronProcess->id = $id;
		$results = array(
				'title' => '',
				'modalBody' => '',
				'modalButtons' => ""
		);
		if ($cronProcess->find(true)){
			$results['title'] = "{$cronProcess->processName} Notes";
			if (strlen($cronProcess->notes) == 0){
				$results['modalBody'] = "No notes have been entered for this process";
			}else{
				$results['modalBody'] = "<div class='helpText'>{$cronProcess->notes}</div>";
			}
		}else{
			$results['title'] = "Error";
			$results['modalBody'] = "We could not find a process with that id.  No notes available.";
		}
		return json_encode($results);
	}

	function getCronNotes()	{
		$id = $_REQUEST['id'];
		$cronLog = new CronLogEntry();
		$cronLog->id = $id;

		$results = array(
				'title' => '',
				'modalBody' => '',
				'modalButtons' => ""
		);
		if ($cronLog->find(true)){
			$results['title'] = "Cron Process {$cronLog->id} Notes";
			if (strlen($cronLog->notes) == 0){
				$results['modalBody'] = "No notes have been entered for this cron run";
			}else{
				$results['modalBody'] = "<div class='helpText'>{$cronLog->notes}</div>";
			}
		}else{
			$results['title'] = "Error";
			$results['modalBody'] = "We could not find a cron entry with that id.  No notes available.";
		}
		return json_encode($results);
	}

  function getOverDriveExtractNotes()	{
		global $interface;
		$id = $_REQUEST['id'];
		$overdriveExtractLog = new OverDriveExtractLogEntry();
		$overdriveExtractLog->id = $id;
	  $results = array(
			  'title' => '',
			  'modalBody' => '',
			  'modalButtons' => ""
	  );
		if ($overdriveExtractLog->find(true)){
			$results['title'] = "OverDrive Extract {$overdriveExtractLog->id} Notes";
			if (strlen($overdriveExtractLog->notes) == 0){
				$results['modalBody'] = "No notes have been entered for this OverDrive Extract run";
			}else{
				$results['modalBody'] = "<div class='helpText'>{$overdriveExtractLog->notes}</div>";
			}
		}else{
			$results['title'] = "Error";
			$results['modalBody'] = "We could not find a OverDrive Extract entry with that id.  No notes available.";
		}
	  return json_encode($results);
	}

	function getAddToWidgetForm(){
		global $interface;
		$user = UserAccount::getLoggedInUser();
		// Display Page
		$interface->assign('id', strip_tags($_REQUEST['id']));
		$interface->assign('source', strip_tags($_REQUEST['source']));
		$existingWidgets = array();
		$listWidget = new ListWidget();
		if (UserAccount::userHasRole('libraryAdmin') || UserAccount::userHasRole('contentEditor') || UserAccount::userHasRole('libraryManager') || UserAccount::userHasRole('locationManager')){
			//Get all widgets for the library
			$userLibrary = Library::getPatronHomeLibrary();
			$listWidget->libraryId = $userLibrary->libraryId;
		}
		$listWidget->orderBy('name');
		$existingWidgets = $listWidget->fetchAll('id', 'name');
		$interface->assign('existingWidgets', $existingWidgets);
		$results = array(
				'title' => 'Create a Widget',
				'modalBody' => $interface->fetch('Admin/addToWidgetForm.tpl'),
				'modalButtons' => "<button class='tool btn btn-primary' onclick='$(\"#bulkAddToList\").submit();'>Create Widget</button>"
		);
		return json_encode($results);
	}

	function copyHooplaSettingsFromLibrary(){
		$results = array(
			'title'     => 'Copy Library Hoopla Settings',
			'body' => '<div class="alert alert-danger">There was an error.</div>',
		);

		$user    = UserAccount::getLoggedInUser();
		if (UserAccount::userHasRole('opacAdmin') || UserAccount::userHasRole('libraryAdmin')){
			$locationId = trim($_REQUEST['id']);
			if (ctype_digit($locationId)){
				$location = new Location();
				if ($location->get($locationId)){
					$location->clearHooplaSettings();
					if ($location->copyLibraryHooplaSettings()){
						$results['body'] = '<div class="alert alert-success">Hoopla settings copied successfully.</div>';
					}else{
						$results['body'] = '<div class="alert alert-danger">At least one Hoopla setting failed to copy.</div>';
					}
				}
			}
		}
		return json_encode($results);
	}

	function clearLocationHooplaSettings(){
		$results = array(
			'title'     => 'Clear Location Hoopla Settings',
			'body' => '<div class="alert alert-danger">There was an error.</div>',
		);

		$user    = UserAccount::getLoggedInUser();
		if (UserAccount::userHasRole('opacAdmin') || UserAccount::userHasRole('libraryAdmin')){
			$locationId = trim($_REQUEST['id']);
			if (ctype_digit($locationId)){
				$location = new Location();
				if ($location->get($locationId)){

					if ($location->clearHooplaSettings()){
						$results['body'] = '<div class="alert alert-success">Hoopla settings were cleared.</div>';
					}else{
						$results['body'] = '<div class="alert alert-danger">Hoopla settings failed to clear.</div>';
					}
				}
			}
		}
		return json_encode($results);
	}

	function clearLibraryHooplaSettings(){
		$results = array(
			'title'     => 'Clear Library Hoopla Settings',
			'body' => '<div class="alert alert-danger">There was an error.</div>',
		);

		$user    = UserAccount::getLoggedInUser();
		if (UserAccount::userHasRole('opacAdmin') || UserAccount::userHasRole('libraryAdmin')){
			$libraryId = trim($_REQUEST['id']);
			if (ctype_digit($libraryId)){
				$library = new Library();
				if ($library->get($libraryId)){

					if ($library->clearHooplaSettings()){
						$results['body'] = '<div class="alert alert-success">Hoopla settings were cleared.</div>';
					}else{
						$results['body'] = '<div class="alert alert-danger">Hoopla settings failed to clear.</div>';
					}
				}
			}
		}
		return json_encode($results);
	}

	//	function markProfileForRegrouping(){
//		$result = array(
//			'success' => false,
//			'message' => 'Invalid Action',
//		);
//		$user = UserAccount::getLoggedInUser();
//		if (UserAccount::userHasRole('opacAdmin')){
//			$id = $_REQUEST['id'];
//			if (!empty($id) && ctype_digit($id)){
//				$indexProfile = new IndexingProfile();
//				if ($indexProfile->get($id)){
//					$result = $indexProfile->markProfileForRegrouping();
//				}
//			}
//		}
//		return json_encode($result);
//	}
//
//	function markProfileForReindexing(){
//		$result = array(
//			'success' => false,
//			'message' => 'Invalid Action',
//		);
//		$user = UserAccount::getLoggedInUser();
//		if (UserAccount::userHasRole('opacAdmin')){
//			$id = $_REQUEST['id'];
//			if (!empty($id) && ctype_digit($id)){
//				$indexProfile = new IndexingProfile();
//				if ($indexProfile->get($id)){
//					$result = $indexProfile->markProfileForReindexing();
//				}
//			}
//		}
//		return json_encode($result);
//	}
}
