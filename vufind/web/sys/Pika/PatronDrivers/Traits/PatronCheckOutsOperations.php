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
 * Trait PatronCheckOutsOperations
 *
 * Defines all the Patron-related actions needs to manage checkouts through the ILS
 */
trait PatronCheckOutsOperations {


	/**
	 * Get Patron Checkouts
	 *
	 * This is responsible for retrieving all checked out titles for a specific patron.
	 *
	 * @param User $patron The user to fetch holds for
	 *
	 * @return MyCheckOut[] An array of the patron's MyCheckOut objects
	 * @access  public
	 */
	public abstract function getMyCheckouts($patron);


	/**
	 * Renew a checked out title for the patron
	 *
	 * @param User   $patron      The User to renew the item for
	 * @param string $renewItemId The ID needed to renew the Item
	 *
	 * @return  array                 An array with the following keys
	 *                                  success - true/false
	 *                                  message - the message to display
	 *                                  itemId  - the Id of the item that was renewed
	 * @access  public
	 */
	public abstract function renewItem($patron, $renewItemId);

}
