<?php
/**
 * Class that handles Ids from various record sources. The class makes it
 * explicit which part of the Id is being used.
 *
 * A SourceAndId object should be passed between classes and methods
 * when dealing with the IDs from specific sources.
 *
 * @category Pika
 * @author   : Pascal Brammeier
 * Date: 6/1/2019
 *
 */


class SourceAndId {

	static $defaultSource = 'ils'; // When an SourceAndId object is constructed with Id info without source data, fallback to this source

	private $fullId;                // The full id in the form of 'source:id' or 'ExternalEcontent:ils:id'
	private $source;                // The indexing profile that this Id is a part of
	private $recordId;              // The id for the particular profile. The Id that would be found in the record
	public  $isIlsEContent = false; // Whether or not this Id is related to an eContent record stored in the ILS
	/** var IndexingProfile */
	private $indexingProfile;

	function __construct($fullIdWithSource){
		$this->setSourceAndId($fullIdWithSource);
	}

	/**
	 * Set the source and record Id
	 * @param string $fullIdWithSource  A string that contains the fullId pattern of 'source:id' or 'ExternalEcontent:ils:id'
	 */
	public function setSourceAndId($fullIdWithSource){
		$idParts  = explode(':', $fullIdWithSource);
		$numParts = count($idParts);
		if ($numParts == 2){ // Typical full Id
			$this->source   = $idParts[0];
			$this->recordId = $idParts[1];
		}elseif ($numParts == 3){ // ILS eContent ID
			$this->isIlsEContent = true;
			$this->source        = $idParts[1];
			$this->recordId      = $idParts[2];
		}elseif ($numParts == 1){ // bare record id. Have to assume the source.
			$this->source   = self::$defaultSource;
			$this->recordId = $idParts[0];
		}
		$this->fullId = $this->source . ':' . $this->recordId;
	}

	function __toString(){
		return $this->getSourceAndId();
	}

	/**
	 * @return string|null The full ID string which has the source part and record id part in the form 'source:id'
	 */
	public function getSourceAndId(){
		return $this->fullId;
	}

	public function getSource(){
		return $this->source;
	}

	public function getRecordId(){
		return $this->recordId;
	}

	/**
	 * Get the indexing profile object associated with this record Id's source
	 *
	 * @return IndexingProfile|null
	 */
	public function getIndexingProfile(){
		if (empty($this->indexingProfile)){
			/** @var $indexingProfiles IndexingProfile[] */
			global $indexingProfiles;
			if (array_key_exists($this->source, $indexingProfiles)){
				$this->indexingProfile = $indexingProfiles[$this->source];
			}
		}
		return $this->indexingProfile;
	}

}