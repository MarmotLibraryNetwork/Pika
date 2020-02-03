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

trait PatronBookingsOperations {

	/**
	 * Fetch the patron's bookings
	 *
	 * @param User $patron The user to fetch bookings for
	 *
	 * @return array
	 */
	public abstract function getMyBookings(\User $patron);

	/**
	 * @param User        $patron    The user to book an item for
	 * @param string      $recordId  The record to book
	 * @param string      $startDate The date to book an item for
	 * @param string|null $startTime The time the booking will start
	 * @param string|null $endDate   The date the booking will end (There are loan rules to set this by default I believe)
	 * @param string|null $endTime   The time the booking will end (There are loan rules to set this by default I believe)
	 *
	 * @return mixed
	 */
	public abstract function bookMaterial($patron, $recordId, $startDate, $startTime = null, $endDate = null, $endTime = null);

	/**
	 * Cancel a Booking
	 *
	 * @param User   $patron          The user the booking belongs to
	 * @param string|array $cancelIds The Id or array of Ids needed to cancel the booking
	 */
	public abstract function cancelBookedMaterial(\User $patron, $cancelIds);

	/**
	 * Cancel all Bookings
	 *
	 * @param User   $patron          The user the bookings belongs to
	 */
	public abstract function cancelAllBookedMaterial(\User $patron);

	/**
	 *  Fetch the calendar to use with scheduling a booking
	 *
	 * @param User   $patron   The user to book an item for
	 * @param string $recordId The record to book
	 *
	 * @return string  An HTML table
	 */
	public abstract function getBookingCalendar(\User $patron, $recordId);
}
