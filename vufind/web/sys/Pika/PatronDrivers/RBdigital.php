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
 * rbDigital
 *
 * Patron driver for RB Digital
 *
 * @category Pika
 * @package  RecordDrivers
 * @author   Chris Froese
 * @author   Pascal Brammeier
 * @see      https://partnerapidoc.rbdigital.com/swagger/ui/index
 *
 */
namespace Pika\PatronDrivers;

use Pika\App;
use Curl\Curl;
use User;
require_once ROOT_DIR . '/services/SourceAndId.php';
require_once ROOT_DIR . '/RecordDrivers/RBdigitalMagazineRecordDriver.php';

class RBdigital {
	private string $tokenBaseUrl;
	private string $webServiceBaseUrl;
	private string $userInterfaceUrl;
	private Curl $curl;
	private App $app;

	public function __construct()
	{
		$this->app  = new App();
		$this->curl = new Curl();

		$this->webServiceBaseUrl = $this->app->config['RBdigital']['webServiceUrl'] . '/v1/libraries/' .
		                           $this->app->config['RBdigital']['libraryId'] . '/';
		$this->tokenBaseUrl      = $this->app->config['RBdigital']['webServiceUrl'] . '/v1/rpc/libraries/' .
		                           $this->app->config['RBdigital']['libraryId'] . '/patrons/';
		$this->userInterfaceUrl  = $this->app->config['RBdigital']['userInterfaceUrl'];

		$headers = [
		 'Accept'        => 'application/json',
		 'Authorization' => 'basic ' . $this->app->config['RBdigital']['apiToken'],
		 'Content-Type'  => 'application/json'
		];

		$this->curl->setHeaders($headers);
	}

	/**
	 * Get the user's rbdigital id or return false if the user is not registered.
	 *
	 * @param  User $patron
	 * @param  boolean $userId  If true will return the RBdigital user id like 9998a354-cbf9-4f6b-b87f-0169eef57ff2
	 * @return int|string|false
	 */
	public function getPatronId(User $patron, $userId = false)
	{
		// check for a cached user id
		if(!$userId) {
			$patronIdCacheKey = $this->app->cache->makePatronKey('id', $patron->id, 'rbdigital');
			if($rbId = $this->app->cache->get($patronIdCacheKey, false)) {
				return $rbId;
			}
		}
		$url = $this->tokenBaseUrl . urlencode($patron->barcode);
		$r = $this->curl->get($url);
		// rbdigital api returns 404 if patron isn't found
		if ($this->curl->getHttpStatusCode() == 404) {
			return false;
		}
		if($userId) {
			return $r->userId;
		}
		$rbId = $r->patronId;
		$this->app->cache->set($patronIdCacheKey, $rbId, 36000);

		return $rbId;
	}

