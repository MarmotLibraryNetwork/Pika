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
 * Implementing the driver for new libraries
 * * A [Carriers] section will be needed in the config files.
 * * Override the class variable ereceipt_options to fit the libraries needs in an extending class
 *
 *
 * @category Pika
 * @package  PatronDrivers
 * @author   Chris Froese
 * Date      7-10-2024
 *
 */

namespace Pika\PatronDrivers;

use AccountProfile;
use Curl\Curl;
use DateTime;
use DateTimeZone;
use DriverInterface;
use JsonException;
use Location;
use MarcRecord;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use Pika\Cache;
use Pika\Logger;
use PinReset;
use RecordDriverFactory;
use RuntimeException;
use SourceAndId;
use User;

class Polaris extends PatronDriverInterface implements DriverInterface
{
    public AccountProfile $accountProfile;

    /**
     * $notification_options How the patron will receive notifications. This value will likely be overriden in an extending class.
     *
     * @var array
     */
    public array $notification_options = [
        0 => "None",
        1 => "Mail",
        2 => "Email",
        3 => "Phone 1",
        4 => "Phone 2",
        5 => "Phone 3",
        6 => "Fax",
        8 => "Text Message",
    ];

    /**
     * $ereceipt_options This value will likely be overriden in an extending class
     * @var array
     */
    public array $ereceipt_options = [
        0 => "None",
        // the documentation differs between create and update patron methods on what the options for ereceipts.
        // 1 => "Mail", The documentation doesn't show this as an option
        2 => "Email",
        3 => "Phone 1",
        4 => "Phone 2",
        5 => "Phone 3",
        // 6 => "FAX", Current documentation doesn't show fax or EDI as options
        // 7 => "EDI",
        8 => "Text Message",
        100 => "Email and Text Message",
    ];

    /**
     * $email_format_options Format of notices and receipts sent by email
     * @var array
     */
    public array $email_format_options = [
        1 => "Plain text",
        2 => "HTML"
    ];

    /**
     * $valid_registration_fileds Valid fields for new patron registration
     * @var array
     */
    public array $valid_registration_fields = [
        "PatronBranchID",
        "PostalCode",
        "ZipPlusFour",
        "City",
        "State",
        "County",
        "CountryID",
        "StreetOne",
        "StreetTwo",
        "StreetThree",
        "NameFirst",
        "NameLast",
        "NameMiddle",
        "User1",
        "User2",
        "User3",
        "User4",
        "User5",
        "Gender",
        "Birthdate",
        "PhoneVoice1",
        "PhoneVoice2",
        "PhoneVoice3",
        "Phone1CarrierID",
        "Phone2CarrierID",
        "Phone3CarrierID",
        "EmailAddress",
        "AltEmailAddress",
        "LanguageID",
        "UserName",
        "Password",
        "Password2",
        "DeliveryOptionID",
        "EnableSMS",
        "TxtPhoneNumber",
        "Barcode",
        "EReceiptOptionID",
        "PatronCode",
        "ExpirationDate",
        "AddrCheckDate",
        "GenderID",
        "LegalNameFirst",
        "LegalNameLast",
        "LegalNameMiddle",
        "UseLegalNameOnNotices",
        "RequestPickupBranchID",
        "UseSingleName",
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

        $this->cache = new Cache();
        $this->ws_base_url = trim($accountProfile->patronApiUrl, '/ ');
        $this->ws_version = $configArray['Catalog']['api_version'];
        $this->ws_url = $this->ws_base_url . '/' . $this->ws_version . '/' . $this->ws_lang_id . '/'
            . $this->ws_app_id . '/1';
        $this->ws_access_key = $configArray['Catalog']['clientKey'];
        $this->ws_access_id = $configArray['Catalog']['clientSecret'];
    }

    /******************** Authentication ********************/

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
     * @throws JsonException
     */
    public function patronLogin($barcode, $pin, $validatedViaSSO = false): ?User
    {
        $barcode = str_replace("’", "'", trim($barcode)); // clean input
        $pin = trim($pin);

        if ($validatedViaSSO) {
            // todo: sso
            return null;
        }

        // barcode might actually be username so well "validate" the patron first to make sure we have a good
        // barcode.
        $r = $this->validatePatron($barcode, $pin);
        if ($r === null || !isset($r->PatronBarcode)) {
            return null;
        }
        $valid_barcode = $r->PatronBarcode;
        $patron_ils_id = $r->PatronID;
        //check cache for patron secret
        if (!$patron_ils_id || !$this->_getCachePatronSecret($patron_ils_id, $pin)) {
            $auth = $this->authenticatePatron($valid_barcode, $pin, $validatedViaSSO);
            if ($auth === null || !isset($auth->PatronID)) {
                return null;
            }
            $patron_ils_id = $auth->PatronID;
        }

        $patron = $this->getPatron($patron_ils_id, $valid_barcode, $pin);
        if ($patron === null) {
            return null;
        }

        // check for password update
        $patron_pw = $patron->getPassword();
        if (!isset($patron_pw) || $patron_pw !== $pin) {
            $patron->updatePassword($pin);
            $patron->update();
        }

        // if the barcode doesn't match valid_barcode consider it a username
        if ($valid_barcode !== $barcode) {
            $patron->alt_username = $barcode;
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
    protected function validatePatron($barcode, $pin = null, $use_staff_session = false)
    {
        if($pin === null && $use_staff_session === false) {
            $this->logger->error("No pin provided for patron login.");
            return null;
        }
        $request_url = $this->ws_url . '/patron/' . $barcode;
        
        if($use_staff_session === false) {
            $hash = $this->_createHash('GET', $request_url, $pin);
        } else {
            $auth = $this->authenticateStaff();
            if ($auth === null) { 
                $this->logger->error("Unable to authenticate as staff user.");
                return null;
            }
            $staff_secret = $auth->AccessSecret;
            $staff_token = $auth->AccessToken;
            $hash = $this->_createHash('GET', $request_url, $staff_secret);
        }
        
        $headers = [
            "PolarisDate: " . gmdate('r'),
            "Authorization: PWS " . $this->ws_access_id . ":" . $hash,
            "Accept: application/json",
        ];
        
        if($use_staff_session === true) {
            $headers[] = "X-PAPI-AccessToken: " . $staff_token;
        }

        $c_opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            //CURLOPT_SSL_VERIFYPEER => 0,
            //CURLOPT_SSL_VERIFYHOST => 0
        ];

        $c = new Curl();
        $c->setOpts($c_opts);
        $c->get($request_url);

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
            //CURLOPT_SSL_VERIFYPEER => 0,
            //CURLOPT_SSL_VERIFYHOST => 0
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

        $this->_setCachePatronSecret($c->response->PatronID, $c->response->AccessSecret, $pin);

        return $c->response;
    }

    protected function authenticateStaff() {
        // /protected/v1/1033/100/1/authenticator/staff
        $protected_url = str_replace('public', 'protected', $this->ws_url);
        $request_url = $protected_url . '/authenticator/staff';
        [$domain, $username] = explode('@', $this->configArray['Polaris']['staffUserName']);
        $pw = $this->configArray['Polaris']['staffUserPw'];
        
        $request_body = json_encode(['Domain' => $domain, 'Username' => $username, 'Password' => $pw]);

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
            //CURLOPT_SSL_VERIFYPEER => 0,
            //CURLOPT_SSL_VERIFYHOST => 0
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
        } 
        if ($error = $this->_isPapiError($c->response)) {
            $this->_logPapiError($error);
            return null;
        }

        return $c->response;
    }
    
