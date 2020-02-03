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
 * Trait SierraPatronPinOperations
 *
 * These methods will be common among Sierra Libraries but are not used by every Sierra Library
 */
trait SierraPatronPinOperations {
	use PatronPinOperations;

	/**
	 * Update a users PIN
	 *
	 * PUT patrons/{id}
	 *
	 * @param User   $patron
	 * @param string $oldPin
	 * @param string $newPin
	 * @param string $confirmNewPin
	 * @return string Error or success message.
	 */
	public function updatePin($patron, $oldPin, $newPin, $confirmNewPin){
		// TODO: Implement updatePin() method.
	}

	public function resetPin($patron, $newPin, $resetToken = null){
		// TODO: Implement resetPin() method.
	}

}
