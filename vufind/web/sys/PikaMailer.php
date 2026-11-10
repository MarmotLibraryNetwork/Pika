<?php
/*
 * Pika Discovery Layer
 * Copyright (C) 2026  Marmot Library Network
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
require_once 'PEAR.php'; // PEAR_Error, still the error type callers check for

use \Pika\Logger;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Pika Mailer Class
 *
 * Wraps PHPMailer, using the [Mail] settings from config.ini:
 *   host        - SMTP server. When empty or false, messages are only written to the log (no mail is sent).
 *   port        - SMTP port
 *   username    - SMTP username (authentication is used only when both username and password are set)
 *   password    - SMTP password
 *   fromAddress - when set, used as the From address; the requested sender becomes the Reply-To
 *
 * Replaces the PEAR Mail based VuFindMailer class.
 */
class PikaMailer {
	private $logger;
	protected $settings = [];

	public function __construct(){
		global $configArray;
		$this->logger = new Logger(__CLASS__);
		$mailConfig   = $configArray['Mail'] ?? [];

		$this->settings = [
			'host' => $mailConfig['host'] ?? false,
			'port' => $mailConfig['port'] ?? 25,
		];
		if (!empty($mailConfig['username']) && !empty($mailConfig['password'])){
			$this->settings['auth']     = true;
			$this->settings['username'] = $mailConfig['username'];
			$this->settings['password'] = $mailConfig['password'];
		}
		if (!empty($mailConfig['fromAddress'])){
			$this->settings['fromAddress'] = $mailConfig['fromAddress'];
		}
	}

	/**
	 * Send a plain text email message.
	 *
	 * @param string      $to      Recipient email address(es); may be a comma separated list
	 * @param string      $from    Sender email address
	 * @param string      $subject Subject line for message
	 * @param string      $body    Message body
	 * @param string|null $replyTo Address replies should go to
	 * @param string|null $cc      Carbon copy address(es); may be a comma separated list
	 *
	 * @return true|PEAR_Error  true on success, PEAR_Error on failure
	 */
	public function send($to, $from, $subject, $body, $replyTo = null, $cc = null){
		$recipients = $this->parseAddresses($to);
		if (empty($recipients)){
			return new PEAR_Error('Invalid Recipient Email Address');
		}
		if (!PHPMailer::validateAddress($from)){
			return new PEAR_Error('Invalid Sender Email Address');
		}

		if (isset($this->settings['fromAddress'])){
			$this->logger->info('Overriding From address, using ' . $this->settings['fromAddress']);
			$fromAddress    = $this->settings['fromAddress'];
			$replyToAddress = $from;
		}else{
			$fromAddress    = $from;
			$replyToAddress = null;
		}
		if (!empty($replyTo)){
			$replyToAddress = $replyTo;
		}

		if (empty($this->settings['host'])){
			// No mail host configured: write the message to the log instead of sending it
			$this->logger->info('Sending e-mail (no mail host configured, message logged only)');
			$this->logger->info("From = $fromAddress");
			$this->logger->info("To = $to");
			if (!empty($replyToAddress)){
				$this->logger->info("Reply-To = $replyToAddress");
			}
			if (!empty($cc)){
				$this->logger->info("CC = $cc");
			}
			$this->logger->info("Subject = $subject");
			$this->logger->info($body);
			return true;
		}

		$mail = new PHPMailer(true); // throw exceptions on error
		try {
			$mail->isSMTP();
			$mail->Host = $this->settings['host'];
			$mail->Port = $this->settings['port'];
			if (!empty($this->settings['auth'])){
				$mail->SMTPAuth = true;
				$mail->Username = $this->settings['username'];
				$mail->Password = $this->settings['password'];
			}else{
				// PEAR Net_SMTP only negotiated STARTTLS as part of authenticating; keep unauthenticated
				// relays (e.g. a local mail server with a self-signed certificate) working the same way.
				$mail->SMTPAutoTLS = false;
			}

			$mail->CharSet = PHPMailer::CHARSET_UTF8;
			$mail->isHTML(false);
			$mail->setFrom($fromAddress);
			foreach ($recipients as $recipient){
				$mail->addAddress($recipient['address'], $recipient['name']);
			}
			if (!empty($replyToAddress)){
				if (PHPMailer::validateAddress($replyToAddress)){
					$mail->addReplyTo($replyToAddress);
				}else{
					$this->logger->warning("Ignoring invalid Reply-To address: $replyToAddress");
				}
			}
			if (!empty($cc)){
				foreach ($this->parseAddresses($cc) as $ccAddress){
					$mail->addCC($ccAddress['address'], $ccAddress['name']);
				}
			}
			$mail->Subject = $subject;
			$mail->Body    = $body;

			$mail->send();
			return true;
		} catch (PHPMailerException $e){
			$this->logger->error('Error sending email: ' . $e->getMessage());
			return new PEAR_Error($e->getMessage());
		}
	}

	/**
	 * Split a comma separated address list into valid addresses.
	 *
	 * @param string $addressList
	 * @return array  list of ['name' => ..., 'address' => ...]
	 */
	private function parseAddresses($addressList){
		if (empty($addressList)){
			return [];
		}
		return PHPMailer::parseAddresses($addressList, false, PHPMailer::CHARSET_UTF8);
	}
}

/**
 * SMS Mailer Class
 *
 * Sends text messages through a carrier's email-to-SMS gateway.
 */
class SMSMailer extends PikaMailer {
	private $carriers = [];

	/**
	 * Loads the SMS carriers from sms.ini, when one is configured.
	 */
	public function __construct(){
		global $configArray;

		if (isset($configArray['Extra_Config']['sms'])){
			$smsConfig = getExtraConfigArray('sms');
			if (!empty($smsConfig['Carriers'])){
				$this->carriers = [];
				foreach ($smsConfig['Carriers'] as $id => $config){
					[$domain, $name] = explode(':', $config, 2);
					$this->carriers[$id] = ['name' => $name, 'domain' => $domain];
				}
			}
		}

		parent::__construct();
	}

	/**
	 * Get the supported carriers, indexed by carrier ID, each with "name" and "domain" keys.
	 *
	 * @return array
	 */
	public function getCarriers(){
		return $this->carriers;
	}

	/**
	 * Send a text message to the specified provider.
	 *
	 * @param string $provider The provider ID to send to
	 * @param string $to       The phone number at the provider
	 * @param string $from     The email address to use as sender
	 * @param string $message  The message to send
	 * @return true|PEAR_Error  true on success, PEAR_Error on failure
	 */
	public function text($provider, $to, $from, $message){
		if (empty($provider) || !array_key_exists($provider, $this->carriers)){
			return new PEAR_Error('Unknown Carrier');
		}

		// Remove any invalid characters from the to address.  We expect only numbers
		$to = preg_replace('/\D/', '', $to);
		$to = $to . '@' . $this->carriers[$provider]['domain'];
		return $this->send($to, $from, '', $message);
	}
}