    /***************** SELF REGISTRATION ****************/
    public function getSelfRegistrationFields(): array
    {
        global $library;
        // get the valid home/pickup locations
        $l = new Location();
        $l->libraryId = $library->libraryId;
        $l->validHoldPickupBranch = '1';
        $l->find();
        $l->orderBy('displayName');
        $homeLocations = $l->fetchAll('ilsLocationId', 'displayName');

        $carrier_options = $this->configArray['Carriers'];
        $ereceipt_options = $this->ereceipt_options;
        $notice_options = $this->notification_options;

        $fields = [];

        $fields[] = [
            'property' => 'personal-info',
            'type' => 'header',
            'value' => 'Personal Information',
            'class' => 'h3',
        ];

        $fields[] = [
            'property' => 'NameFirst',
            'type' => 'text',
            'label' => 'First name',
            'description' => 'Your first name',
            'maxLength' => 50,
            'required' => true,
            'autocomplete' => 'given-name',
        ];

        $fields[] = [
            'property' => 'NameMiddle',
            'type' => 'text',
            'label' => 'Middle name',
            'description' => 'Your middle name or initial',
            'maxLength' => 30,
            'required' => false,
            'autocomplete' => 'additional-name',
        ];

        $fields[] = [
            'property' => 'NameLast',
            'type' => 'text',
            'label' => 'Last name',
            'description' => 'Your last name (surname)',
            'maxLength' => 40,
            'required' => true,
            'autocomplete' => 'family-name',
        ];

        if ($this->configArray['Polaris']['showLegalName']) {
            $fields[] = [
                'property' => 'personal-info',
                'type' => 'header',
                'value' => 'Legal Name',
                'class' => 'h4',
                'description' => 'If the name on your identification is different than specified above, please indicate the full name on your identification. If you wish to use the name on your identification for receiving print or phone notices from the library, please check the box below.',
                'showDescription' => true,
            ];

            $fields[] = [
                'property' => 'LegalNameFirst',
                'type' => 'text',
                'label' => 'Legal First name',
                'description' => 'Your legal first name',
                'maxLength' => 50,
                'required' => false,
                'autocomplete' => 'given-name',
            ];

            $fields[] = [
                'property' => 'LegalNameMiddle',
                'type' => 'text',
                'label' => 'Legal Middle name',
                'description' => 'Your legal middle name or initial',
                'maxLength' => 30,
                'required' => false,
                'autocomplete' => 'additional-name',
            ];

            $fields[] = [
                'property' => 'LegalNameLast',
                'type' => 'text',
                'label' => 'Legal Last name',
                'description' => 'Your legal last name (surname)',
                'maxLength' => 40,
                'required' => false,
                'autocomplete' => 'family-name',
            ];

            $fields[] = [
                'property' => 'UseLegalNameOnNotices',
                'type' => 'checkbox',
                'label' => 'Use legal name on notices?',
                'description' => 'Check this box if you wish to use legal name on notices.',
                'required' => false,
            ];
        }

        $fields[] = [
            'property' => 'Birthdate',
            'type' => 'date',
            'label' => 'Date of Birth (MM-DD-YYYY)',
            'description' => 'Date of birth',
            'maxLength' => 10,
            'required' => true,
            'autocomplete' => 'bday',
        ];

        $fields[] = [
            'property' => 'PatronBranchID',
            'type' => 'enum',
            'label' => 'Home Library/Preferred pickup location',
            'description' => 'Your home library and preferred pickup location.',
            'values' => $homeLocations,
            'required' => true,
        ];

        $fields[] = [
            'property' => 'contact-info',
            'type' => 'header',
            'value' => 'Contact Information',
            'class' => 'h3',
        ];

        $fields[] = [
            'property' => 'StreetOne',
            'type' => 'text',
            'label' => 'Mailing Address 1',
            'description' => 'Mailing Address line 1',
            'maxLength' => 40,
            'required' => true,
            'autocomplete' => 'street-address',
        ];

        $fields[] = [
            'property' => 'StreetTwo',
            'type' => 'text',
            'label' => 'Mailing Address 2',
            'description' => 'Mailing Address line 2',
            'maxLength' => 40,
            'required' => false,
            'autocomplete' => 'street-address',
        ];

        $fields[] = [
            'property' => 'City',
            'type' => 'text',
            'label' => 'City',
            'description' => 'The city you receive mail in.',
            'maxLength' => 128,
            'required' => true,
            'autocomplete' => 'address-level2',
        ];

        $fields[] = [
            'property' => 'State',
            'type' => 'text',
            'label' => 'State',
            'description' => 'The state you receive mail in.',
            'maxLength' => 20,
            'required' => true,
            'autocomplete' => 'address-level1',
        ];

        $fields[] = [
            'property' => 'PostalCode',
            'type' => 'text',
            'label' => 'ZIP code',
            'description' => 'The ZIP code for your mail.',
            'maxLength' => 16,
            'required' => true,
            'autocomplete' => 'postal-code',
        ];

        $fields[] = [
            'property' => 'PhoneVoice1',
            'type' => 'tel',
            'label' => 'Primary phone (XXX-XXX-XXXX)',
            'description' => 'Your primary phone number.',
            'maxLength' => 20,
            'required' => false,
            'autocomplete' => 'tel-national',
        ];

        $fields[] = [
            'property' => 'Phone1CarrierID',
            'type' => 'enum',
            'label' => 'Primary Phone Carrier',
            'description' => 'The carrier of your primary phone.',
            'values' => $carrier_options,
            'required' => false,
        ];

        if ($this->configArray['Polaris']['showPhone2']) {
            $fields[] = [
                'property' => 'PhoneVoice2',
                'type' => 'tel',
                'label' => 'Secondary phone (XXX-XXX-XXXX)',
                'description' => 'Your secondary phone number.',
                'maxLength' => 20,
                'required' => false,
                'autocomplete' => 'tel-national',
            ];

            $fields[] = [
                'property' => 'Phone2CarrierID',
                'type' => 'enum',
                'label' => 'Secondary Phone Carrier',
                'description' => 'The carrier of your secondary phone.',
                'values' => $carrier_options,
                'required' => false,
            ];
        }

        if ($this->configArray['Polaris']['showPhone3']) {
            $fields[] = [
                'property' => 'PhoneVoice3',
                'type' => 'tel',
                'label' => 'Alternate phone (XXX-XXX-XXXX)',
                'description' => 'Alternate phone number.',
                'maxLength' => 20,
                'required' => false,
                'autocomplete' => 'tel-national',
            ];

            $fields[] = [
                'property' => 'Phone3CarrierID',
                'type' => 'enum',
                'label' => 'Alternate Phone Carrier',
                'description' => 'The carrier of your alternate phone.',
                'values' => $carrier_options,
                'required' => false,
            ];
        }

        $fields[] = [
            'property' => 'EmailAddress',
            'type' => 'email',
            'label' => 'Email Address',
            'description' => 'Your email address',
            'maxLength' => 128,
            'required' => false,
            'autocomplete' => 'email',
        ];

        $fields[] = [
            'property' => 'notifications-info',
            'type' => 'header',
            'value' => 'Notifications Settings',
            'class' => 'h3',
        ];

        // If multiple phone numbers are allowed, select the correct one for text messages
        // If multiple phones aren't enabled, default to phone 1
        if ($this->configArray['Polaris']['showPhone2'] || $this->configArray['Polaris']['showPhone3']) {
            $text_phone_options = [1 => 'Primary Phone'];
            if ($this->configArray['Polaris']['showPhone2']) {
                $text_phone_options[2] = 'Secondary Phone';
            }
            if ($this->configArray['Polaris']['showPhone3']) {
                $text_phone_options[3] = 'Alternate Phone';
            }
            $fields[] = [
                'property' => 'TxtPhoneNumber',
                'type' => 'enum',
                'label' => 'Phone number for text messages?',
                'description' => 'Which phone number would you like to receive text messages on?',
                'values' => $text_phone_options,
                'required' => false,
            ];
        } else {
            $fields[] = [
                'property' => 'TxtPhoneNumber',
                'type' => 'hidden',
                'default' => '1',
            ];
        }

        $fields[] = [
            'property' => 'DeliveryOptionID',
            'type' => 'enum',
            'label' => 'How you would like to receive library notices?',
            'description' => 'How you would like to receive library notices?',
            'values' => $notice_options,
            'required' => false,
        ];

        $fields[] = [
            'property' => 'EReceiptOptionID',
            'type' => 'enum',
            'label' => 'How you would like to receive library receipts?',
            'description' => 'How you would like to receive receipts?',
            'values' => $ereceipt_options,
            'required' => false,
        ];


        $fields[] = [
            'property' => 'credentials-info',
            'type' => 'header',
            'value' => 'Username and ' . translate('PIN'),
            'class' => 'h3',
        ];

        $fields[] = [
            'property' => 'UserName',
            'type' => 'text',
            'label' => 'Username',
            'description' => "All usernames must begin with a letter (a-z, A-Z), can contain letters, numbers, and the special characters - _ . @ <br>Spaces are not allowed, and special characters can not be contiguous.",
            'maxLength' => 20,
            'required' => false,
            'autocomplete' => 'username',
            'showDescription' => true,
        ];

        $fields[] = [
            'property' => 'Password',
            'type' => 'pin',
            'label' => translate('PIN'),
            'description' => 'Please set a ' . translate('pin') . '.',
            'maxLength' => 10,
            'required' => true,
        ];

			$fields[] = [
				'property'                 => 'Password2',
				'type'                     => 'pin',
				'label'                    => 'Confirm ' . translate('PIN'),
				'description'              => 'Please confirm your ' . translate('pin') . '.',
				'maxLength'                => 10,
				'showPasswordRequirements' => true,
				'required'                 => true,
			];

        return $fields;
    }

