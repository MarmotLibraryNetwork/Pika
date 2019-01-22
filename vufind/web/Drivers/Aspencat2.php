<?php
/**
 *
 *
 * @category Pika
 * @author: Pascal Brammeier
 * Date: 9/19/2018
 *
 */

require_once ROOT_DIR . '/Drivers/ByWaterKoha.php';
class Aspencat2 extends ByWaterKoha
{

	public function updatePin($user, $oldPin, $newPin, $confirmNewPin){
		// functionality doesn't exist in current drivers. Intentionally disabling for Aspencat
		return 'Sorry, Pin update is not allowed.';
	}

	}