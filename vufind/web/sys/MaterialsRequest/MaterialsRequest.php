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
 * Table Definition for Materials Request
 */
require_once 'DB/DataObject.php';

class MaterialsRequest extends DB_DataObject {
	public $__table = 'materials_request';   // table name

	// Note: if table column names are changed, data for class MaterialsRequestFieldsToDisplay will need updated.
	public $id;
	public $libraryId;
	public $title;
	public $season;
	public $magazineTitle;
	public $magazineDate;
	public $magazineVolume;
	public $magazineNumber;
	public $magazinePageNumbers;
	public $author;
	public $format;
	public $formatId;
	public $subFormat;
	public $ageLevel;
	public $bookType;
	public $isbn;
	public $upc;
	public $issn;
	public $oclcNumber;
	public $publisher;
	public $publicationYear;
	public $abridged;
	public $about;
	public $comments;
	public $status;
	public $phone;
	public $email;
	public $dateCreated;
	public $createdBy;
	public $dateUpdated;
	public $emailSent;
	public $holdsCreated;
	public $placeHoldWhenAvailable;
	public $illItem;
	public $holdPickupLocation;
	public $bookmobileStop;
	public $assignedTo;

	//Dynamic properties setup by joins
	public $numRequests;
	public $description;
	public $userId;
	public $firstName;
	public $lastName;

	function keys(){
		return ['id'];
	}

	static function getFormats(){
		require_once ROOT_DIR . '/sys/MaterialsRequest/MaterialsRequestFormats.php';
		$availableFormats = [];
		$customFormats    = new MaterialsRequestFormats();
		global $library;
		$requestLibrary = $library;
		if (UserAccount::isLoggedIn()){
			$user        = UserAccount::getLoggedInUser();
			$homeLibrary = $user->getHomeLibrary();
			if (isset($homeLibrary)){
				$requestLibrary = $homeLibrary;
			}
		}

		$customFormats->libraryId = $requestLibrary->libraryId;

		if ($customFormats->count() == 0){
			// Default Formats to use when no custom formats are created.

			/** @var MaterialsRequestFormats[] $defaultFormats */
			$defaultFormats   = MaterialsRequestFormats::getDefaultMaterialRequestFormats($requestLibrary->libraryId);
			$availableFormats = [];

			global $configArray;
			foreach ($defaultFormats as $index => $materialRequestFormat){
				$format = $materialRequestFormat->format;
				if (isset($configArray['MaterialsRequestFormats'][$format]) && $configArray['MaterialsRequestFormats'][$format] == false){
					// dont add this format
				}else{
					$availableFormats[$format] = $materialRequestFormat->formatLabel;
				}
			}

		}else{
			$customFormats->orderBy('weight');
			$availableFormats = $customFormats->fetchAll('format', 'formatLabel');
		}

		return $availableFormats;
	}

	public function getFormatObject(){
		if (!empty($this->libraryId) && !empty($this->format)){
			require_once ROOT_DIR . '/sys/MaterialsRequest/MaterialsRequestFormats.php';
			$format            = new MaterialsRequestFormats();
			$format->format    = $this->format;
			$format->libraryId = $this->libraryId;
			if ($format->find(1)){
				return $format;
			}else{
				foreach (MaterialsRequestFormats::getDefaultMaterialRequestFormats($this->libraryId) as $defaultFormat){
					if ($this->format == $defaultFormat->format){
						return $defaultFormat;
					}

				}
			}
		}
		return false;
	}

	static $materialsRequestEnabled = null;

	static function enableMaterialsRequest($forceReload = false){
		if (MaterialsRequest::$materialsRequestEnabled != null && $forceReload == false){
			return MaterialsRequest::$materialsRequestEnabled;
		}
		global $configArray;
		global $library;

		//First make sure we are enabled in the config file
		if (!empty($configArray['MaterialsRequest']['enabled'])){
			$enableMaterialsRequest = $configArray['MaterialsRequest']['enabled'];
			//Now check if the library allows material requests
			if ($enableMaterialsRequest){
				if (isset($library) && $library->enableMaterialsRequest == 0){
					$enableMaterialsRequest = false;
				}elseif (UserAccount::isLoggedIn()){
//					$homeLibrary = Library::getPatronHomeLibrary();
					$homeLibrary = UserAccount::getUserHomeLibrary();
					if (is_null($homeLibrary)){
						$enableMaterialsRequest = false;
					}elseif ($homeLibrary->enableMaterialsRequest == 0){
						$enableMaterialsRequest = false;
					}elseif (isset($library) && $homeLibrary->libraryId != $library->libraryId){
						$enableMaterialsRequest = false;
					}elseif (!empty($configArray['MaterialsRequest']['allowablePatronTypes'])){
						//Check to see if we need to do additional restrictions by patron type
						$allowablePatronTypes = $configArray['MaterialsRequest']['allowablePatronTypes'];
						if (!preg_match("/^$allowablePatronTypes$/i", UserAccount::getUserPType())){
							$enableMaterialsRequest = false;
						}
					}
				}
			}
		}else{
			$enableMaterialsRequest = false;
		}
		MaterialsRequest::$materialsRequestEnabled = $enableMaterialsRequest;
		return $enableMaterialsRequest;
	}

	function getHoldLocationName($locationId){
		require_once ROOT_DIR . '/sys/Location/Location.php';
		$holdLocation = new Location();
		if ($holdLocation->get($locationId)){
			return $holdLocation->displayName;
		}
		return false;
	}