    /**
     * Registers a new patron using the Polaris API.
     *
     * This method collects required registration details from the request, such as branch ID, user ID, and workstation ID,
     * along with valid patron registration fields. If extra parameters are provided, they are merged into the registration details.
     * It sends a request to the Polaris API to create a new patron account. The method returns a success flag and the new patron's
     * barcode upon successful registration.
     *
     * @param array $extra_params Additional parameters for self-registration, allowing for library-specific customization.
     *
     * @return array An array containing the success status (`success` as `true` or `false`) and the patron's barcode if the registration is successful.
     */
    public function selfRegister(array $extra_params = []): array
    {
        // /public/patron
        $patron_registration = [];
        // required credentials
        $patron_registration['LogonBranchID'] = 1; // default to system
        $patron_registration['LogonUserID'] = (int)$this->configArray['Polaris']['staffUserId'];
        $patron_registration['LogonWorkstationID'] = (int)$this->configArray['Polaris']['workstationId'];

        $return = ['success' => false, 'barcode' => '', 'message'=> ''];
        foreach ($_REQUEST as $key => $value) {
            if (in_array($key, $this->valid_registration_fields, true)) {
                if ($key === 'Birthdate') {
                    //$ts = strtotime($value);
                    $bd = date_create_from_format('m-d-Y', $value);
                    $bd_ts = date_format($bd, 'U');
                    $patron_registration[$key] = gmdate('r', $bd_ts);
                    continue;
                }
                // check if both first and last legal names are set
                if($key === 'UseLegalNameOnNotices') {
                    if($value === 'on') {
                        if (!empty($_REQUEST['LegalNameFirst']) && !empty($_REQUEST['LegalNameLast'])) {
                            $patron_registration[$key] = true;
                            continue;
                        } else {
                            $self_reg_error_message = 'Please include both a first and last legal name.';
                            $return['message'] = $self_reg_error_message;
                            return $return;
                        }
                    } else {
                        $patron_registration[$key] = false;
                        continue;
                    }
                } 
                // make sure we have a phone number for the selected phone to receive texts on
                if($key === 'TxtPhoneNumber') {
                    if(($value === 1) && !empty($_REQUEST['PhoneVoice1'])) {
                        $patron_registration[$key] = $value;
                        continue;
                    }
                    if(($value === 2) && !empty($_REQUEST['PhoneVoice2'])) {
                        $patron_registration[$key] = $value;
                        continue;
                    }
                    if(($value === 3) && !empty($_REQUEST['PhoneVoice3'])) {
                        $patron_registration[$key] = $value;
                        continue;
                    }
                }
                // cast TxtPhoneNumber to integer
                if($key === "TxtPhoneNumber") {
                    $patron_registration[$key] = (int)$value;
                    continue;
                }
                
                // type these fields as integers and remove the added *Select from the string.
                if (in_array($key,
                    [
                        // handle the added *Select to the id and name fields. Not sure why this is added-- not needed.
                        "PatronBranchID",
                        "DeliveryOptionID",
                        "EReceiptOptionID",
                        "RequestPickupBranchID",
                        "Phone1CarrierID",
                        "Phone2CarrierID",
                        "Phone3CarrierID",
                        "TxtPhoneNumber"
                    ],
                )) {
                    $patron_registration[$key] = (int)$value;
                    continue;
                }
                // all other request parameters
                $patron_registration[$key] = $value;
            }
        }
        
        // EXTRA SELF REG PARAMETERS
        // a class extending this class use this for library specific needs.
        // do this last in case there are any parameters that need to be overridden
        if ($extra_params) {
            $patron_registration = array_merge($patron_registration, $extra_params);
        }

        $request_url = $this->ws_url . "/patron";
        $extra_headers = ["Content-Type: application/json"];
        $c = $this->_doSystemRequest('POST', $request_url, $patron_registration, $extra_headers);
        
        if ($c === null) {
            if (isset($this->papiLastErrorMessage)) {
                $self_reg_error_message = "We're sorry, we could not complete your registration request. " . $this->papiLastErrorMessage;
            } else {
                $self_reg_error_message = "We're sorry, we could not complete your registration request. Please contact the library for further assistance.";
            }
            $return['message'] = $self_reg_error_message;
            return $return;
        }
        
        // send self registration email
        $email_sent = false;
        if(!empty($_REQUEST['EmailAddress']) && trim($_REQUEST['EmailAddress']) !== '') {
            $email_vars = [
                'email' => trim($_REQUEST['EmailAddress']),
                'barcode' => $c->response->Barcode,
                'ils_branch_id' => $_REQUEST['PatronBranchID'],
                'name' => $_REQUEST['NameFirst'] . ' ' . $_REQUEST['NameLast']
            ];
            $email_sent = $this->sendSelfRegSuccessEmail($email_vars);
        }
        
        if($email_sent) {
            $return['message'] = "Thank you for registering . We will contact you shortly.";
        }
        
        $return['success'] = true;
        $return['barcode'] = $c->response->Barcode;
        
        return $return;
    }

    /**
     * Sends a confirmation email to the patron after a successful self-registration.
     *
     * This method composes and sends an email to the newly registered patron, using the provided email variables.
     * The email includes the patron's barcode, name, and the library's name and catalog URL. The email content is
     * generated using a template file. If the email is successfully sent, the method returns `true`; otherwise,
     * it returns `false` in case of failure or if the library location cannot be found.
     *
     * @param array $email_vars An array of variables needed for the email, including the patron's email, name,
     * library branch ID, and barcode.
     *
     * @return bool `true` if the email is sent successfully, `false` if there is an error or the location cannot be found.
     */
    public function sendSelfRegSuccessEmail(array $email_vars): bool
    {
        global $interface;
        global $library;
        $location_id = $this->polarisBranchIdToLocationId($email_vars['ils_branch_id']);
        $location = new Location();
        $location->locationId = $location_id;
        if(!$location->find(true)) {
            return false;
        }
        $location_name = $location->displayName;
		    $catalog_url  = empty($library->catalogUrl) ? $this->configArray['Site']['url'] : $_SERVER['REQUEST_SCHEME'] . '://' . $library->catalogUrl;

		$interface->assign('emailAddress', $email_vars['email']);
        $interface->assign('patronName', $email_vars['name']);
        $interface->assign('libraryName', $location_name);
        $interface->assign('catalogUrl', $catalog_url);
        $interface->assign('barcode', $email_vars['barcode']);
        $emailBody = $interface->fetch('Emails/self-registration.tpl');
        try {
            $mailer = new PHPMailer;
            $mailer->setFrom($this->configArray['Site']['email']);
            $mailer->addAddress($email_vars['email']);
            $mailer->Subject = '[DO NOT REPLY] Your new library card at ' . $location_name;
            $mailer->Body    = $emailBody;
            $mailer->send();
        } catch (\Exception $e) {
            $this->logger->error($mailer->ErrorInfo);
            return false;
        }
        return true;
    }
    
    public function getNotificationOptions()
    {
        return $this->notification_options;
    }

    public function getErecieptionOptions()
    {
        return $this->ereceipt_options;
    }

    public function getEmailFormatOptions()
    {
        return $this->email_format_options;
    }

    public function getPhoneCarrierOptions()
    {
        return $this->configArray['Carriers'];
    }

    /***************** READING HISTORY ****************/
    /**
     * Fetch a patrons reading history from Polaris ILS
     *
     * @param User $patron
     * @param int $page
     * @param int $recordsPerPage
     * @param string $sortOption
     * @return array
     */
    public function getReadingHistory($patron, $page = 1, $recordsPerPage = -1, $sortOption = "checkedOut")
    {
        // /public/patron/{PatronBarcode}/readinghistory
        // history enabled?
        if ($patron->trackReadingHistory !== 1) {
            return ['historyActive' => false, 'numTitles' => 0, 'titles' => []];
        }

        // rowsperpage=5&page=0 will return all reading history
        $request_url = $this->ws_url . "/patron/{$patron->barcode}/readinghistory?rowsperpage=5&page=0";
        $c = $this->_doPatronRequest($patron, 'GET', $request_url);

        if ($c === null) {
            return false;
        }

        $history = ['historyActive' => true];
        if ($c->response->PAPIErrorCode === 0) {
            $history['numTitles'] = 0;
            $history['titles'] = [];
            return $history;
        }

        // when positive PAPIErrorCode is the number of items returned.
        $history['numTitles'] = $c->response->PAPIErrorCode;
        $titles = [];
        foreach ($c->response->PatronReadingHistoryGetRows as $row) {
            $record = new MarcRecord($this->accountProfile->recordSource . ':' . $row->BibID);
            $title = [];
            if ($record->isValid()) {
                $title['permanentId'] = $record->getPermanentId();
                $title['title'] = $record->getTitle();
                $title['author'] = $record->getPrimaryAuthor();
                $title['format'] = $record->getFormat();
                $title['title_sort'] = $record->getSortableTitle();
                $title['ratingData'] = $record->getRatingData();
                $title['linkUrl'] = $record->getGroupedWorkDriver()->getLinkUrl();
                $title['coverUrl'] = $record->getBookcoverUrl('medium');
            } else {
                $title['title'] = $row->Title;
                $title['author'] = $row->Author;
                $title['format'] = $row->FormatDescription;
            }
            $titles[] = $title;
        }
        $history['titles'] = $titles;
        return $history;
    }
    
    protected function _getReadingHistoryCount($patron) {
        $request_url = $this->ws_url . "/patron/{$patron->barcode}/readinghistory?rowsperpage=1&page=-1";
        $c = $this->_doPatronRequest($patron, 'GET', $request_url);
        
        if ($c === null) {
            return false;
        }
        
        return $c->response->PAPIErrorCode;
    }
    
