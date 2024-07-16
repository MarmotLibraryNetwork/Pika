<?php
/*
 * Pika Discovery Layer
 * Copyright (C) 2024  Marmot Library Network
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
 *
 * Class Polaris
 *
 * Methods needed for completing patron actions in the Polaris ILS
 *
 * This class implements the Polaris API for patron interactions:
 * https://documentation.iii.com/polaris/PAPI/current/PAPIService/PAPIServiceOverview.htm
 *
 * @category Pika
 * @package  PatronDrivers
 * @author   Chris Froese
 * Date      6-10-2024
 *
 */

namespace Pika\PatronDrivers;

use DateInterval;
use MarcRecord;
use Pika\Cache;
use Pika\Logger;
use Curl\Curl;
use DateTime;
use DateTimeZone;
use User;

//use Memcache;

class Polaris extends PatronDriverInterface implements \DriverInterface
{
    /**
     * $api_access_key Polaris web service access key, maps to Catalog->clientKey
     * @var string
     */
    protected string $ws_access_key;
    /**
     * $ws_access_id Polaris web service access ID, maps to Catalog->clientSecret
     *
     * @var string
     */
    protected string $ws_access_id;
    /**
     * $ws_base_url The web service url without the variable bits; version/langId/appId/locationId
     *
     * @var string
     */
    protected string $ws_base_url;
    /**
     * $ws_url The api url with the variable bits added; version/langId/appId/locationId
     *
     * @var string
     */
    protected string $ws_url;
    protected string $ws_version;
    /**
     * $api_lang_id Polaris web service language ID. Default is 1003 (english)
     *
     * @var int
     * @see https://documentation.iii.com/polaris/PAPI/current/PAPIService/PAPIServiceOverview.htm#papiserviceoverview_3170935956_1222507
     */
    protected int $ws_lang_id = 1033;
    /**
     * $ws_app_id Web service app id -- default is 100
     *
     * @var int
     * @see https://documentation.iii.com/polaris/PAPI/current/PAPIService/PAPIServiceOverview.htm#papiserviceoverview_3170935956_1213787
     */
    protected int $ws_app_id = 100;
    protected string $patron_access_secret;

    public \AccountProfile $accountProfile;
    protected array $configArray;
    protected Logger $logger;
    protected Cache $cache;

    /**
     * @inheritDoc
     */
    public function __construct($accountProfile)
    {
        global $configArray;

        $this->configArray = $configArray;
        $this->accountProfile = $accountProfile;
        $this->logger = new Logger(__CLASS__);

        $this->cache = new \Pika\Cache();
        $this->ws_base_url = trim($accountProfile->patronApiUrl, '/ ');
        $this->ws_version = $configArray['Catalog']['api_version'];
        $this->ws_url = $this->ws_base_url . '/' . $this->ws_version . '/' . $this->ws_lang_id . '/'
            . $this->ws_app_id . '/1';
        $this->ws_access_key = $configArray['Catalog']['clientKey'];
        $this->ws_access_id  = $configArray['Catalog']['clientSecret'];

    }

    /**
     * patronLogin
     *
     * Authenticate a patron with username/barcode and password against the /authenticator/patron endpoint.
     *
     * If 3 failed attempts are made the account will be temporarily locked by the Polaris system.
     * Polaris has username/password or barcode/password functionality
     *
     * @param string $barcode
     * @param string $pin
     * @param bool $validatedViaSSO
     * @return User|null
     * @throws \JsonException
     */
    public function patronLogin($barcode, $pin, $validatedViaSSO = false): ?User
    {
        $barcode = str_replace("’", "'", trim($barcode));
        $pin     = trim($pin);

        if ($validatedViaSSO) {
            // todo: sso
            return null;
        }

        $patron_ils_id = $this->getPatronIlsId($barcode);

        // check cache for patron secret
        if (!$patron_ils_id || !$this->_getCachePatronSecret($patron_ils_id)) {
            [$patron_ils_id, $patron_secret] = $this->authenticatePatron($barcode, $pin, $validatedViaSSO);
        }

        $patron = $this->getPatron($patron_ils_id, $barcode);

        // check for password update
        return $patron;
    }

