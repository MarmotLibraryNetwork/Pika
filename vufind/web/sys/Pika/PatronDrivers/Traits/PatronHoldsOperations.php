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
 * @author   : Pascal Brammeier
 * Date: 5/13/2019
 *
 */

/**
 * Trait PatronHoldsOperations
 *
 *  Defines all the Patron-related actions needs to manage holds through the ILS
 */
trait PatronHoldsOperations {

	/**
	 * Get Patron Holds
	 *
	 * This is responsible for retrieving all holds for a specific patron.
	 *
	 * @param User $patron The user to fetch holds for
	 * @param bool $linkedAccount
	 * @return MyHold[]  An array of the patron's MyHold objects
	 */
	public abstract function getMyHolds($patron, $linkedAccount);

	/**
	 * Place Hold on a Bib Record
	 *
	 * This is responsible for  placing bib level holds.
	 *
	 * @param User        $patron       The User to place a hold for
	 * @param string      $recordId     The id of the bib record
	 * @param string      $pickupBranch The branch where the user wants to pickup the item when available
	 * @param null|string $cancelDate   The date to cancel the Hold if it isn't filled
	 *
	 * @return  array                 An array with the following keys
	 *                                  success - true/false
	 *                                  message - the message to display (if item holds are required, this is a form to select the item).
	 *                                  needsItemLevelHold - An indicator that item level holds are required
	 *                                  title - the title of the record the user is placing a hold on
	 * @access  public
	 */
	public abstract function placeHold($patron, $recordId, $pickupBranch, $cancelDate = null);

	/**
	 * Place an Item-level Hold
	 *
	 * This is responsible for both placing item level holds.
	 *
	 * @param User        $patron       The User to place a hold for
	 * @param string      $recordId     The id of the bib record
	 * @param string      $itemId       The id of the item to hold
	 * @param null|string $pickupBranch The branch where the user wants to pickup the item when available
	 *
	 * @return  array                 An array with the following keys
	 *                                  success - true/false
	 *                                  message - the message to display
	 *                                  title - the title of the record the user is placing a hold on
	 * @access  public
	 */
	public abstract function placeItemHold($patron, $recordId, $itemId, $pickupBranch);

	/**
	 * Place Volume-level Hold
	 *
	 * This is responsible for placing volume level holds.
	 *
	 * @param User        $patron       The User to place a hold for
	 * @param string      $recordId     The id of the bib record
	 * @param string      $volumeId     The id of the volume to hold
	 * @param null|string $pickupBranch The branch where the user wants to pickup the item when available
	 *
	 * @return  array                 An array with the following keys
	 *                                  success - true/false
	 *                                  message - the message to display
	 *                                  title - the title of the record the user is placing a hold on
	 * @access  public
	 */
	public abstract function placeVolumeHold($patron, $recordId, $volumeId, $pickupBranch);

	/**
	 * @param User   $patron            The User to change the hold for
	 * @param string $holdId            The Id needed to change the hold's pick up location
	 * @param string $newPickupLocation The pickup location to change the hold to
	 *
	 * @return  array                 An array with the following keys
	 *                                  success - true/false
	 *                                  message - the message to display
	 * @access  public
	 */
	public abstract function changeHoldPickupLocation($patron, $holdId, $newPickupLocation);

	/**
	 * Cancels a hold for a patron
	 *
	 * @param User   $patron   The User to cancel the hold for
	 * @param string $cancelId Information about the hold to be cancelled
	 *
	 * @return  array
	 */
	public abstract function cancelHold($patron, $bibId, $holdId);

	/**
	 * Freezes/Suspends/Pauses a hold for a patron.
	 *
	 * Intended to prevent a hold from being fulfilled without having to cancel the hold or losing a patron's position in
	 * the hold queue unnecessarily.
	 *
	 * @param User   $patron           The User to freeze the hold for
	 * @param string $holdToFreezeId   The Id needed to freeze the hold
	 * @param string $dateToReactivate The date a hold should be automatically thawed.
	 *
	 * @return array
	 * @access  public
	 */
	public abstract function freezeHold($patron, $bibId, $holdId, $dateToReactivate = null);


	/**
	 * Unfreeze/Unsuspend/Resume a hold for a patron
	 *
	 * @param User   $patron       The User to thaw the hold for
	 * @param string $holdToThawId The Id needed to thaw the hold
	 *
	 * @return array
	 * @access  public
	 */
	public abstract function thawHold($patron, $bibId, $holdId);

}


