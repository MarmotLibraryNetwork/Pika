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
 * Trait PatronPinOperations
 *
 *  Defines all the Patron-related actions needed to manage a patron's PIN through the ILS
 */
trait PatronPinOperations {

	/**
	 * Update the patron's PIN.
	 *
	 * This is for when the patron knows their PIN and wants to change its value
	 *
	 * @param User   $patron The user to update PIN for
	 * @param string $oldPin The current PIN
	 * @param string $newPin The PIN to update to
	 *
	 * @return string
	 */
	public abstract function updatePin($patron, $oldPin, $newPin);

	/**
	 * This method completes a patron pin reset operation. The patron has received an email from the ILS which will
	 * include the user Id and a reset Token, which enables Pika to complete the pin reset in the ILS
	 *
	 * @param User        $patron
	 * @param string      $newPin
	 * @param null|string $resetToken
	 *
	 * @return array
	 */
	public abstract function resetPin($patron, $newPin, $resetToken = null);

}
