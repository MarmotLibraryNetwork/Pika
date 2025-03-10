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

require_once ROOT_DIR . '/Action.php';
require_once ROOT_DIR . '/CatalogConnection.php';

use Pika\PatronDrivers\EcontentSystem\OverDriveDriverFactory;

class UserAPI extends AJAXHandler {

	protected $methodsThatRespondWithJSONUnstructured = [
		'setDefaultPin',
		'getPatronPin',
		];

	protected $methodsThatRespondWithJSONResultWrapper = [
		'isLoggedIn',
		'login',
		'logout',
		'validateAccount',
		'getPatronProfile',
		'getPatronHolds',
		'getPatronHoldsOverDrive',
		'getPatronCheckedOutItemsOverDrive',
		'getPatronFines',
		'getPatronCheckedOutItems',
		'renewItem',
		'renewAll',
		'placeHold',
		'placeItemHold',
		'changeHoldPickUpLocation',
		'placeOverDriveHold',
		'cancelOverDriveHold',
		'checkoutOverDriveItem',
		'cancelHold',
		'freezeHold',
		'activateHold',
		'getPatronReadingHistory',
		'loadReadingHistoryFromIls',
		'optIntoReadingHistory',
		'optOutOfReadingHistory',
		'deleteAllFromReadingHistory',
		'deleteSelectedFromReadingHistory',
		'loadUsernameAndPassword',
	];
	/** @var CatalogConnection */
	private $catalog;

	function getCatalogConnection(){
		if ($this->catalog == null){
			// Connect to Catalog
			$this->catalog = CatalogFactory::getCatalogConnectionInstance();
		}
		return $this->catalog;
	}

	/**
	 *
	 * Returns whether or not a user is currently logged in based on session information.
	 * This method is only useful from Pika itself or from files which can share cookies
	 * with the Pika server.
	 *
	 * Returns:
	 * <code>
	 * {result:[true|false]}
	 * </code>
	 *
	 * Sample call:
	 * <code>
	 * https://example.marmot.org/API/UserAPI?method=isLoggedIn
	 * </code>
	 *
	 * Sample response:
	 * <code>
	 * {"result":true}
	 * </code>
	 *
	 * @access private
	 * @author Mark Noble <pika@marmot.org>
	 */
	function isLoggedIn(){
		return UserAccount::isLoggedIn();
	}

	/**
	 * Logs in the user and sets a cookie indicating that the user is logged in.
	 * Must be called by POSTing data to the API.
	 * This method is only useful from Pika itself or from files which can share cookies
	 * with the Pika server.
	 *
	 * Sample call:
	 * <code>
	 * https://example.marmot.org/API/UserAPI
	 * Post variables:
	 *   method=login
	 *   username=23025003575917
	 *   password=7604
	 * </code>
	 *
	 * Sample response:
	 * <code>
	 * {"result":true}
	 * </code>
	 *
	 * @access private
	 * @author Mark Noble <pika@marmot.org>
	 */
	function login(){
		//Login the user.  Must be called via Post parameters.
		if (isset($_POST['username']) && isset($_POST['password'])){
			$user = UserAccount::getLoggedInUser();
			if ($user && !PEAR_Singleton::isError($user)){
				return ['success' => true, 'name' => ucwords($user->firstname . ' ' . $user->lastname)];
			}else{
				$user = UserAccount::login();
				if ($user && !PEAR_Singleton::isError($user)){
					return ['success' => true, 'name' => ucwords($user->firstname . ' ' . $user->lastname)];
				}else{
					return ['success' => false];
				}
			}
		}else{
			return ['success' => false, 'message' => 'This method must be called via POST.'];
		}
	}

	/**
	 * Logs the user out of the system and clears cookies indicating that the user is logged in.
	 * This method is only useful from Pika itself or from files which can share cookies
	 * with the Pika server.
	 *
	 * Sample call:
	 * <code>
	 * https://example.marmot.org/API/UserAPI?method=logout
	 * </code>
	 *
	 * Sample response:
	 * <code>
	 * {"result":true}
	 * </code>
	 *
	 * @access private
	 * @author Mark Noble <pika@marmot.org>
	 */
	function logout(){
		UserAccount::logout();
		return true;
	}

	/**
	 * Validate whether or not an account is valid based on the barcode and pin number provided.
	 *
	 * Parameters:
	 * <ul>
	 * <li>username - The barcode of the user.  Can be truncated to the last 7 or 9 digits.</li>
	 * <li>password - The pin number for the user.
	 * </ul>
	 *
	 * Returns JSON encoded data as follows:
	 * <ul>
	 * <li>success - false if the username or password could not be found, or the folowing user information if the account is valid.</li>
	 * <li>id  The id of the user within Pika</li>
	 * <li>username, cat_username  The patron's library card number</li>
	 * <li>password, cat_password  The patron's PIN number</li>
	 * <li>firstname  The first name of the patron in the ILS</li>
	 * <li>lastname  The last name of the patron in the ILS</li>
	 * <li>email  The patron's e-mail address if set within Horizon.</li>
	 * <li>college, major  not currently used</li>
	 * <li>homeLocationId  the id of the patron's home library within Pika.</li>
	 * <li>MyLocation1Id, myLocation2Id  not currently used</li>
	 * </ul>
	 *
	 * Sample Call:
	 * <code>
	 * https://example.marmot.org/API/UserAPI?method=validateAccount&username=23025003575917&password=7604
	 * </code>
	 *
	 * Sample Response:
	 * <code>
	 * {"result":{
	 *   "success":{
	 *     "id":"5",
	 *     "username":"23025003575917",
	 *     "password":"7604",
	 *     "firstname":"OS test 1",
	 *     "lastname":"",
	 *     "email":"email",
	 *     "cat_username":"23025003575917",
	 *     "cat_password":"7604",
	 *     "college":"null",
	 *     "major":"null",
	 *     "homeLocationId":null,
	 *     "myLocation1Id":null,
	 *     "myLocation2Id":null
	 *     }
	 *   }
	 * }
	 * </code>
	 *
	 * Sample Response failed login:
	 * <code>
	 * {"result":{"success":false}}
	 * </code>
	 *
	 * @author Mark Noble <pika@marmot.org>
	 */
	function validateAccount(){
		[$username, $password] = $this->loadUsernameAndPassword();

		$result = UserAccount::validateAccount($username, $password);
		if ($result != null){
			//get rid of data object fields before returning the result
			unset($result->__table);
			unset($result->created);
			unset($result->_DB_DataObject_version);
			unset($result->_database_dsn);
			unset($result->_database_dsn_md5);
			unset($result->_database);
			unset($result->_query);
			unset($result->_DB_resultid);
			unset($result->_resultFields);
			unset($result->_link_loaded);
			unset($result->_join);
			unset($result->_lastError);
			unset($result->N);

			return ['success' => $result];
		}else{
			return ['success' => false];
		}
	}

