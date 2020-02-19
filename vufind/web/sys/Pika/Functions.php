<?php
/**
 * Pika Discovery Layer
 * Copyright (C) 2020  Marmot Library Network
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */
/**
 * Functions.php
 *
 * Convince functions used in Pika
 *
 * @category Pika
 * @package
 * @author   Chris Froese
 *
 */
namespace Pika\Functions;

use Pika\{Logger};
use \ReCaptcha\ReCaptcha as ReCaptcha;
/**
 * Get the check digit
 *
 * @param $baseId
 * @return int|string
 */
function getCheckDigit($baseId) {
	$baseId      = preg_replace('/\.?[bij]/', '', $baseId);
	$sumOfDigits = 0;
	for ($i = 0; $i < strlen($baseId); $i++) {
		$curDigit    = substr($baseId, $i, 1);
		$sumOfDigits += ((strlen($baseId) + 1) - $i) * $curDigit;
	}
	$modValue = $sumOfDigits % 11;
	if ($modValue == 10) {
		return "x";
	} else {
		return $modValue;
	}
}

function recaptchaGetQuestion() {
	global $configArray;

	if(!isset($configArray['ReCaptcha']['privateKey']) || empty($configArray['ReCaptcha']['privateKey'])) {
		throw new \RuntimeException('No reCaptcha key provided');
	}
	$key = $configArray['ReCaptcha']['privateKey'];

	return '<script src="https://www.google.com/recaptcha/api.js" async defer></script>' .
	       '<div class="g-recaptcha" data-sitekey="'. $key .'">';
}

function recaptchaCheckAnswer($recaptchaResponse = false) {
	global $configArray;

	$logger = new Logger('reCaptcha');
	if(!isset($configArray['ReCaptcha']['privateKey']) || empty($configArray['ReCaptcha']['privateKey'])) {
		throw new \RuntimeException('No reCaptcha key provided');
	}

	if(!$recaptchaResponse) {
		if(!isset($_POST["g-recaptcha-response"])) {
			throw new \DomainException('No reCaptcha response found');
		} else {
			$recaptchaResponse = $_POST["g-recaptcha-response"];
		}
	}
	$remoteIp = $_SERVER["REMOTE_ADDR"];

	$recaptcha = new ReCaptcha($configArray['ReCaptcha']['privateKey']);
	$r = $recaptcha->verify($recaptchaResponse, $remoteIp);
	if ($r->isSuccess()) {
		return true;
	} else {
		$errors = $r->getErrorCodes();
		$errors = print_r($errors, true);
		$logger->warn('reCaptcha failed', ['recaptcha_errors' => $errors]);
		return false;
	}
}
