<?php
/**
 *
 *
 * @category Pika
 * @author: Pascal Brammeier
 * Date: 9/14/2018
 *
 */

require_once ROOT_DIR . '/sys/SIP2.php';
class KohaSIP extends sip2
{

	//TODO: this function should really replace the base method. Would need to build a version of doSipLogin for the parent class
	function connect(){
		global $logger;
		/* Socket Communications  */
		$this->_debugmsg( "SIP2: --- BEGIN SIP communication ---");

		/* Get the IP address for the target host. */
		$address = $this->hostname;

		/* Create a TCP/IP socket. */
		$this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

		/* check for actual truly false result using ===*/
		if ($this->socket === false) {
			$logger->log("Unable to create socket to SIP server at $this->hostname", PEAR_LOG_ERR);
			$this->_debugmsg( "SIP2: socket_create() failed: reason: " . socket_strerror($this->socket));
			return false;
		} else {
			$this->_debugmsg( "SIP2: Socket Created" );
		}
		$this->_debugmsg( "SIP2: Attempting to connect to '$address' on port '{$this->port}'...");

		//Set SIP timeouts
		socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, array('sec' => 2, 'usec' => 500));
		socket_set_option($this->socket, SOL_SOCKET, SO_SNDTIMEO, array('sec' => 2, 'usec' => 500));
		//Make the socket blocking so we can ensure we get responses without rewriting everything.
		socket_set_block($this->socket);

		/* open a connection to the host */
		$connectionTimeout = 10;
		$connectStart      = time();
		while (!@socket_connect($this->socket, $address, $this->port)){
			$error = socket_last_error($this->socket);
			if ($error == 114 || $error == 115){
				if ((time() - $connectStart) >= $connectionTimeout)
				{
					$this->disconnect();
					$logger->log("Connection to $address $this->port timed out", PEAR_LOG_ERR);
					return false;
				}
				$logger->log("Waiting for connection", PEAR_LOG_DEBUG);
				sleep(1);
				continue;
			}else{
				$logger->log("Unable to connect to $address $this->port", PEAR_LOG_ERR);
				$logger->log("SIP2: socket_connect() failed.\nReason: ($error) " . socket_strerror($error), PEAR_LOG_ERR);
				$this->_debugmsg("SIP2: socket_connect() failed.\nReason: ($error) " . socket_strerror($error));
				return false;
			}
		}

		$this->_debugmsg( "SIP2: --- SOCKET READY ---" );

		global $configArray;
		if (!empty($configArray['SIP2']['sipLogin']) && !empty($configArray['SIP2']['sipPassword'])){
			$login    = $configArray['SIP2']['sipLogin'];
			$password = $configArray['SIP2']['sipPassword'];

			if ($this->doSipLogin($login, $password)){
				return true;
			}
		}

		$this->disconnect();
		return false;
	}


	private function doSipLogin($login, $password) {
		$sipLoginCall  = $this->msgLogin($login, $password);
		$loginResponse = $this->get_message($sipLoginCall);
		$loginResult   = $this->parseLoginResponse($loginResponse);
		return (boolean) $loginResult['fixed']['Ok'];
	}

}