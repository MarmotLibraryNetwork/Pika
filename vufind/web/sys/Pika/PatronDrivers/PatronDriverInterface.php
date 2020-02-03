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
 * Interface PatronDriverInterface
 *
 * This interface defines the methods needed for all patron-related interactions that happen between
 * Pika and the ILS.
 *
 * Within the extension of this interface you need to include the traits your driver will integrate :
 *
 *  PatronHoldsOperations
 *  PatronCheckOutsOperations
 *  PatronBookingsOperations
 *  PatronFineOperations
 *  PatronReadingHistoryOperations
 *  PatronSelfRegistrationOperations
 *  PatronPinOperations
 *
 * @category Pika
 * @author   : Pascal Brammeier
 * Date: 5/13/2019
 *
 */
namespace Pika\PatronDrivers;
abstract class PatronDriverInterface {

	/**
	 * Patron Login
	 *
	 * This is responsible for authenticating a patron against the catalog.
	 * Interface defined in CatalogConnection.php
	 *
	 * @param string  $username        The patron username
	 * @param string  $password        The patron password
	 * @param boolean $validatedViaSSO True if the patron has already been validated via SSO.  If so we don't need to validation, just retrieve information
	 *
	 * @return User|Exception          The User object for the patron
	 *                                 If an error occurs, return an appropriate exception.
	 * @access  public
	 */

	public abstract function patronLogin($username, $password, $validatedViaSSO);


	/**
	 * Update the User's information in Pika from data in the ILS
	 *
	 * @param User    $patron               The User Object to make updates to
	 * @param boolean $canUpdateContactInfo Permission check that updating is allowed
	 *
	 * @return array                        Array of error messages for errors that occurred while updating the user's
	 *                                      information in Pika
	 */
	public abstract function updatePatronInfo($patron, $canUpdateContactInfo);

	/**
	 *  Whether or not the ILS has a 'username' field that can be substituted for the login field.
	 * The default implementation is that it does not have the username field
	 *
	 * @return bool
	 */
	public function hasUsernameField(){
		return false;
	}

	/**
	 *   When the ILS provides the capability to look up users with out having login info already, implement this function
	 * so that Pika can create an User object for users that haven't been stored in Pika's database already.
	 * @param $patronBarcode
	 * @return bool|User
	 */
	public function findNewUser($patronBarcode) {
		return false;
	}
}
