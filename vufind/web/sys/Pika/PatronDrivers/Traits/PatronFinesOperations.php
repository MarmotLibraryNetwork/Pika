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
 * Trait PatronFineOperations
 *
 * Handle patron related interactions with the ILS regarding fines
 */
trait PatronFinesOperations {

	/**
	 * Get Patron Fines
	 *
	 * This is responsible for retrieving all fines info for a specific patron.
	 *
	 * @param User $patron The user to fetch holds for
	 *
	 * @return MyFine[] An array of the patron's MyFine objects
	 * @access  public
	 */
	public abstract function getMyFines($patron);

}