    /**
     * @param $barcode
     * @param $pin
     * @param $validatedViaSSO
     * @return array|null
     */
    protected function authenticatePatron($barcode, $pin, bool $validatedViaSSO = false): ?array
    {
        $request_url  = $this->ws_url . '/authenticator/patron';
        $request_body = json_encode(['Barcode' => $barcode, 'Password' => $pin]);

        $hash = $this->_createHash('POST', $request_url);

        $headers = [
            "PolarisDate: " . gmdate('r'),
            "Authorization: PWS " . $this->ws_access_id . ":" . $hash,
            "Accept: application/json",
            "Content-Type: application/json",
            "Content-Length: " . strlen($request_body)
        ];
        $c_opts  = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers
        ];

        $c = new Curl();
        $c->setOpts($c_opts);
        $c->post($request_url, $request_body);

        if ($c->error || $c->httpStatusCode !== 200) {
            $this->logger->error('Curl error: ' . $c->errorMessage, ['http_code' => $c->httpStatusCode]);
            return null;
        } elseif($error = $this->_isPapiError($c->response)) {
            $this->_logPapiError($error);
            return null;
        }
        //$this->patron_access_secret = $c->response->AccessSecret;
        $this->_setCachePatronSecret($c->response->PatronID, $c->response->AccessSecret);
        return ['patron_id' => $c->response->PatronID, 'patron_access_secret' => $c->response->AccessSecret];
    }

    /**
     * Check if user exists in database, create user if needed, update user if needed and return User object.
     *
     * @param $ils_id
     * @param $barcode
     * @return User|null
     */
    protected function getPatron($ils_id, $barcode): ?User
    {
        // get user from cache if cache object exists
        if($user = $this->_getCachePatronObject($ils_id)) {
            return $user;
        }

        $create_user = false;

        $user = new User();
        $user->ilsUserId = $ils_id;
        if (!$user->find(true) || $user->N === 0) {
            // if there's no patron in database
            $create_user = true;
        }

        if($barcode && $user->barcode !== $barcode) {
            $user->barcode = $barcode;
            if (!$create_user) { // don't update the user if the user doesn't already exist
                $user->update(); // update barcode immediately to avoid issues with ajax calls
            }
        }

        // get the basic user data from the Polaris API
        $request_url = $this->ws_url . '/patron/' . $barcode . '/basicdata?addresses=true&notes=true';
        $patron_access_secret = $this->_getCachePatronSecret($ils_id);
        $hash = $this->_createHash('GET', $request_url, $patron_access_secret);

        $headers = [
            "PolarisDate: " . gmdate('r'),
            "Authorization: PWS " . $this->ws_access_id . ":" . $hash,
            "Accept: application/json"
        ];
        $c_opts  = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers
        ];

        $c = new Curl();
        $c->setOpts($c_opts);
        $c->get($request_url);

        if ($c->error || $c->httpStatusCode !== 200) {
            $this->logger->error('Curl error: ' . $c->errorMessage, ['http_code' => $c->httpStatusCode]);
            return null;
        } elseif($error = $this->_isPapiError($c->response)) {
            $this->_logPapiError($error);
            return null;
        }

        $patron_response = $c->response->PatronBasicData;
        /***
         * Database checks and updates
         */
        // Names
        if($user->firstname !== $patron_response->NameFirst || $user->lastname !== $patron_response->NameLast) {
            $user->firstname = $patron_response->NameFirst;
            $user->lastname = $patron_response->NameLast;
            if(!$create_user) {
                $user->update();
            }
        }

        // Email
        if($user->email !== $patron_response->EmailAddress) {
            $user->email = $patron_response->EmailAddress;
            if(!$create_user) {
                $user->update();
            }
        }

        // Patron information
        if ($user->patronType !== $patron_response->PatronCodeID || $user->homeLibraryId !== $patron_response->PatronOrgID) {
            $user->patronType = $patron_response->PatronCodeID;
            $user->homeLibraryId = $patron_response->PatronOrgID;
            if(!$create_user) {
                $user->update();
            }
        }

        if($create_user) {
            $user->source = 'ils';
            $user->created = date("Y-m-d H:i:s");
            $user->insert();
        }

        /***
         * The following class variables aren't stored in database and need to be created on demand
         */

        // Names
        $user->fullname = $user->firstname . ' ' . $user->lastname;

        // Expiration
        // date can be returned in Microsoft format
        if($this->isMicrosoftDate($patron_response->ExpirationDate)) {
            try {
                $expiration_date = $this->microsoftDateToISO($patron_response->ExpirationDate);
                $user->expires = $expiration_date;
            } catch (Exception $e) {
                $this->logger->error($e->getMessage());
                $user->expires = null;
            }
        } else {
            $user->expires = $patron_response->ExpirationDate;
        }

        // Address
        $patron_address = $patron_response->PatronAddresses[0];
        $user->address1 = $patron_address->StreetOne;
        $user->address2 = $patron_address->StreetTwo;
        $user->city = $patron_address->City;
        $user->state = $patron_address->State;
        $user->zip = $patron_address->PostalCode;

        // Phone
        $user->phone = $patron_response->PhoneNumber;

        // Notices
        // Possible values in Polaris API
        //1 - Mail
        //2 - Email
        //3 - Phone 1
        //4 - Phone 2
        //5 - Phone 3
        //6 - Fax
        //8 - Text Message
        // todo: for now stuff these into a Pika accepted code- Mail, Telephone, E-mail
        switch ($patron_response->DeliveryOptionID) {
            case 1:
                $user->noticePreferenceLabel = 'Mail';
                break;
            case 2:
                $user->noticePreferenceLabel = 'E-mail';
                break;
            case 3:
            case 4:
            case 5:
            case 6:
                // no 7 according to the docs
            case 8:
                // Assuming any ID >= 3 means 'Phone'
                $user->noticePreferenceLabel = 'Phone';
                break;
            default:
                $user->noticePreferenceLabel = null;
                break;
        }

        // Hold counts
        // Polaris returns number of ILS AND number of ILL holds in counts.
        //$user->numHoldsIls = $patron_response->HoldRequestsCurrentCount;
        //$user->numHoldsAvailableIls = $patron_response->HoldRequestsHeldCount;


        // Notes
        $user->webNote = $patron_response->PatronNotes;

        $this->_setCachePatronObject($user);

        return $user;
    }

    protected function getPatronIlsId($barcode)
    {
        $patron = new User();
        $patron->barcode = $barcode;
        // if there's more than one patron with barcode don't return
        if($patron->find(true) && $patron->N === 1) {
            return $patron->ilsUserId;
        }
        return false;
    }

    /**
     * Add patrons secret to cache
     *
     * @param $patron_ils_id
     * @param $patron_secret
     * @return bool
     */
    protected function _setCachePatronSecret($patron_ils_id, $patron_secret): bool
    {
        $patron_secret_cache_key = 'patronilsid'.$patron_ils_id.'secret';
        $expires = new DateInterval('PT23H');
        return $this->cache->set($patron_secret_cache_key, $patron_secret, $expires);
    }

    /**
     * Get a patrons secret from cache
     *
     * @param $patron_ils_id
     * @return false|string
     */
    protected function _getCachePatronSecret($patron_ils_id)
    {
        $patron_secret_cache_key = 'patronilsid'.$patron_ils_id.'secret';
        $key = $this->cache->get($patron_secret_cache_key, false);
        if($key) {
            $this->logger->info('Patron secret found in cache.');
            return $key;
        } else {
            $this->logger->info('Patron secret not found in cache.');
            return false;
        }
    }

    /**
     * Save a user object to cache
     *
     * @param User $patron
     * @return bool
     */
    protected function _setCachePatronObject(User $patron)
    {
        $patron_object_cache_key = 'patronilsid'.$patron->ilsUserId.'object';
        $expires = 10 * 60;
        return $this->cache->set($patron_object_cache_key, $patron, $expires);
    }

    /**
     * Get a user object from cache
     *
     * @param $patron_ils_id
     * @return false|mixed
     */
    protected function _getCachePatronObject($patron_ils_id)
    {
        $patron_object_cache_key = 'patronilsid'.$patron_ils_id.'object';
        $patron = $this->cache->get($patron_object_cache_key, false);
        if($patron) {
            $this->logger->info('Patron object found in cache.');
            return $patron;
        } else {
            $this->logger->info('Patron object not found in cache.');
            return false;
        }
    }

    protected function _deleteCachePatronObjectByPatronId($patron_ils_id)
    {
        $patron_object_cache_key = 'patronilsid'.$patron_ils_id.'object';
        return $this->cache->delete($patron_object_cache_key);
    }

    /**
     * Check Polaris web service return for an api error
     *
     * @param  array|object $res Return from web service as array or object
     * @return array|false Returns array with two elements; ErrorMessage and PAPIErrorCode or false if no error.
     */
    protected function _isPapiError($res)
    {
        if(is_array($res)) {
            if ($res['PAPIErrorCode'] < 0) {
                return [
                    'ErrorMessage'  => $res['ErrorMessage'],
                    'PAPIErrorCode' => $res['PAPIErrorCode']
                ];
            }
        } elseif(is_object($res)) {
            if ($res->PAPIErrorCode < 0) {
                return [
                    'ErrorMessage'  => $res->ErrorMessage,
                    'PAPIErrorCode' => $res->PAPIErrorCode
                ];
            }
        }
        return false;
    }

    /**
     * Accepts return from _isPapiError and Writes the error to log
     *
     * @param array $e
     * @return void
     */
    protected function _logPapiError(array $e): void
    {
        if(is_array($e['ErrorMessage'])) {
            $error_message = implode("\n", $e['ErrorMessage']);
            $this->logger->error('Polaris API error: ' . $error_message, $e);
        } else {
            $this->logger->error('Polaris API error: ' . $e['ErrorMessage'], $e);
        }
    }

    /**
     * Converts a Microsoft format date to ISO 8601 format.
     *
     * The Microsoft format date is typically in the form of "Date(1835074800000-0700)",
     * where the number represents the number of milliseconds since the Unix epoch (January 1, 1970),
     * and the timezone offset indicates the offset from UTC.
     *
     * @param string $microsoftDate The Microsoft format date string.
     * @return string The date in ISO 8601 format.
     * @throws Exception If the provided date string is not in a valid Microsoft format.
     */
    protected function microsoftDateToISO($microsoftDate)
    {
        // Extract the timestamp and the timezone offset from the Microsoft date format
        if (preg_match('/^\/?Date\((\d+)([+-]\d{4})\)\/?$/', $microsoftDate, $matches)) {
            $timestamp = $matches[1] / 1000; // Convert milliseconds to seconds
            $timezoneOffset = $matches[2];

            // Create a DateTime object from the timestamp
            $dateTime = new DateTime("@$timestamp");

            // Set the timezone offset
            $hours = substr($timezoneOffset, 0, 3);
            $minutes = substr($timezoneOffset, 0, 1) . substr($timezoneOffset, 3, 2);
            $dateTime->setTimezone(new DateTimeZone("$hours:$minutes"));

            // Return the date in ISO 8601 format
            return $dateTime->format('c');
        } else {
            throw new Exception("Invalid Microsoft date format: $microsoftDate");
        }
    }

    protected function isMicrosoftDate($microsoftDate)
    {
        if (preg_match('/^\/?Date\((\d+)([+-]\d{4})\)\/?$/', $microsoftDate, $matches)) {
            return true;
        }
        return false;
    }
    /**
     * @inheritDoc
     */
    public function hasNativeReadingHistory()
    {
        // TODO: Implement hasNativeReadingHistory() method.
        return false;
    }

    /**
     * @inheritDoc
     */
    public function getNumHoldsOnRecord($id)
    {
        // TODO: Implement getNumHoldsOnRecord() method.
    }

    /**
     * @inheritDoc
     */
    public function getMyCheckouts($patron, $linkedAccount = false)
    {

        if(!$linkedAccount) {
            //		    if($patronCheckouts = $this->cache->get($patronCheckoutsCacheKey)) {
            //			    $this->logger->info("Found checkouts in memcache:".$patronCheckoutsCacheKey);
            //			    return $patronCheckouts;
            //		    }
        }
        $request_url = $this->ws_url . '/patron/' . $patron->barcode . '/itemsout/all?excludeecontent=false';
        if(!$patron_access_secret = $this->_getCachePatronSecret($patron->ilsUserId)) {
            $patron_pin = $patron->getPassword();
            $patron_access_secret = $this->authenticatePatron($patron->barcode, $patron_pin);
        }
        $hash = $this->_createHash('GET', $request_url, $patron_access_secret);

        $headers = [
            "PolarisDate: " . gmdate('r'),
            "Authorization: PWS " . $this->ws_access_id . ":" . $hash,
            "Accept: application/json"
        ];
        $c_opts  = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers
        ];

        $c = new Curl();
        $c->setOpts($c_opts);
        $c->get($request_url);

        if ($c->error || $c->httpStatusCode !== 200) {
            $this->logger->error('Curl error: ' . $c->errorMessage, ['http_code' => $c->httpStatusCode]);
            return null;
        } elseif($error = $this->_isPapiError($c->response)) {
            $this->_logPapiError($error);
            return null;
        }

        $checkouts_response = $c->response->PatronItemsOutGetRows;
        if(count($checkouts_response) === 0) {
            return [];
        }

        $offset = 0;
        $total  = 0;
        $count  = 0;
        $limit  = 100;

        $checkouts = [];
        foreach($checkouts_response as $c) {
            $bib_id = $c->BibID;
            $checkout['checkoutSource'] =  $this->accountProfile->recordSource;
            $checkout['recordId']       = $c->BibID;
            $checkout['id']             = $c->ItemID;
            $checkout['dueDate']        = strtotime($c->DueDate);
            $checkout['checkoutDate']   = strtotime($c->CheckOutDate);
            $checkout['renewCount']     = $c->RenewalCount;
            $checkout['barcode']        = $c->Barcode ?? '';
            $checkout['itemid']         = $c->ItemID;
            $checkout['renewIndicator'] = $c->ItemID;
            $checkout['renewMessage']   = '';
						$checkout['canrenew'] = $c->CanItemBeRenewed;
						
            $recordDriver = new MarcRecord($this->accountProfile->recordSource . ':' . $c->BibID);
            if ($recordDriver->isValid()) {
                $checkout['coverUrl']      = $recordDriver->getBookcoverUrl('medium');
                $checkout['groupedWorkId'] = $recordDriver->getGroupedWorkId();
                $checkout['ratingData']    = $recordDriver->getRatingData();
                $checkout['format']        = $recordDriver->getPrimaryFormat();
                $checkout['author']        = $recordDriver->getPrimaryAuthor();
                $checkout['title']         = $recordDriver->getTitle();
                $checkout['title_sort']    = $recordDriver->getSortableTitle();
                $checkout['link']          = $recordDriver->getLinkUrl();
            } else {
                $checkout['coverUrl']      = '';
                $checkout['groupedWorkId'] = '';
                $checkout['format']        = 'Unknown';
                $checkout['author']        = '';
            }

            $checkouts[] = $checkout;
        }
				return $checkouts;

    }

    /**
     * @inheritDoc
     */
    public function renewItem($patron, $recordId, $itemId, $itemIndex)
    {
        // TODO: Implement renewItem() method.
    }

    /**
     * @inheritDoc
     */
    public function getMyHolds($patron)
    {
        return [];
        // TODO: Implement getMyHolds() method.
    }

    /**
     * @inheritDoc
     */
    public function placeHold($patron, $recordId, $pickupBranch, $cancelDate = null)
    {
        // TODO: Implement placeHold() method.
    }

    /**
     * @inheritDoc
     */
    public function placeItemHold($patron, $recordId, $itemId, $pickupBranch)
    {
        // TODO: Implement placeItemHold() method.
    }

    /**
     * @inheritDoc
     */
    public function cancelHold($patron, $recordId, $cancelId)
    {
        // TODO: Implement cancelHold() method.
    }

    public function freezeHold($patron, $recordId, $itemToFreezeId, $dateToReactivate)
    {
        // TODO: Implement freezeHold() method.
    }

    public function thawHold($patron, $recordId, $itemToThawId)
    {
        // TODO: Implement thawHold() method.
    }

    public function changeHoldPickupLocation($patron, $recordId, $itemToUpdateId, $newPickupLocation)
    {
        // TODO: Implement changeHoldPickupLocation() method.
    }

    protected function _doRequest($method = 'GET', $url, $params = [], $extraHeaders = null)
    {
        $date = gmdate('r');
    }

    /**
     * Create a hash for API authentication.
     *
     * This function generates a hash using HMAC-SHA1 encryption and base64 encoding.
     * The hash is used for authenticating API requests. The date must be in GMT date/time format (RFC-1123).
     *
     * @param string $http_method The HTTP method (e.g., 'GET', 'POST', etc.).
     * @param string $uri The URI of the API endpoint.
     * @param string $patron_access_secret The password of the patron.
     * @return string The base64-encoded hash.
     */
    protected function _createHash(string $http_method, string $uri, $patron_access_secret = false): string
    {
        $date = gmdate('r');
        // Concatenate the input parameters into a single string
        $s = $http_method . $uri . $date;
        if ($patron_access_secret) {
            $s .= $patron_access_secret;
        }
        // Generate the HMAC-SHA1 hash using the client key from the configuration
        $hash = hash_hmac('sha1', $s, $this->configArray['Catalog']['clientKey'], true);
        // Encode the hash in base64 and return it
        return base64_encode($hash);
    }

    public function updatePatronInfo($patron, $canUpdateContactInfo)
    {
        // TODO: Implement updatePatronInfo() method.
    }
}
