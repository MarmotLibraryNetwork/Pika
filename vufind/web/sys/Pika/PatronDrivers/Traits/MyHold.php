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
 *
 *
 * @category Pika
 * @author: Pascal Brammeier
 * Date: 12/6/2019
 *
 */

namespace Pika;

/**
 * Class MyHold
 *
 * Basic Object to define the properties of a patron's Hold needed to display the hold on the MyHolds page.
 * This class should be as ILS-agnostic as possible.
 */
class MyHold {

	public $user;        // The name of the patron that the hold belongs to
	public $userId;      // The Pika Id number of the patron the hold belongs to

	public $id;          // The id of the hold? or the record Id?
	public $cancelId;    // The hold id needed to cancel, freeze, thaw or change pickup location of this hold
	public $cancelable;  // Whether or not the hold can be cancelled

	public $coverUrl;
	public $linkUrl;

	public $title;
	public $title2;
	public $volume;
	public $author; // Can be an array
	public $format = array();

	public $location;           // The name of the location the hold will arrive at
	public $locationUpdateable; // Whether or not the pick up location can be changed for this hold

	public $create;                 // The date the hold was placed
	public $availableTime;          //The date the hold became available for pick up
	public $expire;                 // The date an available hold will expire.  The Pick-Up By date
	public $automaticCancellation;  // The date the hold will automatically cancel if not fulfilled

	public $status;     // The status of the hold
	public $position;   //The place that the hold is in the hold queue. (eg. 4 of 24)

	public $allowFreezeHolds; // Whether or not freezing/thawing holds is allowed
	public $frozen;           // Whether or not the hold is frozen
	public $reactivate;       // The date the frozen hold will automatically thaw
	public $freezable;        // Whether or not the hold is freezable

}
