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
 * Record Driver for display of LargeImages from Islandora
 *
 * @category VuFind-Plus-2014
 * @author Mark Noble <mark@marmot.org>
 * Date: 12/9/2015
 * Time: 1:47 PM
 */
require_once ROOT_DIR . '/RecordDrivers/IslandoraDriver.php';
class PlaceDriver extends IslandoraDriver {

	public function getViewAction() {
		return 'Place';
	}

	protected function getPlaceholderImage() {
		return '/interface/themes/responsive/images/places.png';
	}

	public function isEntity(){
		return true;
	}

	public function getGeoData(){
		global $timer;
		//First check to see if we have latitude & longitude in the mods data
		$marmotExtension = $this->getMarmotExtension();
		$foundLatLon = false;
		$geoData = null;
		if (strlen($marmotExtension) > 0){
			$latitude = $this->getModsValue('latitude', 'mods');
			$longitude = $this->getModsValue('longitude', 'mods');
			if (strlen($latitude) &&
					strlen($longitude)){

				$geoData = array();
				if (!is_numeric($latitude) || !is_numeric($longitude)){
					$geoData['latitude'] = $this->convertDMSToDD($latitude);
					$geoData['longitude'] = $this->convertDMSToDD($longitude);
					//Update MODS record to include the lat/lon in decimal degrees
					$modsData = $this->getModsData();
					$modsData = str_replace('<marmot:latitude></marmot:latitude>', "<marmot:latitude>{$geoData['latitude']}</marmot:latitude", $modsData);
					$modsData = str_replace('<marmot:longitude></marmot:longitude>', "<marmot:longitude>{$geoData['longitude']}</marmot:longitude", $modsData);
					$this->archiveObject->getDatastream('MODS')->content = $modsData;
				}else{
					$geoData['latitude'] = $latitude;
					$geoData['longitude'] = $longitude;
				}
				$foundLatLon = true;
			}
			$timer->logTime("Loaded Lat Long from MODS");
		}

		if (!$foundLatLon){
			$links = $this->getLinks();
			if (isset($links) && is_array($links)){
				foreach ($links as $link){
					if ($link['type'] == 'whosOnFirst'){
						//Check to see if we've already downloaded Who's on First data
						if ($this->archiveObject->getDatastream('WOF')){
							$whosOnFirstDataRaw = $this->archiveObject->getDatastream('WOF')->content;
						}else{
							$whosOnFirstDataRaw = file_get_contents($link['link']);
							$datastream = $this->archiveObject->constructDatastream('WOF');
							$datastream->mimetype = 'application/json';
							$datastream->label = 'Raw data from Who\'s on First';
							$datastream->setContentFromString($whosOnFirstDataRaw);
							$this->archiveObject->ingestDatastream($datastream);
						}
						$whosOnFirstData = json_decode($whosOnFirstDataRaw, true);
						if ($whosOnFirstData == null){
							if (preg_match('/<a href="(.*\\.geojson)"/', $whosOnFirstDataRaw, $matches)){
								$newLink = 'https://whosonfirst.mapzen.com' . $matches[1];
								$whosOnFirstDataRaw = file_get_contents($newLink);
								$this->archiveObject->getDatastream('WOF')->content = $whosOnFirstDataRaw;
								$whosOnFirstData = json_decode($whosOnFirstDataRaw, true);
							}
						}

						if ($whosOnFirstData != null){
							$geoData = array();
							$geoData['latitude'] = $whosOnFirstData['properties']['lbl:latitude'];
							$geoData['longitude'] = $whosOnFirstData['properties']['lbl:longitude'];

							$boundingBox = $whosOnFirstData['bbox'];
							$foundLatLon = true;
							$timer->logTime("Loaded Lat Long from Whos on First");
							break;
						}
					}elseif ($link['type'] == 'geoNames'){
						if ($this->archiveObject->getDatastream('GEONAMES')){
							$geoNamesRaw = $this->archiveObject->getDatastream('GEONAMES')->content;
						}else{
							$geoNamesRaw = file_get_contents($link['link']);
							$datastream = $this->archiveObject->constructDatastream('GEONAMES');
							$datastream->mimetype = 'text/xml';
							$datastream->label = 'Raw data from GeoNames';
							$datastream->setContentFromString($geoNamesRaw);
							$this->archiveObject->ingestDatastream($datastream);
						}
						if (preg_match('/<wgs84_pos:lat>(.*?)<\/wgs84_pos:lat>\\s+<wgs84_pos:long>(.*?)<\/wgs84_pos:long>/', $geoNamesRaw, $matches)) {
							$geoData = array();
							$geoData['latitude'] = $matches[1];
							$geoData['longitude'] = $matches[2];

							$foundLatLon = true;
							$timer->logTime("Loaded Lat Long from GeoNames");
							break;
						}

					}
				}
			}
			if ($foundLatLon){
				//we found the latitude and longitude from a link update the MODS record
				$modsData = $this->getModsData();
				$modsData = str_replace('<marmot:latitude/>', "<marmot:latitude>{$geoData['latitude']}</marmot:latitude>", $modsData);
				$modsData = str_replace('<marmot:longitude/>', "<marmot:longitude>{$geoData['longitude']}</marmot:longitude>", $modsData);
				$this->archiveObject->getDatastream('MODS')->content = $modsData;
			}
		}

		return $geoData;
	}

	function convertDMSToDD($input) {
		if (is_numeric($input)){
			return $input;
		}
		$parts = preg_split('/[^\d\w.]+/', $input);
		$degrees = $parts[0];
		$minutes = $parts[1];
		$seconds = $parts[2];
		$direction = $parts[3];
		$dd = $degrees + $minutes/60 + $seconds/(60*60);

		if ($direction == "S" || $direction == "W") {
			$dd = $dd * -1;
		} // Don't do anything for N or E
		return $dd;
	}

	public function getFormat(){
		return 'Place';
	}
}
