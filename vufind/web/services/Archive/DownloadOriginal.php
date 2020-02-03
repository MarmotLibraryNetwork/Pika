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
 * Allows downloading the original object after checking permissions
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 5/16/2016
 * Time: 10:47 AM
 */
require_once ROOT_DIR . '/services/Archive/Object.php';
class Archive_DownloadOriginal extends Archive_Object{
	function launch(){
		global $interface;
		global $logger;
		$this->loadArchiveObjectData();
		$anonymousMasterDownload = $interface->getVariable('anonymousMasterDownload');
		$verifiedMasterDownload = $interface->getVariable('verifiedMasterDownload');

		if ($anonymousMasterDownload || (UserAccount::isLoggedIn() && $verifiedMasterDownload)){
			$expires = 60*60*24*14;  //expire the cover in 2 weeks on the client side
			header("Cache-Control: maxage=".$expires);
			header('Expires: ' . gmdate('D, d M Y H:i:s', time()+$expires) . ' GMT');
			$pid = $this->pid;
			$pid = str_replace(':', '_', $pid);
			$masterDataStream = $this->archiveObject->getDatastream('OBJ');
			header('Content-Disposition: attachment; filename=' . $pid . '_original' . $this->recordDriver->getExtension($masterDataStream->mimetype));
			header('Content-type: ' . $masterDataStream->mimetype);
			header('Content-Length: ' . $masterDataStream->size);
			$tempFile = tempnam(sys_get_temp_dir(), 'ido');
			$masterDataStream->getContent($tempFile);
			$bytesWritten = $this->readfile_chunked($tempFile);
			unlink($tempFile);
			exit();
		}else{
			PEAR_Singleton::raiseError('Sorry, You do not have permission to download this image.');
		}
	}

	// Read a file and display its content chunk by chunk
	function readfile_chunked($tempFile, $retbytes = TRUE) {
		$handle = fopen($tempFile, 'rb');
		$buffer = '';
		$cnt    = 0;

		if ($handle === false) {
			return false;
		}

		while (!feof($handle)) {
			$buffer = fread($handle, 1048576);
			echo $buffer;
			ob_flush();
			flush();

			if ($retbytes) {
				$cnt += strlen($buffer);
			}
		}

		$status = fclose($handle);

		if ($retbytes && $status) {
			return $cnt; // return num. bytes delivered like readfile() does.
		}

		return $status;
	}

}
