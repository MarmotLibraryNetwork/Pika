<?php
/**
 *
 *
 * @category Pika
 * @author   : Pascal Brammeier
 * Date: 6/1/2019
 *
 */


class sourceAndId {

	const DEFAULT_SOURCE = 'ils';

	private $fullId;                // The full id in the form of 'source:id' or 'ExternalEcontent:ils:id'
	private $source;                // The indexing profile that this Id is a part of
	private $recordId;              // The id for the particular profile. The Id that would be found in the record
	public  $isIlsEContent = false; // Whether or not this Id is related to an eContent record stored in the ILS

	function __construct($fullIdWithSource){
		$this->setSourceAndId($fullIdWithSource);
	}

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
			$this->source   = self::DEFAULT_SOURCE;
			$this->recordId = $idParts[0];
		}
		$this->fullId = $this->source . ':' . $this->recordId;
	}

	function __toString(){
		return $this->getSourceAndId();
	}

	public function getSourceAndId(){
		return $this->fullId;
	}

	public function getSource(){
		return $this->source;
	}

	public function getRecordId(){
		return $this->recordId;
	}
}