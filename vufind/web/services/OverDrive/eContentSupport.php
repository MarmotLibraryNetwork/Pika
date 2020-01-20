<?php
/**
 *
 * Copyright (C) Villanova University 2007.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 */


require_once ROOT_DIR . '/Action.php';

class eContentSupport extends Action {
	function launch(){
		// Overdrive download links can potentially have a link back to this page.
		// Submitting the form will cause the ajax popup to be run.

		global $interface;

		if (isset($_REQUEST['submit'])){
			require_once ROOT_DIR . 'services/OverDrive/AJAX.php';
			$overdriveAJAX  = new OverDrive_AJAX();
			$_GET['method'] = 'submitSupportForm';
			$overdriveAJAX->launch();

		}elseif (isset($_REQUEST['lightbox'])){
			require_once ROOT_DIR . 'services/OverDrive/AJAX.php';
			$overdriveAJAX  = new OverDrive_AJAX();
			$_GET['method'] = 'getSupportForm';
			$overdriveAJAX->launch();
		}else{
			$interface->assign('lightbox', false);
			$this->display('eContentSupport.tpl', 'eContent Support');
		}
	}
}

