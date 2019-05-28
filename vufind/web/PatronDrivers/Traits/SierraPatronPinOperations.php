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

	public function updatePin($patron, $oldPin, $newPin){
		// TODO: Implement updatePin() method.
	}

	public function resetPin($patron, $newPin, $resetToken = null){
		// TODO: Implement resetPin() method.
	}

}