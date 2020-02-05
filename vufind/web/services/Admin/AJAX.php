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

class Admin_AJAX extends AJAXHandler {

	protected $methodsThatRespondWithJSONUnstructured = array(
		'getAddToWidgetForm',
		'markProfileForRegrouping',
		'markProfileForReindexing',
		'copyHooplaSettingsFromLibrary',
		'clearLocationHooplaSettings',
		'clearLibraryHooplaSettings',
	);

	function getAddToWidgetForm(){
		global $interface;
		$user = UserAccount::getLoggedInUser();
		// Display Page
		$interface->assign('id', strip_tags($_REQUEST['id']));
		$interface->assign('source', strip_tags($_REQUEST['source']));
		require_once ROOT_DIR . '/sys/Widgets/ListWidget.php';
		$listWidget      = new ListWidget();
		if (UserAccount::userHasRoleFromList(['libraryAdmin', 'contentEditor', 'libraryManager', 'locationManager'])){
			//Get all widgets for the library
			$userLibrary           = UserAccount::getUserHomeLibrary();
			$listWidget->libraryId = $userLibrary->libraryId;
		}
		$listWidget->orderBy('name');
		$existingWidgets = $listWidget->fetchAll('id', 'name');
		$interface->assign('existingWidgets', $existingWidgets);
		$results = array(
			'title'        => 'Create a Widget',
			'modalBody'    => $interface->fetch('Admin/addToWidgetForm.tpl'),
			'modalButtons' => "<button class='tool btn btn-primary' onclick='$(\"#bulkAddToList\").submit();'>Create Widget</button>",
		);
		return $results;
	}

	function copyHooplaSettingsFromLibrary(){
		$results = array(
			'title'     => 'Copy Library Hoopla Settings',
			'body' => '<div class="alert alert-danger">There was an error.</div>',
		);

		$user    = UserAccount::getLoggedInUser();
		if (UserAccount::userHasRoleFromList(['opacAdmin', 'libraryAdmin'])){
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
		if (UserAccount::userHasRoleFromList(['opacAdmin', 'libraryAdmin'])){
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
		if (UserAccount::userHasRoleFromList(['opacAdmin', 'libraryAdmin'])){
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
