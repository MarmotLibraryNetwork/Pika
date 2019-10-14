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