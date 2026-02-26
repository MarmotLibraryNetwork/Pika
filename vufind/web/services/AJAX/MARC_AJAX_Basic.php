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
 *  Basic Trait to use with class AJAX Handler for operations that are used with MARC records.
 *
 * @category Pika
 * @author   : Pascal Brammeier
 * Date: 4/28/2019
 *
 */

require_once ROOT_DIR . '/AJAXHandler.php';

trait MARC_AJAX_Basic {
	/*protected array $methodsThatRespondWithJSONUnstructured;
	protected array $methodsThatRespondWithJSONResultWrapper;
	protected array $methodsThatRespondWithXML;
	protected array $methodsThatRespondWithHTML;
	protected array $methodsThatRespondThemselves;*/

	function __construct(){
		// Add allowed AJAX method calls to the ones already set
		$this->methodsThatRespondWithJSONUnstructured = !is_null($this->methodsThatRespondWithJSONUnstructured) ? array_merge($this->methodsThatRespondWithJSONUnstructured, ['reloadCover']) : ['reloadCover'];
		/*$this->methodsThatRespondWithJSONResultWrapper = array_merge($this->methodsThatRespondWithJSONResultWrapper, array());
		$this->methodsThatRespondWithHTML             = array_merge($this->methodsThatRespondWithHTML, array());
		$this->methodsThatRespondWithXML              = array_merge($this->methodsThatRespondWithXML, array());*/
		$this->methodsThatRespondThemselves = !is_null($this->methodsThatRespondThemselves) ? array_merge($this->methodsThatRespondThemselves, ['downloadMarc']) : ['downloadMarc'];
	}

	function downloadMarc(){
		require_once ROOT_DIR . '/services/SourceAndId.php';
		require_once ROOT_DIR . '/RecordDrivers/MarcRecord.php';
		$sourceAndId      = new SourceAndId($_REQUEST['id']);
		$marcData         = MarcLoader::loadMarcRecordByILSId($sourceAndId);
		$downloadFileName = urlencode($sourceAndId);
		header('Content-Description: File Transfer');
		header('Content-Type: application/octet-stream');
		header("Content-Disposition: attachment; filename*={$downloadFileName}.mrc");
		header('Content-Transfer-Encoding: binary');
		header('Expires: 0');
		header('Cache-Control: must-revalidate');
		header('Pragma: public');
		header('Content-Length: ' . strlen($marcData->toRaw()));
		ob_clean();
		flush();
		echo($marcData->toRaw());
	}

	function reloadCover(){
		require_once ROOT_DIR . '/services/SourceAndId.php';
		require_once ROOT_DIR . '/RecordDrivers/MarcRecord.php';
		$sourceAndId  = new SourceAndId($_REQUEST['id']);
		$recordDriver = RecordDriverFactory::initRecordDriverById($sourceAndId);
		if ($recordDriver->isValid()){
			$success = true;

			// Reload covers for different sizes
			foreach (['small', 'medium', 'large'] as $size) {
				if (!$this->sendReloadCoverURl($recordDriver, $size)) {
					$success = false;
				}
			}

			//Also reload covers for the grouped work
			$groupedWorkDriver = $recordDriver->getGroupedWorkDriver();
			if ($groupedWorkDriver->isValid()){
				foreach (['small', 'medium', 'large'] as $size) {
					if (!$this->sendReloadCoverURl($groupedWorkDriver, $size)) {
						$success = false;
					}
				}
			}

			if ($success){
				return ['success' => true, 'message' => 'Covers have been reloaded.  You may need to refresh the page to clear your local cache.'];
			}else{
				return ['success' => false, 'message' => 'Some or all covers have not been reloaded.'];
			}
		} else {
			return ['success' => false, 'message' => 'Invalid Id.'];
		}
	}

	/**
	 * @param RecordInterface $recordDriver
	 * @param string $size
	 * @return bool
	 */
	private function sendReloadCoverURl(RecordInterface $recordDriver, string $size): bool{
		global $configArray;
		$reloadCoverURL = str_replace('&amp;', '&', $recordDriver->getBookcoverUrl($size, true)) . '&reload';
		$options        = ['http' => ['user_agent' => $configArray['Islandora2']['userAgent'] ]];
		$context        = stream_context_create($options);
		$response       = file_get_contents($reloadCoverURL, false, $context);
		if ($response === false){
			$this->logger->error('Error reloading cover URL: ' . $reloadCoverURL);
			return false;
		}elseif (!getimagesizefromstring($response)){
			$this->logger->error('Reload Cover URL: ' . $reloadCoverURL, [$response]);
			$this->checkForCloudflareChallengeResponse($response);
			return false;
		}
		return true;
	}

	/**
	 * Test if a call's response includes Cloudflare Challenge HTML.
	 *
	 * @param $response
	 * @return bool
	 */
	private function checkForCloudflareChallengeResponse($response){
		if (str_contains($response, 'challenge-error-text')){
			$this->logger->error('Received Cloudflare Challenge Response');
			return true;
		}
		return false;
	}
}
