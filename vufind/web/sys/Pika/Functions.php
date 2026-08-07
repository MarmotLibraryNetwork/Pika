<?php
/*
 * Pika Discovery Layer
 * Copyright (C) 2023  Marmot Library Network
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

/**
 * Returns the HTML snippet that loads the reCAPTCHA v3 API and exposes the site
 * key and action name to JavaScript as window globals. Assign the return value to
 * the Smarty variable $captcha; templates render it with {$captcha}.
 *
 * The $action string is sent with the token and validated server-side by
 * recaptchaCheckAnswer(). It also appears in the Google reCAPTCHA admin console
 * for per-action analytics. Use a consistent lowercase identifier for each form
 * (e.g. 'selfreg', 'email', 'sms', 'support', 'requestcopy').
 *
 * Keys are read from config.pwd.ini [ReCaptcha] siteKey.
 */
function recaptchaGetQuestion(string $action = 'submit') {
	global $configArray;

	if (empty($configArray['ReCaptcha']['siteKey'])) {
		throw new \RuntimeException('No reCaptcha key provided');
	}
	$key    = htmlspecialchars($configArray['ReCaptcha']['siteKey'], ENT_QUOTES);
	$action = htmlspecialchars($action, ENT_QUOTES);

	return '<script src="https://www.google.com/recaptcha/api.js?render=' . $key . '" async defer></script>' .
	       '<script>window.pikaRecaptchaSiteKey = "' . $key . '"; window.pikaRecaptchaAction = "' . $action . '";</script>';
}

/**
 * Verifies a reCAPTCHA v3 token against Google's API and returns true if the
 * response passes the score threshold.
 *
 * Token source: $_REQUEST['g-recaptcha-response'], or pass one explicitly.
 * Score threshold: config.ini [ReCaptcha] passingScoreThreshold (default 0.5).
 * Action validation: when $expectedAction is provided, Google rejects tokens
 *   generated for a different action, preventing cross-form token reuse.
 *
 * Returns false (rather than throwing) on a low score so callers can show a
 * user-facing error. Throws RuntimeException only for missing configuration and
 * DomainException only when no token is present at all.
 *
 * Keys are read from config.pwd.ini [ReCaptcha] secretKey.
 */
function recaptchaCheckAnswer($recaptchaResponse = false, string $expectedAction = '') {
	global $configArray;

	if (empty($configArray['ReCaptcha']['secretKey'])) {
		throw new \RuntimeException('No reCaptcha key provided');
	}

	if (!$recaptchaResponse) {
		if (!isset($_REQUEST['g-recaptcha-response'])) {
			throw new \DomainException('No reCaptcha response found');
		} else {
			$recaptchaResponse = $_REQUEST['g-recaptcha-response'];
		}
	}

	$threshold = (float)($configArray['ReCaptcha']['passingScoreThreshold'] ?? 0.5);
	$remoteIp  = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'];
	$recaptcha = new ReCaptcha($configArray['ReCaptcha']['secretKey']);

	if ($expectedAction) {
		$recaptcha->setExpectedAction($expectedAction);
	}
	$recaptcha->setScoreThreshold($threshold);

	$logger = new Logger('reCaptcha');
	$r      = $recaptcha->verify($recaptchaResponse, $remoteIp);
	if ($r->isSuccess()) {
		$logger->info('reCaptcha passed', ['score' => $r->getScore(), 'action' => $expectedAction]);
		return true;
	}
	$logger->warn('reCaptcha failed', ['recaptcha_errors' => print_r($r->getErrorCodes(), true), 'score' => $r->getScore()]);
	return false;
}

/**
 * Controller-friendly wrapper around recaptchaCheckAnswer().
 *
 * Every controller that gates a form on reCAPTCHA wants the same three-way
 * behavior, so it lives here once instead of being repeated at each call site:
 *
 *   - reCAPTCHA not configured for this site  -> true  (the feature is optional,
 *     so an unconfigured site must not be locked out of its own forms)
 *   - token verified above the score threshold -> true
 *   - anything else, including a thrown error  -> false
 *
 * Unlike recaptchaCheckAnswer() this never throws, so callers can branch on a
 * plain bool. Pair it with the matching recaptchaGetQuestion($action) call that
 * renders the widget — the action strings must agree or Google rejects the token.
 *
 * @param string $action Action name matching the one passed to recaptchaGetQuestion().
 * @return bool True when the submission may proceed.
 */
function recaptchaIsValid(string $action): bool {
	global $configArray;

	if (empty($configArray['ReCaptcha']['secretKey'])) {
		return true;
	}

	try {
		return recaptchaCheckAnswer(false, $action);
	} catch (\Exception $e) {
		$logger = new Logger('reCaptcha');
		$logger->warn('reCaptcha check could not be completed', ['action' => $action, 'error' => $e->getMessage()]);
		return false;
	}
}
