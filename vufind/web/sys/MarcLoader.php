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

require_once 'File/MARC.php';
require_once ROOT_DIR . '/services/SourceAndId.php';
/**
 * Class MarcLoader
 *
 * Loads a Marc record from the database or file system as appropriate.
 */
class MarcLoader{
	/**
	 * @param array $record An array of record data from Solr
	 * @return File_MARC_Record
	 */
	public static function loadMarcRecordFromRecord($record){
		if ($record['recordtype'] == 'marc'){
			return MarcLoader::loadMarcRecordByILSId(new SourceAndId($record['id']));
		}else{
			return null;
		}

	}

	private static $loadedMarcRecords = array();

	/**
	 * @param SourceAndId $sourceAndId The id of the record within the ils
	 *
	 * @return File_MARC_Record
	 */
	public static function loadMarcRecordByILSId(SourceAndId $sourceAndId){
		$fullId = $sourceAndId->getSourceAndId();

		if (array_key_exists($fullId, MarcLoader::$loadedMarcRecords)){
			return MarcLoader::$loadedMarcRecords[$fullId];
		}

		$marcRecord     = false;
		$individualName = self::getIndividualMarcFileName($sourceAndId);
		if (file_exists($individualName)){
			$rawMarc = file_get_contents($individualName);
			try {
				$marc = new File_MARC($rawMarc, File_MARC::SOURCE_STRING);
				if (!($marcRecord = $marc->next())){
					PEAR_Singleton::raiseError(new PEAR_Error('Could not load marc record for record ' . $fullId));
				}

				//Make sure not to use too much memory
				global $memoryWatcher;
				if (count(MarcLoader::$loadedMarcRecords) > 50){
					array_shift(MarcLoader::$loadedMarcRecords);
					$memoryWatcher->logMemory("Removed Cached MARC");
				}
				$memoryWatcher->logMemory("Loaded MARC for $fullId");
				MarcLoader::$loadedMarcRecords[$fullId] = $marcRecord;
			} catch (File_MARC_Exception $e){
				PEAR_Singleton::raiseError(new PEAR_Error('Could not load marc record for record ' . $fullId));
			}
		}

		return $marcRecord;
	}

	/**
	 * @param SourceAndId $sourceAndId Passed as <type>:<id>
	 *
	 * @return int|boolean
	 */
	public static function lastModificationTimeForIlsId($sourceAndId){
		$individualName = self::getIndividualMarcFileName($sourceAndId);
		if (!empty($individualName)){
			return filemtime($individualName);
		}else{
			return false;
		}
	}

	/**
	 * @param SourceAndId $sourceAndId Passed as <type>:<id>
	 *
	 * @return boolean
	 */
	public static function marcExistsForILSId($sourceAndId){
		$individualName = self::getIndividualMarcFileName($sourceAndId);
		if (!empty($individualName)){
			return file_exists($individualName);
		}else{
			return false;
		}
	}

	/**
	 * Gets the full path (and name) for the Individual Marc File associated with the record
	 *
	 * @param SourceAndId $sourceAndId The ID of the record to get file path for
	 *
	 * @return string
	 */
	private static function getIndividualMarcFileName(SourceAndId $sourceAndId){
		$indexingProfile = $sourceAndId->getIndexingProfile();
		$shortId         = str_replace('.', '', $sourceAndId->getRecordId());
		if (strlen($shortId) < 9){
			$shortId = str_pad($shortId, 9, '0', STR_PAD_LEFT);
		}
		if ($indexingProfile->createFolderFromLeadingCharacters){
			$firstChars = substr($shortId, 0, $indexingProfile->numCharsToCreateFolderFrom);
		}else{
			$firstChars = substr($shortId, 0, strlen($shortId) - $indexingProfile->numCharsToCreateFolderFrom);
		}
		if (!empty($indexingProfile->individualMarcPath)){
			$individualMarcFileName = $indexingProfile->individualMarcPath . "/{$firstChars}/{$shortId}.mrc";
		}
		return $individualMarcFileName;
	}

	/**
	 * @param SourceAndId $sourceAndId
	 *
	 * @return IndexingProfile
	 */
	public static function getIndexingProfileForId(SourceAndId $sourceAndId){
		$recordType = $sourceAndId->getSource();
		/** @var $indexingProfiles IndexingProfile[] */
		global $indexingProfiles;
		if (array_key_exists($recordType, $indexingProfiles)){
			return $indexingProfiles[$recordType];
		}
	}
}
