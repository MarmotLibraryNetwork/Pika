<?php
/*
 * Pika Discovery Layer
 * Copyright (C) 2026  Marmot Library Network
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
 * Collections to Exclude from Archive Search. Currently single entry table
  */
require_once ROOT_DIR . '/services/Admin/Admin.php';
require_once ROOT_DIR . '/sys/Archive/ArchivePrivateCollection.php';
//Use ArchivePrivateCollection; //TODO: test this
class Admin_ArchivePrivateCollections extends Admin_Admin{

	function launch() {
		$privateCollections = new ArchivePrivateCollection();
		$privateCollections->find(true);
		if (isset($_POST['privateCollections'])){
			$privateCollections->privateCollections = strip_tags($_POST['privateCollections']);
			//TODO: validate contains only id numbers and line returns; strip spaces
			if ($privateCollections->id){
				$privateCollections->update();
			}else{
				$privateCollections->insert();
			}
		}
		global /** @var UInterface $interface */ $interface;
		$interface->assign('privateCollections', $privateCollections->privateCollections);

		$this->display('archivePrivateCollections.tpl', 'Archive Private Collections');
	}

	function getAllowableRoles() {
		return ['archives'];
	}
}