	function getRequestFormFields($libraryId, $isStaffRequest = false){
		require_once ROOT_DIR . '/sys/MaterialsRequest/MaterialsRequestFormFields.php';
		$formFields            = new MaterialsRequestFormFields();
		$formFields->libraryId = $libraryId;
		$formFields->orderBy('weight');
		/** @var MaterialsRequestFormFields[] $fieldsToSortByCategory */
		$fieldsToSortByCategory = $formFields->fetchAll();

		// If no values set get the defaults.
		if (empty($fieldsToSortByCategory)){
			$fieldsToSortByCategory = $formFields::getDefaultFormFields($libraryId);
		}

		if (!$isStaffRequest){
			foreach ($fieldsToSortByCategory as $fieldKey => $fieldDetails){
				if (in_array($fieldDetails->fieldType, ['assignedTo', 'createdBy', 'libraryCardNumber', 'id', 'status'])){
					unset($fieldsToSortByCategory[$fieldKey]);
				}
			}
		}

		// If we use another interface variable that is sorted by category, this should be a method in the Interface class
		$requestFormFields = [];
		if ($fieldsToSortByCategory){
			foreach ($fieldsToSortByCategory as $formField){
				if (!array_key_exists($formField->formCategory, $requestFormFields)){
					$requestFormFields[$formField->formCategory] = [];
				}
				$requestFormFields[$formField->formCategory][] = $formField;
			}
		}
		return $requestFormFields;
	}

	function getAuthorLabelsAndSpecialFields($libraryId){
		require_once ROOT_DIR . '/sys/MaterialsRequest/MaterialsRequestFormats.php';
		return MaterialsRequestFormats::getAuthorLabelsAndSpecialFields($libraryId);
	}


	/**
	 * Update a Request's status and send any emails doing so would cause.
	 *
	 * @param int $newStatus  status Id number
	 */
	function updateRequestStatus($newStatus){
		require_once ROOT_DIR . '/sys/MaterialsRequest/MaterialsRequestStatus.php';
		$materialsRequestStatus     = new MaterialsRequestStatus();
		$materialsRequestStatus->id = $newStatus;
		if ($materialsRequestStatus->find(true)){
			$this->status      = $newStatus;
			$this->dateUpdated = time();
			if ($this->update()){
				if ($materialsRequestStatus->sendEmailToPatron && $this->email){
					// Generate Email to Patron
					$replyToAddress = $emailSignature = '';
					if (!empty($this->assignedTo)){
						require_once ROOT_DIR . '/sys/Account/UserStaffSettings.php';
						$staffSettings = new UserStaffSettings();
						if ($staffSettings->get('userId', $this->assignedTo)){
							if (!empty($staffSettings->materialsRequestReplyToAddress)){
								$replyToAddress = $staffSettings->materialsRequestReplyToAddress;
							}
							if (empty($replyToAddress)){
								global $pikaLogger;
								$pikaLogger->error('Materials Request Staff User does not have a populated replyTo email address. User Id: ' . $this->assignedTo);
							}
							if (!empty($staffSettings->materialsRequestEmailSignature)){
								$emailSignature = $staffSettings->materialsRequestEmailSignature;
							}
						} else {
							global $pikaLogger;
							$pikaLogger->error('Did not find Materials Request Staff user. User Id: ' .$this->assignedTo);
						}
					} else {
						//TODO: Use current user info and assign to them?
					}

					$body = '*****This is an auto-generated email response. Please do not reply.*****';
					$body .= "\r\n\r\n" . $materialsRequestStatus->emailTemplate;

					if (!empty($emailSignature)){
						$body .= "\r\n\r\n" . $emailSignature;
					}

					//Replace tags with appropriate values
//					$materialsRequestUser     = new User();
//					$materialsRequestUser->id = $this->createdBy;
//					if ($materialsRequestUser->find(true)){
//						foreach ($materialsRequestUser as $fieldName => $fieldValue){
//							if (!is_array($fieldValue)){
//								$body = str_replace('{' . $fieldName . '}', $fieldValue, $body);
//							}
//						}
//					} else {
//						global $pikaLogger;
//						$pikaLogger->error('Failed to fetch Material Request Creator. ID: ' . $this->createdBy);
//					}
					foreach ($this as $fieldName => $fieldValue){
						if (!is_array($fieldValue)){
							$body = str_replace('{' . $fieldName . '}', $fieldValue, $body);
						}
					}

					global $configArray;
					require_once ROOT_DIR . '/sys/Mailer.php';
					$mail  = new VuFindMailer();
					$error = $mail->send($this->email, $configArray['Site']['email'], "Your Materials Request Update", $body, $replyToAddress);
					if (PEAR_Singleton::isError($error)){
						global $pikaLogger;
						$pikaLogger->error("Error sending Materials Request email: " . $error->message);
						return $error->message;
					} else {
						return true;
					}
				} else {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Remove any non-number character (except X) because ISBNs have an X check digit
	 *
	 * @param $string
	 * @return array|string|string[]|null
	 */
	function removeNonNumbers($string){
		return preg_replace('/[^\dX]/i', '', $string);
	}


	/**
	 *  Take an array of strings and build a quoted and escape list of them for use in an SQL query.
	 *
	 * @param string[] $stringArray
	 * @return string
	 */
	function buildListOfQuotedAndSQLEscapedItems(array $stringArray): string{
		$commaSeparatedList = '';
		foreach ($stringArray as $item){
			if (strlen($commaSeparatedList) > 0){
				$commaSeparatedList .= ',';
			}
			$commaSeparatedList .= "'" . $this->escape($item) . "'";
		}
		return $commaSeparatedList;
	}

}
