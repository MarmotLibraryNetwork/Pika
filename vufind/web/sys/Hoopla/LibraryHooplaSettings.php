<?php
/**
 * Database object that stores Library level options for what kind of records to
 * include or exclude from Hoopla in their search results. (The library scope)
 *
 * @category Pika
 * @author   : Pascal Brammeier
 * Date: 4/29/2019
 *
 */

require_once ROOT_DIR . '/sys/Hoopla/HooplaSetting.php';

class LibraryHooplaSettings extends HooplaSetting {

	public $__table = 'library_hoopla_setting';
	public $libraryId;

}