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
 * Circulation System Specific Driver Class
 *
 * This interface class is the definition of the required methods for
 * interacting with the local ILS.
 *
 */
interface DriverInterface
{
	/**
	 * DriverInterface constructor.
	 * @param AccountProfile $accountProfile
	 */
	public function __construct($accountProfile);

	public function patronLogin($username, $password, $validatedViaSSO);

	/**
	 * Specifies Whether an ILS has its own reading history functions we can use
	 *
	 * @param $id
	 * @return int
	 */
	public function hasNativeReadingHistory();

	/**
	 * Return the number of holds that are on a record.
	 * This is used on for My Holds page to display the hold queue length for circulation systems that don't provide
	 * this information directly in the information about a specific hold.
	 *
	 * @param $id
	 * @return int|false
	 */
	public function getNumHoldsOnRecord($id);

	/**
	 * Get Patron Transactions
	 *
	 * This is responsible for retrieving all transactions (i.e. checked out items)
	 * by a specific patron.
	 *
	 * @param User $patron    The user to load transactions for
	 *
	 * @return array        Array of the patron's transactions on success
	 * @access public
	 */
	public function getMyCheckouts($patron);

	/**
	 * @return boolean true if the driver can renew all titles in a single pass
	 */
//	public function hasFastRenewAll();

	/**
	 * Renew all titles currently checked out to the user
	 *
	 * @param $patron  User
	 * @return mixed
	 */
//	public function renewAll($patron);

	/**
	 * Renew a single title currently checked out to the user
	 *
	 * @param $patron     User
	 * @param $recordId   string
	 * @param $itemId     string
	 * @param $itemIndex  string
	 * @return mixed
	 */
	public function renewItem($patron, $recordId, $itemId, $itemIndex);

	/**
	 * Get Patron Holds
	 *
	 * This is responsible for retrieving all holds for a specific patron.
	 *
	 * @param User $patron The user to load transactions for
	 *
	 * @return array        Array of the patron's holds
	 * @access public
	 */
	public function getMyHolds($patron);

	/**
	 * Place Hold
	 *
	 * This is responsible for both placing holds as well as placing recalls.
	 *
	 * @param   User $patron             The User to place a hold for
	 * @param   string $recordId         The id of the bib record
	 * @param   string $pickupBranch     The branch where the user wants to pickup the item when available
	 * @param   null|string $cancelDate  The date to cancel the Hold if it isn't filled
	 * @return  array                 An array with the following keys
	 *                                success - true/false
	 *                                message - the message to display (if item holds are required, this is a form to select the item).
	 *                                needsItemLevelHold - An indicator that item level holds are required
	 *                                title - the title of the record the user is placing a hold on
	 * @access  public
	 */
	public function placeHold($patron, $recordId, $pickupBranch, $cancelDate = null);

	/**
	 * Place Item Hold
	 *
	 * This is responsible for both placing item level holds.
	 *
	 * @param   User    $patron     The User to place a hold for
	 * @param   string  $recordId   The id of the bib record
	 * @param   string  $itemId     The id of the item to hold
	 * @param   string  $pickupBranch The branch where the user wants to pickup the item when available
	 * @return  array                 An array with the following keys
	 *                                success - true/false
	 *                                message - the message to display
	 *                                title - the title of the record the user is placing a hold on
	 * @access  public
	 */
	function placeItemHold($patron, $recordId, $itemId, $pickupBranch);

	/**
	 * Cancels a hold for a patron
	 *
	 * @param   User    $patron     The User to cancel the hold for
	 * @param   string  $recordId   The id of the bib record
	 * @param   string  $cancelId   Information about the hold to be cancelled
	 * @return  array
	 */
	function cancelHold($patron, $recordId, $cancelId);

	function freezeHold($patron, $recordId, $itemToFreezeId, $dateToReactivate);
	//TODO: refactor $recordId as last and optional, since it isn't required for any actual freeze hold actions in our ILS drivers
	// (except carlx sip calls)

	function thawHold($patron, $recordId, $itemToThawId);
	//TODO: refactor $recordId as last and optional, since it isn't required for any actual thaw hold actions in our ILS drivers
	// (except carlx sip calls)

	function changeHoldPickupLocation($patron, $recordId, $itemToUpdateId, $newPickupLocation);

}
