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
 * Description goes here
 *
 * @category Pika
 * @author Mark Noble <mark@marmot.org>
 * Date: 4/29/13
 * Time: 8:48 AM
 */

class NovelistFactory {
	static private $novelistDriver;

	static function getNovelist(){
		if (!isset(self::$novelistDriver)){
			global $configArray;
			if (!isset($configArray['Novelist']['apiVersion']) || $configArray['Novelist']['apiVersion'] < 3){
				die("This version of Novelist is no longer supported!");
			}else{
				require_once ROOT_DIR . '/sys/Novelist/Novelist3.php';
				self::$novelistDriver = new Novelist3();
			}
		}
		return self::$novelistDriver;
	}
}
