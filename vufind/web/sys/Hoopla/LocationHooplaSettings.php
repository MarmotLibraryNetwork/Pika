<?php
/**
 * Database object that stores Location level options for what kind of records to
 * include or exclude from Hoopla in their search results. (The location scope)
 *
 * @category Pika
 * @author   : Pascal Brammeier
 * Date: 4/29/2019
 *
 */

require_once ROOT_DIR . '/sys/Hoopla/HooplaSetting.php';

class LocationHooplaSettings extends HooplaSetting {
	public $__table = 'location_hoopla_setting';
	public $locationId;


}
