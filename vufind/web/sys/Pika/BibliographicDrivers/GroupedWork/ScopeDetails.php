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
 * Date: 9/16/2021
 *
 */


namespace Pika\BibliographicDrivers\GroupedWork;

class ScopeDetails {
	public $recordFullIdentifier;
	public $itemIdentifier;
	public $groupedStatus;
	public $status;
	public bool $locallyOwned;
	public bool $available;
	public bool $holdable;
	public bool $bookable;
	public bool $inLibraryUseOnly;
	public bool $libraryOwned;
	public $holdablePTypes;
	public $bookablePTypes;
	public $localUrl;

	/**
	 * @param $scopeDetailArray string[]
	 */
	public function __construct($scopeDetailArray){
		$this->recordFullIdentifier = $scopeDetailArray[0];
		$this->itemIdentifier       = $scopeDetailArray[1];
		$this->groupedStatus        = $scopeDetailArray[2];
		$this->status               = $scopeDetailArray[3];
		$this->locallyOwned         = $scopeDetailArray[4] == '1' || $scopeDetailArray[4] == 'true';
		$this->available            = $scopeDetailArray[5] == '1' || $scopeDetailArray[5] == 'true';
		$this->holdable             = $scopeDetailArray[6] == '1' || $scopeDetailArray[6] == 'true';
		$this->bookable             = $scopeDetailArray[7] == '1' || $scopeDetailArray[7] == 'true';
		$this->inLibraryUseOnly     = $scopeDetailArray[8] == '1' || $scopeDetailArray[8] == 'true';
		$this->libraryOwned         = $scopeDetailArray[9] == '1' || $scopeDetailArray[9] == 'true';
		$this->holdablePTypes       = $scopeDetailArray[10] ?? '';
		$this->bookablePTypes       = $scopeDetailArray[11] ?? '';
		$this->localUrl             = $scopeDetailArray[12] ?? '';
	}
}