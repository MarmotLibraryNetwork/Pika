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
 * RBdigital/AJAX.php
 *
 * @category Pika
 * @package  RBdigital
 * @author   Chris Froese
 *
 */
use \Pika\PatronDrivers\RBdigital;
require_once ROOT_DIR . '/AJAXHandler.php';
require_once ROOT_DIR . '/services/AJAX/MARC_AJAX_Basic.php';
require_once ROOT_DIR . '/services/SourceAndId.php';

class RBdigital_AJAX  extends AJAXHandler {

	use MARC_AJAX_Basic;

	protected array $methodsThatRespondWithJSONUnstructured = [
	 'returnRBdigitalMagazine',
	];

	protected array $methodsThatRespondThemselves = [
	 'readMagazineOnline',
	];
	protected array $methodsThatRespondWithJSONResultWrapper = [];
	protected array $methodsThatRespondWithXML = [];
	protected array $methodsThatRespondWithHTML = [];

	public function returnRBdigitalMagazine(){
		$issueId = $_REQUEST['issueId'];
		$userId  = $_REQUEST['userId'];
		$user = UserAccount::getLoggedInUser();
		if(! $user) {
			return ['success' => false, 'message' => 'An error occurred.'];
		}
		$patron = $user->getUserReferredTo($userId);
		if(!$patron) {
			return ['success' => false, 'message' => "You don\'t have permissions to return titles for that user."];
		}
		$rbd = new RBdigital();

		return $rbd->returnMagazine($patron, $issueId);
	}

	public function readMagazineOnline() {
		$user = UserAccount::getLoggedInUser();
		$issueId = $_REQUEST['issueId'];
		$userId  = $_REQUEST['userId'];

		if(! $user) {
			return ['success' => false, 'message' => 'An error occurred.'];
		}
		$patron = $user->getUserReferredTo($userId);
		if(!$patron) {
			//return ['success' => false, 'message' => "You don\'t have permissions to read titles checked out to that user."];
		}
		$rbd = new RBdigital();
		$rbd->redirectToRBdigitalMagazine($user, $issueId);
		return true;
	}
}
