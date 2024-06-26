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
require_once 'Mail.php';
require_once 'Mail/RFC822.php';
use \Pika\Logger;
/**
 * VuFind Mailer Class
 *
 * This is a wrapper class to load configuration options and perform email
 * functions.  See the comments in web/conf/config.ini for details on how
 * email is configured.
 *
 * @author      Demian Katz <demian.katz@villanova.edu>
 * @access      public
 */
class VuFindMailer {
	private $logger;
	protected $settings;      // settings for PEAR Mail object

	/**
	 * Constructor
	 *
	 * Sets up mailing functionality using settings from config.ini.
	 *
	 * @access  public
	 */
	public function __construct(){
		global $configArray;
		$this->logger = new Logger(__CLASS__);
		// Load settings from the config file into the object; we'll do the
		// actual creation of the mail object later since that will make error
		// detection easier to control.
			$this->settings = [
				'host' => $configArray['Mail']['host'],
				'port' => $configArray['Mail']['port']
			];
		if (!empty($configArray['Mail']['username']) && !empty($configArray['Mail']['password'])){
			$this->settings['auth']     = true;
			$this->settings['username'] = $configArray['Mail']['username'];
			$this->settings['password'] = $configArray['Mail']['password'];
		}
		if (isset($configArray['Mail']['fromAddress'])){
			$this->settings['fromAddress'] = $configArray['Mail']['fromAddress'];
		}
	}

	/**
	 * Send an email message.
	 *
	 * @access  public
	 * @param   string  $to         Recipient email address
	 * @param   string  $from       Sender email address
	 * @param   string  $subject    Subject line for message
	 * @param   string  $body       Message body
	 * @param   string  $replyTo    Someone to reply to
	 *
	 * @return  mixed               PEAR error on error, boolean true otherwise
	 */
	public function send($to, $from, $subject, $body, $replyTo = null, $cc = null){

		// Validate sender and recipient
		$validator = new Mail_RFC822();
		//Allow the to address to be split
		disableErrorHandler();
		try {
			//Validate the address list to make sure we don't get an error.
			$validator->parseAddressList($to);
		} catch (Exception $e){
			return new PEAR_Error('Invalid Recipient Email Address');
		}
		enableErrorHandler();

		if (!$validator->isValidInetAddress($from)){
			return new PEAR_Error('Invalid Sender Email Address');
		}

		$headers = [
			'To'           => $to,
			'Subject'      => $subject,
			'Date'         => date('D, d M Y H:i:s O'),
			'Content-Type' => 'text/plain; charset="UTF-8"'
		];
		if (isset($this->settings['fromAddress'])){
			$this->logger->info("Overriding From address, using " . $this->settings['fromAddress']);
			$headers['From']     = $this->settings['fromAddress'];
			$headers['Reply-To'] = $from;

		}else{
			$headers['From'] = $from;
		}
		if ($replyTo != null){
			$headers['Reply-To'] = $replyTo;
		}
		if ($cc != null){
			$headers['CC'] = $cc;
		}
		// Get mail object
		if ($this->settings['host'] != false){
			$mailFactory = new Mail();
			$mail        = $mailFactory->factory('smtp', $this->settings);
			if (PEAR_Singleton::isError($mail)){
				return $mail;
			}

			// Send message
			return $mail->send($to, $headers, $body);
		}else{
			//Mail to false just emits the information to screen
			$formattedMail = '';
			foreach ($headers as $key => $header){
				$formattedMail .= $key . ': ' . $header . '<br>';
			}
			$formattedMail .= $body;
			$this->logger->info("Sending e-mail");
			$this->logger->info("From = $from");
			$this->logger->info("To = $to");
			$this->logger->info($subject);
			$this->logger->info($formattedMail);
			return true;
		}

		}
}

/**
 * SMS Mailer Class
 *
 * This class extends the VuFindMailer to send text messages.
 *
 * @author      Demian Katz <demian.katz@villanova.edu>
 * @access      public
 */
class SMSMailer extends VuFindMailer {
	private $carriers = [];
	/**
	 * Constructor
	 *
	 * Sets up SMS carriers and other settings from sms.ini.
	 *
	 * @access  public
	 */
	public function __construct(){
		global $configArray;

		// if using sms.ini, then load the carriers from it
		// otherwise, fall back to the default list of US carriers
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
	 * Get a list of carriers supported by the module.  Returned as an array of
	 * associative arrays indexed by carrier ID and containing "name" and "domain"
	 * keys.
	 *
	 * @access  public
	 * @return  array
	 */
	public function getCarriers() {
		return $this->carriers;
	}

	/**
	 * Send a text message to the specified provider.
	 *
	 * @param   string      $provider       The provider ID to send to
	 * @param   string      $to             The phone number at the provider
	 * @param   string      $from           The email address to use as sender
	 * @param   string      $message        The message to send
	 * @access  public
	 * @return  mixed               PEAR error on error, boolean true otherwise
	 */
	public function text($provider, $to, $from, $message) {
		$knownCarriers = array_keys($this->carriers);
		if (empty($provider) || !in_array($provider, $knownCarriers)) {
			return new PEAR_Error('Unknown Carrier');
		}

		//Remove any invalid characters from the to address.  We expect only numbers
		$to = preg_replace('/\D/', '', $to);
		$to = $to . '@' . $this->carriers[$provider]['domain'];
		$subject = '';
		return $this->send($to, $from, $subject, $message);
	}
}
