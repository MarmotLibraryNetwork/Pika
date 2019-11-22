<?php
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