	/**
	 * Get Patron checkouts
	 *
	 * Retrieve all checked out items for a patron
	 *
	 * @param  User $patron The user to load transactions for
	 * @return array        Array of the patron's transactions on success
	 * @access public
	 */
	public function getCheckouts($patron)
	{
		//Get the rbdigital id for the patron
		$rbId = $this->getPatronId($patron);
		if(!$rbId) {
			return [];
		}

		$checkouts   = array();

		$patronMagazinesUrl = $this->webServiceBaseUrl . 'patrons/' . $rbId . '/patron-magazines/history';
		$params = [
		  'pageIndex' => 0,
		  'pageSize'  => 100
		];
		$patronMagazines = $this->curl->get($patronMagazinesUrl, $params);

		if ($patronMagazines->resultSetCount == 0) {
			return [];
		}

		foreach ($patronMagazines->resultSet as $patronMagazine) {
			$patronMagazineDetails = $patronMagazine->item;

			$checkout = [];
			$issue_image = $patronMagazineDetails->images[0]->url;
			$checkout['id']              = $patronMagazineDetails->issueId;
			$checkout['magazineId']      = $patronMagazineDetails->magazineId;
			$checkout['title']           = $patronMagazineDetails->title;
			$checkout['publisher']       = $patronMagazineDetails->publisher;
			$checkout['checkoutdate']     = strtotime($patronMagazineDetails->checkoutOn);
			$checkout['issueDate']        = strtotime($patronMagazineDetails->publishedOn);
			$checkout['canRenew']        = false;
			$checkout['accessOnlineUrl'] = '';

			$source   = 'RBdigital';
			$sourceId = $patronMagazineDetails->rbzid;
			$sourceAndID = new \sourceAndId($source . ':' . $sourceId);
			$checkout['checkoutSource']  = $source;
			$checkout['recordId']        = $sourceId;

			if (!empty($checkout['id'])) {

				$recordDriver = new \RBdigitalMagazineRecordDriver($sourceAndID);
				if ($recordDriver->isValid()) {
					$checkout['coverUrl']        = $issue_image; //$recordDriver->getBookcoverUrl('medium');
					$checkout['groupedWorkId']   = $recordDriver->getGroupedWorkId();
					$checkout['ratingData']      = $recordDriver->getRatingData();
					$checkout['format']          = $patronMagazineDetails->mediaType;
					$checkout['title']           = $recordDriver->getTitle();
					$curTitle['title_sort']      = $recordDriver->getSortableTitle();
					$checkout['linkUrl']         = $recordDriver->getLinkUrl();
					$checkout['accessOnlineUrl'] = "#";

				} else {
					$checkout['coverUrl']      = $patronMagazineDetails;
					$checkout['groupedWorkId'] = "";
					$checkout['format']        = $patronMagazineDetails->mediaType;
					}
				}

			$checkout['user']   = $patron->getNameAndLibraryLabel();
			$checkout['userId'] = $patron->id;

			$checkouts[] = $checkout;
		}

		return $checkouts;
	}

	public function getCheckoutCount($patron) {
		return count($this->getCheckouts($patron));
	}

	/**
	 * Return a magazine issue
	 *
	 * @param $patron       User
	 * @param $issueId   string
	 * @return array
	 */
	public function returnMagazine(User $patron, $issueId)
	{
		$result = ['success' => false, 'message' => 'Unknown error'];

		$rbId = $this->getPatronId($patron);
		if ($rbId == false) {
			$result['message'] = 'You are not registered with RBdigital.  You will need to create an account there before continuing.';
			return $result;
		}

		$returnMagazineUrl = $this->webServiceBaseUrl . 'patrons/' . $rbId . '/patron-magazines/' . $issueId;
		$res = $this->curl->delete($returnMagazineUrl);

		if($this->curl->getHttpStatusCode() == 200){
			$result['success'] = true;
			$result['message'] = "The magazine was returned successfully.";
		}

		return $result;
	}

	/**
	 * @param User    $patron
	 * @param string  $issueId
	 * @retrun void
	 */
	public function redirectToRBdigitalMagazine(User $patron, $issueId) {
		// get the rbdigital USER id
		$rbUserId = $this->getPatronId($patron, true);
		// get the bearer token
		$this->curl->setHeader('Content-Type', 'application/json');
		$url = $this->webServiceBaseUrl . 'tokens';
		$params = ["userId" => $rbUserId];
		$res = $this->curl->post($url, $params);
		//$res = json_decode($res);
		if($res->bearer) {
			header('Authorization: bearer '. $res->bearer);
		}
		header('Location: https://www.rbdigital.com/reader.php#/reader/readsvg/'.$issueId.'/Cover');
		die();
	}

