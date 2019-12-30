<?php
/**
 * Description goes here
 *
 * @category Pika
 * @author Mark Noble <mark@marmot.org>
 * Date: 4/29/13
 * Time: 8:48 AM
 */

class NovelistFactory {
	static private $novelistDriver;

	static function getNovelist(){
		if (!isset(self::$novelistDriver)){
			global $configArray;
			if (!isset($configArray['Novelist']['apiVersion']) || $configArray['Novelist']['apiVersion'] < 3){
				die("This version of Novelist is no longer supported!");
			}else{
				require_once ROOT_DIR . '/sys/Novelist/Novelist3.php';
				self::$novelistDriver = new Novelist3();
			}
		}
		return self::$novelistDriver;
	}
}