<?php
/**
 *
 *
 * @category Pika
 * @author: Pascal Brammeier
 * Date: 9/10/2018
 *
 */

require_once 'DriverInterface.php';

class WCPL2 extends HorizonROA
{
	public function canRenew($itemType = null)
	{
		if (in_array($itemType, array('BKLCK', 'PBLCK'))) {
			return false;
		}
		return true;
	}


}