	/**
	 * @param User   $patron
	 * @param string $recordId
	 *
	 * @return array results (success, message)
	 */
	public function checkOutTitle($patron, $recordId)
	{
		$result      = ['success' => false, 'message' => 'Unknown error'];
		$rbdigitalId = $this->getPatronId($patron);
		if ($rbdigitalId == false) {
			$result['message'] = 'Sorry, you are not registered with RBdigital.  You will need to create an account there before continuing.';
		} else {
			require_once ROOT_DIR . '/RecordDrivers/RBdigitalRecordDriver.php';
			$actionUrl = $this->webServiceBaseUrl . '/v1/libraries/' . $this->libraryId . '/patrons/' . $rbdigitalId . '/checkouts/' . $recordId;

			$response = $this->curl->post($actionUrl);
			if ($response == false) {
				$result['message'] = "Invalid information returned from API, please retry your checkout after a few minutes.";
				global $logger;
				$logger->log("Invalid information from rbdigital api\r\n$actionUrl\r\n$rawResponse", PEAR_LOG_ERR);
				$logger->log(print_r($this->curl->getResponseHeaders(), true), PEAR_LOG_ERR);
				$curl_info = $this->curl->getInfo();
				$logger->log(print_r($curl_info, true), PEAR_LOG_ERR);
			} else {
				if (!empty($response->output) && $response->output == 'SUCCESS') {

					$result['success'] = true;
					$result['message'] = translate([
					 'text' => 'rbdigital-checkout-success',
					 'defaultText' => 'Your title was checked out successfully. You can read or listen to the title from your account.'
					]);

					/** @var Memcache $memCache */
					global $memCache;
					$memCache->delete('rbdigital_summary_' . $patron->id);
				} else {
					$result['message'] = $response->output;
				}

			}
		}
		return $result;
	}

	/**
	 * Checkout an RBdigital magazine
	 *
	 * @param User   $patron
	 * @param string $recordId
	 *
	 * @return array results (success, message)
	 */
	public function checkoutMagazine($patron, $recordId)
	{
		$result      = ['success' => false, 'message' => 'Unknown error'];
		$rbId = $this->getPatronId($patron);
		if ($rbId == false) {
			$result['message'] = 'Sorry, you are not registered with RBdigital.  You will need to create an account there before continuing.';
		} else {
			//Get the current issue for the magazine
			require_once ROOT_DIR . '/sys/RBdigital/RBdigitalMagazine.php';
			$product             = new RBdigitalMagazine();
			$product->magazineId = $recordId;
			if ($product->find(true)) {
				require_once ROOT_DIR . '/RecordDrivers/RBdigitalRecordDriver.php';
				$actionUrl = $this->webServiceBaseUrl . '/v1/libraries/' . $this->libraryId . '/patrons/' . $rbId . '/patron-magazines/' . $product->issueId;
				// /v{version}/libraries/{libraryId}/patrons/{patronId}/patron-magazines/{issueId}

				//RBdigital does not return a status so we assume that it checked out ok
				$this->curl->post($actionUrl);

				$this->trackUserUsageOfRBdigital($patron);
				$this->trackMagazineCheckout($recordId);

				$result['success'] = true;
				$result['message'] = 'The magazine was checked out successfully. You can read the magazine from the rbdigital app.';

				/** @var Memcache $memCache */
				global $memCache;
				$memCache->delete('rbdigital_summary_' . $patron->id);
			} else {
				$result['message'] = "Could not find magazine to checkout";
			}
		}
		return $result;
	}

	public function createAccount(User $user)
	{
		$result = ['success' => false, 'message' => 'Unknown error'];

		$registrationData = [
		 'username'   => $_REQUEST['username'],
		 'password'   => $_REQUEST['password'],
		 'firstName'  => $_REQUEST['firstName'],
		 'lastName'   => $_REQUEST['lastName'],
		 'email'      => $_REQUEST['email'],
		 'postalCode' => $_REQUEST['postalCode'],
		 'libraryCard'=> $_REQUEST['libraryCard'],
		 'libraryId'  => $this->app->config['RBdigital']['libraryId'],
		 'tenantId'   => $this->app->config['RBdigital']['libraryId']
		];

		//TODO: add pin if the library configuration uses pins

		$actionUrl = $this->webServiceBaseUrl . 'patrons/';

		$response = $this->curl->post($actionUrl, json_encode($registrationData));
		if ($response == false) {
			$result['message'] = "Invalid information returned from API, please retry your action after a few minutes.";
			global $logger;
			$logger->log("Invalid information from rbdigital api " . $response, PEAR_LOG_ERR);
		} else {
			if (!empty($response->authStatus) && $response->authStatus == 'Success') {
				$user->rbdigitalId = $response->patron->patronId;
				$result['success'] = true;
				$result['message'] = "Your have been registered successfully.";
			} else {
				$result['message'] = $response->message;
			}
		}

		return $result;
	}

