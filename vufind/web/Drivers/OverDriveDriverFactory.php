<?php
class OverDriveDriverFactory{
	static function getDriver(){
		global $configArray;
		//TODO: obsolete ['OverDrive']['interfaceVersion']
//		if (isset($configArray['OverDrive']['interfaceVersion'])
//			&& $configArray['OverDrive']['interfaceVersion'] == 2){
//			require_once ROOT_DIR . '/Drivers/OverDriveDriver2.php';
//			$overDriveDriver = new OverDriveDriver2();
//		}else{
			require_once ROOT_DIR . '/Drivers/OverDriveDriver3.php';
			$overDriveDriver = new OverDriveDriver3();
//		}
		return $overDriveDriver;
	}
}