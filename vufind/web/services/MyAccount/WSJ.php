<?php
/*
 * Copyright (C) 2021  Marmot Library Network
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *
 *
 * @category Pika
 * @author: Pascal Brammeier
 * Date: 12/1/2020
 *
 */

require_once ROOT_DIR . '/services/MyAccount/MyAccount.php';

class WSJ extends MyAccount {

	function launch(){
		/** @var Library $library */
		global $configArray;
		$library = UserAccount::getUserHomeLibrary();
		if (!empty($configArray['WSJ']['url'][$library->subdomain])){
			header('Location: ' . $configArray['WSJ']['url'][$library->subdomain]);
			exit();
		}else{
			$this->display('../WSJ/noAccess.tpl', "No Access");
		}
	}

}