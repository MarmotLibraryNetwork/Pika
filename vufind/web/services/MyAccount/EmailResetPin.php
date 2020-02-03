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
 *
 *
 * @category Pika
 * @author: Pascal Brammeier
 * Date: 8/16/2016
 *
 */

require_once ROOT_DIR . "/Action.php";
require_once ROOT_DIR . '/CatalogConnection.php';

class EmailResetPin extends Action{
	protected $catalog;

	function __construct()
	{
	}

	function launch($msg = null)
	{
		global $interface;

		if (isset($_REQUEST['submit'])){

			$this->catalog = CatalogFactory::getCatalogConnectionInstance(null, null);
			$driver        = $this->catalog->driver;
			if (method_exists($driver, 'emailResetPin')){
				$barcode     = strip_tags($_REQUEST['barcode']);
				$emailResult = $driver->emailResetPin($barcode);
			}else{
				$emailResult = array(
					'error' => 'This functionality is not available in the circulation system.',
				);
			}
			$interface->assign('emailResult', $emailResult);
			$this->display('emailResetPinResults.tpl', 'Email to Reset Pin');
		}else{
			$this->display('emailResetPin.tpl', 'Email to Reset Pin');
		}
	}
}
