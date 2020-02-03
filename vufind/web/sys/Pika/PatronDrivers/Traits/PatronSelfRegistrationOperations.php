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
 * Trait PatronSelfRegistrationOperations
 *
 * Defines all the patron-related actions needed to handle Self-Registration through the ILS
 */
trait PatronSelfRegistrationOperations {


	/**
	 * Send a Self Registration request to the ILS.  The relevant input should be taken from the $_REQUEST variable.
	 *
	 * @return  array                 An array with the following keys
	 *                                  success - true/false
	 *                                  barcode - the barcode for the self-registered user
	 */
	public abstract function selfRegister();

	/**
	 * @return array  An Object structure array to build the fields for the Self-Registration form.
	 */
	public abstract function getSelfRegistrationFields();
}
