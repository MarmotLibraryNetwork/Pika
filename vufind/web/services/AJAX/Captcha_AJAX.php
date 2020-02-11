<?php
/**
 * Copyright (C) 2020  Marmot Library Network
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *
 *
 * @category Pika
 * @author: Pascal Brammeier
 * Date: 2/6/2020
 *
 */


trait Captcha_AJAX {
	protected function setUpCaptchaForTemplate(){
		global $configArray;
		if (isset($configArray['ReCaptcha']['publicKey'])) {
			global $interface;
			require_once ROOT_DIR . '/recaptcha/recaptchalib.php';
			$recaptchaPublicKey = $configArray['ReCaptcha']['publicKey'];
			$captchaCode        = recaptcha_get_html($recaptchaPublicKey);
			$interface->assign('captcha', $captchaCode);
		}
	}

	protected function isRecaptchaValid(){
		$recaptchaValid = false;
		if (UserAccount::isLoggedIn()){
			$recaptchaValid = true;
		}else{
			global $configArray;
			if (isset($configArray['ReCaptcha']['privateKey'])){
				require_once ROOT_DIR . '/recaptcha/recaptchalib.php';
				$privateKey     = $configArray['ReCaptcha']['privateKey'];
				$response       = recaptcha_check_answer($privateKey, $_SERVER["REMOTE_ADDR"], $_REQUEST["g-recaptcha-response"]);
				$recaptchaValid = $response->is_valid;
			}
		}
		return $recaptchaValid;
	}

}