	/**
	 * Load patron profile information for a user based on username and password.
	 * Includes information about print titles and eContent titles that the user has checked out.
	 * Does not include information about OverDrive titles since tat
	 *
	 * Usage:
	 * <code>
	 * {siteUrl}/API/UserAPI?method=getPatronProfile&username=patronBarcode&password=pin
	 * </code>
	 *
	 * Parameters:
	 * <ul>
	 * <li>username - The barcode of the user.  Can be truncated to the last 7 or 9 digits.</li>
	 * <li>password - The pin number for the user. </li>
	 * </ul>
	 *
	 * Returns JSON encoded data as follows:
	 * <ul>
	 * <li>success  true if the account is valid, false if the username or password were incorrect</li>
	 * <li>message  a reason why the method failed if success is false</li>
	 * <li>profile  profile information including name, address, e-mail, number of holds, number of checked out items, fines.</li>
	 * <li>firstname  The first name of the patron in the ILS</li>
	 * <li>lastname  The last name of the patron in the ILS</li>
	 * <li>fullname  The combined first and last name for the patron in the ILS</li>
	 * <li>address1  The street information for the patron</li>
	 * <li>city  The city where the patron lives</li>
	 * <li>state  The state where the patron lives</li>
	 * <li>zip  The zip code for the patron</li>
	 * <li>phone  The phone number for the patron</li>
	 * <li>email  The email for the patron</li>
	 * <li>homeLocationId  The id of the patron's home branch within Pika</li>
	 * <li>homeLocationName  The full name of the patron's home branch</li>
	 * <li>expires  The expiration date of the patron's library card</li>
	 * <li>fines  the amount of fines on the patron's account formatted for display</li>
	 * <li>finesVal  the amount of  fines on the patron's account without formatting</li>
	 * <li>numHolds  The number of holds the patron currently has</li>
	 * <li>numHoldsAvailable  The number of holds the patron currently has that are available</li>
	 * <li>numHoldsRequested  The number of holds the patron currently has that are not available</li>
	 * <li>numCheckedOut  The number of items the patron currently has checked out.</li>
	 * <li>bypassAutoLogout - 1 if the user has chosen to bypass te automatic logout script or 0 if they have not.</li>
	 * <li>numEContentCheckedOut - The number of eContent items that the user currently has checked out. </li>
	 * <li>numEContentAvailableHolds - The number of available eContent holds for the user that can be checked out. </li>
	 * <li>numEContentUnavailableHolds - The number of unavailable eContent holds for the user.</li>
	 * <li>numEContentWishList - The number of eContent titles the user has added to their wishlist.</li>
	 * </ul>
	 *
	 * Sample Call:
	 * <code>
	 * https://example.marmot.org/API/UserAPI?method=getPatronProfile&username=23025003575917&password=7604
	 * </code>
	 *
	 * Sample Response failed login:
	 * <code>
	 * {"result":{
	 *   "success":false,
	 *   "message":"Login unsuccessful"
	 * }}
	 * </code>
	 *
	 * Sample Response:
	 * <code>
	 * { "result" : { "profile" : {
	 *   "address1" : "P O BOX 283",
	 *   "bypassAutoLogout" : "0",
	 *   "city" : "LOUVIERS",
	 *   "displayName" : "",
	 *   "email" : "test@comcast.net",
	 *   "expires" : "02/03/2039",
	 *   "fines" : 0,
	 *   "finesval" : "",
	 *   "firstname" : "",
	 *   "fullname" : "POS test 1",
	 *   "homeLocationId" : "3",
	 *   "homeLocationName" : "Philip S. Miller",
	 *   "lastname" : "POS test 1",
	 *    "numCheckedOut" : 0,
	 *   "numEContentAvailableHolds" : 0,
	 *   "numEContentCheckedOut" : 0,
	 *   "numEContentUnavailableHolds" : 0,
	 *   "numEContentWishList" : 0,
	 *   "numHolds" : 0,
	 *   "numHoldsAvailable" : 0,
	 *   "numHoldsRequested" : 0,
	 *   "phone" : "303-555-5555",
	 *   "state" : "CO",
	 *   "zip" : "80131"
	 * },
	 * "success" : true
	 * } }
	 * </code>
	 *
	 * @author Mark Noble <pika@marmot.org>
	 */
	function getPatronProfile(){
		[$username, $password] = $this->loadUsernameAndPassword();

		$user = UserAccount::validateAccount($username, $password);
		if ($user && !PEAR_Singleton::isError($user)){
			//Remove a bunch of junk from the user data
			unset($user->N);
			unset($user->query);
			foreach ($user as $key => $value){
				if (substr($key, 0, 1) == '_'){
					unset($user->$key);
				}
			}

			return ['success' => true, 'profile' => $user];
		}else{
			return ['success' => false, 'message' => 'Login unsuccessful'];
		}
	}

