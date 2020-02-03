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
 * Displays Information about Digital Repository (Islandora) Entity
 *
 * @category VuFind-Plus-2014
 * @author Mark Noble <mark@marmot.org>
 * Date: 3/22/2016
 * Time: 11:15 AM
 */
require_once ROOT_DIR . '/services/Archive/Object.php';
abstract class Archive_Entity extends Archive_Object {
	function loadRelatedContentForEntity(){
		global $interface;
		$directlyRelatedObjects = $this->recordDriver->getDirectlyRelatedArchiveObjects();
		$interface->assign('directlyRelatedObjects', $directlyRelatedObjects);
	}

}
