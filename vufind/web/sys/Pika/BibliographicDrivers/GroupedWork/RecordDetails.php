<?php
/*
 * Pika Discovery Layer
 * Copyright (C) 2023  Marmot Library Network
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
 *
 *
 * @category Pika
 * @author: Pascal Brammeier
 * Date: 9/15/2021
 *
 */


namespace Pika\BibliographicDrivers\GroupedWork;

class RecordDetails {
	public $recordFullIdentifier;
	public $primaryFormat;
	public $primaryFormatCategory;
	public $edition;
	public $primaryLanguage;
	public $publisher;
	public $publicationDate;
	public $physicalDescription;
	public bool $abridged = false;

	/**
	 * Exploded string of line from the Solr document item_details field
	 *
	 * @param $recordDetailsArray string[]
	 */
	function __construct($recordDetailsArray){
		$this->recordFullIdentifier  = $recordDetailsArray[0];
		$this->primaryFormat         = $recordDetailsArray[1];
		$this->primaryFormatCategory = $recordDetailsArray[2];
		$this->edition               = $recordDetailsArray[3];
		$this->primaryLanguage       = $recordDetailsArray[4];
		$this->publisher             = $recordDetailsArray[5];
		$this->publicationDate       = $recordDetailsArray[6];
		$this->physicalDescription   = $recordDetailsArray[7];
		$this->abridged              = !empty($recordDetailsArray[8]);
	}
}