	/**
	 * Get eContent and ILS holds for a user based on username and password.
	 *
	 * Parameters:
	 * <ul>
	 * <li>username - The barcode of the user.  Can be truncated to the last 7 or 9 digits.</li>
	 * <li>password - The pin number for the user. </li>
	 * </ul>
	 *
	 * Returns:
	 * <ul>
	 * <li>success  true if the account is valid, false if the username or password were incorrect</li>
	 * <li>message  a reason why the method failed if success is false</li>
	 * <li>holds  information about each hold including when it was placed, when it expires, and whether or not it is available for pickup.  Holds are broken into two sections: available and unavailable.  Available holds are ready for pickup.</li>
	 * <li>Id  the record/bib id of the title being held</li>
	 * <li>location  The location where the title will be picked up</li>
	 * <li>expire  the date the hold will expire if it is unavailable or the date that it must be picked up if the hold is available</li>
	 * <li>expireTime  the expire information in number of days since January 1, 1970 </li>
	 * <li>create  the date the hold was originally placed</li>
	 * <li>createTime  the create information in number of days since January 1, 1970</li>
	 * <li>reactivate  The date the hold will be reactivated if the hold is suspended</li>
	 * <li>reactivateTime  the reactivate information in number of days since January 1, 1970</li>
	 * <li>available  whether or not the hold is available for pickup</li>
	 * <li>position  the patron's position in the hold queue</li>
	 * <li>frozen  whether or not the hold is frozen</li>
	 * <li>itemId  the barcode of the item that filled the hold if the hold has been filled.</li>
	 * <li>Status  a textual status of the item (Available, Suspended, Active, In Transit)</li>
	 * </ul>
	 *
	 * Sample Call:
	 * <code>
	 * https://example.marmot.org/API/UserAPI?method=getPatronHolds&username=23025003575917&password=7604
	 * </code>
	 *
	 * Sample Response:
	 * <code>
	 * { "result" :
	 *   { "holds" :
	 *     { "unavailable" : [
	 *       { "author" : "Bernhardt, Gale, 1958-",
	 *            "available" : false,
	 *            "availableTime" : null,
	 *            "barcode" : "33025016545293",
	 *            "create" : "2011-12-20 00:00:00",
	 *            "createTime" : 15328,
	 *            "expire" : "[No expiration date]",
	 *            "expireTime" : null,
	 *            "format" : "Book",
	 *            "format_category" : [ "Books" ],
	 *            "frozen" : false,
	 *            "id" : 868679,
	 *            "isbn" : [ "1931382921 (paper)",
	 *                "9781931382922"
	 *              ],
	 *            "itemId" : 1559061,
	 *            "location" : "Parker",
	 *            "position" : 1,
	 *            "reactivate" : "",
	 *            "reactivateTime" : null,
	 *            "sortTitle" : "training plans for multisport athletes",
	 *            "status" : "In Transit",
	 *            "title" : "Training plans for multisport athletes /",
	 *            "upc" : ""
	 *       } ]
	 *     },
	 *     { "available" : [
	 *       { "author" : "Hunter, Erin.",
	 *            "available" : true,
	 *            "availableTime" : null,
	 *            "barcode" : "33025025084185",
	 *            "create" : "2011-09-27 00:00:00",
	 *            "createTime" : 15244,
	 *            "expire" : "2012-01-09 00:00:00",
	 *            "expireTime" : 15348,
	 *            "format" : "Book",
	 *            "format_category" : [ "Books" ],
	 *            "frozen" : false,
	 *            "id" : 1012238,
	 *            "isbn" : [ "9780061555220",
	 *                "0061555223"
	 *              ],
	 *            "itemId" : 2216202,
	 *            "location" : "Parker",
	 *            "position" : 2,
	 *            "reactivate" : "",
	 *            "reactivateTime" : 15308,
	 *            "sortTitle" : "forgotten warrior",
	 *            "status" : "Available",
	 *            "title" : "The forgotten warrior /",
	 *            "upc" : ""
	 *          } ]
	 *     },
	 *     "success" : true
	 *  }
	 * }
	 * </code>
	 *
	 * @author Mark Noble <pika@marmot.org>
	 */
	function getPatronHolds(){
		[$username, $password] = $this->loadUsernameAndPassword();

		$user = UserAccount::validateAccount($username, $password);
		if ($user && !PEAR_Singleton::isError($user)){
			$allHolds = $user->getMyHolds();
			return ['success' => true, 'holds' => $allHolds];
		}else{
			return ['success' => false, 'message' => 'Login unsuccessful'];
		}
	}

	/**
	 * Get a list of holds with details from OverDrive.
	 *
	 */
	function getPatronHoldsOverDrive(){
		[$username, $password] = $this->loadUsernameAndPassword();
		$user = UserAccount::validateAccount($username, $password);
		if ($user && !PEAR_Singleton::isError($user)){
			$eContentDriver = OverDriveDriverFactory::getDriver();
			$eContentHolds  = $eContentDriver->getOverDriveHolds($user);
			return ['success' => true, 'holds' => $eContentHolds];
		}else{
			return ['success' => false, 'message' => 'Login unsuccessful'];
		}
	}

	/**
	 * Get a list of items that are currently checked out to the user within OverDrive.
	 *
	 */
	function getPatronCheckedOutItemsOverDrive(){
		[$username, $password] = $this->loadUsernameAndPassword();
		$user = UserAccount::validateAccount($username, $password);
		if ($user && !PEAR_Singleton::isError($user)){
			$eContentDriver          = OverDriveDriverFactory::getDriver();
			$eContentCheckedOutItems = $eContentDriver->getOverDriveCheckouts($user);
			return ['success' => true, 'items' => $eContentCheckedOutItems];
		}else{
			return ['success' => false, 'message' => 'Login unsuccessful'];
		}
	}

	/**
	 * Get fines from the ILS for a user based on username and password.
	 *
	 * Parameters:
	 * <ul>
	 * <li>username - The barcode of the user.  Can be truncated to the last 7 or 9 digits.</li>
	 * <li>password - The pin number for the user.</li>
	 * <li>includeMessages - Whether or not messages to the user should be included within list of fines. (optional, defaults to false)</li>
	 * </ul>
	 *
	 * Sample Call:
	 * <code>
	 * https://example.marmot.org/API/UserAPI?method=getPatronFines&username=23025003575917&password=7604
	 * </code>
	 *
	 * Sample Response:
	 * <code>
	 * {"result":{
	 *   "success":true,
	 *   "fines":[
	 *     {"reason":"Privacy - Family permission",
	 *      "amount":"$0.00",
	 *      "message":"",
	 *      "date":"09\/27\/2005"
	 *     },
	 *     {"reason":"Charges Misc. Fees",
	 *      "amount":"$5.00",
	 *      "message":"",
	 *      "date":"04\/14\/2011"
	 *     }
	 *   ]
	 * }}
	 * </code>
	 *
	 * @author Mark Noble <pika@marmot.org>
	 */
	function getPatronFines(){
		[$username, $password] = $this->loadUsernameAndPassword();
		$includeMessages = isset($_REQUEST['includeMessages']) ? $_REQUEST['includeMessages'] : false;
		$user            = UserAccount::validateAccount($username, $password);
		if ($user && !PEAR_Singleton::isError($user)){
			$fines = $this->getCatalogConnection()->getMyFines($user, $includeMessages);
			return ['success' => true, 'fines' => $fines];
		}else{
			return ['success' => false, 'message' => 'Login unsuccessful'];
		}
	}