    public function loadReadingHistoryFromIls($patron, $loadAdditional = null)
    {
        $per_round = 1000;
        if ((int)$patron->trackReadingHistory !== 1) {
            return ['historyActive' => false, 'numTitles' => 0, 'titles' => []];
        }
        
        // get the total number of entries
        $num_titles_total = $this->_getReadingHistoryCount($patron);
        
        if($num_titles_total === false) {
            // return an error?
            return false;
        }
        
        // no reading history in ILS
        if($num_titles_total === 0) {
            return ['historyActive' => true, 'numTitles' => 0, 'titles' => []];
        }
        
        // additional calls to complete load
        $page = $loadAdditional ?? 1;
        $next_page = $page + 1;
        if(($next_page * $per_round) > $num_titles_total) {
            $next_page = false;
        }
        
        $request_url = $this->ws_url . "/patron/{$patron->barcode}/readinghistory?rowsperpage={$per_round}&page={$page}";
        $c = $this->_doPatronRequest($patron, 'GET', $request_url);

        if ($c === null) {
            return false;
        }

        $history = [];
        
        // no reading history, PAPI error code will be number of items if positive
        if ($c->response->PAPIErrorCode === 0) {
            $history['numTitles'] = 0;
            $history['titles'] = [];
            return $history;
        }

        $titles = [];
        foreach ($c->response->PatronReadingHistoryGetRows as $row) {
            $title = [];
            if ($this->isMicrosoftDate($row->CheckOutDate)) {
                $date = $this->microsoftDateToISO($row->CheckOutDate);
                $checkout_date = strtotime($date);
            } else {
                $checkout_date = strtotime($row->CheckOutDate);
            }
            $title['checkout'] = $checkout_date;
            $title['ilsReadingHistoryId'] = $row->PatronReadingHistoryID;
            $title['recordId'] = $row->BibID;
            $title['source'] = $this->accountProfile->recordSource;

            $record = new MarcRecord($this->accountProfile->recordSource . ':' . $row->BibID);
            if ($record->isValid()) {
                $title['permanentId'] = $record->getPermanentId();
                $title['title'] = $record->getTitle();
                $title['author'] = $record->getPrimaryAuthor();
                $title['format'] = $record->getFormat();
                $title['title_sort'] = $record->getSortableTitle();
            } else {
                $title['title'] = $row->Title;
                $title['author'] = $row->Author;
                $title['format'] = $row->FormatDescription;
                $simpleSortTitle = preg_replace('/^The\s|^An?\s/i', '', $row->Title); // remove beginning The, A, or An
                $title['title_sort'] = $simpleSortTitle;
            }
            $titles[] = $title;
        }
        
        $num_titles = count($titles);
        if ($next_page !== false){
            $history['nextRound'] = $next_page;
        }
        $history['titles'] = $titles;
        $history['numTitles'] = $num_titles;
        return $history;
    }

    /**
     * Opts a patron into the reading history feature using the Polaris API.
     *
     * This function sends a request to the Polaris API to enable the reading history feature for a patron.
     * If the API request fails, the function returns `false`; otherwise, it returns `true` indicating a successful opt-in.
     *
     * @param User $patron An object representing the patron, which should have a `barcode` property used to identify the patron in the API request.
     *
     * @return bool `true` if the opt-in is successful, `false` if the API request fails.
     */
    public function optInReadingHistory(User $patron): bool
    {
        $opt_in = [
            "LogonBranchID" => 1,
            "LogonUserID" => $this->configArray['Polaris']['staffUserId'],
            "LogonWorkstationID" => $this->configArray['Polaris']['workstationId'],
            "ReadingListFlag" => 1,
        ];

        $request_url = $this->ws_url . "/patron/{$patron->barcode}";
        $extra_headers = ["Content-Type: application/json"];

        $r = $this->_doPatronRequest($patron, 'PUT', $request_url, $opt_in, $extra_headers);

        if ($r === null) {
            return false;
        }
        $this->_deleteCachePatronObject($patron->ilsUserId);
        return true;
    }

    /**
     * Opts a patron out of the reading history feature using the Polaris API.
     *
     * This function sends a request to the Polaris API to disable the reading history feature for a patron.
     * If the API request fails, the function returns `false`; otherwise, it returns `true` indicating a successful opt-out.
     *
     * @param User $patron An object representing the patron, which should have a `barcode` property used to identify the patron in the API request.
     *
     * @return bool `true` if the opt-out is successful, `false` if the API request fails.
     */
    public function optOutReadingHistory(User $patron)
    {
        $opt_out = [
            "LogonBranchID" => 1,
            "LogonUserID" => $this->configArray['Polaris']['staffUserId'],
            "LogonWorkstationID" => $this->configArray['Polaris']['workstationId'],
            "ReadingListFlag" => 0,
        ];

        $request_url = $this->ws_url . "/patron/{$patron->barcode}";
        $extra_headers = ["Content-Type: application/json"];

        $r = $this->_doPatronRequest($patron, 'PUT', $request_url, $opt_out, $extra_headers);

        if ($r === null) {
            return false;
        }
        $this->_deleteCachePatronObject($patron->ilsUserId);
        return true;
    }

    /**
     * @inheritDoc
     */
    public function hasNativeReadingHistory()
    {
        return true;
    }

    /***************** FINES ****************/
    /**
     * Retrieves the outstanding fines for a specific patron from the Polaris API.
     *
     * This function makes a request to the Polaris API to get the patron's outstanding account details,
     * and processes the response to format the fines information in a structured array.
     *
     * @param object $patron An object representing the patron, which should have a `barcode` property used to identify the patron in the API request.
     *
     * @return array|false An array of fines associated with the patron, where each fine includes the title, date, reason, amount, outstanding amount,
     * and any additional details. Returns `false` if the API request fails.
     */
    public function getMyFines(User $patron)
    {
        // Polaris API function PatronAccountGet
        // /public/patron/{PatronBarcode}/account/outstanding
        $request_url = $this->ws_url . "/patron/{$patron->barcode}/account/outstanding";
        $c = $this->_doPatronRequest($patron, 'GET', $request_url);

        if ($c === null) {
            return false;
        }

        $patron_fines = [];
        foreach ($c->response->PatronAccountGetRows as $row) {
            if ($this->isMicrosoftDate($row->TransactionDate)) {
                $date = $this->microsoftDateToISO($row->TransactionDate);
                $date = date('m-d-Y', strtotime($date));
            } else {
                $date = date('m-d-Y', strtotime($row->TransactionDate));
            }

            $details = [];
            if ($row->CheckOutDate !== null) {
                if ($this->isMicrosoftDate($row->CheckOutDate)) {
                    $d = $this->microsoftDateToISO($row->CheckOutDate);
                    $checkout_date = date('m-d-Y', strtotime($d));
                } else {
                    $checkout_date = date('m-d-Y', strtotime($row->CheckOutDate));
                }
                $details[] = [
                    "label" => "Out:",
                    "value" => date('m-d-Y', strtotime($checkout_date)),
                ];
            }
            if ($row->FreeTextNote !== null) {
                $details[] = [
                    "label" => "Note:",
                    "value" => $row->FreeTextNote,
                ];
            }

            $patron_fines[] = [
                'title' => $row->Title === '' ? "Unknown" : $row->Title,
                'date' => $date,
                'reason' => $row->TransactionTypeDescription . ", " . $row->FeeDescription,
                'amount' => number_format($row->TransactionAmount, 2),
                'amountOutstanding' => number_format($row->OutstandingAmount, 2),
                'details' => $details,
            ];
        }
        return $patron_fines;
    }


