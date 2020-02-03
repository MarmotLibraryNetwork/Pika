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

class OverDriveDriverFactory{
	static function getDriver(){
		global $configArray;
		//TODO: obsolete ['OverDrive']['interfaceVersion']
//		if (isset($configArray['OverDrive']['interfaceVersion'])
//			&& $configArray['OverDrive']['interfaceVersion'] == 2){
//			require_once ROOT_DIR . '/Drivers/OverDriveDriver2.php';
//			$overDriveDriver = new OverDriveDriver2();
//		}else{
			require_once ROOT_DIR . '/Drivers/OverDriveDriver3.php';
			$overDriveDriver = new OverDriveDriver3();
//		}
		return $overDriveDriver;
	}
}