	/**
	 * Get eContent and ILS records that are checked out to a user based on username and password.
	 *
	 * Parameters:
	 * <ul>
	 * <li>username - The barcode of the user.  Can be truncated to the last 7 or 9 digits.</li>
	 * <li>password - The pin number for the user. </li>
	 * <li>includeEContent - Optional flag for whether or not to include checked out eContent. Set to false to only include print titles.</li>
	 * </ul>
	 *
	 * Sample Call:
	 * <code>
	 * https://example.marmot.org/API/UserAPI?method=getPatronCheckedOutItems&username=23025003575917&password=7604
	 * </code>
	 *
	 * Sample Response:
	 * <code>
	 * TODO: update
	 * {"result":{
	 *   "success":true,
	 *   "checkedOutItems":{
	 *     "33025021368319":{
	 *       "id":"966379",
	 *       "itemid":"33025021368319",
	 *       "dueDate":"01\/24\/2012",
	 *       "checkoutdate":"2011-12-27 00:00:00",
	 *       "barcode":"33025021368319",
	 *       "renewCount":"1",
	 *       "request":null,
	 *       "overdue":false,
	 *       "daysUntilDue":16,
	 *       "title":"Be iron fit : time-efficient training secrets for ultimate fitness \/",
	 *       "sortTitle":"be iron fit : time-efficient training secrets for ultimate fitness \/ time-efficient training secrets for ultimate fitness \/",
	 *       "author":"Fink, Don.",
	 *       "format":"Book",
	 *       "isbn":"9781599218571"
	 *       ,"upc":"",
	 *       "format_category":"Books",
	 *       "holdQueueLength":3
	 *     }
	 *   }
	 * }}
	 * </code>
	 *
	 * @author Mark Noble <pika@marmot.org>
	 */
	function getPatronCheckedOutItems(){
		global $offlineMode;
		if ($offlineMode){
			return ['success' => false, 'message' => 'Circulation system is offline'];
		}else{
			if (!empty($_REQUEST['token'])){
				$user = $this->validateUserApiToken();
			}else{
				[$username, $password] = $this->loadUsernameAndPassword();
				/** @var User $user */
				$user = UserAccount::validateAccount($username, $password);
			}
			if (!empty($user) && !PEAR_Singleton::isError($user)){
				$allCheckedOut = $user->getMyCheckouts(false);

				return ['success' => true, 'checkedOutItems' => $allCheckedOut];
			}else{
				return ['success' => false, 'message' => 'Login unsuccessful'];
			}
		}
	}

	/**
	 * Renews an item that has been checked out within the ILS.
	 *
	 * Parameters:
	 * <ul>
	 * <li>username - The barcode of the user.  Can be truncated to the last 7 or 9 digits.</li>
	 * <li>password - The pin number for the user. </li>
	 * <li>itemBarcode - The barcode of the item to be renewed.</li>
	 * </ul>
	 *
	 * Sample Call:
	 * <code>
	 * https://example.marmot.org/API/UserAPI?method=renewItem&username=23025003575917&password=7604&itemBarcode=33025021368319
	 * </code>
	 *
	 * Sample Response (failed renewal):
	 * <code>
	 * {"result":{
	 *   "success":true,
	 *   "renewalMessage":{
	 *     "itemId":"33025021368319",
	 *     "result":false,
	 *     "message":"This item may not be renewed - Item has been requested."
	 *   }
	 * }}
	 * </code>
	 *
	 * Sample Response (successful renewal):
	 * <code>
	 * {"result":{
	 *   "success":true,
	 *   "renewalMessage":{
	 *     "itemId":"33025021723869",
	 *     "result":true,
	 *     "message":"#Renewal successful."
	 *   }
	 * }}
	 * </code>
	 *
	 * @author Mark Noble <pika@marmot.org>
	 */
	function renewItem(){
		[$username, $password] = $this->loadUsernameAndPassword();
		$itemBarcode = $_REQUEST['itemBarcode'];
		$user        = UserAccount::validateAccount($username, $password);
		if ($user && !PEAR_Singleton::isError($user)){
			$renewalMessage = $this->getCatalogConnection()->renewItem($user, null, $itemBarcode, null);
			return ['success' => true, 'renewalMessage' => $renewalMessage];
		}else{
			return ['success' => false, 'message' => 'Login unsuccessful'];
		}
	}

	/**
	 * Renews all items that have been checked out to the user from the ILS.
	 * Returns a count of the number of items that could be renewed.
	 *
	 * Parameters:
	 * <ul>
	 * <li>username - The barcode of the user.  Can be truncated to the last 7 or 9 digits.</li>
	 * <li>password - The pin number for the user. </li>
	 * </ul>
	 *
	 * Sample Call:
	 * <code>
	 * https://example.marmot.org/API/UserAPI?method=renewAll&username=23025003575917&password=7604
	 * </code>
	 *
	 * Sample Response:
	 * <code>
	 * {"result":{
	 *   "success":true,
	 *   "renewalMessage":"0006 of 8 items were renewed successfully."
	 * }}
	 * </code>
	 *
	 * @author Mark Noble <pika@marmot.org>
	 */
	function renewAll(){
		[$username, $password] = $this->loadUsernameAndPassword();
		$user = UserAccount::validateAccount($username, $password);
		if ($user && !PEAR_Singleton::isError($user)){
			$renewalMessage = $this->getCatalogConnection()->renewAll($user);
			return ['success' => $renewalMessage['success'], 'renewalMessage' => $renewalMessage['message']];
		}else{
			return ['success' => false, 'message' => 'Login unsuccessful'];
		}
	}

