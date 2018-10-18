<?php
/**
 *
 *
 * @category Pika
 * @author: Pascal Brammeier
 * Date: 9/19/2018
 *
 */


class Addison extends Sierra
{

	public function _getLoginFormValues($patron){
		$loginData = array();
		$loginData['code'] = $patron->cat_username;
		$loginData['pin']  = $patron->cat_password;
//		$loginData['pat_submit']  = 'xxx';

		return $loginData;
	}

}