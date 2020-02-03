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
 * Allows display of a single image from Islandora
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 9/8/2015
 * Time: 8:43 PM
 */

require_once ROOT_DIR . '/services/Archive/Object.php';
class Archive_Image extends Archive_Object{
	function launch(){
		global $interface;
		$this->loadArchiveObjectData();
		//$this->loadExploreMoreContent();

		$interface->assign('showExploreMore', true);
		$interface->setTemplate('image.tpl');

		// Display Page
		$this->display('image.tpl');
	}
}
