<?php
/**
 * Sierra API functions specific to Addison Public Library.
 *
 * @category Pika
 * @package  PatronDrivers
 * @author   Chris Froese
 * @author   Pascal Brammeier
 * Date: 5/13/2019
 */

namespace Pika\PatronDrivers;

use Pika\SierraPatronListOperations;

require_once ROOT_DIR . "/sys/Pika/PatronDrivers/Traits/SierraPatronListOperations.php";

class Addison extends Sierra {

	use SierraPatronListOperations;

}