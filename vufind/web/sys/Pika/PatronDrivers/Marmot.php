<?php
/**
 *
 *
 * @category Pika
 * @author   : Pascal Brammeier
 * Date: 5/13/2019
 *
 */

require_once ROOT_DIR . "/PatronDrivers/Sierra.php";
require_once ROOT_DIR . "/PatronDrivers/Traits/SierraPatronSelfRegistrationOperations.php";
require_once ROOT_DIR . "/PatronDrivers/Traits/PatronBookingsOperations.php";

class Marmot extends Sierra {

	use SierraPatronSelfRegistrationOperations;
	use PatronBookingsOperations;

	public function getMyBookings($patron){
		// TODO: Implement getMyBookings() method.
	}

	public function bookMaterial($patron, $recordId, $startDate, $startTime = null, $endDate = null, $endTime = null){
		// TODO: Implement bookMaterial() method.
	}

	public function cancelBookedMaterial($patron, $cancelId){
		// TODO: Implement cancelBookedMaterial() method.
	}

	public function getBookingCalendar($patron, $recordId){
		// TODO: Implement getBookingCalendar() method.
	}


}