	/**
	 * Places a hold on an item that is available within the ILS. The location where the user would like to pickup
	 * the title must be specified as well als the record the user would like a hold placed on.
	 *
	 * Parameters:
	 * <ul>
	 * <li>username - The barcode of the user.  Can be truncated to the last 7 or 9 digits.</li>
	 * <li>password - The pin number for the user. </li>
	 * <li>bibId    - The id of the record within the ILS.</li>
	 * <li>campus    the location where the patron would like to pickup the title (optional). If not provided, the patron's home location will be used.</li>
	 * </ul>
	 *
	 * Returns JSON encoded data as follows:
	 * <ul>
	 * <li>success  true if the account is valid and the hold could be placed, false if the username or password were incorrect or the hold could not be placed.</li>
	 * <li>holdMessage  a reason why the method failed if success is false, or information about hold queue position if successful.</li>
	 * </ul>
	 *
	 * Sample Call:
	 * <code>
	 * https://example.marmot.org/API/UserAPI?method=renewAll&username=23025003575917&password=7604&bibId=1004012&campus=pa
	 * </code>
	 *
	 * Sample Response (successful hold):
	 * <code>
	 * {"result":{
	 *   "success":true,
	 *   "holdMessage":"Placement of hold request successful. You are number 1 in the queue."
	 * }}
	 * </code>
	 *
	 * Sample Response (failed hold):
	 * <code>
	 * {"result":{
	 *   "success":false,
	 *   "holdMessage":"Unable to place a hold request. You have already requested this."
	 * }}
	 * </code>
	 *
	 * @author Mark Noble <pika@marmot.org>
	 */
	function placeHold(){
		$bibId = $_REQUEST['bibId'];

		if (!empty($_REQUEST['token'])){
			$patron = $this->validateUserApiToken();
		}else{
			[$username, $password] = $this->loadUsernameAndPassword();
			$patron = UserAccount::validateAccount($username, $password);
		}
		if (!empty($patron) && !PEAR_Singleton::isError($patron)){
			if (isset($_REQUEST['campus'])){
				$pickupBranch = trim($_REQUEST['campus']);
			}else{
				$pickupBranch = $patron->homeLocationCode;
			}
			$holdMessage = $patron->placeHold($bibId, $pickupBranch);
			return $holdMessage;
		}else{
			return ['success' => false, 'message' => 'Login unsuccessful'];
		}
	}

	function placeItemHold(){
		$bibId  = $_REQUEST['bibId'];
		$itemId = $_REQUEST['itemId'];

		if (!empty($_REQUEST['token'])){
			$patron = $this->validateUserApiToken();
		}else{
			[$username, $password] = $this->loadUsernameAndPassword();
			$patron = UserAccount::validateAccount($username, $password);
		}
		if (!empty($patron) && !PEAR_Singleton::isError($patron)){
			if (isset($_REQUEST['campus'])){
				$pickupBranch = trim($_REQUEST['campus']);
			}else{
				$pickupBranch = $patron->homeLocationCode;
			}
			$holdMessage = $patron->placeItemHold($bibId, $itemId, $pickupBranch);
			return $holdMessage;
		}else{
			return ['success' => false, 'message' => 'Login unsuccessful'];
		}
	}

	function changeHoldPickUpLocation(){
		[$username, $password] = $this->loadUsernameAndPassword();
		$user = UserAccount::validateAccount($username, $password);
		if ($user && !PEAR_Singleton::isError($user)){
			$holdId      = $_REQUEST['holdId'];
			$newLocation = $_REQUEST['location'];
			$holdMessage = $user->changeHoldPickUpLocation($holdId, $newLocation);
			return ['success' => $holdMessage['success'], 'holdMessage' => $holdMessage['message']];
		}else{
			return ['success' => false, 'message' => 'Login unsuccessful'];
		}
	}

	/**
	 * Place a hold within OverDrive.
	 *
	 */
	function placeOverDriveHold(){
		[$username, $password] = $this->loadUsernameAndPassword();
		$user = UserAccount::validateAccount($username, $password);
		if ($user && !PEAR_Singleton::isError($user)){
			$overDriveId = $_REQUEST['overDriveId'];
			$driver      = OverDriveDriverFactory::getDriver();
			$holdMessage = $driver->placeOverDriveHold($overDriveId, $user);
			return ['success' => $holdMessage['success'], 'message' => $holdMessage['message']];
		}else{
			return ['success' => false, 'message' => 'Login unsuccessful'];
		}
	}

	/**
	 * Cancel a hold within OverDrive
	 *
	 */
	function cancelOverDriveHold(){
		[$username, $password] = $this->loadUsernameAndPassword();
		$user = UserAccount::validateAccount($username, $password);
		if ($user && !PEAR_Singleton::isError($user)){
			$overDriveId = $_REQUEST['overDriveId'];
			$driver      = OverDriveDriverFactory::getDriver();
			$result      = $driver->cancelOverDriveHold($overDriveId, $user);
			return ['success' => $result['success'], 'message' => $result['message']];
		}else{
			return ['success' => false, 'message' => 'Login unsuccessful'];
		}
	}

	/**
	 * Checkout an item in OverDrive.
	 *
	 */
	function checkoutOverDriveItem(){
		[$username, $password] = $this->loadUsernameAndPassword();
		$user = UserAccount::validateAccount($username, $password);
		if ($user && !PEAR_Singleton::isError($user)){
			$overDriveId = $_REQUEST['overDriveId'];
			$driver      = OverDriveDriverFactory::getDriver();
			$holdMessage = $driver->checkoutOverDriveTitle($overDriveId, $user);
			return ['success' => $holdMessage['success'], 'message' => $holdMessage['message']];
		}else{
			return ['success' => false, 'message' => 'Login unsuccessful'];
		}
	}

