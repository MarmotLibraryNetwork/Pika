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
require_once ROOT_DIR . "/PatronDrivers/Traits/SierraPatronPinOperations.php";

class Addison extends Sierra {
	use SierraPatronSelfRegistrationOperations;
	use SierraPatronPinOperations;


}