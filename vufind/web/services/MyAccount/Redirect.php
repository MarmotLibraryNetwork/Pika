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
 * Redirect.php
 *
 * @category Pika
 * @package
 * @author   Chris Froese
 *
 */

require_once ROOT_DIR . "/sys/Account/User.php";

class Redirect extends Action {

	private $request_method = false;
	private $app;

	public function __construct($error_class = null)
	{
		$this->request_method = $_SERVER['REQUEST_METHOD'];

		parent::__construct(null);
		$this->launch();
	}

	public function launch() {
		$service = $_REQUEST['action'];
		if(method_exists($this, $service)) {
			$this->$service();
		}
	}

	private function readRBdigitalMagazine() {
		if(!isset($_REQUEST['userId']) || !isset($_REQUEST['issueId'])) {
			// todo: return to my account and show error message.
			die('Missing parameters.');
		}
		$patron = new User;
		$patron->id = $_REQUEST['userId'];
		$patron->find(true);
		if($patron->N == 0) {
			die('Patron does not exist.');
		}

		$rbdigital = new Pika\PatronDrivers\RBdigital();
		$rbdigital->redirectToRBdigitalMagazine($patron, $_REQUEST['issueId']);
		//https://www.rbdigital.com/reader.php#/reader/readsvg/469647/Cover
	}



}
