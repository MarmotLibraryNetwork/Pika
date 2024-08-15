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
 * Date      7-10-2024
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
use Location;
use RecordDriverFactory;

//use Memcache;

class Polaris extends PatronDriverInterface implements \DriverInterface
{
    public \AccountProfile $accountProfile;
    public array $polaris_errors = [
        '-201' => 'Failed to insert entry in addresses table',
        '-221' => 'Failed to insert entry in PostalCodes table',
        '-222' => 'Invalid PostalCodeLength',
        '-223' => 'Invalid PostalCodeFormat',
        '-501' => 'Patron personal information change is not allowed',
        '-3000' => 'Patron does not exist',
        '-3001' => 'Failed to insert entry in Patrons table',
        '-3400' => 'Failed to insert entry in Patronaddresses table',
        '-3401' => 'Invalid AddressType',
        '-3500' => 'Country code does not exist',
        '-3501' => 'Patron branch is not defined',
        '-3502' => 'Patron branch is not a valid branch',
        '-3503' => 'Last name is not defined',
        '-3504' => 'First name is not defined',
        '-3505' => 'Barcode is already used for another patron',
        '-3506' => 'Transaction branch is not defined',
        '-3507' => 'Transaction user is not defined',
        '-3508' => 'Transaction workstation is not defined',
        '-3509' => 'Passwords do not match',
        '-3510' => 'Postal code problems - mismatch city, state, county',
        '-3511' => 'Postal code problems - mismatch city, state',
        '-3512' => 'Postal code problems - mismatch city, county',
        '-3513' => 'Postal code problems - mismatch state, county',
        '-3514' => 'Postal code problems - mismatch county',
        '-3515' => 'Postal code problems - mismatch state',
        '-3516' => 'Postal code problems - mismatch city',
        '-3517' => 'Postal code problems - postal code not found',
        '-3518' => 'Invalid Email address',
        '-3519' => 'Invalid DeliveryMethod Value (No Address for Patron)',
        '-3520' => 'Invalid DeliveryMethod Value (No Email Address for Patron)',
        '-3521' => 'Invalid DeliveryMethod Value (No PhoneVoice1 for Patron)',
        '-3522' => 'Invalid DeliveryMethod Value (No PhoneVoice2 for Patron)',
        '-3523' => 'Invalid DeliveryMethod Value (No PhoneVoice3 for Patron)',
        '-3524' => 'Invalid DeliveryMethod Value (No PhoneFax for Patron)',
        '-3525' => 'Invalid DeliveryMethod Value',
        '-3526' => 'Invalid EmailFormat Value',
        '-3527' => 'Invalid ReadingList Value',
        '-3528' => 'Duplicate name',
        '-3529' => 'Duplicate username',
        '-3530' => 'Failed to insert entry in Patron Registration table',
        '-3531' => 'Patron delivery notices address not defined',
        '-3532' => 'Invalid PhoneVoice1 value',
        '-3533' => 'Invalid Password format',
        '-3534' => 'Invalid Password length',
        '-3535' => 'Patron password change is not allowed',
        '-3536' => 'Invalid GenderID for the Registered Branch',
        '-3537' => 'Invalid LegalName Configuration',
        '-3540' => 'Invalid Birthdate',
        '-3541' => 'Invalid NameLast Length',
        '-3542' => 'Invalid NameFirst Length',
        '-3543' => 'Invalid NameMiddle Length',
        '-3544' => 'Invalid LegalNameLast Length',
        '-3545' => 'Invalid LegalNameFirst Length',
        '-3546' => 'Invalid LegalNameMiddle Length',
        '-3547' => 'Invalid Username Length',
        '-3548' => 'Invalid Barcode Length',
        '-3550' => 'Invalid Patron Barcode',
        '-3551' => 'Patron Address Not Defined',
        '-3552' => 'Patron Password Not Defined',
        '-3553' => 'Patron Address Street One Invalid',
        '-3554' => 'Patron Address Postal Code Invalid',
        '-3555' => 'Patron Address City Invalid',
        '-3556' => 'Patron Address State Invalid',
        '-3557' => 'Patron Username Format Invalid',
        '-3558' => 'Patron Address Country Not Defined',
        '-3559' => 'Patron Delivery Notices Address Not Defined',
        '-3560' => 'Patron Address Street Two Invalid',
        '-3561' => 'Patron Address Street Three Invalid',
        '-3562' => 'Patron Address Free Text Label Invalid',
        '-3600' => 'Charge transaction does not exist',
        '-3601' => 'Charge transaction for this patron does not exist',
        '-3602' => 'Payment method for payment is invalid',
        '-3603' => 'Invalid amount is being paid',
        '-3604' => 'Invalid transaction type being paid',
        '-3605' => 'General patron account database error',
        '-3606' => 'Payment transaction does not exist',
        '-3607' => 'Payment transaction for this patron does not exist',
        '-3608' => 'Payment transaction cannot be voided because another action taken on payment',
        '-3610' => 'Payment amount is more than the sum of the charges',
        '-3612' => 'Invalid PatronCodeID',
        '-3613' => 'Invalid PhoneVoice2',
        '-3614' => 'Invalid PhoneVoice3',
        '-3615' => 'Invalid Alt Email Address',
        '-3616' => 'Invalid TXTPhoneNumber',
        '-3617' => 'Invalid PhoneCarrier',
        '-3619' => 'Invalid DeliveryMethod No Phone',
        '-3620' => 'Invalid Email Address for EReceipt',
        '-3621' => 'Patron Is Secure',
        '-3622' => 'Invalid RequestPickupBranchID',
        '-3623' => 'Invalid User1',
        '-3624' => 'Invalid User2',
        '-3625' => 'Invalid User3',
        '-3626' => 'Invalid User4',
        '-3627' => 'Invalid User5',
        '-3628' => 'Invalid LanguageID',
        '-3629' => 'Invalid FormerID',
        '-3630' => 'Invalid StatisticalClassID for the Registered Branch',
        '-3634' => 'Patron Required Fields Missing',
        '-3635' => 'Invalid Patron Address Country',
        '-4000' => 'Invalid application ID supplied',
        '-4001' => 'Invalid patron ID supplied',
        '-4002' => 'Invalid workstation ID supplied',
        '-4003' => 'Invalid request ID supplied',
        '-4004' => 'Invalid requesting org ID supplied',
        '-4005' => 'Invalid patron barcode',
        '-4006' => 'Invalid bibliographic record ID supplied',
        '-4007' => 'Invalid pickup org ID supplied',
        '-4016' => 'Cannot change pickup branch for request in statusID',
        '-4019' => 'Hold Pickup Area SA disabled',
        '-4020' => 'Hold Pickup Area Invalid for pickup branch',
        '-4021' => 'Hold Pickup Area ID Invalid',
        '-4022' => 'Hold Pickup Area not enabled for the pickup branch',
        '-4023' => 'Hold Pickup Area already set',
        '-4100' => 'Invalid request GUID supplied',
        '-4101' => 'Invalid txn group qualifier supplied',
        '-4102' => 'Invalid txn qualifier supplied',
        '-4103' => 'Invalid answer supplied',
        '-4104' => 'Invalid state supplied',
        '-4201' => 'Invalid request ID supplied',
        '-4202' => 'Invalid current org ID supplied',
        '-4203' => 'Cancel prevented for hold requests with status of Held',
        '-4204' => 'Cancel prevented for hold request with status of Unclaimed',
        '-4205' => 'Cancel prevented for hold request with a status of Canceled',
        '-4206' => 'Cancel prevented for hold request with a status of Expired',
        '-4207' => 'Cancel prevented for hold request with a status of Out to Patron',
        '-4208' => 'Cancel prevented for hold request with a status of Shipped',
        '-4300' => 'No requests available to cancel',
        '-4400' => 'Invalid Application date supplied',
        '-4401' => 'Application date must be greater than or equal to today\'s date',
        '-4402' => 'Application date must be earlier than 2 years from today',
        '-4403' => 'Invalid pickup branch assigned to hold request',
        '-4404' => 'Error occurred loading SA "days to expire"',
        '-4405' => 'Request must have a status of Active, Inactive or Pending',
        '-4406' => 'No requests available to suspend',
        '-4407' => 'Request status invalid for this process',
        '-4408' => 'Invalid request status change requested',
        '-4409' => 'Invalid hold user not supplied reason',
        '-4410' => 'This is the only item available for hold',
        '-4411' => 'No other items at other branches are available to fill this hold',
        '-5000' => 'Invalid OrganizationID specified',
        '-6000' => 'Invalid loan unit supplied',
        '-6001' => 'ItemCheckout record does not exist',
        '-6101' => 'Patron block',
        '-6103' => 'Item record status is not Final',
        '-6104' => 'Item status is Returned-ILL',
        '-6110' => 'Item Status is Non-Circulating',
        '-6112' => 'Item block',
        '-6113' => 'Item status is In-Transit',
        '-6115' => 'Invalid item circulation period',
        '-6116' => 'Item on-the-fly',
        '-6117' => 'Multiple course reserves',
        '-6118' => 'Overdue fine',
        '-6119' => 'Renewal block',
        '-7000' => 'Invalid CourseReserveID specified',
        '-8000' => 'Invalid PolarisUserID specified',
        '-8001' => 'Polaris user is not permitted',
        '-8002' => 'StaffUser_NotSupplied',
        '-8003' => 'StaffUser_NotFound',
        '-8004' => 'StaffUser_Account_Disabled',
        '-9000' => 'Invalid WorkstationID specified',
    ];
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
    protected $papiLastErrorMessage;
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
        $this->ws_access_id = $configArray['Catalog']['clientSecret'];
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
        $pin = trim($pin);

