<?php
require_once ('File/MARC.php');
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
			return MarcLoader::loadMarcRecordByILSId($record['id']);
		}else{
			return null;
		}

	}

	/**
	 * @param string $ilsId       The id of the record within the ils
	 * @param string $recordType  The type of the record in the system
	 * @return File_MARC_Record
	 */
	private static $loadedMarcRecords = array();
	public static function loadMarcRecordByILSId($id){
		$recordInfo = explode(':', $id);
		$ilsId      = $recordInfo[1];

		if (array_key_exists($ilsId, MarcLoader::$loadedMarcRecords)){
			return MarcLoader::$loadedMarcRecords[$ilsId];
		}
		$marcRecord = false;

		list($indexingProfile, $ilsId) = self::getIndexingProfileForId($id);
		$individualName = self::getIndividualMarcFileName($ilsId, $indexingProfile);
		if (file_exists($individualName)){
			$rawMarc = file_get_contents($individualName);
			try {
				$marc = new File_MARC($rawMarc, File_MARC::SOURCE_STRING);
				if (!($marcRecord = $marc->next())){
					PEAR_Singleton::raiseError(new PEAR_Error('Could not load marc record for record ' . $id));
				}

				//Make sure not to use too much memory
				global $memoryWatcher;
				if (count(MarcLoader::$loadedMarcRecords) > 50){
					array_shift(MarcLoader::$loadedMarcRecords);
					$memoryWatcher->logMemory("Removed Cached MARC");
				}
				$memoryWatcher->logMemory("Loaded MARC for $id");
				MarcLoader::$loadedMarcRecords[$id] = $marcRecord;
			} catch (File_MARC_Exception $e){
				PEAR_Singleton::raiseError(new PEAR_Error('Could not load marc record for record ' . $id));
			}
		}

		return $marcRecord;
	}

	/**
	 * @param string $id       Passed as <type>:<id>
	 * @return int
	 */
	public static function lastModificationTimeForIlsId($id){
		list($indexingProfile, $ilsId) = self::getIndexingProfileForId($id);
		$individualName = self::getIndividualMarcFileName($ilsId, $indexingProfile);
		if (!empty($individualName)){
			return filemtime($individualName);
		}else{
			return false;
		}
	}

	/**
	 * @param string $id       Passed as <type>:<id>
	 * @return boolean
	 */
	public static function marcExistsForILSId($id){
		list($indexingProfile, $ilsId) = self::getIndexingProfileForId($id);
		$individualName = self::getIndividualMarcFileName($ilsId, $indexingProfile);
		if (!empty($individualName)){
			return file_exists($individualName);
		}else{
			return false;
		}
	}

	/**
	 * Gets the full path (and name) for the Indivdual Marc File associated with the record
	 *
	 * @param string          $individualRecordId The ID of the record to get file path for
	 * @param IndexingProfile $indexingProfile    The Indexing Profile of the collection the record is a part of
	 * @return string
	 */
	private static function getIndividualMarcFileName($individualRecordId, $indexingProfile){
		$shortId = str_replace('.', '', $individualRecordId);
		if (strlen($shortId) < 9){
			$shortId = str_pad($shortId, 9, "0", STR_PAD_LEFT);
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

	private static function getIndexingProfileForId($id){
		if (strpos($id, ':') !== false){
			$recordInfo = explode(':', $id);
			$recordType = $recordInfo[0];
			$ilsId      = $recordInfo[1];
		}else{
			//Try to infer the indexing profile from the module
			/** @var IndexingProfile $activeRecordProfile */
			global $activeRecordProfile;
			if ($activeRecordProfile){
				$recordType = $activeRecordProfile->name;
			}else{
				$recordType = 'ils';
			}
			$ilsId = $id;
		}

		/** @var $indexingProfiles IndexingProfile[] */
		global $indexingProfiles;
		if (array_key_exists($recordType, $indexingProfiles)){
			$indexingProfile = $indexingProfiles[$recordType];
		}else{
			$indexingProfile = $indexingProfiles['ils'];
		}

		return array($indexingProfile, $ilsId);
	}
}