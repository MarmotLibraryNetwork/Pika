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
 * Allows downloading the large image for an Object after checking permissions
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 5/16/2016
 * Time: 10:47 AM
 */
require_once ROOT_DIR . '/services/Archive/Object.php';
class DownloadLC extends Archive_Object{
	function launch(){
		global $interface;
		$this->loadArchiveObjectData();
		$anonymousLcDownload = $interface->getVariable('anonymousLcDownload');
		$verifiedLcDownload = $interface->getVariable('verifiedLcDownload');

		if ($anonymousLcDownload || (UserAccount::isLoggedIn() && $verifiedLcDownload)){
			$expires = 60*60*24*14;  //expire the cover in 2 weeks on the client side
			header("Cache-Control: maxage=".$expires);
			header('Expires: ' . gmdate('D, d M Y H:i:s', time()+$expires) . ' GMT');
			$pid = $this->pid;
			$pid = str_replace(':', '_', $pid);
			header('Content-Disposition: attachment; filename=' . $pid . '_lc' . $this->recordDriver->getExtension($this->archiveObject->getDatastream('LC')->mimetype));
			header('Content-type: ' . $this->archiveObject->getDatastream('LC')->mimetype);
			$lcDataStream = $this->archiveObject->getDatastream('LC');
			echo($lcDataStream->content);
			exit();
		}else{
			PEAR_Singleton::raiseError('Sorry, You do not have permission to download this image.');
		}
	}
}