	/**
	 * Cancel a hold that was placed within the ILS.
	 *
	 * Parameters:
	 * <ul>
	 * <li>username - The barcode of the user.  Can be truncated to the last 7 or 9 digits.</li>
	 * <li>password - The pin number for the user. </li>
	 * <li>availableholdselected[]  an array of holds that should be canceled.  Each item should be specfied as <bibId>:<itemId>. BibId and itemId can be retrieved as part of the getPatronHolds API</li>
	 * <li>waitingholdselected[] - an array of holds that are not ready for pickup that should be canceled.  Each item should be specified as <bibId>:0.</li>
	 * </ul>
	 *
	 * Returns:
	 * <ul>
	 * <li>success  true if the account is valid and the hold could be canceled, false if the username or password were incorrect or the hold could not be canceled.</li>
	 * <li>holdMessage  a reason why the method failed if success is false</li>
	 * </ul>
	 *
	 * Sample Call:
	 * <code>
	 * https://example.marmot.org/API/UserAPI?method=cancelHold&username=23025003575917&password=1234&waitingholdselected[]=1003198
	 * </code>
	 *
	 * Sample Response:
	 * <code>
	 * </code>
	 *
	 * Sample Response (failed):
	 * <code>
	 * {"result":{
	 *   "success":false,
	 *   "holdMessage":"Your hold could not be cancelled. Please try again later or see your librarian."
	 * }}
	 * </code>
	 *
	 * Sample Response (succeeded):
	 * <code>
	 * {"result":{
	 *   "success":true,
	 *   "holdMessage":"Your hold was cancelled successfully."
	 * }}
	 * </code>
	 *
	 * @author Mark Noble <pika@marmot.org>
	 */
	function cancelHold(){
		[$username, $password] = $this->loadUsernameAndPassword();
		$user = UserAccount::validateAccount($username, $password);
		if ($user && !PEAR_Singleton::isError($user)){
			// Cancel Hold requires one of these, which one depends on the ILS
			$recordId = $cancelId = null;
			if (!empty($_REQUEST['recordId'])){
				$recordId = $_REQUEST['recordId'];
			}
			if (!empty($_REQUEST['cancelId'])){
				$cancelId = $_REQUEST['cancelId'];
			}

			$holdMessage = $user->cancelHold($recordId, $cancelId);
			return ['success' => $holdMessage['success'], 'holdMessage' => $holdMessage['message']];
		}else{
			return ['success' => false, 'message' => 'Login unsuccessful'];
		}
	}

	/**
	 * Freezes a hold that has been placed on a title within the ILS.  Only unavailable holds can be frozen.
	 * Note:  Horizon implements suspending and activating holds as a toggle.  If a hold is suspended, it will be activated
	 * and if a hold is active it will be suspended.  Care should be taken when calling the method with holds that are in the wrong state.
	 *
	 * Parameters:
	 * <ul>
	 * <li>username - The barcode of the user.  Can be truncated to the last 7 or 9 digits.</li>
	 * <li>password - The pin number for the user. </li>
	 * <li>waitingholdselected[] - an array of holds that are not ready for pickup that should be frozen. Each item should be specified as <bibId>:0.</li>
	 * <li>suspendDate - The date that the hold should be automatically reactivated.</li>
	 * </ul>
	 *
	 * Returns:
	 * <ul>
	 * <li>success  true if the account is valid and the hold could be frozen, false if the username or password were incorrect or the hold could not be frozen.</li>
	 * <li>holdMessage  a reason why the method failed if success is false</li>
	 * </ul>
	 *
	 * Sample Call:
	 * <code>
	 * https://example.marmot.org/API/UserAPI?method=freezeHold&username=23025003575917&password=1234&waitingholdselected[]=1004012:0&suspendDate=1/25/2012
	 * </code>
	 *
	 * Sample Response:
	 * <code>
	 * {"result":{
	 *   "success":true,
	 *   "holdMessage":"Your hold was updated successfully."
	 * }}
	 * </code>
	 *
	 * @author Mark Noble <pika@marmot.org>
	 */
	function freezeHold(){
		[$username, $password] = $this->loadUsernameAndPassword();
		$user = UserAccount::validateAccount($username, $password);
		if ($user && !PEAR_Singleton::isError($user)){
			$itemId = trim($_REQUEST['holdId']);
			if (!empty($itemId && ctype_alnum($itemId))){
				$reactivateDate = trim($_REQUEST['suspendDate']);
				$holdMessage = $this->getCatalogConnection()->freezeHold($user, null, $itemId, $reactivateDate);
				return ['success' => $holdMessage['success'], 'holdMessage' => $holdMessage['message']];
			}
			return ['success' => false, 'message' => 'Invalid hold Id'];
		}else{
			return ['success' => false, 'message' => 'Login unsuccessful'];
		}
	}

	/**
	 * Activates a hold that was previously suspended within the ILS.  Only unavailable holds can be activated.
	 * Note:  Horizon implements suspending and activating holds as a toggle.  If a hold is suspended, it will be activated
	 * and if a hold is active it will be suspended.  Care should be taken when calling the method with holds that are in the wrong state.
	 *
	 * Parameters:
	 * <ul>
	 * <li>username - The barcode of the user.  Can be truncated to the last 7 or 9 digits.</li>
	 * <li>password - The pin number for the user. </li>
	 * <li>waitingholdselected[] - an array of holds that are not ready for pickup that should be frozen. Each item should be specified as <bibId>:0.</li>
	 * </ul>
	 *
	 * Returns:
	 * <ul>
	 * <li>success  true if the account is valid and the hold could be activated, false if the username or password were incorrect or the hold could not be activated.</li>
	 * <li>holdMessage  a reason why the method failed if success is false</li>
	 * </ul>
	 *
	 * Sample Call:
	 * <code>
	 * https://example.marmot.org/API/UserAPI?method=activateHold&username=23025003575917&password=1234&waitingholdselected[]=1004012:0
	 * </code>
	 *
	 * Sample Response:
	 * <code>
	 * {"result":{
	 *   "success":true,
	 *   "holdMessage":"Your hold was updated successfully."
	 * }}
	 * </code>
	 *
	 * @author Mark Noble <pika@marmot.org>
	 */
	function activateHold(){
		[$username, $password] = $this->loadUsernameAndPassword();
		$user = UserAccount::validateAccount($username, $password);
		if ($user && !PEAR_Singleton::isError($user)){
			$itemId = trim($_REQUEST['holdId']);
			if (!empty($itemId && ctype_alnum($itemId))){
				$holdMessage = $this->getCatalogConnection()->thawHold($user, null, $itemId);
				return ['success' => $holdMessage['success'], 'holdMessage' => $holdMessage['message']];
			}
			return ['success' => false, 'message' => 'Invalid hold Id'];
		}else{
			return ['success' => false, 'message' => 'Login unsuccessful'];
		}
	}

