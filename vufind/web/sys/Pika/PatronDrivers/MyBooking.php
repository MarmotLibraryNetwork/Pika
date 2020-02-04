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
 *  Class for defining the properties for a Booking
 *  A Booking is akin to a hold with a scheduled time component
 *
 * @category Pika
 * @author: Pascal Brammeier
 * Date: 11/21/2019
 *
 */


namespace Pika\PatronDrivers;


class MyBooking {
	public $id;
	public $title;
	public $sortTitle;
	public $startDateTime; // timestamp
	public $endDateTime;   // timestamp
	public $status;
	public $cancelName;
	public $cancelValue;

	// These are set when the MARC record is available
	public $author;
	public $format;
	public $linkUrl;
	public $coverUrl;

	// These are set when there is Grouped Work information
	public $groupedWorkId;
	public $ratingData;

	// These are set in the CatalogConnection
	public $userId;
	public $userDisplayName;


}
