<?php
/*
 * Pika Discovery Layer
 * Copyright (C) 2026  Marmot Library Network
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
 * Displays active orders from active_orders.csv produced by the Sierra Export API
 *
 * @category Pika
 * @author   Pascal Brammeier <pika@marmot.org>
 */

require_once ROOT_DIR . '/services/Admin/Admin.php';
require_once ROOT_DIR . '/sys/Indexing/IndexingProfile.php';

class Admin_ActiveOrders extends Admin_Admin {

	function getAllowableRoles() {
		return ['opacAdmin', 'cataloging'];
	}

	function launch() {
		global $interface, $configArray;

		if (($configArray['Catalog']['ils'] ?? '') !== 'Sierra') {
			$interface->assign('notSierra', true);
			$this->display('activeOrders.tpl', 'Active Orders');
			return;
		}

		// Find all indexing profiles that have an active_orders.csv file
		$profileList  = []; // id => name
		$ilsProfileId = null;
		$allProfiles  = new IndexingProfile();
		$allProfiles->find();
		while ($allProfiles->fetch()) {
			$csvPath = $allProfiles->marcPath . DIR_SEP . 'active_orders.csv';
			if (file_exists($csvPath)) {
				$profileList[$allProfiles->id] = $allProfiles->name;
				if ($allProfiles->sourceName === 'ils') {
					$ilsProfileId = $allProfiles->id;
				}
			}
		}

		if (empty($profileList)) {
			$interface->assign('noFile', true);
			$this->display('activeOrders.tpl', 'Active Orders');
			return;
		}

		$defaultId  = $ilsProfileId ?? array_key_first($profileList);
		$selectedId = $_REQUEST['id'] ?? $defaultId;
		$profile    = new IndexingProfile();
		$profile->get($selectedId);
		$csvPath = $profile->marcPath . DIR_SEP . 'active_orders.csv';

		$headers = [];
		$rows    = [];
		$fh = fopen($csvPath, 'r');
		if ($fh) {
			$headers = fgetcsv($fh);
			while (($row = fgetcsv($fh)) !== false) {
				$rows[] = $row;
			}
			fclose($fh);
		}

		$interface->assign('profiles',   $profileList);
		$interface->assign('selectedId', $selectedId);
		$interface->assign('headers',    $headers);
		$interface->assign('rows',       $rows);
		$interface->assign('fileDate',   filemtime($csvPath));
		$this->display('activeOrders.tpl', 'Active Orders');
	}
}