    /******************** Errors ********************/
    protected function _isError($r)
    { // todo: finish this up
//        if ($r->error || $r->httpStatusCode !== 200) {
//            $this->logger->error(
//                'Curl error: ' . $c->errorMessage,
//                ['http_code' => $c->httpStatusCode],
//                ['RequestURL' => $request_url, "Headers" => $headers],
//            );
//            return null;
//        } elseif ($error = $this->_isPapiError($c->response)) {
//            $this->_logPapiError($error);
//            return null;
//        }
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
     * Check if user exists in database, create user if needed, update user if needed and return User object.
     *
     * @param $ils_id
     * @param $barcode
     * @param $pin
     * @return User|null
     */
    protected function getPatron($ils_id, $barcode, $pin): ?User
    {
        // get user from cache if cache object exists
        if ($user = $this->_getCachePatronObject($ils_id)) {
            return $user;
        }

        $create_user = false;

        $user = new User();
        $user->ilsUserId = $ils_id;
        $user->source = $this->accountProfile->recordSource;

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
        $request_url = $this->ws_url . '/patron/' . $barcode . '/basicdata?addresses=true';
        $patron_access_secret = $this->_getCachePatronSecret($ils_id, $pin);
        if ($patron_access_secret === false) {
            $auth = $this->authenticatePatron($barcode, $pin);
            if ($auth === null) {
                return null;
            }
            $patron_access_secret = $auth->AccessSecret;
        }

        $hash = $this->_createHash('GET', $request_url, $patron_access_secret);

        $headers = [
            "PolarisDate: " . gmdate('r'),
            "Authorization: PWS " . $this->ws_access_id . ":" . $hash,
            "Accept: application/json",
        ];
        $c_opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            //CURLOPT_SSL_VERIFYPEER => 0,
            //CURLOPT_SSL_VERIFYHOST => 0
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

        // Patron library and location
        $location = new Location();
        $location->ilsLocationId = $patron_response->PatronOrgID;
        $patron_location_display_name = '';
        if ($location->find(true)) {
            // Need this for display on account profile page 
            $patron_location_display_name = $location->displayName;
            // Set location
            if ($user->homeLocationId !== $location->locationId) {
                $user->homeLocationId = $location->locationId;
                if (!$create_user) {
                    $user->update();
                }
            }
            // Set library
            if ($user->homeLibraryId !== $location->libraryId) {
                $user->homeLibraryId = $location->libraryId;
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

        // Patron code
        if ($user->patronType !== $patron_response->PatronCodeID) {
            $user->patronType = $patron_response->PatronCodeID;
            if (!$create_user) {
                $user->update();
            }
        }

        if ($create_user) {
            $user->source = $this->accountProfile->recordSource;
            $user->created = date("Y-m-d H:i:s");
            $user->insert();
        }

        /***
         * The following class variables aren't stored in database and need to be created on demand
         */

        // Location name
        $user->homeLocation = $patron_location_display_name;

        // Preferred pickup location
        $pickup_location = new Location();
        $pickup_location->ilsLocationId = $patron_response->RequestPickupBranchID;
        if ($pickup_location->find(true)) {
            $user->preferredPickupLocationCode = $pickup_location->code;
        } else {
            $user->preferredPickupLocationCode = '';
        }
        $user->preferredPickupLocationId = $patron_response->RequestPickupBranchID;

        // Names
        $user->fullname = $user->firstname . ' ' . $user->lastname;
        // Legal name is Polaris specific
        $user->legalFirstName = $patron_response->LegalNameFirst;
        $user->legalMiddleName = $patron_response->LegalNameMiddle;
        $user->legalLastName = $patron_response->LegalNameLast;
        $user->legalFullName = ($patron_response->LegalFullName === '') ? null : $patron_response->LegalFullName;
        // Name preference for notices
        $user->useLegalNameOnNotices = (bool)$patron_response->UseLegalNameOnNotices;

        // Expiration
        // date can be returned in Microsoft format
        if ($this->isMicrosoftDate($patron_response->ExpirationDate)) {
            try {
                $expiration_date = $this->microsoftDateToISO($patron_response->ExpirationDate);
                $user->expires = date('m-d-Y', strtotime($expiration_date));
            } catch (Exception $e) {
                $this->logger->error($e->getMessage());
                $user->expires = $patron_response->ExpirationDate;
            }
        } else {
            $user->expires = $patron_response->ExpirationDate;
        }

        // Address
        $patron_address = $patron_response->PatronAddresses[0];
        $user->address_id = $patron_address->AddressID;
        $user->address1 = $patron_address->StreetOne;
        $user->address2 = $patron_address->StreetTwo;
        $user->city = $patron_address->City;
        $user->state = $patron_address->State;
        $user->zip = $patron_address->PostalCode;

        // Phones
        $user->phone = $patron_response->PhoneNumber;
        $user->phone_carrier_id = $patron_response->Phone1CarrierID;
        $user->phone2 = $patron_response->PhoneNumber2;
        $user->phone2_carrier_id = $patron_response->Phone2CarrierID;
        $user->phone3 = $patron_response->PhoneNumber3;
        $user->phone3_carrier_id = $patron_response->Phone3CarrierID;
        $user->txt_phone_id = $patron_response->TxtPhoneNumber;

        // Notices
        // Possible values in Polaris API
        //1 - Mail
        //2 - Email
        //3 - Phone 1
        //4 - Phone 2
        //5 - Phone 3
        //6 - Fax
        //8 - Text Message
        $user->noticePreferenceId = $patron_response->DeliveryOptionID;

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

        // The ID corresponding to the type of e receipt the patron has selected
        $user->ereceiptId = $patron_response->EReceiptOptionID;
        // The ID corresponding to the type of emails the patron want to receive (plain or HTMl)
        $user->emailFormatId = $patron_response->EmailFormatID;

        // Checkouts and holds count
        // Polaris returns number of ILS AND number of ILL holds in counts.
        $user->numHoldsIls = $patron_response->HoldRequestsCurrentCount;
        $user->numHoldsAvailableIls = $patron_response->HoldRequestsHeldCount;
        $user->numHoldsRequestedIls = $patron_response->HoldRequestsTotalCount;
        $user->numCheckedOutIls = $patron_response->ItemsOutCount;

        // Fines // todo: why to separate fines fields? Do we need a currency symbol? 
        $user->finesVal = $patron_response->ChargeBalance;
        $user->fines = number_format($patron_response->ChargeBalance, 2);
        // Notes
        // todo: do we need this? Polaris notes seem to be only for staff
        // $user->webNote = $patron_response->PatronNotes;

        $this->_setCachePatronObject($user);

        return $user;
    }


    /******************** Checkouts ********************/

    /**
     * Retrieve a Patron's Checked Out Items
     *
     * This method retrieves all currently checked-out items for a specific patron from the library's
     * system. It processes the data returned by the API, transforming it into a structured array
     * that includes detailed information about each item, such as due dates, checkout dates,
     * renewability, bibliographic information, and cover images. The method supports handling
     * special cases like inter-library loans (ILL) and manages records even if they lack standard MARC data.
     *
     * If the method encounters an issue (e.g., no data returned, an error in the request),
     * it will return an empty array.
     *
     * @param User $patron The patron object representing the user whose checkouts are being retrieved.
     * @param bool $linkedAccount (Optional) Indicates whether to retrieve checkouts for a linked account. Defaults
     * to `false`.
     *
     * @return array|null An array of the patron's checkouts. Returns empty array if the request fails or if there
     * are no checkouts.
     * @access public
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
                $checkout['title'] = $c->Title ?? '';
                $checkout['format'] = $c->FormatDescription ?? 'Unknown';
                $checkout['author'] = $c->Author ?? '';
            }

            $checkouts[] = $checkout;
        }
        return $checkouts;
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
        $patron_access_secret = $this->_getCachePatronSecret($patron->ilsUserId, $patron->getPassword());
        if ($patron_access_secret === false) {
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
        array $extra_headers = []
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
            //CURLOPT_SSL_VERIFYPEER => 0,
            //CURLOPT_SSL_VERIFYHOST => 0
        ];

        $c = new Curl();
        $c->setOpts($c_opts);
        // todo: check headers for content length and content type
        switch ($method) {
            case 'GET':
                $c->get($url);
                break;
            case 'POST':
                if (is_array($body)) {
                    $body = json_encode($body);
                }
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

        if ($c->error || $c->httpStatusCode !== 200) {
            $this->logger->error('Curl error: ' . $c->errorMessage, [
                'http_code' => $c->httpStatusCode,
                'request_url' => $url,
                'Headers' => var_export($c->requestHeaders, true),
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
        }

        // If neither ":" nor "/" is found, return the entire string after "ILL-"
        if (preg_match('/ILL-(.*)/', $title, $matches)) {
            return trim($matches[1]);
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

    /******************** Utilities ********************/

    /** Caching **/
    /**
     * Save a user object to cache
     *
     * @param User $patron
     * @return bool
     */
    protected function _setCachePatronObject(User $patron): bool
    {
        $patron_object_cache_key = $this->cache->makePatronKey('patron', $patron->ilsUserId);
        $expires = 30;
        return $this->cache->set($patron_object_cache_key, $patron, $expires);
    }

    /**
     * Remove a patron object from cache
     *
     * @param $patron_ils_id
     * @return bool
     */
    protected function _deleteCachePatronObject($patron_ils_id)
    {
        $patron_object_cache_key = $this->cache->makePatronKey('patron', $patron_ils_id);
        return $this->cache->delete($patron_object_cache_key);
    }

    /**
     * Get a user object from cache
     *
     * @param $patron_ils_id
     * @return false|mixed
     */
    protected function _getCachePatronObject($patron_ils_id)
    {
        $patron_object_cache_key = $this->cache->makePatronKey('patron', $patron_ils_id);
        $patron = $this->cache->get($patron_object_cache_key, false);
        if ($patron !== false) {
            $this->logger->info('Patron object found in cache.');
            return $patron;
        } else {
            $this->logger->info('Patron object not found in cache.');
            return false;
        }
    }

    /**
     * Get a patrons secret from cache
     *
     * @param $patron_ils_id
     * @return false|string
     */
    protected function _getCachePatronSecret($patron_ils_id, $patron_pin)
    {
        $patron_secret_cache_key = 'patronilsid' . $patron_ils_id . 'secret_' . md5($patron_ils_id . $patron_pin);
        $key = $this->cache->get($patron_secret_cache_key, false);
        if ($key !== false) {
            $this->logger->info('Patron secret found in cache.');
            return $key;
        } else {
            $this->logger->info('Patron secret not found in cache.');
            return false;
        }
    }

    /**
     * Remove patrons secret from cache
     *
     * @param $patron_ils_id
     * @return bool
     */
    protected function _deleteCachePatronSecret($patron_ils_id, $patron_pin): bool
    {
        $patron_secret_cache_key = 'patronilsid' . $patron_ils_id . 'secret_' . md5($patron_ils_id . $patron_pin);
        return $this->cache->delete($patron_secret_cache_key);
    }

    /**
     * Add patrons secret to cache
     *
     * @param $patron_ils_id
     * @param $patron_secret
     * @return bool
     */
    protected function _setCachePatronSecret($patron_ils_id, $patron_secret, $pin): bool
    {
        $patron_secret_cache_key = 'patronilsid' . $patron_ils_id . 'secret_' . md5($patron_ils_id . $pin);
        $expires = 60 * 60; // todo: one hour until we get an expiration date and time from the api
        return $this->cache->set($patron_secret_cache_key, $patron_secret, $expires);
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

        if ($c === null /*|| count($c->response->PatronHoldRequestsGetRows) === 0*/) {
					// Can not return early till ILL holds are checked
            return ['available' => $availableHolds, 'unavailable' => $unavailableHolds];
        }
        // translations
        $frozen = translate('frozen');

        foreach ($c->response->PatronHoldRequestsGetRows as $hold) {
            $h = [];
            $pickup_branch_id = $this->polarisBranchIdToLocationId($hold->PickupBranchID);
            $h['currentPickupId'] = $pickup_branch_id;
            if ($pickup_branch_id !== null) {
                $location = new Location();
                $location->locationId = $pickup_branch_id;
                $location->find(true);
                $h['currentPickupName'] = $location->displayName;
                $h['location'] = $location->displayName;
            } else {
                $h['currentPickupName'] = $hold->PickupBranchName;
                $h['location'] = $hold->PickupBranchName;
            }
            $h['holdSource'] = $this->accountProfile->recordSource;
            $h['userId'] = $patron->id;
            $h['user'] = $patron->displayName;
            $h['cancelId'] = $hold->HoldRequestID;
            $h['cancelable'] = true;
            $h['freezeable'] = $hold->CanSuspend === true;
            $h['status'] = $hold->StatusDescription;
            $h['frozen'] = false; // status will be inactive if frozen
            $h['locationUpdateable'] = true;
            $h['position'] = $hold->QueuePosition . ' of ' . $hold->QueueTotal;

            $h['create'] = '';
            if ($this->isMicrosoftDate($hold->ActivationDate)) {
                $create = $this->microsoftDateToISO($hold->ActivationDate);
                $h['create'] = strtotime($create);
            } else {
                $h['create'] = strtotime($hold->ActivationDate);
            }
//          "expire" displays as Pickup By date in holds interface
            $h['expire'] = '';
            if(isset($hold->PickupByDate)) {
                if ($this->isMicrosoftDate($hold->PickupByDate)) {
                    $expire = $this->microsoftDateToISO($hold->PickupByDate);
                    $h['expire'] = strtotime($expire);
                } else {
                    $h['expire'] = strtotime($hold->PickupByDate);
                }
            }

            // load marc record
            $recordSourceAndId = new SourceAndId($this->accountProfile->recordSource . ':' . $hold->BibID);
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
                $h['title'] = $this->cleanIllTitle($hold->Title);
                $h['sortTitle'] = preg_replace('/^The\s|^An?\s/i', '', $this->cleanIllTitle($hold->Title));
                $h['author'] = $this->cleanIllAuthor($hold->Author);
                $h['format'] = '';
            }
            // special handling by status id
            switch ($hold->StatusID) {
                case 1: // inactive/frozen
                    $h['freezeable'] = false;
                    $h['frozen'] = true;
                    $h['status'] = $frozen;
                    // reactivation date 
                    if ($this->isMicrosoftDate($hold->ActivationDate)) {
                        $reactivate_date = $this->microsoftDateToISO($hold->ActivationDate);
                        $h['reactivate'] = $reactivate_date;
                        $h['reactivateTime'] = strtotime($reactivate_date);
                    } else {
                        $h['reactivate'] = $hold->ActivationDate;
                        $h['reactivateTime'] = strtotime($hold->ActivationDate);
                    }
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

	        // Item Level hold
	        if ($hold->ItemLevelHold) {
		        $h['volume'] = $hold->CallNumber;
	        }

	        if ($hold->StatusID === 6) { // ready for pickup
                $availableHolds[] = $h;
            } elseif ($hold->StatusID !== 8 && $hold->StatusID !== 9 && $hold->StatusID !== 16) { // status 16 is canceled items. don't show unless ILL request
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

					if (!in_array($hold->Status, ['Received', 'Cancelled', 'Returned'])){
						$h                = [];
                        //$h['freezeable']         = Not sure if ILL holds can be frozen, likely not
						//$h['position']           = API doesn't provide this information for ILL holds
						$pickup_branch_id     = $this->polarisBranchIdToLocationId($hold->PickupBranchID);
						$h['currentPickupId'] = $pickup_branch_id;
						if ($pickup_branch_id !== null){
							$location             = new Location();
							$location->locationId = $pickup_branch_id;
							$location->find(true);
							$h['currentPickupName'] = $location->displayName;
							$h['location']          = $location->displayName;
						}else{
							$h['currentPickupName'] = $hold->PickupBranchName ?? $hold->PickupBranch ?? null;
							$h['location']          = $hold->PickupBranch;
						}
						$h['holdSource']         = $this->accountProfile->recordSource;
						$h['userId']             = $patron->id;
						$h['user']               = $patron->displayName;
						$h['cancelId']           = $hold->ILLRequestID;
						$h['cancelable']         = true;// todo: can ill holds be canceled?
						$h['status']             = $hold->Status;
						$h['frozen']             = false;
						$h['locationUpdateable'] = true;
						if ($this->isMicrosoftDate($hold->ActivationDate)){
							$create      = $this->microsoftDateToISO($hold->ActivationDate);
							$h['create'] = strtotime($create);
						}else{
							$h['create'] = strtotime($hold->ActivationDate);
						}
						 $h['expire'] = ''; 
                        // ILL request doesn't include expires date 
                        // ~~Pika won't have marc data for ILL holds~~
                        // The above is incorrect. MARC records are created when the title is received by library and Pika
                        // will have access to MARC data.
						// load marc record
						$recordSourceAndId = new SourceAndId($this->accountProfile->recordSource . ':' . $hold->BibRecordID);
						$record            = RecordDriverFactory::initRecordDriverById($recordSourceAndId);
						if ($record->isValid()){
							$h['id']        = $record->getUniqueID();
							$h['shortId']   = $record->getShortId();
							$h['title']     = $this->cleanIllTitle($record->getTitle());
							$h['sortTitle'] = $record->getSortableTitle();
							$h['author']    = $record->getPrimaryAuthor();
							$h['format']    = $record->getFormat();
							$h['link']      = $record->getRecordUrl();
							$h['coverUrl']  = $record->getBookcoverUrl('medium'); // todo: Prospector cover?
						}else{
							$title          = $this->cleanIllTitle($hold->Title);
							$author         = $this->cleanIllAuthor($hold->Author);
							$cover_url      = $this->getIllCover() ?? '';
							$h['title']     = $title;
							$h['sortTitle'] = preg_replace('/^The\s|^An?\s/i', '', $title);
							$h['author']    = $author;
							$h['format']    = $hold->Format;
							$h['coverUrl']  = $cover_url;
						}
						if ($hold->ILLStatusID === 10){
							$availableHolds[] = $h;
						}else{
							$unavailableHolds[] = $h;
						}
					}
        } // end foreach

        return ['available' => $availableHolds, 'unavailable' => $unavailableHolds];
    }

    protected function polarisBranchIdToLocationId($branch_id)
    {
        $location = new Location();
        $location->ilsLocationId = $branch_id;
        if ($location->find(true) === 1) {
            return $location->locationId;
        }
        return null;
    }

    protected function locationCodeToPolarisBranchId($branch_name)
    {
        $location = new Location();
        $location->code = $branch_name;
        if ($location->find(true) === 1) {
            return $location->ilsLocationId;
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
    public function placeHold($patron, $recordId, $pickupBranch, $cancelDate = null, $hasHomePickupItems = false)
    {
		// Determine if item-level hold is needed
	    $sourceAndId = new SourceAndId($this->accountProfile->recordSource . ':' . $recordId);
        $record = new MarcRecord($sourceAndId);
        if ($record->isValid() && in_array('Journal', $record->getFormats())) {
            $items = [];
            $itemIdsToBarcode = $record->getItemIdsAndBarcodes();
            $solrRecord = $record->getGroupedWorkDriver()->getRelatedRecord($sourceAndId->getSourceAndId());
            foreach ($solrRecord['itemDetails'] as $itemDetails) {
                if ($itemDetails['holdable']){
                    $items[] = [
                        //'itemNumber' => $itemDetails['itemId'],
                        'itemNumber' => $itemIdsToBarcode[$itemDetails['itemId']],
                        // Return the barcode as the item number to be used to place the item level hold
                        'location'   => $itemDetails['shelfLocation'],
                        'callNumber' => $itemDetails['callNumber'],
                        'status'     => $itemDetails['status'],
                        //'barcode'    => $itemIdsToBarcode[$itemDetails['itemId']], //TODO: set as volume?
                    ];
                }
            }
            $return         = [
                'message'    => 'This title requires item level holds, please select an item to place a hold on.',
                'success'    => 'true',
                'canceldate' => $cancelDate,
                'items'      => $items
            ];
            return $return;
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
        );

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

    /**
     * @inheritDoc
     */
    public function placeItemHold($patron, $recordId, $itemId, $pickupBranch)
    {
        $bib_id = trim($recordId);
        $barcode = trim($itemId);
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
        );

        $patron_id = $patron->ilsUserId;

        $request_url = $this->ws_url . '/holdrequest';
        $request_body = [
            "PatronID" => (int)$patron_id,
            "BibID" => (int)$bib_id,
            "ItemBarcode" => (int)$barcode,
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
        $record = RecordDriverFactory::initRecordDriverById($this->accountProfile->recordSource . ':' . $bib_id);
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
    public function changeHoldPickupLocation($patron, $recordId, $itemToUpdateId, $newPickupLocation): array
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

    /**
     * @inheritDoc
     */
    public function getNumHoldsOnRecord($id)
    {
        return false;
    }

    /* Patron Updates */
    /**
     * Updates the patron's information based on the specified update scope or profile update action.
     *
     * This function checks whether the patron is allowed to update their contact information.
     * If updates are allowed, it performs the specified update action (contact information, username, or PIN)
     * or calls a custom update method if defined. If updates are not allowed, an error message is returned.
     *
     * @param User $patron An object representing the patron, containing the patron's details required for updates.
     * @param bool $canUpdateContactInfo A boolean flag indicating whether the patron is permitted to update their contact information.
     *
     * @return array An array containing the result of the update action, or an error message if the update is not permitted or fails.
     */
    public function updatePatronInfo($patron, $canUpdateContactInfo)
    {
        // /public/patron/{PatronBarcode}
        if (!$canUpdateContactInfo) {
            return ['You can not update your information. Please contact your library for assistance.'];
        }
        /*
        * If a method exits in this class or a class extending this class it will be passed a User object.
        */
        if (isset($_REQUEST['profileUpdateAction']) && method_exists($this, $_REQUEST['profileUpdateAction'])) {
            $profileUpdateAction = trim($_POST['profileUpdateAction']);
            return $this->$profileUpdateAction($patron);
        }

        $update_scope = trim($_REQUEST['updateScope']);
        switch ($update_scope) {
            case 'contact':
                return $this->updatePatronContact($patron);
                break;
            case 'username':
                return $this->updatePatronUsername($patron);
                break;
            case 'pin':
                return $this->updatePin($patron);
                break;
            default:
                return ['An error occurred. Please contact your library for assistance.'];
        }
    }

    /**
     * Updates the contact information of a patron using the Polaris API.
     *
     * This function constructs a request to the Polaris API to update the patron's contact details, including phone number,
     * email address, and mailing address. It uses required credentials such as branch ID, user ID, and workstation ID to
     * authenticate the request. If the request fails, an error message is returned.
     *
     * @param User $patron An instance of the User class representing the patron whose contact information needs to be updated.
     *
     * @return array An array containing error messages if the update fails, or an empty array if the update is successful.
     */
    protected function updatePatronContact(User $patron): array
    {
        // /public/patron/{PatronBarcode}
        $this->_deleteCachePatronObject($patron->ilsUserId);
        $patron->clearCache();

        $contact = [];

        // required credentials
        $contact['LogonBranchID'] = 1; // default to system
        $contact['LogonUserID'] = $this->configArray['Polaris']['staffUserId'];
        $contact['LogonWorkstationID'] = $this->configArray['Polaris']['workstationId'];
        // patron updates
        // if a field isn't required and is not set, don't include in the update request
        // PhoneVoice1 maps to get patrons PhoneNumber when "GET"ing the patron
        if (isset($_REQUEST['phone'])) {
            $contact['PhoneVoice1'] = $_REQUEST['phone'];
        }
        if (isset($_REQUEST['phone2'])) {
            $contact['PhoneVoice2'] = $_REQUEST['phone2'];
        }
        if (isset($_REQUEST['phone3'])) {
            $contact['PhoneVoice3'] = $_REQUEST['phone3'];
        }
        if (isset($_REQUEST['Phone1CarrierID'])) {
            $contact['Phone1CarrierID'] = (int)$_REQUEST['Phone1CarrierID'];
        }
        if (isset($_REQUEST['Phone2CarrierID'])) {
            $contact['Phone2CarrierID'] = (int)$_REQUEST['Phone2CarrierID'];
        }
        if (isset($_REQUEST['Phone3CarrierID'])) {
            $contact['Phone3CarrierID'] = (int)$_REQUEST['Phone3CarrierID'];
        }
        if (isset($_REQUEST['email'])) {
            $contact['EmailAddress'] = trim($_REQUEST['email']);
        }

        $address['AddressID'] = (int)$patron->address_id;
        $address['StreetOne'] = $_REQUEST['address1'];
        if (isset($_REQUEST['address2'])) {
            $address['StreetTwo'] = $_REQUEST['address2'];
        }
        $address['City'] = $_REQUEST['city'];
        $address['State'] = $_REQUEST['state'];
        $address['PostalCode'] = $_REQUEST['zip'];
        // add address array to request.
        $contact['PatronAddresses'][] = $address;
        // pickup branch 
        $contact['RequestPickupBranchID'] = $_REQUEST['pickupLocation'];

        $errors = [];
        $request_url = $this->ws_url . "/patron/{$patron->barcode}";
        $extra_headers = ["Content-Type: application/json"];

        $r = $this->_doPatronRequest($patron, 'PUT', $request_url, $contact, $extra_headers);

        if ($r === null) {
            if (isset($this->papiLastErrorMessage)) {
                $errors[] = $this->papiLastErrorMessage;
            } else {
                $errors[] = "Unable to update profile. Please contact your library for further assistance.";
            }
            return $errors;
        }
        $errors[] = "Your contact information has been updated successfully.";
        return $errors;
    }

    /**
     * Updates the username of a patron using the Polaris API.
     *
     * This function sends a request to the Polaris API to update the patron's username. The new username is retrieved
     * from the request parameters. If the username field is empty or the update fails, an appropriate error message is returned.
     *
     * @param User $patron An instance of the User class representing the patron whose username needs to be updated.
     *
     * @return array An array containing error messages if the update fails or the username field is missing,
     * or an empty array if the update is successful.
     */
    protected function updatePatronUsername(User $patron): array
    {
        // /public/patron/{PatronBarcode}/username/{NewUsername}
        $this->_deleteCachePatronObject($patron->ilsUserId);
        $patron->clearCache();

        // update patron username
        $username = trim($_REQUEST['alternate_username']);
        if (empty($username)) {
            return ['Username field is required.'];
        }

        $request_url = $this->ws_url . "/patron/{$patron->barcode}/username/{$username}";

        $r = $this->_doPatronRequest($patron, 'PUT', $request_url);
        $errors = [];
        if ($r === null) {
            $error_message = "Unable to update username.";
            if (isset($this->papiLastErrorMessage)) {
                $error_message .= ' ' . $this->papiLastErrorMessage;
            } else {
                $error_message .= ' Please contact your library for further assistance.';
            }
            $errors[] = $error_message;
            return $errors;
        }
        // todo: profile page success messages are difficult. Need a better solution
        $errors[] = 'Your username has been updated successfully.';
        return $errors;
    }

    /**
     * Updates the PIN (Personal Identification Number) of a patron using the Polaris API.
     *
     * This function clears the cached patron secret and object, constructs a request to the Polaris API
     * to update the patron's PIN, and handles the response. The new PIN is obtained from the request parameters.
     * If the update is successful, the new PIN is updated in the local database. If the update fails,
     * an error message is returned.
     *
     * @param User $patron An instance of the User class representing the patron whose PIN needs to be updated.
     *
     * @return string|array A success message indicating that the PIN was updated, or an array containing an error message if the update fails.
     */
    public function updatePin(User $patron)
    {
        // clear the cached patron secrete and patron object
        $this->_deleteCachePatronSecret($patron->ilsUserId, $patron->getPassword());
        $this->_deleteCachePatronObject($patron->ilsUserId);
        $patron->clearCache();
        // /public/patron/{PatronBarcode}
        // required credentials
        $update['LogonBranchID'] = 1; // default to system
        $update['LogonUserID'] = $this->configArray['Polaris']['staffUserId'];
        $update['LogonWorkstationID'] = $this->configArray['Polaris']['workstationId'];
        // new pin
        $new_pin = trim($_REQUEST['pin1']);
        $update['Password'] = $new_pin;

        $request_url = $this->ws_url . "/patron/" . $patron->barcode;
        $extra_headers = ["Content-Type: application/json"];

        $errors = [];
        $r = $this->_doPatronRequest($patron, 'PUT', $request_url, $update, $extra_headers);
        if ($r === null) {
            $error_message = "Unable to update pin.";
            if (isset($this->papiLastErrorMessage)) {
                $error_message .= ' ' . $this->papiLastErrorMessage;
            }
            $errors[] = $error_message;
            return $errors;
        }
        // success update the pin in the database
        $patron->setPassword($new_pin);
        $patron->update();

        $errors[] = 'Your ' . translate('pin') . ' was updated successfully.';
        return $errors;
    }

    protected function updateNotificationsPreferences($patron)
    {
        $this->_deleteCachePatronObject($patron->ilsUserId);
        $patron->clearCache();

        $notification_preferences = [];
        // required credentials
        $notification_preferences['LogonBranchID'] = 1; // default to system
        $notification_preferences['LogonUserID'] = (int)$this->configArray['Polaris']['staffUserId'];
        $notification_preferences['LogonWorkstationID'] = (int)$this->configArray['Polaris']['workstationId'];
        // notifications
        $notification_preferences['DeliveryOptionID'] = (int)$_REQUEST['notification_method'];
        $notification_preferences['EmailFormat'] = (int)$_REQUEST['email_format'];
        $notification_preferences['EReceiptOptionID'] = (int)$_REQUEST['ereceipt_method'];

        $errors = [];

        $request_url = $this->ws_url . "/patron/{$patron->barcode}";
        $extra_headers = ["Content-Type: application/json"];

        $r = $this->_doPatronRequest($patron, 'PUT', $request_url, $notification_preferences, $extra_headers);

        if ($r === null) {
            if (isset($this->papiLastErrorMessage)) {
                $errors[] = $this->papiLastErrorMessage;
            } else {
                $errors[] = "Unable to update notification preferences. Please contact your library for further assistance.";
            }
            return $errors;
        }
        $errors[] = "Your notification preferences have been updated successfully.";
        return $errors;
    }

    public function resetPin($patron, $newPin, $resetToken)
    {
        // for Polaris implementation of resetPin, we don't need a user in database
        // thus the user id can be either an ILS id or a Pika id.
        $user_id = trim($_REQUEST['uid']);
        $barcode = trim($_REQUEST['bc']);
        $pin = $newPin;
        
        $pinReset = new PinReset();
        $pinReset->userId = $user_id;
        $pinReset->find(true);
        if ($pinReset->N === 0) {
            return [
                'error' => 'Unable to reset your ' . translate('pin') . '. You have not requested a ' .
                    translate('pin') . ' reset.',
            ];
        }
        // expired?
        if ($pinReset->expires < time()) {
            return ['error' => 'The reset token has expired. Please request a new ' . translate('pin') . ' reset.'];
        }
        $token = $pinReset->selector . $pinReset->token;
        // make sure and type cast the two numbers
        if ((int)$token !== (int)$resetToken) {
            return ['error' => 'Unable to reset your ' . translate('pin') . '. Invalid reset token.'];
        }

        // delete possible cached user object
        $this->_deleteCachePatronObject($user_id);
        
        // everything is good, update PIN in Polaris
        // use staff credentials for public call
        $login_user_id = (int)$this->configArray['Polaris']['staffUserId'];
        $login_user_workstation_id = (int)$this->configArray['Polaris']['workstationId'];

        $staff_auth = $this->authenticateStaff();
        if($staff_auth === null) {
            return ['error' => 'An error occurred while processing your request. Please visit your library to reset your ' . translate('pin') . '.'];
        }
        $staff_secret = $staff_auth->AccessSecret;
        $staff_token = $staff_auth->AccessToken;

        $request_url = $this->ws_url . '/patron/' . $barcode;
        $hash = $this->_createHash('PUT', $request_url, $staff_secret);

        $body['LogonBranchID'] = 1; // default to system
        $body['LogonUserID'] = $login_user_id;
        $body['LogonWorkstationID'] = $login_user_workstation_id;
        $body['Password'] = (string)$pin;
        $body = json_encode($body);

        $headers = [
            "PolarisDate: " . gmdate('r'),
            "X-PAPI-AccessToken:" . $staff_token,
            "Authorization: PWS pika:" . $hash,
            "Accept: application/json",
            "Content-Type: application/json",
            'Content-Length: ' . strlen($body)
        ];

        $c = new Curl();
        $c->setUrl($request_url);
		    // NOTE: Setting the URL first before setting the options is important to get a good response from Polaris
        $c->setOpt(CURLOPT_RETURNTRANSFER, true);
        $c->setOpt(CURLOPT_POSTFIELDS, $body);
        $c->setOpt(CURLOPT_CUSTOMREQUEST, 'PUT');
        // this needs to be set LAST!
        $c->setOpt(CURLOPT_HTTPHEADER, $headers);
        $c->exec();

        // errors
        if ($c->error || $c->httpStatusCode !== 200) {
            $this->logger->error('Curl error: ' . $c->errorMessage, [
                'http_code' => $c->httpStatusCode,
                'request_url' => $request_url,
                'Headers' => var_export($c->requestHeaders, true)
            ]);
            return ['error' => 'An error occurred while processing your request. Please visit your library to reset your ' . translate('pin') . '.'];
        } elseif ($error = $this->_isPapiError($c->response)) {
            $this->_logPapiError($error);
            $this->papiLastErrorMessage = $c->response->ErrorMessage;
            return ['error' => 'An error occurred while processing your request.' . $c->response->ErrorMessage];
        }
        $pinReset->delete();
        return true;
    }

    /**
     * emailResetPin
     *
     * Sends an email reset link to the patrons email address
     *
     * @param string $barcode
     * @return array|bool        true if email is sent, error array on fail
     * @throws ErrorException
     * @throws Exception
     */
    public function emailResetPin(string $barcode)
    {
        // check if the user is valid, use staff session 
        $res = $this->validatePatron($barcode, null, true);
        if($res === null) {
            return ['error' => 'The barcode you provided is not valid. Please check the barcode and try again.'];
        }
        
        if($res->ValidPatron === false) {
            return ['error' => 'The barcode you provided is not valid. Please check with your local library to reset your .'];
        }
        // get the patron info from the Polaris, don't count on Pika database being correct.
        // use staff credentials for the request.
        $staff_auth = $this->authenticateStaff();
        if($staff_auth === null) {
            return ['error' => 'An error occurred while processing your request. Please visit your library to reset your ' . translate('pin') . '.'];
        }
        
        $staff_secret = $staff_auth->AccessSecret;
        $staff_token = $staff_auth->AccessToken;
        
        // get patron data
        // we need to find an email address for this patron
        $request_url = $this->ws_url . '/patron/' . $barcode . '/basicdata?addresses=false';
        $hash = $this->_createHash('GET', $request_url, $staff_secret);

        $headers = [
            "PolarisDate: " . gmdate('r'),
            "X-PAPI-AccessToken: " . $staff_token,
            "Authorization: PWS " . $this->ws_access_id . ":" . $hash,
            "Accept: application/json",
        ];
        
        $c_opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            //CURLOPT_SSL_VERIFYPEER => 0,
            //CURLOPT_SSL_VERIFYHOST => 0
        ];

        $c = new Curl();
        $c->setOpts($c_opts);
        $c->get($request_url);

        if ($c->error || $c->httpStatusCode !== 200) {
            $this->logger->error(
                'Curl error: ' . $c->errorMessage,
                ['http_code' => $c->httpStatusCode],
                ['RequestURL' => $request_url, 'RequestHeaders' => $headers],
            );
            return ['error' => 'An error occurred while processing your request. Please try again later or contact your library administrator.'];
        }
        
        if ($error = $this->_isPapiError($c->response)) {
            $this->_logPapiError($error);
            return ['error' => 'An error occurred while processing your request. ' . $error['ErrorMessage']];
        }
        
        $patron_email = $c->response->PatronBasicData->EmailAddress ?? null;
        $patron_ils_id = $c->response->PatronBasicData->PatronID ?? null;
        // If the email is empty at this point we don't have a good address for the patron.
        if ($patron_email === null) {
            return [
                'error' => 'You do not have an email address on your account. Please visit your library to reset your ' . translate('pin') . '.',
            ];
        }
        
        if ($patron_ils_id === null) {
            return [
                'error' => 'We are unable to find your account. Please visit your library to reset your ' . translate('pin') . '.',
            ];
        }
        // check for a pika user
        $patron = new User();
        $patron->barcode = $barcode;
        $patron->ilsUserId = $patron_ils_id;
        
        if($patron->find(true)) {
            $patron_id = $patron->id;
        } else {
            $patron_id = $patron_ils_id;
        }
        
        // make sure there's no old token.
        $pinReset = new PinReset();
        $pinReset->userId = $patron_id;
        $pinReset->delete();

        $resetToken = $pinReset->insertReset();
        // build reset url (Note: the site url gets automatically set as the interface url
        // for Polaris, add the barcode to the URL so we can properly find the patron in the ILS
        $resetUrl = $this->configArray['Site']['url'] . "/MyAccount/ResetPin?uid=" . $patron_id . '&resetToken=' . $resetToken . 
        '&bc=' . $barcode;

        // build the message
        $pin = translate('pin');
        $subject = '[DO NOT REPLY] ' . ucfirst($pin) . ' Reset Link';

        global $interface;
        $interface->assign('pin', $pin);
        $interface->assign('resetUrl', $resetUrl);
        $htmlMessage = $interface->fetch('Emails/pin-reset-email.tpl');

        $mail = new PHPMailer();
        $mail->setFrom($this->configArray['Site']['email'], $this->configArray['Site']['title'], 0);
        $mail->addAddress($patron->email);
        $mail->Subject = $subject;
        $mail->msgHTML($htmlMessage);
        $mail->AltBody = strip_tags($htmlMessage);

        if (!$mail->send()) {
            $this->logger->error('Failed to send email pin reset. ' . $mail->ErrorInfo);
            return ['error' => "We're sorry. We are unable to send mail at this time. Please try again."];
        }
        return true;
    }

    /**
     * If library uses username field
     *
     * @return bool
     */
    public function hasUsernameField()
    {
        if (isset($this->configArray['OPAC']['allowUsername'])) {
            return (bool)$this->configArray['OPAC']['allowUsername'];
        } else {
            return false;
        }
    }
    
    protected function getPolarisOrganizations()
    {
        // /public/organizations/all
        $orgs_cache_key = "polaris_organizations_all";
        if ($orgs = $this->cache->get($orgs_cache_key)) {
            return $orgs;
        }
        $request_url = $this->ws_url . "/organizations/all";
        $orgs = $this->_doSystemRequest("GET", $request_url);
        if ($orgs === null) {
            return $orgs;
        }
        // set a long-ish cache life
        $ttl = 60 * 60 * 144;
        $this->cache->set($orgs_cache_key, $orgs, $ttl);
        return $orgs;
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
            // remove possible "-" from minutes
            $minutes = str_replace("-", "", $minutes);
            $dateTime->setTimezone(new DateTimeZone("$hours:$minutes"));

            // Return the date in ISO 8601 format
            return $dateTime->format('c');
        } else {
            throw new RuntimeException("Invalid Microsoft date format: $microsoftDate");
        }
    }
    protected array $polaris_errors = [
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
} // end class Polaris