	/**
	 * Loads the reading history for the user.  Includes print, eContent, and OverDrive titles.
	 * Note: The return of this method can be quite lengthy if the patron has a large number of items in their reading history.
	 *
	 * Parameters:
	 * <ul>
	 * <li>username - The barcode of the user.  Can be truncated to the last 7 or 9 digits.</li>
	 * <li>password - The pin number for the user. </li>
	 * </ul>
	 *
	 * Returns:
	 * <ul>
	 * <li>success  true if the account is valid and the hold could be canceled, false if the username or password were incorrect or the hold could not be canceled.</li>
	 * <li>holdMessage  a reason why the method failed if success is false</li>
	 * </ul>
	 *
	 * Sample Call:
	 * <code>
	 * https://example.marmot.org/API/UserAPI?method=getPatronReadingHistory&username=23025003575917&password=1234
	 * </code>
	 *
	 * Sample Response:
	 * <code>
	 * {"result":{
	 *   "success":true,
	 *   "readingHistory":[
	 *     {"recordId":"597608",
	 *      "checkout":"2011-03-18",
	 *      "checkoutTime":1300428000,
	 *      "lastCheckout":"2011-03-22",
	 *      "lastCheckoutTime":1300773600,
	 *      "title":"The wanderer",
	 *      "title_sort":"wanderer",
	 *      "author":"O.A.R. (Musical group)",
	 *      "format":"Music CD",
	 *      "format_category":"Music",
	 *      "isbn":"",
	 *      "upc":"803494030726"
	 *     },
	 *     {"recordId":"808990",
	 *      "checkout":"2011-03-18",
	 *      "checkoutTime":1300428000,
	 *      "lastCheckout":"2011-03-22",
	 *      "lastCheckoutTime":1300773600,
	 *      "title":"Seals \/",
	 *      "title_sort":"seals \/",
	 *      "author":"Sexton, Colleen A.,",
	 *      "format":"Book",
	 *      "format_category":"Books",
	 *      "isbn":"9781600140563",
	 *      "upc":""
	 *     }
	 *   ]
	 * }}
	 * </code>
	 *
	 * @author Mark Noble <pika@marmot.org>
	 */
	function getPatronReadingHistory(){
		global $offlineMode;
		if ($offlineMode){
			return ['success' => false, 'message' => 'Circulation system is offline'];
		}else{
			[$username, $password] = $this->loadUsernameAndPassword();
			$user = UserAccount::validateAccount($username, $password);
			if ($user && !PEAR_Singleton::isError($user)){
				//TODO: go through the user object & include paging/sort options
				$readingHistory = $this->getCatalogConnection()->getReadingHistory($user);

				return ['success' => true, 'readingHistory' => $readingHistory['titles']];
			}else{
				return ['success' => false, 'message' => 'Login unsuccessful'];
			}
		}
	}

	/**
	 *  Process to load a user's reading history from the ILS from the Pika cron process UpdateReadingHistory
	 *
	 * @return array
	 */
	function loadReadingHistoryFromIls(){
		global $offlineMode;
		if ($offlineMode){
			return ['success' => false, 'message' => 'Circulation system is offline'];
		}else{
			$loadAdditional = null;
			if (!empty($_REQUEST['nextRound']) && ctype_digit($_REQUEST['nextRound'])){
				$loadAdditional = $_REQUEST['nextRound'];
			}
			if (!empty($_REQUEST['token'])){
				$user = $this->validateUserApiToken();
			}else{
				[$username, $password] = $this->loadUsernameAndPassword();
				$user = UserAccount::validateAccount($username, $password);
			}
			if (!empty($user) && !PEAR_Singleton::isError($user)){
				if ($user->trackReadingHistory){
					$loadReadingHistoryResponse = $user->loadReadingHistoryFromIls($loadAdditional);
					if (!$loadReadingHistoryResponse){
						return ['success' => false, 'message' => 'Did not load reading history from ILS.'];
					}
					if (empty($loadReadingHistoryResponse['nextRound'])){
						return ['success' => true, 'readingHistory' => $loadReadingHistoryResponse['titles']];
					}else{
						return [
							'success'        => true,
							'nextRound'      => $loadReadingHistoryResponse['nextRound'],
							'readingHistory' => $loadReadingHistoryResponse['titles'],
						];
					}
				}else{
					return ['success' => false, 'message' => 'User is not opted in for reading history'];
				}
			}else{
				return ['success' => false, 'message' => 'Login unsuccessful'];
			}
		}
	}


	/**
	 * Allows reading history to be collected for the patron.  If this option is not selected,
	 * no reading history for the patron wil be stored.
	 *
	 * Parameters:
	 * <ul>
	 * <li>username - The barcode of the user.  Can be truncated to the last 7 or 9 digits.</li>
	 * <li>password - The pin number for the user. </li>
	 * </ul>
	 *
	 * Returns:
	 * <ul>
	 * <li>success  true if the account is valid and the reading history could be turned on, false if the username or password were incorrect or the reading history could not be turned on.</li>
	 * </ul>
	 *
	 * Sample Call:
	 * <code>
	 * https://example.marmot.org/API/UserAPI?method=optIntoReadingHistory&username=23025003575917&password=1234
	 * </code>
	 *
	 * Sample Response:
	 * <code>
	 * {"result":{"success":true}}
	 * </code>
	 *
	 * @author Mark Noble <pika@marmot.org>
	 */
	function optIntoReadingHistory(){
		[$username, $password] = $this->loadUsernameAndPassword();
		$user = UserAccount::validateAccount($username, $password);
		if ($user && !PEAR_Singleton::isError($user)){
			$result = $user->optInReadingHistory();
			return ['success' => $result];
		}else{
			return ['success' => false, 'message' => 'Login unsuccessful'];
		}
	}

	/**
	 * Stops collecting reading history for the patron and removes any reading history entries that have been collected already.
	 *
	 * Parameters:
	 * <ul>
	 * <li>username - The barcode of the user.  Can be truncated to the last 7 or 9 digits.</li>
	 * <li>password - The pin number for the user. </li>
	 * </ul>
	 *
	 * Returns:
	 * <ul>
	 * <li>success  true if the account is valid and the reading history could be turned off, false if the username or password were incorrect or the reading history could not be turned off.</li>
	 * </ul>
	 *
	 * Sample Call:
	 * <code>
	 * https://example.marmot.org/API/UserAPI?method=optOutOfReadingHistory&username=23025003575917&password=1234
	 * </code>
	 *
	 * Sample Response:
	 * <code>
	 * {"result":{"success":true}}
	 * </code>
	 *
	 * @author Mark Noble <pika@marmot.org>
	 */
	function optOutOfReadingHistory(){
		[$username, $password] = $this->loadUsernameAndPassword();
		$user = UserAccount::validateAccount($username, $password);
		if ($user && !PEAR_Singleton::isError($user)){
			$result = $user->optOutReadingHistory();
			return ['success' => $result];
		}else{
			return ['success' => false, 'message' => 'Login unsuccessful'];
		}
	}