	public function isUserRegistered(User $user)
	{
		if ($this->getPatronId($user) != false) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Renew a single title currently checked out to the user
	 *
	 * @param $patron     User
	 * @param $recordId   string
	 * @return array
	 */
	public function renewCheckout($patron, $recordId)
	{
		$result = ['success' => false, 'message' => 'Unknown error'];

		$rbdigitalId = $this->getPatronId($patron);
		if ($rbdigitalId == false) {
			$result['message'] = 'Sorry, you are not registered with RBdigital.  You will need to create an account there before continuing.';
		} else {
			$actionUrl = $this->webServiceBaseUrl . '/v1/libraries/' . $this->libraryId . '/patrons/' . $rbdigitalId . '/checkouts/' . $recordId;

			$response = $this->curl->put($actionUrl);
//			$response    = json_decode($rawResponse);
			if ($response == false) {
				$result['message'] = "Invalid information returned from API, please retry your action after a few minutes.";
				global $logger;
				$logger->log("Invalid information from rbdigital api " . $rawResponse, PEAR_LOG_ERR);
			} else {
				if (!empty($response->output) && $response->output == 'success') {
					$result['success'] = true;
					$result['message'] = "Your title was renewed successfully.";
				} else {
					$result['message'] = $response->output;
				}
			}
		}
		return $result;
	}

	/**
	 * Return a title currently checked out to the user
	 *
	 * @param $patron     User
	 * @param $recordId   string
	 * @return array
	 */
	public function returnCheckout($patron, $recordId)
	{
		$result = ['success' => false, 'message' => 'Unknown error'];

		$rbdigitalId = $this->getPatronId($patron);
		if ($rbdigitalId == false) {
			$result['message'] = 'Sorry, you are not registered with RBdigital.  You will need to create an account there before continuing.';
		} else {
			$actionUrl = $this->webServiceBaseUrl . '/v1/libraries/' . $this->libraryId . '/patrons/' . $rbdigitalId . '/checkouts/' . $recordId;

			$rawResponse = $this->curl->delete($actionUrl);
			$response    = json_decode($rawResponse);
			if ($response == false) {
				$result['message'] = "Invalid information returned from API, please retry your action after a few minutes.";
				global $logger;
				$logger->log("Invalid information from rbdigital api " . $rawResponse, PEAR_LOG_ERR);
			} else {
				if (!empty($response->message) && $response->message == 'success') {
					$result['success'] = true;
					$result['message'] = "Your title was returned successfully.";

					/** @var Memcache $memCache */
					global $memCache;
					$memCache->delete('rbdigital_summary_' . $patron->id);
				} else {
					$result['message'] = $response->message;
				}
			}
		}
		return $result;
	}





	/**
	 * Get Patron Holds
	 *
	 * This is responsible for retrieving all holds for a specific patron.
	 *
	 * @param User $patron The user to load transactions for
	 *
	 * @return array        Array of the patron's holds
	 * @access public
	 */
	public function getHolds($patron)
	{

//		$rbDigitalPatronId = $this->getPatronId($patron);
//
//		$patronHoldsUrl = $this->webServiceBaseUrl . '/v1/libraries/' . $this->libraryId . '/patrons/' . $rbDigitalPatronId . '/holds';
//
//		$patronHolds = $this->curl->get($patronHoldsUrl);
//
//		$holds = array(
//		 'available' => array(),
//		 'unavailable' => array()
//		);
//
//		if ($rbDigitalPatronId == false) {
//			return $holds;
//		}
//
//		if (isset($patronHolds->message)) {
//			//Error in RBdigital APIS
//			global $logger;
//			$logger->log("Error in RBdigital {$patronHolds->message}", PEAR_LOG_WARNING);
//		} else {
//			foreach ($patronHolds as $tmpHold) {
//				$hold                  = array();
//				$hold['id']            = $tmpHold->isbn;
//				$hold['transactionId'] = $tmpHold->transactionId;
//				$hold['holdSource']    = 'RBdigital';
//
//				$recordDriver = new RBdigitalRecordDriver($hold['id']);
//				if ($recordDriver->isValid()) {
//					$hold['coverUrl']   = $recordDriver->getBookcoverUrl('medium');
//					$hold['title']      = $recordDriver->getTitle();
//					$hold['sortTitle']  = $recordDriver->getTitle();
//					$hold['author']     = $recordDriver->getPrimaryAuthor();
//					$hold['linkUrl']    = $recordDriver->getLinkUrl(false);
//					$hold['format']     = $recordDriver->getFormats();
//					$hold['ratingData'] = $recordDriver->getRatingData();
//				}
//				$hold['user']   = $patron->getNameAndLibraryLabel();
//				$hold['userId'] = $patron->id;
//
//				$key                        = $hold['holdSource'] . $hold['id'] . $hold['user'];
//				$holds['unavailable'][$key] = $hold;
//			}
//		}
//
//		return $holds;
	}

	/**
	 * Place Hold
	 *
	 * This is responsible for both placing holds as well as placing recalls.
	 *
	 * @param User   $patron          The User to place a hold for
	 * @param string $recordId        The id of the bib record
	 * @return  array                 An array with the following keys
	 *                                success - true/false
	 *                                message - the message to display (if item holds are required, this is a form to
	 *                                select the item).
	 * @access  public
	 */
	public function placeHold($patron, $recordId)
	{
//		$result      = ['success' => false, 'message' => 'Unknown error'];
//		$rbdigitalId = $this->getPatronId($patron);
//		if ($rbdigitalId == false) {
//			$result['message'] = 'Sorry, you are not registered with RBdigital.  You will need to create an account there before continuing.';
//		} else {
//			$actionUrl = $this->webServiceBaseUrl . '/v1/libraries/' . $this->libraryId . '/patrons/' . $rbdigitalId . '/holds/' . $recordId;
//
//			$response = $this->curl->post($actionUrl);
//			if ($response == false) {
//				$result['message'] = "Invalid information returned from API, please retry your hold after a few minutes.";
//				global $logger;
//				$logger->log("Invalid information from rbdigital api\r\n$actionUrl\r\n$rawResponse", PEAR_LOG_ERR);
//				$logger->log(print_r($this->curl->getResponseHeaders(), true), PEAR_LOG_ERR);
//				$curl_info = $this->curl->getInfo();
//				$logger->log(print_r($curl_info, true), PEAR_LOG_ERR);
//			} else {
//				if (is_numeric($response)) {
//					$this->trackUserUsageOfRBdigital($patron);
//					$this->trackRecordHold($recordId);
//					$result['success'] = true;
//					$result['message'] = "Your hold was placed successfully.";
//
//					/** @var Memcache $memCache */
//					global $memCache;
//					$memCache->delete('rbdigital_summary_' . $patron->id);
//				} else {
//					$result['message'] = $response->message;
//				}
//			}
//		}
//		return $result;
	}

	/**
	 * Cancels a hold for a patron
	 *
	 * @param User   $patron   The User to cancel the hold for
	 * @param string $recordId The id of the bib record
	 * @return  array
	 */
	function cancelHold($patron, $recordId)
	{
//		$result      = ['success' => false, 'message' => 'Unknown error'];
//		$rbdigitalId = $this->getPatronId($patron);
//		if ($rbdigitalId == false) {
//			$result['message'] = 'Sorry, you are not registered with RBdigital.  You will need to create an account there before continuing.';
//		} else {
//			$actionUrl = $this->webServiceBaseUrl . '/v1/libraries/' . $this->libraryId . '/patrons/' . $rbdigitalId . '/holds/' . $recordId;
//
//			$rawResponse = $this->curl->delete($actionUrl);
//			$response    = json_decode($rawResponse);
//			if ($response == false) {
//				$result['message'] = "Invalid information returned from API, please retry your action after a few minutes.";
//				global $logger;
//				$logger->log("Invalid information from rbdigital api " . $rawResponse, PEAR_LOG_ERR);
//			} else {
//				if (!empty($response->message) && $response->message == 'success') {
//					$result['success'] = true;
//					$result['message'] = "Your hold was cancelled successfully.";
//					/** @var Memcache $memCache */
//					global $memCache;
//					$memCache->delete('rbdigital_summary_' . $patron->id);
//				} else {
//					$result['message'] = $response->message;
//				}
//			}
//		}
//		return $result;
	}

	/**
	 * @param User $patron
	 *
	 * @return array
	 */
	public function getAccountSummary($patron)
	{
//		/** @var Memcache $memCache */
//		global $memCache;
//		global $configArray;
//		global $timer;
//
//		if ($patron == false) {
//			return array(
//			 'numCheckedOut' => 0,
//			 'numAvailableHolds' => 0,
//			 'numUnavailableHolds' => 0,
//			);
//		}
//
//		$summary = $memCache->get('rbdigital_summary_' . $patron->id);
//		if ($summary == false || isset($_REQUEST['reload'])) {
//			//Get the rbdigital id for the patron
//			$rbdigitalId = $this->getPatronId($patron);
//
//			//Get account information from api
//			$patronSummaryUrl = $this->webServiceBaseUrl . '/v1/tenants/' . $this->libraryId . '/patrons/' . $rbdigitalId . '/patron-config';
//
//			$response = $this->curl->get($patronSummaryUrl);
//
//			$summary                  = array();
//			$summary['numCheckedOut'] = empty($response->audioBooks->checkouts) ? 0 : count($response->audioBooks->checkouts);
//			$summary['numCheckedOut'] += empty($response->magazines->checkouts) ? 0 : count($response->magazines->checkouts);
//
//			//RBdigital automatically checks holds out so nothing is available
//			$summary['numAvailableHolds']   = 0;
//			$summary['numUnavailableHolds'] = empty($response->audioBooks->holds) ? 0 : count($response->audioBooks->holds);
//
//			$timer->logTime("Finished loading titles from rbdigital summary");
//			$memCache->set('rbdigital_summary_' . $patron->id, $summary, $configArray['Caching']['account_summary']);
//		}
//
//		return $summary;
	}



	public function redirectToRBdigital(User $patron, RBdigitalRecordDriver $recordDriver)
	{
		$this->addBearerTokenHeader($patron);
		header('Location:' . $this->userInterfaceUrl . '/book/' . $recordDriver->getUniqueID());
		die();
//        $result = ['success' => false, 'message' => 'Unknown error'];
//        $rbdigitalId = $this->getRBdigitalId($patron);
//        if ($rbdigitalId == false) {
//            $result['message'] = 'Sorry, you are not registered with RBdigital.  You will need to create an account there before continuing.';
//        } else {
//            //Get the link to redirect to with the proper bearer information
//            /*
//             * POST to api.rbdigital.com/v1/tokens/
//                with values of…
//                libraryId
//                UserName
//                Password
//
//                You should get a bearer token in response along the lines of...
//                {"bearer": "5cc2058bd2b76b28943de9cf","result": true}
//
//                …and should then be able to set an authorization header using…
//                bearer 5cc2063fd2b76b28943deb32
//             */

//        }
//        return $result;

	}

	/**
	 * @param User $patron
	 * @return void
	 */
	private function addBearerTokenHeader(User $patron)
	{
		if (!empty($patron->rbdigitalUsername) && !empty($patron->rbdigitalPassword)) {
			$tokenUrl = $this->webServiceBaseUrl . '/v1/tokens/';
			$userData = [
			 'libraryId' => $this->libraryId,
			 'UserName' => $patron->rbdigitalUsername,
			 'Password' => $patron->rbdigitalPassword,
			];
			$response = $this->curl->post($tokenUrl, $userData);

			if ($response == false) {
				$result['message'] = "Invalid information returned from API, please retry your hold after a few minutes.";
				global $logger;
				$logger->log("Invalid information from rbdigital api\r\n$tokenUrl\r\n$rawResponse", PEAR_LOG_ERR);
				$logger->log(print_r($this->curl->getResponseHeaders(), true), PEAR_LOG_ERR);
				$curl_info = $this->curl->getInfo();
				$logger->log(print_r($curl_info, true), PEAR_LOG_ERR);
			} else {
				//We should get back a bearer token
				if ($response->result == true) {
					$bearerToken = $response->bearer;
					header('Authorization: bearer ' . $bearerToken);
				}
			}
		}
	}

	/**
	 * @return boolean true if the driver can renew all titles in a single pass
	 */
	public function hasFastRenewAll()
	{
		return false;
	}

	/**
	 * Renew all titles currently checked out to the user
	 *
	 * @param $patron  User
	 * @return mixed
	 */
	public function renewAll($patron)
	{
		return false;
	}

	public function getUserInterfaceUrl()
	{
		return $this->userInterfaceUrl;
	}

	public function hasNativeReadingHistory()
	{
		return false;
	}
	// end RbDigital.php
}
////			$this->curl->setHeaders($this->curlHeaders);
//			$patronCheckoutUrl = $this->webServiceURL . '/v1/libraries/' . $this->libraryId . '/patrons/' . $rbdigitalId . '/checkouts';
//			$patronCheckouts   = $this->curl->get($patronCheckoutUrl);
//			if (isset($patronCheckouts->message)){
//				//Error in RBdigital APIS
//				global $logger;
//				$logger->log("Error in RBdigital {$patronCheckouts->message}", PEAR_LOG_WARNING);
//			}else{
//				foreach ($patronCheckouts as $patronCheckout){
//					$checkout                   = array();
//					$checkout['checkoutSource'] = 'RBdigital';
//
//					$checkout['id']       = $patronCheckout->transactionId;
//					$checkout['recordId'] = $patronCheckout->isbn;
//					$checkout['title']    = $patronCheckout->title;
//					$checkout['author']   = $patronCheckout->authors;
//
//					$dateDue = DateTime::createFromFormat('Y-m-d', $patronCheckout->expiration);
//					if ($dateDue){
//						$dueTime = $dateDue->getTimestamp();
//					}else{
//						$dueTime = null;
//					}
//					$checkout['dueDate']     = $dueTime;
//					$checkout['itemId']      = $patronCheckout->isbn;
//					$checkout['canRenew']    = $patronCheckout->canRenew;
//					$checkout['hasDrm']      = $patronCheckout->hasDrm;
//					$checkout['downloadUrl'] = $patronCheckout->downloadUrl;
//					if (strlen($checkout['downloadUrl']) == 0){
//						$checkout['output'] = $patronCheckout->output;
//					}
//					$checkout['accessOnlineUrl'] = '';
//
//					if (!empty($checkout['id'])){
//						require_once ROOT_DIR . '/RecordDrivers/RBdigitalRecordDriver.php';
//						$recordDriver = new RBdigitalRecordDriver($checkout['recordId']);
//						if ($recordDriver->isValid()){
//							$checkout['coverUrl']        = $recordDriver->getBookcoverUrl('medium');
//							$checkout['ratingData']      = $recordDriver->getRatingData();
//							$checkout['groupedWorkId']   = $recordDriver->getGroupedWorkId();
//							$checkout['format']          = $recordDriver->getPrimaryFormat();
//							$checkout['author']          = $recordDriver->getPrimaryAuthor();
//							$checkout['title']           = $recordDriver->getTitle();
//							$curTitle['title_sort']      = $recordDriver->getTitle();
//							$checkout['linkUrl']         = $recordDriver->getLinkUrl();
//							$checkout['accessOnlineUrl'] = $recordDriver->getAccessOnlineLinkUrl($patron);
//						}else{
//							$checkout['coverUrl']      = "";
//							$checkout['groupedWorkId'] = "";
//							$checkout['format']        = $patronCheckout->mediaType;
//						}
//					}
//
//					$checkout['user']   = $patron->getNameAndLibraryLabel();
//					$checkout['userId'] = $patron->id;
//
//					$checkouts[] = $checkout;
//				}
//			}