        if ($validatedViaSSO) {
            // todo: sso
            return null;
        }

        // barcode might actually be username so well "validate" the patron first to make sure we have a good
        // barcode.
        $r = $this->validatePatron($barcode, $pin);
        $valid_barcode = $r->PatronBarcode;
        if ($valid_barcode === null) {
            return null;
        }

        $patron_ils_id = $this->getPatronIlsId($valid_barcode);
        //check cache for patron secret
        if (!$patron_ils_id || !$this->_getCachePatronSecret($patron_ils_id)) {
            $auth = $this->authenticatePatron($valid_barcode, $pin, $validatedViaSSO);
            if ($auth === null || !isset($auth->PatronID)) {
                return null;
            } else {
                $patron_ils_id = $auth->PatronID;
            }
        }

        $patron = $this->getPatron($patron_ils_id, $valid_barcode);

        // check for password update
        $patron_pw = $patron->getPassword();
        if (!isset($patron_pw) || $patron_pw !== $pin) {
            $patron->updatePassword($pin);
        }
        return $patron;
    }

    /**
     * Check if a patron exists in the Polaris database.
     *
     * This method will return a real barcode in case the patron is using a username. NOTE: When creating the header
     * auth has the users password must be used in the auth hash.
     *
     * @param $barcode
     * @param $pin
     * @return null|JSON
     * @see https://documentation.iii.com/polaris/PAPI/current/PAPIService/PAPIServicePatronValidate.htm#papiservicepatronvalidate_1221164799_1220680
     */
    protected function validatePatron($barcode, $pin)
    {
        $request_url = $this->ws_url . '/patron/' . $barcode;
        $hash = $this->_createHash('GET', $request_url, $pin);

        $headers = [
            "PolarisDate: " . gmdate('r'),
            "Authorization: PWS " . $this->ws_access_id . ":" . $hash,
            "Accept: application/json",
        ];

        $c_opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
        ];

        $c = new Curl();
        $c->setOpts($c_opts);
        $c->get($request_url);
        // todo: log header from curl object
        if ($c->error || $c->httpStatusCode !== 200) {
            $this->logger->error(
                'Curl error: ' . $c->errorMessage,
                ['http_code' => $c->httpStatusCode],
                ['RequestURL' => $request_url, 'RequestHeaders' => $headers],
            );
            return null;
        }

        if ($error = $this->_isPapiError($c->response)) {
            $this->_logPapiError($error);
            return null;
        }

        return $c->response;
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

    /**
     * Check Polaris web service return for an api error
     *
     * @param array|object $res Return from web service as array or object
     * @return array|false Returns array with two elements; ErrorMessage and PAPIErrorCode or false if no error.
     */
    protected function _isPapiError($res)
    {
        if (is_array($res)) {
            if ($res['PAPIErrorCode'] < 0) {
                if (empty($res['ErrorMessage']) && in_array(
                        (string)$res['PAPIErrorCode'],
                        $this->polaris_errors,
                        false,
                    )) {
                    $res['ErrorMessage'] = $this->polaris_errors[(string)$res['PAPIErrorCode']];
                }
                return [
                    'ErrorMessage' => $res['ErrorMessage'],
                    'PAPIErrorCode' => $res['PAPIErrorCode'],
                ];
            }
        } elseif (is_object($res)) {
            if ($res->PAPIErrorCode < 0) {
                if (empty($res->ErrorMessage) && in_array((string)$res->PAPIErrorCode, $this->polaris_errors, false)) {
                    $error_message = $this->polaris_errors[(string)$res->PAPIErrorCode];
                } else {
                    $error_message = $res->ErrorMessage;
                }
                return [
                    'ErrorMessage' => $error_message,
                    'PAPIErrorCode' => $res->PAPIErrorCode,
                ];
            }
        }
        return false;
    }

    /**
     * Accepts return from _isPapiError and writes the error to log
     *
     * @param array $e
     * @return void
     */
    protected function _logPapiError($e): void
    {
        if (is_array($e['ErrorMessage'])) {
            $error_message = implode("\n", $e['ErrorMessage']);
            $this->logger->error('Polaris API error: ' . $error_message, $e);
        } else {
            $this->logger->error('Polaris API error: ' . $e['ErrorMessage'], $e);
        }
    }

    /**
     * Get a patrons ILS patron id
     *
     * @param $barcode
     * @return int|false
     */
    protected function getPatronIlsId($barcode)
    {
        $patron = new User();
        $patron->barcode = $barcode;
        // if there's more than one patron with barcode don't return
        if ($patron->find(true) && $patron->N === 1) {
            return $patron->ilsUserId;
        }
        return false;
    }

    /**
     * Get a patrons secret from cache
     *
     * @param $patron_ils_id
     * @return false|string
     */
    protected function _getCachePatronSecret($patron_ils_id)
    {
        $patron_secret_cache_key = 'patronilsid' . $patron_ils_id . 'secret';
        $key = $this->cache->get($patron_secret_cache_key, false);
        if ($key) {
            $this->logger->info('Patron secret found in cache.');
            return $key;
        } else {
            $this->logger->info('Patron secret not found in cache.');
            return false;
        }
    }

    /**
     * Authenticate a patron and return the patron id and patron access secret key.
     *
     * @param string $barcode
     * @param string $pin
     * @param bool $validatedViaSSO
     * @return object|null
     */
    protected function authenticatePatron(string $barcode, string $pin, bool $validatedViaSSO = false): ?object
    {
        $request_url = $this->ws_url . '/authenticator/patron';
        $request_body = json_encode(['Barcode' => $barcode, 'Password' => $pin]);

        $hash = $this->_createHash('POST', $request_url);

        $headers = [
            "PolarisDate: " . gmdate('r'),
            "Authorization: PWS " . $this->ws_access_id . ":" . $hash,
            "Accept: application/json",
            "Content-Type: application/json",
            "Content-Length: " . strlen($request_body),
        ];
        $c_opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
        ];

        $c = new Curl();
        $c->setOpts($c_opts);
        $c->post($request_url, $request_body);

        if ($c->error || $c->httpStatusCode !== 200) {
            $this->logger->error(
                'Curl error: ' . $c->errorMessage,
                ['http_code' => $c->httpStatusCode],
                ['RequestURL' => $request_url, 'RequestHeaders' => $headers, 'RequestBody' => $request_body],
            );
            return null;
        } elseif ($error = $this->_isPapiError($c->response)) {
            $this->_logPapiError($error);
            return null;
        }

        $this->_setCachePatronSecret($c->response->PatronID, $c->response->AccessSecret);

        return $c->response;
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
        $patron_secret_cache_key = 'patronilsid' . $patron_ils_id . 'secret';
        $expires = 60 * 60 * 23;
        return $this->cache->set($patron_secret_cache_key, $patron_secret, $expires);
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
        if ($user = $this->_getCachePatronObject($ils_id)) {
            return $user;
        }

        $create_user = false;

        $user = new User();
        $user->ilsUserId = $ils_id;
        $user->source = 'ils';

        if (!$user->find(true) || $user->N === 0) {
            // if there's no patron in database
            $create_user = true;
        }

        if ($barcode && $user->barcode !== $barcode) {
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
            "Accept: application/json",
        ];
        $c_opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
        ];

        $c = new Curl();
        $c->setOpts($c_opts);
        $c->get($request_url);

        if ($c->error || $c->httpStatusCode !== 200) {
            $this->logger->error(
                'Curl error: ' . $c->errorMessage,
                ['http_code' => $c->httpStatusCode],
                ['RequestURL' => $request_url, "Headers" => $headers],
            );
            return null;
        } elseif ($error = $this->_isPapiError($c->response)) {
            $this->_logPapiError($error);
            return null;
        }

        $patron_response = $c->response->PatronBasicData;
        /***
         * Database checks and updates
         */
        // Names
        if ($user->firstname !== $patron_response->NameFirst || $user->lastname !== $patron_response->NameLast) {
            $user->firstname = $patron_response->NameFirst;
            $user->lastname = $patron_response->NameLast;
            if (!$create_user) {
                $user->update();
            }
        }

        // Email
        if ($user->email !== $patron_response->EmailAddress) {
            $user->email = $patron_response->EmailAddress;
            if (!$create_user) {
                $user->update();
            }
        }

        // Patron location
        // set location first then reference parentId to set library
        $location = new Location();
        $location->ilsLocationId = $patron_response->PatronOrgID;
        if ($location->find(true)) {
            if ($user->homeLocationId !== $location->locationId) {
                $user->homeLocationId = $location->locationId;
                if (!$create_user) {
                    $user->update();
                }
            }
        } else {
            $this->logger->error('Can not determine users home location. Defaulting to 1');
            $user->homeLocationId = 1;
            if (!$create_user) {
                $user->update();
            }
        }

        // Patron Library
        // todo: call to /REST/public/{Version}/{LangID}/{AppID}/{OrgID}/organizations/all (cache result for a looooong time)
        // todo: match OrganizationID to ilsLocationId
        // todo: get ParentOrganizationID from match
        // todo: use library->scope = ParentOrganizationID to find correct library.
        $user->homeLibraryId = 2;

        // Patron code
        if ($user->patronType !== $patron_response->PatronCodeID) {
            $user->patronType = $patron_response->PatronCodeID;
            if (!$create_user) {
                $user->update();
            }
        }

        if ($create_user) {
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
        if ($this->isMicrosoftDate($patron_response->ExpirationDate)) {
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

        // Checkouts and holds count
        // Polaris returns number of ILS AND number of ILL holds in counts.
        $user->numHoldsIls = $patron_response->HoldRequestsCurrentCount;
        $user->numHoldsAvailableIls = $patron_response->HoldRequestsHeldCount;
        $user->numHoldsRequestedIls = $patron_response->HoldRequestsTotalCount;
        $user->numCheckedOutIls = $patron_response->ItemsOutCount;

        // Fines
        $user->finesVal = $patron_response->ChargeBalance;

        // Notes
        // todo: do we need this?
        // $user->webNote = $patron_response->PatronNotes;

        $this->_setCachePatronObject($user);

        return $user;
    }

    /**
     * Get a user object from cache
     *
     * @param $patron_ils_id
     * @return false|mixed
     */
    protected function _getCachePatronObject($patron_ils_id)
    {
        $patron_object_cache_key = 'patronilsid' . $patron_ils_id . 'object';
        $patron = $this->cache->get($patron_object_cache_key, false);
        if ($patron) {
            $this->logger->info('Patron object found in cache.');
            return $patron;
        } else {
            $this->logger->info('Patron object not found in cache.');
            return false;
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
            $timestamp = (int)$timestamp; // cast to an int in case of decimel
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
            throw new \RuntimeException("Invalid Microsoft date format: $microsoftDate");
        }
    }

    /**
     * Save a user object to cache
     *
     * @param User $patron
     * @return bool
     */
    protected function _setCachePatronObject(User $patron): bool
    {
        $patron_object_cache_key = 'patronilsid' . $patron->ilsUserId . 'object';
        $expires = 30;
        return $this->cache->set($patron_object_cache_key, $patron, $expires);
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
    public function getMyCheckouts($patron, $linkedAccount = false): ?array
    {
        if (!$linkedAccount) {
            // todo: do something
        }

        $request_url = $this->ws_url . '/patron/' . $patron->barcode . '/itemsout/all?excludeecontent=false';
        $c = $this->_doPatronRequest($patron, 'GET', $request_url);

        $checkouts_response = $c->response->PatronItemsOutGetRows;
        if ($c === null || count($checkouts_response) === 0) {
            return [];
        }

        $checkouts = [];
        foreach ($checkouts_response as $c) {
            $checkout = []; // reset checkout
            // handle dates
            if ($this->isMicrosoftDate($c->DueDate)) {
                $due_date = strtotime($this->microsoftDateToISO($c->DueDate));
            } else {
                $due_date = strtotime($c->DueDate);
            }
            if ($this->isMicrosoftDate($c->CheckOutDate)) {
                $checkout_date = strtotime($this->microsoftDateToISO($c->CheckOutDate));
            } else {
                $checkout_date = strtotime($c->CheckOutDate);
            }
            $checkout['checkoutSource'] = $this->accountProfile->recordSource;
            $checkout['recordId'] = $c->BibID;
            $checkout['id'] = $c->ItemID;
            $checkout['dueDate'] = $due_date;
            $checkout['checkoutDate'] = $checkout_date;
            $checkout['renewCount'] = $c->RenewalCount;
            $checkout['barcode'] = $c->Barcode ?? '';
            $checkout['itemid'] = $c->ItemID;
            $checkout['renewIndicator'] = $c->ItemID;
            $checkout['renewMessage'] = '';
            $checkout['canrenew'] = $c->CanItemBeRenewed;

            $recordDriver = new MarcRecord($this->accountProfile->recordSource . ':' . $c->BibID);
            if ($recordDriver->isValid()) {
                $checkout['coverUrl'] = $recordDriver->getBookcoverUrl('medium');
                $checkout['groupedWorkId'] = $recordDriver->getGroupedWorkId();
                $checkout['ratingData'] = $recordDriver->getRatingData();
                $checkout['format'] = $recordDriver->getPrimaryFormat();
                $checkout['author'] = $recordDriver->getPrimaryAuthor();
                $checkout['title_sort'] = $recordDriver->getSortableTitle();
                $checkout['link'] = $recordDriver->getLinkUrl();
                if ($this->isIllCheckout($c)) {
                    $checkout['title'] = $this->cleanIllTitle($c->Title);
                } else {
                    $checkout['title'] = $recordDriver->getTitle();
                }
            } elseif ($this->isIllCheckout($c) && (!$recordDriver->isValid() || null === $recordDriver->isValid())) {
                // handle ILL checkouts
                // Polaris creates marc records in the system for ILL checkouts.
                // Only do special handling if marc record isn't available.
                //$checkout['coverUrl']      = ''; // todo: inn-reach cover
                $checkout['format'] = $this->getMaterialFormatFromId($c->FormatID);
                $checkout['author'] = $this->cleanIllAuthor($c->Author);
                $checkout['title'] = $this->cleanIllTitle($c->Title);
                $checkout['title_sort'] = $checkout['title'];
            } else {
                $checkout['coverUrl'] = '';
                $checkout['groupedWorkId'] = '';
                $checkout['format'] = 'Unknown';
                $checkout['author'] = '';
            }

            $checkouts[] = $checkout;
        }
        return $checkouts;
    }

    /**
     * Executes a system-level HTTP request with the specified method, URL, and request body.
     *
     * This method generates a hash for authorization and then delegates the actual request execution
     * to the `_doRequest` method. It supports various HTTP methods (GET, POST, PUT) and allows for
     * additional headers and request body content to be included in the request.
     *
     * @param string $method The HTTP method to use for the request. Defaults to 'GET'. Supported methods: 'GET', 'POST', 'PUT'.
     * @param string $url The URL to which the request is sent.
     * @param array|string $body The request body to send, which can be an array or string. For GET requests, this is typically empty.
     * @param array $extra_headers Additional headers to include in the request. These are merged with the default headers.
     *
     * @return ?Curl Returns a `Curl` object on successful request, or `null` if an error occurs.
     */
    protected function _doSystemRequest(string $method = 'GET', string $url, $body = [], $extra_headers = []): ?Curl
    {
        $hash = $this->_createHash($method, $url);
        $c = $this->_doRequest($hash, $method, $url, $body, $extra_headers);
        return $c;
    }

    /**
     * Executes a patron-specific HTTP request with the specified method, URL, and request body.
     *
     * This method handles requests that require patron-specific authentication. It retrieves
     * the patron's access secret, creates an authorization hash, and then delegates the request execution
     * to the `_doRequest` method. It supports various HTTP methods (GET, POST, PUT) and allows for
     * additional headers and request body content to be included in the request.
     *
     * @param User $patron The patron object representing the user making the request.
     * @param string $method The HTTP method to use for the request. Defaults to 'GET'. Supported methods: 'GET', 'POST', 'PUT'.
     * @param string $url The URL to which the request is sent.
     * @param array|string $body The request body to send, which can be an array or string. For GET requests, this is typically empty.
     * @param array $extra_headers Additional headers to include in the request. These are merged with the default headers.
     *
     * @return ?Curl Returns a `Curl` object on successful request, or `null` if an error occurs.
     */
    protected function _doPatronRequest(
        User $patron,
        string $method = 'GET',
        $url,
        $body = [],
        $extra_headers = []
    ): ?Curl {
        if (!$patron_access_secret = $this->_getCachePatronSecret($patron->ilsUserId)) {
            $patron_pin = $patron->getPassword();
            $auth = $this->authenticatePatron($patron->barcode, $patron_pin);
            if (!isset($auth)) {
                return null;
            }
            $patron_access_secret = $auth->AccessSecret;
        }

        $hash = $this->_createHash($method, $url, $patron_access_secret);
        $c = $this->_doRequest($hash, $method, $url, $body, $extra_headers);
        return $c;
    }

    /**
     * Executes an HTTP request using the provided method, URL, and request body.
     *
     * This method performs an HTTP request (GET, POST, or PUT) with the specified URL, method, and body.
     * It constructs the necessary headers, including authorization and content type, and handles errors
     * that may arise during the request. The method returns a `Curl` object on success, or `null` if an
     * error occurs.
     *
     * @param string $hash A unique hash string used for authorization in the request header.
     * @param string $method The HTTP method to use for the request. Defaults to 'GET'. Supported methods: 'GET', 'POST', 'PUT'.
     * @param string $url The URL to which the request is sent.
     * @param array|string $body The request body to send, which can be an array or string. For GET requests, this is typically empty.
     * @param array $extra_headers Additional headers to include in the request. These are merged with the default headers.
     *
     * @return ?Curl Returns a `Curl` object on successful request, or `null` if an error occurs.
     */
    protected function _doRequest(
        string $hash,
        string $method = 'GET',
        string $url,
        $body = [],
        $extra_headers = []
    ): ?Curl {
        $this->papiLastErrorMessage = null;
        $headers = [
            "PolarisDate: " . gmdate('r'),
            "Authorization: PWS " . $this->ws_access_id . ":" . $hash,
            "Accept: application/json",
        ];

        foreach ($extra_headers as $header) {
            $headers[] = $header;
        }

        $c_opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
        ];

        $c = new Curl();
        $c->setOpts($c_opts);
        // todo: check headers for content length and content type
        switch ($method) {
            case 'GET':
                $c->get($url);
                break;
            case 'POST':
                $c->post($url, $body);
                break;
            case 'PUT':
                // PUT will change the content-type header in the array passed to setOpts. Need to set it separately
                // and use a custom request. this happens in the PHP curl extension itself and not in php-curl-class.

                // API server will want content length even if no body content is sent... maybe?
                if (!empty($body) && is_array($body)) {
                    $body = json_encode($body);
                    $c->setOpt(CURLOPT_POSTFIELDS, $body);
                    $headers[] = 'Content-Length: ' . strlen($body);
                } elseif (is_string($body)) {
                    $headers[] = 'Content-Length: ' . strlen($body);
                    $c->setOpt(CURLOPT_POSTFIELDS, $body);
                } else {
                    $headers[] = 'Content-Length: 0';
                }
                $c->setUrl($url);
                $c->setOpt(CURLOPT_CUSTOMREQUEST, 'PUT');
                // this needs to be set LAST!
                $c->setOpt(CURLOPT_HTTPHEADER, $headers);
                $c->exec();
                break;
        }
        // todo: request headers need to be an array or string to be logged.
        if ($c->error || $c->httpStatusCode !== 200) {
            $this->logger->error('Curl error: ' . $c->errorMessage, [
                'http_code' => $c->httpStatusCode,
                'request_url' => $url,
                'Headers' => implode(PHP_EOL, $c->requestHeaders),
            ]);
            return null;
        } elseif ($error = $this->_isPapiError($c->response)) {
            $this->_logPapiError($error);
            $this->papiLastErrorMessage = $c->response->ErrorMessage;
            return null;
        }

        return $c;
    }

    /**
     * @param $item Checkout item
     * @return bool
     */
    protected function isIllCheckout($item): bool
    {
        return $item->FormatDescription === "Interlibrary Loan";
    }

    /**
     * Clean an InReach title
     *
     * @param string $title
     * @return string
     */
    protected function cleanIllTitle(string $title): string
    {
        if (preg_match('/ILL-(.*?)([:\/])/', $title, $matches)) {
            return trim($matches[1]);
        } else {
            // If neither ":" nor "/" is found, return the entire string after "ILL-"
            if (preg_match('/ILL-(.*)/', $title, $matches)) {
                return trim($matches[1]);
            }
        }
        return $title; // Return full title if ILL- isn't found
    }

    /**
     * Get the format from Polaris format ID
     *
     * @param int $material_format_id
     * @return string
     * @see https://documentation.iii.com/polaris/PAPI/current/PAPIService/PAPIServiceOverview.htm#papiserviceoverview_3170935956_1214294
     */
    protected function getMaterialFormatFromId(int $material_format_id): string
    {
        $media_types = [
            1 => "Book",
            2 => "Printed or Manuscript Music",
            3 => "Cartographic Material",
            4 => "Visual Materials",
            5 => "Sound Recording",
            6 => "Electronic Resources",
            7 => "Archival Mixed Materials",
            8 => "Serial",
            9 => "Printed Music",
            10 => "Manuscript Music",
            11 => "Printed Cartographic Material",
            12 => "Manuscript Cartographic Material",
            13 => "Map",
            14 => "Globe",
            15 => "Manuscript Material",
            16 => "Projected Medium",
            17 => "Motion Picture",
            18 => "Video Recording",
            19 => "Two Dimensional Non-projected Graphic",
            20 => "Three Dimensional Object",
            21 => "Musical Sound Recording",
            22 => "Nonmusical Sound Recording",
            23 => "Kit",
            24 => "Periodical",
            25 => "Newspaper",
            26 => "Microform",
            27 => "Large Print",
            28 => "Braille",
            29 => "DVD",
            30 => "Videotape",
            31 => "Music CD",
            32 => "eBook",
            33 => "Audio Book",
            38 => "Digital Collection",
            39 => "Abstract",
            40 => "Blu-ray Disc",
            41 => "Eaudiobook",
            42 => "Book + CD",
            43 => "Book + Cassette",
            44 => "Video Game",
            45 => "Blu-ray + DVD",
            46 => "Book + DVD",
            47 => "Atlas",
            48 => "Streaming Music",
            49 => "Streaming Video",
            50 => "Emagazine",
            51 => "Vinyl",
            52 => "Audio Book on CD",
            53 => "Audio Book on Cassette",
        ];

        if (array_key_exists($material_format_id, $media_types)) {
            return $media_types[$material_format_id];
        }
        return 'Unknown';
    }

    /**
     * Clean an InReach author
     *
     * @param $author
     * @return string If error, original string is returned. Cleaned string otherwise.
     */
    protected function cleanIllAuthor($author): string
    {
        // Use a regular expression to remove ", author" with optional spaces after the comma and optional
        // ending period
        $cleaned_author = preg_replace("/,\s*author\.?$/", "", $author);
        return $cleaned_author ?? $author;
    }

    /**
     * @inheritDoc
     *
     */
    public function renewItem($patron, $recordId, $itemId, $itemIndex)
    {
        // /public/patron/{PatronBarcode}/itemsout/{ID}
        $return = ['success' => false, 'message' => "Unable to renew your checkout."];
        $request_url = $this->ws_url . '/patron/' . $patron->barcode . '/itemsout/' . $itemId;
        $request_body = json_encode([
            "Action" => "renew",
            "LogonBranchID" => 1,
            "LogonUserID" => (int)$this->configArray['Polaris']['staffUserId'],
            "LogonWorkstationID" => (int)$this->configArray['Polaris']['workstationId'],
            "RenewData" => [
                "IgnoreOverrideErrors" => true,
            ],
        ]);

        $extra_headers = ['Content-type: application/json'];
        $c = $this->_doPatronRequest($patron, 'PUT', $request_url, $request_body, $extra_headers);
        if ($c === null) {
            return $return;
        }
        return ['success' => true, 'message' => "Your checkout has been renewed."];
    }

    /**
     * @inheritDoc
     */
    public function getMyHolds($patron)
    {
        $availableHolds = [];
        $unavailableHolds = [];
        // ILS HOLDS //////////////////////////////////////
        /**
         * @var $request_url
         * @see: https://documentation.iii.com/polaris/PAPI/current/PAPIService/PAPIServicePatronHoldRequestsGet.htm#papiservicepatronholdrequestsget_314867296_1219168
         */
        $request_url = $this->ws_url . '/patron/' . $patron->barcode . '/holdrequests/all';
        $c = $this->_doPatronRequest($patron, 'GET', $request_url);

        if ($c === null || count($c->response->PatronHoldRequestsGetRows) === 0) {
            return ['available' => $availableHolds, 'unavailable' => $unavailableHolds];
        }
        // translations
        $frozen = translate('frozen');

        foreach ($c->response->PatronHoldRequestsGetRows as $hold) {
            $pickup_branch_id = $this->polarisBranchIdToLocationId($hold->PickupBranchID);
            $h = [];
            $h['holdSource'] = $this->accountProfile->recordSource;
            $h['userId'] = $patron->id;
            $h['user'] = $patron->displayName;
            $h['cancelId'] = $hold->HoldRequestID;
            $h['cancelable'] = true;
            $h['freezeable'] = $hold->CanSuspend === true;
            $h['status'] = $hold->StatusDescription;
            $h['frozen'] = false; // status will be inactive if frozen
            $h['location'] = $hold->PickupBranchName;
            $h['locationUpdateable'] = true;
            $h['position'] = $hold->QueuePosition . ' of ' . $hold->QueueTotal;
            $h['currentPickupName'] = $hold->PickupBranchName;
            $h['currentPickupId'] = $pickup_branch_id;
            $h['automaticCancellation'] = isset($hold->notNeededAfterDate) ? strtotime(
                $hold->notNeededAfterDate,
            ) : null;

            $h['create'] = '';
            if ($this->isMicrosoftDate($hold->ActivationDate)) {
                $create = $this->microsoftDateToISO($hold->ActivationDate);
                $h['create'] = strtotime($create);
            } else {
                $h['create'] = strtotime($hold->ActivationDate);
            }

            $h['expire'] = '';
            if ($this->isMicrosoftDate($hold->ExpirationDate)) {
                $expire = $this->microsoftDateToISO($hold->ExpirationDate);
                $h['expire'] = strtotime($expire);
            } else {
                $h['expire'] = strtotime($hold->ExpirationDate);
            }

            // load marc record
            $recordSourceAndId = new \SourceAndId($this->accountProfile->recordSource . ':' . $hold->BibID);
            $record = RecordDriverFactory::initRecordDriverById($recordSourceAndId);
            if ($record->isValid()) {
                $h['id'] = $record->getUniqueID();
                $h['shortId'] = $record->getShortId();
                $h['title'] = $record->getTitle();
                $h['sortTitle'] = $record->getSortableTitle();
                $h['author'] = $record->getPrimaryAuthor();
                $h['format'] = $record->getFormat();
                $h['link'] = $record->getRecordUrl();
                $h['coverUrl'] = $record->getBookcoverUrl('medium');
            } else {
                // todo: fall back to API
                $h['title'] = '';
                $h['sortTitle'] = '';
                $h['author'] = '';
                $h['format'] = '';
            }
            // special handling by status id
            switch ($hold->StatusID) {
                case 1: // inactive/frozen
                    $h['freezeable'] = false;
                    $h['frozen'] = true;
                    $h['status'] = $frozen;
                    break;
                case 5: // shipped
                    $h['cancelable'] = false;
                    $h['locationUpdateable'] = true;
                    break;
                case 6: // ready for pickup
                    $h['cancelable'] = false; // holds ready for pickup can't be canceled in Polaris
                    $h['locationUpdateable'] = false;
                    break;
                case 7: // Not supplied (unknown?)
                case 16: // canceled
                    $h['cancelable'] = false; // holds ready for pickup can't be canceled in Polaris
                    $h['locationUpdateable'] = false;
                    $h['freezeable'] = false;
                    break;
            }
            if ($hold->StatusID === 6) { // ready for pickup
                $availableHolds[] = $h;
            } elseif ($hold->StatusID !== 16) {
                $unavailableHolds[] = $h;
            }
        } // end foreach

        // ILL HOLDS //////////////////////////////////////
        $request_url = $this->ws_url . '/patron/' . $patron->barcode . '/illrequests/all';
        $c = $this->_doPatronRequest($patron, 'GET', $request_url);

        if ($c === null || count($c->response->PatronILLRequestsGetRows) === 0) {
            return ['available' => $availableHolds, 'unavailable' => $unavailableHolds];
        }

        foreach ($c->response->PatronILLRequestsGetRows as $hold) {
            $pickup_branch_id = $this->polarisBranchIdToLocationId($hold->PickupBranchID);

            $h = [];
            //$h['freezeable']         = Not sure if ILL holds can be frozen, likely not
            //$h['position']           = API doesn't provide this information for ILL holds
            $h['holdSource'] = $this->accountProfile->recordSource;
            $h['userId'] = $patron->id;
            $h['user'] = $patron->displayName;
            $h['cancelId'] = $hold->ILLRequestID;
            $h['cancelable'] = true; // todo: can ill holds be canceled?
            $h['status'] = $hold->Status;
            $h['frozen'] = false;
            $h['location'] = $hold->PickupBranch;
            $h['locationUpdateable'] = true;
            $h['currentPickupName'] = $hold->PickupBranch;
            $h['currentPickupId'] = $pickup_branch_id; // todo: pickup branch id

            $h['create'] = '';
            if ($this->isMicrosoftDate($hold->ActivationDate)) {
                $create = $this->microsoftDateToISO($hold->ActivationDate);
                $h['create'] = strtotime($create);
            } else {
                $h['create'] = strtotime($hold->ActivationDate);
            }
            // $h['expire'] = ''; // ILL request doesn't include expires date

            // load marc record
            $recordSourceAndId = new \SourceAndId($this->accountProfile->recordSource . ':' . $hold->BibRecordID);
            $record = RecordDriverFactory::initRecordDriverById($recordSourceAndId);
            if ($record->isValid()) {
                $h['id'] = $record->getUniqueID();
                $h['shortId'] = $record->getShortId();
                $h['title'] = $this->cleanIllTitle($record->getTitle());
                $h['sortTitle'] = $record->getSortableTitle();
                $h['author'] = $record->getPrimaryAuthor();
                $h['format'] = $record->getFormat();
                $h['link'] = $record->getRecordUrl();
                $h['coverUrl'] = $record->getBookcoverUrl('medium'); // todo: Prospector cover?
            } else {
                $title = $this->cleanIllTitle($hold->Title);
                $author = $this->cleanIllAuthor($hold->Author);
                $cover_url = $this->getIllCover() ?? '';
                $h['title'] = $title;
                $h['sortTitle'] = $title;
                $h['author'] = $author;
                $h['format'] = $hold->Format;
                $h['coverUrl'] = $cover_url;
            }

            if ($hold->ILLStatusID === 10) {
                $availableHolds[] = $h;
            } else {
                $unavailableHolds[] = $h;
            }
        } // end foreach

        return ['available' => $availableHolds, 'unavailable' => $unavailableHolds];
    }

    protected function polarisBranchIdToLocationId($branch_id)
    {
        $location = new Location();
        $location->ilsLocationId = $branch_id;
        if ($location->find(true) && $location->N === 1) {
            return $location->locationId;
        }
        return null;
    }

    public function getIllCover()
    {
        global $library;
        $coverUrl = null;
        // grab the theme for Inn reach cover
        // start with the base theme and work up to local theme checking for image
        if (!empty($library)) {
            $themeParts = explode(',', $library->themeName);
        } else {
            $themeParts = explode(',', $this->configArray['Site']['theme']);
        }
        $themeParts = array_reverse($themeParts);
        $path = $this->configArray['Site']['local'];
        foreach ($themeParts as $themePart) {
            $themePart = trim($themePart);
            $imagePath = $path . '/interface/themes/' . $themePart . '/images/InnReachCover.png';
            if (file_exists($imagePath)) {
                $coverUrl = '/interface/themes/' . $themePart . '/images/InnReachCover.png';
            }
        }
        return $coverUrl;
    }

    /**
     *
     * @inheritDoc
     */
    public function placeHold($patron, $recordId, $pickupBranch, $cancelDate = null)
    {
        // get title to use in return
        $record = RecordDriverFactory::initRecordDriverById($this->accountProfile->recordSource . ':' . $recordId);

        if ($record->isValid()) {
            $volumes = $record->getVolumeInfoForRecord();
        }
        // lookup the pickup location Polaris branch id
        $location = new Location();
        $location->code = $pickupBranch;
        if (!$location->find(true) || $location->N = 0) {
            $this->logger->error('ERROR: Bad location code: ' . $pickupBranch);
            return [
                'success' => false,
                'message' => 'An error occurred while processing your request. Please try again later or contact your library.',
            ];
        }

        $polaris_pu_branch_id = $location->ilsLocationId;
        $requesting_location = $this->locationIdToPolarisBranchId(
            $patron->homeLocationId,
        ); // todo: should be the local interface
        $patron_id = $patron->ilsUserId;

        $request_url = $this->ws_url . '/holdrequest';

        $request_body = [
            "PatronID" => (int)$patron_id,
            "BibID" => (int)$recordId,
            "PickupOrgID" => (int)$polaris_pu_branch_id,
            "ActivationDate" => gmdate('r'),
            "WorkstationID" => (int)$this->configArray['Polaris']['workstationId'],
            "UserID" => (int)$this->configArray['Polaris']['staffUserId'],
            "RequestingOrgID" => (int)$requesting_location,
        ];
        $body_json = json_encode($request_body);
        $body_length = strlen($body_json);
        $extra_headers = [
            "Content-Type: application/json",
            "Content-Length: " . $body_length,
        ];
        // initial hold request
        $c = $this->_doSystemRequest('POST', $request_url, $body_json, $extra_headers);

        // errors
        if ($c === null) { // api or curl error
            if (isset($this->papiLastErrorMessage)) {
                return [
                    'success' => false,
                    'message' => "Your hold could not be placed. {$this->papiLastErrorMessage}",
                ];
            }
            return [
                'success' => false,
                'message' => 'An error occurred while processing your request. Please try again later or contact your library.',
            ];
        }
        $r = $c->response;
        // age conflict
        if ($r->StatusValue === 10) {
            // todo: patron not old enough message
        }
        // get title to use in return
        $record = RecordDriverFactory::initRecordDriverById($this->accountProfile->recordSource . ':' . $recordId);
        $title = false;
        if ($record->isValid()) {
            $title = trim($record->getTitle());
        }
        // success // all done // there will likely be a message that needs a response.
        if ($r->StatusValue === 1) {
            $return = ['success' => true, 'message' => 'Your hold was successfully placed.'];
            if ($title) {
                $return['message'] = "Your hold for <strong>{$title}</strong> was successfully placed.";
            }
            return $return;
        }

        // continue to reply yes until hold is placed or we get an error
        $status_type = $r->StatusType;
        do {
            $c = $this->_placeHoldRequestReply($r, (int)$requesting_location);
            $r = $c->response;
            $status_type = $r->StatusType;
        } while ($status_type === 3);

        if ($r->StatusValue === 1) {
            $return = ['success' => true, 'message' => 'Your hold was successfully placed.'];
            if ($title) {
                $return['message'] = "Your hold for <strong>{$title}</strong> was successfully placed.";
            }
            return $return;
        }

        return [
            'success' => false,
            'message' => 'An error occurred while processing your request. Please try again later or contact your library.',
        ];
    }

    protected function locationIdToPolarisBranchId($location_id)
    {
        $location = new Location();
        $location->locationId = $location_id;

        if ($location->find(true) && $location->N === 1 && isset($location->ilsLocationId)) {
            return $location->ilsLocationId;
        }
        return null;
    }
    
    protected function _placeHoldRequestReply($hold_response, $requesting_org_id): ?Curl
    {
        $status_to_state = [
            2 => 1,
            4 => 2,
            5 => 3,
            6 => 4,
            7 => 5,
        ];
        $request_url = $this->ws_url . '/holdrequest/' . $hold_response->RequestGUID;

        $request_body = [
            "TxnGroupQualifier" => $hold_response->TxnGroupQualifer,
            "TxnQualifier" => $hold_response->TxnQualifier,
            "RequestingOrgID" => $requesting_org_id,
            "Answer" => 1, // always answer yes (1)
            "State" => $status_to_state[$hold_response->StatusValue],
        ];
        $request_body = json_encode($request_body);
        $extra_headers = ['Content-Type: application/json'];
        return $this->_doSystemRequest('PUT', $request_url, $request_body, $extra_headers);
    }

    /**
     * @inheritDoc
     */
    public function placeItemHold($patron, $recordId, $itemId, $pickupBranch)
    {
        $recordId = trim($recordId);
    }

    /**
     * @inheritDoc
     *
     */
    public function cancelHold($patron, $recordId, $cancelId)
    {
        // /public/patron/{PatronBarcode}/holdrequests/{RequestID}/cancelled
        $barcode = $patron->barcode;
        $workstation_id = $this->configArray['Polaris']['workstationId'];
        $staff_user_id = $this->configArray['Polaris']['staffUserId'];
        $request_url = $this->ws_url . "/patron/{$barcode}/holdrequests/{$cancelId}/cancelled?wsid={$workstation_id}" .
            "&userid={$staff_user_id}";

        $r = $this->_doPatronRequest($patron, 'PUT', $request_url);

        $return = ['success' => false, 'message' => 'Unable to cancel your hold.'];
        if ($r === null) {
            if (isset($this->papiLastErrorMessage)) {
                $return['message'] .= ' ' . $this->papiLastErrorMessage;
            } else {
                $return['message'] .= " Please contact your library for further assistance.";
            }
            return $return;
        }

        return ['success' => true, 'message' => 'Your hold has been canceled.'];
    }

    /**
     * Freezes a patron's hold request, setting it to become inactive until a specified reactivation date.
     *
     * This method sends a request to the library system's API to freeze a hold request for a patron.
     * It accepts a reactivation date. The method uses `_doPatronRequest` to execute the API call and returns a success
     * message if the hold is successfully frozen, or an error message if the request fails.
     *
     * @param User $patron The patron object representing the user whose hold is being frozen.
     * @param int|string $recordId The ID of the record associated with the hold (not directly used in the request but kept for reference).
     * @param int|string $itemToFreezeId The ID of the specific hold request to be frozen.
     * @param string $dateToReactivate The date when the hold should be reactivated, in a format parsable by DateTime.
     *
     * @return array An array containing the success status (`true` or `false`) and a message indicating the result of the operation.
     */
    public function freezeHold($patron, $recordId, $itemToFreezeId, $dateToReactivate): array
    {
        // /public/1/patron/{PatronBarcode}/holdrequests/{RequestID}/inactive
        try {
            $date = new DateTime($dateToReactivate, new DateTimeZone('UTC'));
            $active_date = $date->format('r');
        } catch (\Exception $e) {
            $active_date = $dateToReactivate;
        }
        // translations
        $frozen = translate('frozen');
        $freeze = translate('freeze');

        $barcode = $patron->barcode;
        $staff_user_id = $this->configArray['Polaris']['staffUserId'];
        $request_url = $this->ws_url . "/patron/{$barcode}/holdrequests/{$itemToFreezeId}/inactive";
        $request_body = ["UserID" => (int)$staff_user_id, "ActivationDate" => $active_date];
        $extra_headers = ['Content-Type: application/json'];

        $return = ['success' => false, 'message' => "Unable to {$freeze} your hold."];

        $r = $this->_doPatronRequest($patron, 'PUT', $request_url, $request_body, $extra_headers);
        if ($r === null) {
            if (isset($this->papiLastErrorMessage)) {
                $return['message'] .= ' ' . $this->papiLastErrorMessage;
            }
            return $return;
        }
        return ['success' => true, 'message' => "Your hold has been {$frozen}."];
    }

    /**
     * Thaws a patron's hold request (making it active again).
     *
     * This method reactivates a previously frozen hold request for a patron.
     * It constructs the necessary request URL and body, and uses the `_doPatronRequest`
     * method to perform the API call. The method returns a success message if the hold is successfully thawed,
     * or an error message if the request fails.
     *
     * @param User $patron The patron object representing the user whose hold is being thawed.
     * @param int|string $recordId The ID of the record associated with the hold (not directly used in the request but kept for reference).
     * @param int|string $itemToThawId The ID of the specific hold request to be thawed.
     *
     * @return array An array containing the success status (`true` or `false`) and a message indicating the result of the operation.
     */
    public function thawHold($patron, $recordId, $itemToThawId): array
    {
        // /public/1/patron/{PatronBarcode}/holdrequests/{RequestID}/active
        // translations
        $thawed = translate('thawed');
        $thaw = translate('thaw');

        $barcode = $patron->barcode;
        $staff_user_id = $this->configArray['Polaris']['staffUserId'];
        $request_url = $this->ws_url . "/patron/{$barcode}/holdrequests/{$itemToThawId}/active";
        $request_body = ["UserID" => (int)$staff_user_id, "ActivationDate" => gmdate('r')];
        $extra_headers = ["Content-Type: application/json"];


        $return = ['success' => false, 'message' => "Unable to {$thaw} your hold."];

        $r = $this->_doPatronRequest($patron, 'PUT', $request_url, $request_body, $extra_headers);
        if ($r === null) {
            if (isset($this->papiLastErrorMessage)) {
                $return['message'] .= ' ' . $this->papiLastErrorMessage;
            }
            return $return;
        }
        return ['success' => true, 'message' => "Your hold has been {$thawed}."];
    }

    /**
     * Note: it seems the hold id comes across in the variable $itemToUpdateId
     * Note: not in the documentation but holdPickupAreaID IS required. Set to 0 (zero)
     * @param $patron
     * @param $recordId
     * @param $itemToUpdateId
     * @param $newPickupLocation
     * @return array
     */
    public function changeHoldPickupLocation($patron, $recordId, $itemToUpdateId, $newPickupLocation)
    {
        // /public/patron/{PatronBarcode}/holdrequests/{RequestID}/pickupbranch?userid={user_id}&wsid={workstation_id}
        // &pickupbranchid={pickupbranch_id}&holdPickupAreaID=0
        // todo: if we ever have a Polaris library with pickup areas we'll need to rework this.
        $staff_user_id = $this->configArray['Polaris']['staffUserId'];
        $workstation_id = $this->configArray['Polaris']['workstationId'];
        $pickup_branch_id = $this->locationCodeToPolarisBranchId($newPickupLocation);

        $request_url = $this->ws_url . "/patron/{$patron->barcode}/holdrequests/{$itemToUpdateId}/pickupbranch?userid=" .
            "{$staff_user_id}&wsid={$workstation_id}&pickupbranchid={$pickup_branch_id}&holdPickupAreaID=0";

        $return = ['success' => false, 'message' => "Unable to change pickup location."];
        $c = $this->_doPatronRequest($patron, 'PUT', $request_url);
        if ($c === null) {
            if (isset($this->papiLastErrorMessage)) {
                $return['message'] .= ' ' . $this->papiLastErrorMessage;
            } else {
                $return['message'] .= " Please contact your library for further assistance.";
            }
            return $return;
        }
        return ['success' => true, 'message' => "The pickup location has been updated."];
    }

    protected function locationCodeToPolarisBranchId($branch_name)
    {
        $location = new Location();
        $location->code = $branch_name;
        if ($location->find(true) && $location->N === 1) {
            return $location->ilsLocationId;
        }
        return null;
    }

    public function updatePatronInfo($patron, $canUpdateContactInfo)
    {
        // TODO: Implement updatePatronInfo() method.
    }

    /**
     * Remove a patron object from cache
     *
     * @param $patron_ils_id
     * @return bool
     */
    protected function _deleteCachePatronObject($patron_ils_id)
    {
        $patron_object_cache_key = 'patronilsid' . $patron_ils_id . 'object';
        return $this->cache->delete($patron_object_cache_key);
    }


} // end class Polaris