	/**
	 * Clears the user's reading history, but does not stop the collection of new data.  If items are currently checked out
	 * to the user they will be added to the reading history the next time cron runs.
	 *
	 * Parameters:
	 * <ul>
	 * <li>username - The barcode of the user.  Can be truncated to the last 7 or 9 digits.</li>
	 * <li>password - The pin number for the user. </li>
	 * </ul>
	 *
	 * Returns:
	 * <ul>
	 * <li>success  true if the account is valid and the reading history could cleared, false if the username or password were incorrect or the reading history could not be cleared.</li>
	 * </ul>
	 *
	 * Sample Call:
	 * <code>
	 * https://example.marmot.org/API/UserAPI?method=deleteAllFromReadingHistory&username=23025003575917&password=1234
	 * </code>
	 *
	 * Sample Response:
	 * <code>
	 * </code>
	 *
	 * @author Mark Noble <pika@marmot.org>
	 */
	function deleteAllFromReadingHistory(){
		[$username, $password] = $this->loadUsernameAndPassword();
		$user = UserAccount::validateAccount($username, $password);
		if ($user && !PEAR_Singleton::isError($user)){
			$result = $user->deleteAllReadingHistory();
			return ['success' => $result];
		}else{
			return ['success' => false, 'message' => 'Login unsuccessful'];
		}
	}

	/**
	 * Removes one or more titles from the user's reading history.
	 *
	 * Parameters:
	 * <ul>
	 * <li>username - The barcode of the user.  Can be truncated to the last 7 or 9 digits.</li>
	 * <li>password - The pin number for the user. </li>
	 * <li>selected - A list of record ids to be deleted from the reading history.</li>
	 * </ul>
	 *
	 * Returns:
	 * <ul>
	 * <li>success  true if the account is valid and the items could be removed from the reading history, false if the username or password were incorrect or the items could not be removed from the reading history.</li>
	 * </ul>
	 *
	 * Sample Call:
	 * <code>
	 * https://example.marmot.org/API/UserAPI?method=deleteSelectedFromReadingHistory&username=23025003575917&password=1234&selected[]=25855
	 * </code>
	 *
	 * Sample Response:
	 * <code>
	 * {"result":{"success":true}}
	 * </code>
	 *
	 * @author Mark Noble <pika@marmot.org>
	 */
	function deleteSelectedFromReadingHistory(){
		[$username, $password] = $this->loadUsernameAndPassword();
		$user = UserAccount::validateAccount($username, $password);
		if ($user && !PEAR_Singleton::isError($user)){
			$selectedTitles = $_REQUEST['selected'];
			$result         = $user->deleteMarkedReadingHistory($selectedTitles);
			return ['success' => $result];
		}else{
			return ['success' => false, 'message' => 'Login unsuccessful'];
		}
	}

	public function setDefaultPin(){
//		global $pikaLogger;
//		$pikaLogger->debug('POST', [$_POST]);
//		$pikaLogger->debug('REQUEST', [$_REQUEST]);

		if (!empty($_REQUEST['token'])){
			global $configArray;
			if (!empty($configArray['System']['allowSetDefaultPin'])){
				// Check that a config setting has been set as additional security precaution
				$user = $this->validateUserApiToken();
				if ($user){
					if (!empty($_POST['defaultPin'])){
						$user->setPassword($_POST['defaultPin']);
						if ($user->update()){
							return ['success' => true];
						}else{
							global $pikaLogger;
							$pikaLogger->error("Failed to set a default pin for user $user->id");
							return ['success' => false, 'message' => 'Failed to set default pin'];
						}
					}else{
						global $pikaLogger;
						$pikaLogger->error("setDefaultPassword received request for user $user->id that did not contain a default Pin");
						return ['success' => false, 'message' => 'Missing default pin'];
					}
				}
			}
		}
		return ['success' => false];
	}

	public function getPatronPin(){
		if (!empty($_REQUEST['token'])){
			global $configArray;
			if (!empty($configArray['System']['allowGetPatronPin'])){
				// Check that a config setting has been set as additional security precaution
				$user = $this->validateUserApiToken();
				if ($user){
					$pin = $user->getPassword();
					if (!empty($pin)){
						global $pikaLogger;
						$pikaLogger->notice("Returning pin for patron $user->id");
						return [
							'success' => true,
							'pin'     => $pin,
						];
					}
				}
			}
		}
		return ['success' => false];
	}

	/**
	 * @return array
	 */
	private function loadUsernameAndPassword(){
		$username = $_REQUEST['username'] ?? '';
		$password = $_REQUEST['password'] ?? '';
		if (is_array($username)){
			$username = reset($username);
		}
		if (is_array($password)){
			$password = reset($password);
		}
		return [$username, $password];
	}

	/**
	 * Validate the API request's supplied token for the User of interest
	 *
	 * @return false|User
	 */
	private function validateUserApiToken(){
		if (!empty($_REQUEST['token'])){
			if (!empty($_REQUEST['userId'])){
				if (ctype_digit($_REQUEST['userId'])){
					$user = new User();
					if ($user->get($_REQUEST['userId'])){
						global $configArray;
						if (!empty($configArray['System']['userApiToken'])){
							$barcode      = $user->getBarcode();
							$tokenToMatch = md5($barcode . $configArray['System']['userApiToken']);
							if ($tokenToMatch == $_REQUEST['token']){
								return $user;
							} else {
								global $pikaLogger;
								$pikaLogger->warning('User API call had a invalid token parameter');
							}
						}else{
							global $pikaLogger;
							$pikaLogger->error('Received User API call with token parameter but no token in config');
						}
					}
				}
			}else{
				global $pikaLogger;
				$pikaLogger->error('Received User API call with token parameter but no userId', [$_REQUEST]);
				//TODO: request url
			}
		}
		return false;
	}
}
