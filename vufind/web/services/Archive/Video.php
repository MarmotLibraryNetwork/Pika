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
 * Allows display of a Video from Islandora
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 9/8/2015
 * Time: 8:44 PM
 */

require_once ROOT_DIR . '/services/Archive/Object.php';
class Archive_Video  extends Archive_Object{
	function launch() {
		global $interface;
		global $configArray;
		$this->loadArchiveObjectData();

		if ($this->archiveObject->getDatastream('MP4') != null) {
			$interface->assign('videoLink', $configArray['Islandora']['objectUrl'] . "/{$this->pid}/datastream/MP4/view");
		}else if ($this->archiveObject->getDatastream('OBJ') != null) {
			$interface->assign('videoLink', $configArray['Islandora']['objectUrl'] . "/{$this->pid}/datastream/OBJ/view");
		}

		$interface->assign('showExploreMore', true);

		// Display Page
		$this->display('video.tpl');
	}
}
