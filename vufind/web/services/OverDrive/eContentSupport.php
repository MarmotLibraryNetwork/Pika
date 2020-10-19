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


require_once ROOT_DIR . '/Action.php';
require_once ROOT_DIR . '/services/OverDrive/AJAX.php';

class eContentSupport extends Action {
	function launch(){
		// Overdrive download links can potentially have a link back to this page.
		// Submitting the form will cause the ajax popup to be run.

		global $interface;
		$overdriveAJAX  = new OverDrive_AJAX();

		// Error Messaging delivered by OverDrive's Content fulfillment when there's an error
		$errorMessage = '';
		$logger = new Pika\Logger(__CLASS__);
		foreach (['ErrorCode', 'ErrorDescription', 'ErrorDetails', 'reserveId', 'read_error'] as $errorUrlParameter){
			if (!empty($_REQUEST[$errorUrlParameter])){
				$errorInfo    = "OverDrive delivered error parameter '$errorUrlParameter' :{$_REQUEST[$errorUrlParameter]}";
				$errorMessage .= "$errorInfo\n";
				$logger->error($errorInfo);
			}
		}
		$interface->assign('overDriveErrorMessages', $errorMessage);

		if (isset($_REQUEST['submit'])){
			$_GET['method'] = 'submitSupportForm';
			$overdriveAJAX->launch();
		}elseif (isset($_REQUEST['lightbox'])){
			$_GET['method'] = 'getSupportForm';
			$overdriveAJAX->launch();
		}else{
			$overdriveAJAX->getSupportForm(); //pre-populate form
			$interface->assign('lightbox', false);
			$this->display('eContentSupport.tpl', 'eContent Support');
		}
	}
}

