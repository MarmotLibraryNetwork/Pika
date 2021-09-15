<?php
/*
 * Copyright (C) 2021  Marmot Library Network
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
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

class ItemDetails {

	public $recordIdentifier;
	public $itemIdentifier;
	public $shelfLocation;
	public $callNumber;
	public $format;
	public $formatCategory;
	public int $numCopies;
	public bool $isOrderItem;
	public bool $isEContent;
	public $eContentSource;
	public $eContentUrl;
	public $subFormat;
	public $detailedStatus;
	public $lastCheckinDate;
	public $locationCode;

	/**
	 * Populate the object for the solr field
	 *
	 * @param $itemDetailsArray []  Exploded string of line from the Solr document item_details field
	 */
	public function __construct($itemDetailsArray){
		$this->recordIdentifier = $itemDetailsArray[0];
		$this->itemIdentifier   = $itemDetailsArray[1]  == 'null' ? '' : $itemDetailsArray[1];
		$this->shelfLocation    = $itemDetailsArray[2];
		$this->callNumber       = $itemDetailsArray[3];
		$this->format           = $itemDetailsArray[4];
		$this->formatCategory   = $itemDetailsArray[5];
		$this->numCopies        = (int) $itemDetailsArray[6];
		$this->isOrderItem      = $itemDetailsArray[7] == '1' || $itemDetailsArray[7] == 'true';
		$this->isEContent       = $itemDetailsArray[8] == '1' || $itemDetailsArray[8] == 'true';
		$this->eContentSource   = $itemDetailsArray[9];
		//TODO: have to decrement indexes below by one after econtentFile is removed from the item_details field
		$this->eContentUrl      = $itemDetailsArray[11];
		$this->subFormat        = $itemDetailsArray[12];
		$this->detailedStatus   = $itemDetailsArray[13];
		$this->lastCheckinDate  = $itemDetailsArray[14] ?? '';
		$this->locationCode     = $itemDetailsArray[15] ?? '';
	}
}