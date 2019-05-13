<?php
/**
 *
 *
 * @category Pika
 * @author   : Pascal Brammeier
 * Date: 5/13/2019
 *
 */

/**
 * Class Sierra
 *
 * Main driver to define the main methods needed for completing patron actions in the ILS
 *
 * Some optional actions that only some Sierra Libraries use can be included by extending this class
 * and using the relevant Traits
 */
require_once ROOT_DIR . "/PatronDrivers/PatronDriverInterface.php";
require_once ROOT_DIR . "/PatronDrivers/Traits/PatronHoldsOperations.php";
require_once ROOT_DIR . "/PatronDrivers/Traits/PatronCheckOutsOperations.php";
require_once ROOT_DIR . "/PatronDrivers/Traits/PatronFineOperations.php";
require_once ROOT_DIR . "/PatronDrivers/Traits/PatronReadingHistoryOperations.php";

class Sierra extends PatronDriverInterface {

	use PatronHoldsOperations;
	use PatronCheckOutsOperations;
	use PatronFineOperations;
	use PatronReadingHistoryOperations;

	public function getMyCheckouts($patron){
		// TODO: Implement getMyCheckouts() method.
	}

	public function renewItem($patron, $renewItemId){
		// TODO: Implement renewItem() method.
	}

	public function patronLogin($username, $password, $validatedViaSSO){
		// TODO: Implement patronLogin() method.
	}

	public function updatePatronInfo($patron, $canUpdateContactInfo){
		// TODO: Implement updatePatronInfo() method.
	}

	public function getMyFines($patron){
		// TODO: Implement getMyFines() method.
	}

	public function getMyHolds($patron){
		// TODO: Implement getMyHolds() method.
	}

	public function placeHold($patron, $recordId, $pickupBranch, $cancelDate = null){
		// TODO: Implement placeHold() method.
	}

	public function placeItemHold($patron, $recordId, $itemId, $pickupBranch){
		// TODO: Implement placeItemHold() method.
	}

	public function placeVolumeHold($patron, $recordId, $volumeId, $pickupBranch){
		// TODO: Implement placeVolumeHold() method.
	}

	public function changeHoldPickupLocation($patron, $holdId, $newPickupLocation){
		// TODO: Implement changeHoldPickupLocation() method.
	}

	public function cancelHold($patron, $cancelId){
		// TODO: Implement cancelHold() method.
	}

	public function freezeHold($patron, $holdToFreezeId, $dateToReactivate = null){
		// TODO: Implement freezeHold() method.
	}

	public function thawHold($patron, $holdToThawId){
		// TODO: Implement thawHold() method.
	}

	public function hasNativeReadingHistory(){
		// TODO: Implement hasNativeReadingHistory() method.
	}

	public function loadReadingHistoryFromIls($patron, $loadAdditional = null){
		// TODO: Implement loadReadingHistoryFromIls() method.
	}

	public function optInReadingHistory($patron){
		// TODO: Implement optInReadingHistory() method.
	}

	public function optOutReadingHistory($patron){
		// TODO: Implement optOutReadingHistory() method.
	}

	public function deleteAllReadingHistory($patron){
		// TODO: Implement deleteAllReadingHistory() method.
	}

}