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
 * Collections and Objects to Exclude from Archive Search.
  */
require_once ROOT_DIR . '/services/Admin/Admin.php';
require_once ROOT_DIR . '/sys/Archive/ArchivePrivateCollection.php';
class Admin_ArchivePrivateCollections extends Admin_Admin{

	function launch() {
		$collectionsRow = new ArchivePrivateCollection();
		$collectionsRow->type = 'collection';
		$collectionsRow->find(true);

		$objectsRow = new ArchivePrivateCollection();
		$objectsRow->type = 'object';
		$objectsRow->find(true);

		if (isset($_POST['privateCollections'])){
			$collectionsRow->privateCollections = $this->sanitizeNodeIds($_POST['privateCollections']);
			if ($collectionsRow->id){
				$collectionsRow->update();
			}else{
				$collectionsRow->insert();
			}
		}

		if (isset($_POST['privateObjects'])){
			$objectsRow->privateCollections = $this->sanitizeNodeIds($_POST['privateObjects']);
			if ($objectsRow->id){
				$objectsRow->update();
			}else{
				$objectsRow->insert();
			}
		}

		global /** @var UInterface $interface */ $interface;
		$interface->assign('privateCollections', $collectionsRow->privateCollections);
		$interface->assign('privateObjects',     $objectsRow->privateCollections);

		$this->display('archivePrivateCollections.tpl', 'Archive Private Collections');
	}

	/** Strip each line to digits only; discard lines that are not purely numeric. */
	private function sanitizeNodeIds(string $input): string {
		$lines = preg_split('/[\r\n]+/', $input);
		$valid = [];
		foreach ($lines as $line){
			$line = trim($line);
			if (ctype_digit($line)){
				$valid[] = $line;
			}
		}
		return implode("\r\n", $valid);
	}

	function getAllowableRoles() {
		return ['archives'];
	}
}
