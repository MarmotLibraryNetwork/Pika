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
 * Control how subjects are handled when linking to the catalog.
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 2/22/2016
 * Time: 7:05 PM
 */
require_once ROOT_DIR . '/services/Admin/Admin.php';
require_once ROOT_DIR . '/sys/ArchiveSubject.php';

class Admin_ArchiveSubjects extends Admin_Admin {

	function launch(){
		global $interface;
		$archiveSubjects = new ArchiveSubject();
		$archiveSubjects->find(true);
		if (isset($_POST['subjectsToIgnore'])){
			$archiveSubjects->subjectsToIgnore   = strip_tags($_POST['subjectsToIgnore']);
			$archiveSubjects->subjectsToRestrict = strip_tags($_POST['subjectsToRestrict']);
			if ($archiveSubjects->id){
				$archiveSubjects->update();
			}else{
				$archiveSubjects->insert();
			}
		}
		$interface->assign('subjectsToIgnore', $archiveSubjects->subjectsToIgnore);
		$interface->assign('subjectsToRestrict', $archiveSubjects->subjectsToRestrict);

		$this->display('archiveSubjects.tpl', 'Archive Subjects');
	}

	function getAllowableRoles(){
		return array('opacAdmin', 'archives');
	}
}
