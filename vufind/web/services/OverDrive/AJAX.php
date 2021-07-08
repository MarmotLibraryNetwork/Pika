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

require_once ROOT_DIR . '/AJAXHandler.php';

use Pika\PatronDrivers\EcontentSystem\OverDriveDriverFactory;

class OverDrive_AJAX extends AJAXHandler {

	protected $methodsThatRespondWithJSONUnstructured = [
		'checkoutOverDriveTitle',
		'placeOverDriveHold',
		'freezeOverDriveHold',
		'thawOverDriveHold',
		'cancelOverDriveHold',
		'getOverDriveHoldPrompts',
		'getOverDriveFreezeHoldPrompts',
		'getOverDriveUpdateHoldPrompts',
		'returnOverDriveItem',
		'selectOverDriveDownloadFormat',
		'getDownloadLink',
		'getOverDriveCheckoutPrompts',
		'forceUpdateFromAPI',
		'getSupportForm',
		'submitSupportForm',
		'getIssuesList',
		'getOverDriveIssueCheckoutPrompt',
		'issueCheckoutPrompts',
		'doOverDriveMagazineIssueCheckout'
	];

	protected $methodsThatRespondThemselves = [];

	function forceUpdateFromAPI(){
		$id                            = $_REQUEST['id'];
		$overDriveProduct              = new Pika\BibliographicDrivers\OverDrive\OverDriveAPIProduct();
		$overDriveProduct->overdriveId = $id;
		if ($overDriveProduct->find(true)){
			if ($overDriveProduct->needsUpdate == true){
				return ['success' => true, 'message' => 'This title was already marked to be updated from the API again the next time the extract is run.'];
			}
			$overDriveProduct->needsUpdate = true;
			$numRows                       = $overDriveProduct->update();
			if ($numRows == 1){
				return ['success' => true, 'message' => 'This title will be updated from the API again the next time the extract is run.'];
			}else{
				return ['success' => false, 'message' => 'Unable to mark the title for needing update. Could not update the title.'];
			}
		}else{

			return ['success' => false, 'message' => 'Unable to mark the title for needing update. Could not find the title.'];
		}
	}

	function placeOverDriveHold(){
		$user = UserAccount::getLoggedInUser();
		if ($user){
			$patronId = $_REQUEST['patronId'];
			$patron   = $user->getUserReferredTo($patronId);
			if ($patron){
				$overDriveId = $_REQUEST['overDriveId'];
				if (!empty($overDriveId)){
					if (!empty($_REQUEST['rememberOverDriveEmail'])){
						// Update the user's overdrive email if the remember email was checked on
						if (!empty($_REQUEST['overDriveEmail']) && $_REQUEST['overDriveEmail'] != $patron->overDriveEmail){
							$patron->overDriveEmail = $_REQUEST['overDriveEmail'];
						}
						$patron->update();
					}

					$driver      = OverDriveDriverFactory::getDriver();
					$holdMessage = $driver->placeOverDriveHold($overDriveId, $patron, $_REQUEST['overDriveEmail']);
					if ($holdMessage['success']){
						$holdMessage['buttons'] = '<a class="btn btn-primary" href="/MyAccount/Holds" role="button">View My Holds</a>';
					}
					return $holdMessage;
				}
			}else{
				return ['success' => false, 'message' => 'Sorry, it looks like you don\'t have permissions to place holds for that user.'];
			}
		}else{
			return ['success' => false, 'message' => 'You must be logged in to place a hold.'];
		}
	}

	function checkoutOverDriveTitle(){
		$user = UserAccount::getLoggedInUser();
		if ($user){
			$patronId = $_REQUEST['patronId'];
			$patron   = $user->getUserReferredTo($patronId);
			if ($patron){
				if (isset($_REQUEST['useDefaultLendingPeriods'])){
					// Turn off prompt if this checkbox was checked ( to disable prompting for future checkouts)
					$patron->promptForOverDriveLendingPeriods = 0;
					$patron->update();
				}
				$driver            = OverDriveDriverFactory::getDriver();
				$overDriveId       = $_REQUEST['id'] ?? $_REQUEST['overDriveId'];
				if(!empty($_REQUEST['issueId'])){
					$overDriveId     = $_REQUEST['issueId'];
				}
				$lendingPeriod     = empty($_REQUEST['lendingPeriod']) ? null : $_REQUEST['lendingPeriod'];
				$formatType        = empty($_REQUEST['formatType']) ? null : $_REQUEST['formatType'];
				$result            = $driver->checkoutOverDriveTitle($overDriveId, $patron, $lendingPeriod, $formatType);
				if ($result['success']){
					$result['buttons'] ??= ''; // The response can return the buttons for placing a hold due to an error
					//TODO: there can be multiple formats; one of which could be applicable below
					if (!empty($result['formatType'])){
						switch ($result['formatType']){
							case 'ebook-overdrive':
								$result['buttons'] = '<a href="#" onclick="return Pika.OverDrive.followOverDriveDownloadLink(\'' . $patronId . "', '" . $overDriveId . "', '" . $result['formatType'] . '\')" class="btn btn-warning">Read Online Now</a>';
								break;
							case 'magazine-overdrive':
							if ($result['issueId']){
								// The checked out magazine issue will have a different id than the record we made the check out against.
								$result['buttons'] = '<a href="#" onclick="return Pika.OverDrive.followOverDriveDownloadLink(\'' . $patronId . "', '" . $result['issueId'] . "', '" . $result['formatType'] . '\')" class="btn btn-warning">Read Online Now</a>';
							}
							break;
							case 'ebook-mediado':
								$result['buttons']  = '<a href="#" onclick="return Pika.OverDrive.followOverDriveDownloadLink(\'' . $patronId . "', '" . $overDriveId . "', '" . $result['formatType'] . '\')" class="btn btn-warning">Read Online MediaDo Now</a>';
								break;
							case 'audiobook-overdrive':
								$result['buttons'] = '<a href="#" onclick="return Pika.OverDrive.followOverDriveDownloadLink(\'' . $patronId . "', '" . $overDriveId . "', '" . $result['formatType'] . '\')" class="btn btn-warning">Listen Online Now</a>';
								break;
							case 'video-streaming':
								$result['buttons'] = '<a href="#" onclick="return Pika.OverDrive.followOverDriveDownloadLink(\'' . $patronId . "', '" . $overDriveId . "', '" . $result['formatType'] . '\')" class="btn btn-warning">Watch Online Now</a>';
								break;
						}
					}
					$result['buttons'] .= '<a class="btn btn-primary" href="/MyAccount/CheckedOut" role="button">View My Check Outs</a>';
				}
				return $result;
			}else{
				return ['success' => false, 'message' => 'Sorry, it looks like you don\'t have permissions to checkout titles for that user.'];
			}
		}else{
			return ['success' => false, 'message' => 'You must be logged in to checkout an item.'];
		}
	}

	function returnOverDriveItem(){
		$user        = UserAccount::getLoggedInUser();
		$overDriveId = $_REQUEST['overDriveId'];
		if ($user){
			$patronId = $_REQUEST['patronId'];
			$patron   = $user->getUserReferredTo($patronId);
			if ($patron){
				$driver = OverDriveDriverFactory::getDriver();
				$result = $driver->returnOverDriveItem($overDriveId, $patron);
				//$logger->log("Checkout result = $result", PEAR_LOG_INFO);
				return $result;
			}else{
				return ['success' => false, 'message' => 'Sorry, it looks like you don\'t have permissions to return titles for that user.'];
			}
		}else{
			return ['success' => false, 'message' => 'You must be logged in to return an item.'];
		}
	}

	function selectOverDriveDownloadFormat(){
		$user = UserAccount::getLoggedInUser();
		if ($user){
			$patronId = $_REQUEST['patronId'];
			$patron   = $user->getUserReferredTo($patronId);
			if ($patron){
				$driver      = OverDriveDriverFactory::getDriver();
				$overDriveId = $_REQUEST['overDriveId'];
				$formatType  = $_REQUEST['formatType'];
				$result      = $driver->selectOverDriveDownloadFormat($overDriveId, $formatType, $patron);
				//$logger->log("Checkout result = $result", PEAR_LOG_INFO);
				return $result;
			}else{
				return ['success' => false, 'message' => 'Sorry, it looks like you don\'t have permissions to download titles for that user.'];
			}
		}else{
			return ['success' => false, 'message' => 'You must be logged in to download a title.'];
		}
	}

	function getDownloadLink(){
		$user = UserAccount::getLoggedInUser();
		if ($user){
			$patronId = $_REQUEST['patronId'];
			$patron   = $user->getUserReferredTo($patronId);
			if ($patron){
				$driver      = OverDriveDriverFactory::getDriver();
				$overDriveId = $_REQUEST['overDriveId'];
				$formatType  = $_REQUEST['formatType'];
				$result      = $driver->getDownloadLink($overDriveId, $formatType, $patron);
				return $result;
			}else{
				return ['success' => false, 'message' => 'Sorry, it looks like you don\'t have permissions to download titles for that user.'];
			}
		}else{
			return ['success' => false, 'message' => 'You must be logged in to download a title.'];
		}
	}

	function getOverDriveHoldPrompts(){
		$user = UserAccount::getLoggedInUser();
		global $interface;
		$id = $_REQUEST['id'];
		$interface->assign('overDriveId', $id);
		if ($user->overDriveEmail == 'undefined'){
			$user->overDriveEmail = '';
		}
		$promptForEmail = false;
		if (strlen($user->overDriveEmail) == 0 || $user->promptForOverDriveEmail == 1){
			$promptForEmail = true;
		}

		$overDriveUsers = $user->getRelatedOverDriveUsers();
		$interface->assign('overDriveUsers', $overDriveUsers);
		if (count($overDriveUsers) == 1){
			$interface->assign('patronId', reset($overDriveUsers)->id);
		}

		$interface->assign('overDriveEmail', $user->overDriveEmail);
		$interface->assign('promptForEmail', $promptForEmail);
		if ($promptForEmail || count($overDriveUsers) > 1){
			$promptTitle = 'OverDrive Hold Options';
			return [
				'promptNeeded' => true,
				'promptTitle'  => $promptTitle,
				'prompts'      => $interface->fetch('OverDrive/ajax-overdrive-hold-prompt.tpl'),
				'buttons'      => '<input class="btn btn-primary" type="submit" name="submit" value="Place Hold" onclick="return Pika.OverDrive.processOverDriveHoldPrompts();">',
			];
		}else{
			return [
				'patronId'                => reset($overDriveUsers)->id,
				'promptNeeded'            => false,
				'overDriveEmail'          => $user->overDriveEmail,
//				'rememberOverdriveEmail'  => $promptForEmail,
			];
		}
	}

	function getOverDriveFreezeHoldPrompts(){
		$user = UserAccount::getLoggedInUser();
		if (!empty($user)){
			$patronId = $_REQUEST['patronId'];
			$patron   = $user->getUserReferredTo($patronId);
			if ($patron){
				$id = $_REQUEST['id'];
				if (!empty($id)){
					global $interface;
					$interface->assign('overDriveId', $id);
					$interface->assign('patronId', $patronId);
					if ($user->overDriveEmail == 'undefined'){
						$user->overDriveEmail = '';
					}
					$promptForEmail = false;
					if (strlen($user->overDriveEmail) == 0 || $user->promptForOverDriveEmail == 1){
						$promptForEmail = true;
					}
					if (!empty($_REQUEST['thawDate'])){
						$thawDate = date('m-d-Y', $_REQUEST['thawDate']);
						if ($thawDate){
							$interface->assign('thawDate', $thawDate);
						}

					}
					$interface->assign('overDriveEmail', $user->overDriveEmail);
					$interface->assign('promptForEmail', $promptForEmail);
					$title       = translate('Freeze Hold');// language customization
					$promptTitle = "OverDrive $title Options";
					return [
						'title'   => $promptTitle,
						'message' => $interface->fetch('OverDrive/ajax-overdrive-freeze-hold-prompt.tpl'),
						'buttons' => '<input class="btn btn-primary" type="submit" name="submit" value="' . $title . '" onclick="$(\'#overdriveFreezeHoldPromptsForm\').submit(); return false;">',
					];
				}
			}else{
				return ['success' => false, 'message' => 'Sorry, it looks like you don\'t have permissions to ' .translate("freeze"). ' titles for that user.'];
			}
		}else{
			return ['success' => false, 'message' => 'You must be logged in to '.translate("freeze"). 'an item'];
		}
	}

	function getOverDriveUpdateHoldPrompts(){
		$user = UserAccount::getLoggedInUser();
		if (!empty($user)){
			$patronId = $_REQUEST['patronId'];
			$patron   = $user->getUserReferredTo($patronId);
			if ($patron){
				$id = $_REQUEST['id'];
				if (!empty($id)){
					global $interface;
					$interface->assign('overDriveId', $id);
					$interface->assign('patronId', $patronId);
					if ($user->overDriveEmail == 'undefined'){
						$user->overDriveEmail = '';
					}
					$promptForEmail = false;
					if (strlen($user->overDriveEmail) == 0 || $user->promptForOverDriveEmail == 1){
						$promptForEmail = true;
					}
					if (!empty($_REQUEST['thawDate'])){
						$thawDate = date('m-d-Y', $_REQUEST['thawDate']);
						if ($thawDate){
							$interface->assign('thawDate', $thawDate);
						}

					}
					$interface->assign('overDriveEmail', $user->overDriveEmail);
					$interface->assign('promptForEmail', $promptForEmail);
					$title       = translate('Freeze Hold');// language customization
					$promptTitle = "OverDrive Hold Options";
					return [
						'title'   => $promptTitle,
						'message' => $interface->fetch('OverDrive/ajax-overdrive-freeze-hold-prompt.tpl'),
						'buttons' =>  $interface->fetch('OverDrive/ajax-overdrive-freeze-hold-buttons.tpl'),
//						'buttons' => '<input class="btn btn-primary" type="submit" name="submit" value="' . $title . '" onclick="$(\'#overdriveFreezeHoldPromptsForm\').submit(); return false;">',
					];
				}
			}else{
				return ['success' => false, 'message' => 'Sorry, it looks like you don\'t have permissions to ' .translate("freeze"). ' titles for that user.'];
			}
		}else{
			return ['success' => false, 'message' => 'You must be logged in to '.translate("freeze"). 'an item'];
		}
	}

	function getIssuesList(){
		$parentId = $_REQUEST['parentId'];
			$overdriveIssues = new Pika\BibliographicDrivers\OverDrive\OverDriveAPIMagazineIssues();
			$data = [];

				$overdriveIssues->parentId = $parentId;
				$overdriveIssues->find();
				$issuesList = array_reverse($overdriveIssues->fetchAll());
				$i=0;
				foreach($issuesList as $issue)
				{
					$formatted =  "<div id=\"scrollerTitleIssues" . $i ."\" class=\"scrollerTitle\" onclick=\"Pika.OverDrive.checkoutOverdriveMagazineByIssueID('" . $issue->overdriveId . "')\"><img src=\"". $issue->coverUrl . "\" class=\"scrollerTitleCover\" alt=\"" . $issue->edition ."\"></div>";

					$issues = [
						'id' => $issue->overdriveId,
						'image' => $issue->coverUrl,
						'author' => '',
						'title' => $issue->edition,
						'formattedTitle' => $formatted
					];

					$data[$i] = $issues;
					$i++;
				}
			global $timer;
			$timer->logTime("Finished getIssues for OverDrive record {$parentId}");

		return $data;
	}

	function freezeOverDriveHold(){
		$user = UserAccount::getLoggedInUser();
		if ($user){
			$patronId = $_REQUEST['patronId'];
			$patron   = $user->getUserReferredTo($patronId);
			if ($patron){
				$overDriveId = $_REQUEST['overDriveId'];
				if (!empty($overDriveId)){
					if (!empty($_REQUEST['rememberOverDriveEmail'])){
						// Update the user's overdrive email if the remember email was checked on
						if (!empty($_REQUEST['overDriveEmail']) && $_REQUEST['overDriveEmail'] != $patron->overDriveEmail){
							$patron->overDriveEmail = $_REQUEST['overDriveEmail'];
						}
						$patron->update();
					}

					$daysFromNow = null;
					if (!empty($_REQUEST['thawDate'])){
						$thawDate = DateTime::createFromFormat('m-d-Y', $_REQUEST['thawDate']);
						if ($thawDate){
							$diff = $thawDate->diff(new DateTime(), true);
							if ($diff->days !== false){ //Could be zero days
								$daysFromNow = $diff->days;
							}
						}
					}
					$driver = OverDriveDriverFactory::getDriver();
					$result = $driver->freezeOverDriveHold($overDriveId, $patron, $_REQUEST['overDriveEmail'], $daysFromNow);
					return $result;
				}
			}else{
				return ['success' => false, 'message' => 'Sorry, it looks like you don\'t have permissions to update holds for that user.'];
			}
		}else{
			return ['success' => false, 'message' => 'You must be logged in to update a hold.'];
		}
	}

	function getOverDriveCheckoutPrompts(){
		global $interface;
		$user           = UserAccount::getLoggedInUser();
		$issueId = "";
		$isMagazine =false;
		$overDriveUsers = $user->getRelatedOverDriveUsers();
		if (!empty($overDriveUsers)){
			$id = $_REQUEST['id'];
			if (empty($_REQUEST['formatType'])){
				$recordDriver   = new \OverDriveRecordDriver($id);
				$formats        = $recordDriver->getItems();
				$lendingPeriods = [];
				$formatClass    = [];

				foreach ($formats as $format){
					if ($format->textId == 'magazine-overdrive'){
						$isMagazine = true;
						if(!empty($_REQUEST['issueId'])){
						$issueId = $_REQUEST['issueId'];
							}
						break;
					}else{
						$tmp = $format->getFormatClass();
						if ($tmp){ // avoid things with out a format class (magazine)
							$formatClass[] = $tmp;
						}
					}
				}
				$formatClass = array_unique($formatClass);
				$formatClass = count($formatClass) == 1 ? $formatClass[0] : null;  // can only setting lending period options for one format class (count() should be either 1 or 0 (0 for magazines)
			}else{
				$overDriveFormat = new Pika\BibliographicDrivers\OverDrive\OverDriveAPIProductFormats();
				$formatClass     = $overDriveFormat->getFormatClass($_REQUEST['formatType']);
				if($_REQUEST['formatType']=="magazine-overdrive")
				{
					$isMagazine = true;
					if(!empty($_REQUEST['issueId'])){
						$issueId = $_REQUEST['issueId'];
					}
				}
				$interface->assign('formatType', $_REQUEST['formatType']);
			}
			$interface->assign('issueId', $issueId);
			$interface->assign('isMagazine', $isMagazine);
			$interface->assign('overDriveId', $id);
			$interface->assign('overDriveUsers', $overDriveUsers);

			if ($formatClass){
				foreach ($overDriveUsers as &$tmpUser){
					if ($tmpUser->promptForOverDriveLendingPeriods){
						$cache    ??= new Pika\Cache();
						$cacheKey = $cache->makePatronKey('overdrive_settings', $tmpUser->id);
						$settings = $cache->get($cacheKey);
						if (empty($settings)){
							$driver   ??= OverDriveDriverFactory::getDriver(); //PHPStorm highlights this as an error, but it *does* in fact work. pascal 10/16/20
							$settings = $driver->getUserOverDriveAccountSettings($tmpUser);
						}
						if (isset($settings['lendingPeriods'][$formatClass])){
							$tmpUser->lendingPeriod       = $settings['lendingPeriods'][$formatClass]->lendingPeriod; // Assign setting to the User Object for the template to use
							$lendingPeriods[$tmpUser->id] = $settings['lendingPeriods'][$formatClass]->options;
						}
					}
				}
				$interface->assign('lendingPeriods', $lendingPeriods);
			}

			if($isMagazine)
			{
				$rd = new OverdriveRecordDriver($id);
				$issues = $rd->getMagazineIssues();
				$interface->assign('issues', $issues);
				return [
					'promptNeeded' => true,
					'promptTitle'  => 'OverDrive Magazine Issue Checkout Options',
					'prompts'      => $interface->fetch('OverDrive/ajax-overdrive-issue-checkout-prompt.tpl'),
					'buttons'      => '<input class="btn btn-primary" type="submit" name="submit" value="Checkout Title" onclick="return Pika.OverDrive.processOverDriveCheckoutPrompts();">',
				];
			}

			if (count($overDriveUsers) > 1 || ($user->promptForOverDriveLendingPeriods && !$isMagazine)){
				return [
					'promptNeeded' => true,
					'promptTitle'  => 'OverDrive Checkout Options',
					'prompts'      => $interface->fetch('OverDrive/ajax-overdrive-checkout-prompt.tpl'),
					'buttons'      => '<input class="btn btn-primary" type="submit" name="submit" value="Checkout Title" onclick="return Pika.OverDrive.processOverDriveCheckoutPrompts();">',
				];
			}elseif (count($overDriveUsers) == 1){
				$_REQUEST['patronId'] = $user->id;
				return $this->checkoutOverDriveTitle();
			}
		}else{
			// No Overdrive Account Found, give the user an error message
			global $logger;
			$logger->log('No valid Overdrive account was found to check out an Overdrive title. UserID : ' . $user->id, PEAR_LOG_ERR);
			return [
				'promptNeeded' => true,
				'promptTitle'  => 'Error',
				'prompts'      => 'No valid Overdrive account was found to check this title out with.',
				'buttons'      => '',
			];
		}
	}

	function cancelOverDriveHold(){
		$user = UserAccount::getLoggedInUser();
		if ($user){
			$patronId = $_REQUEST['patronId'];
			$patron   = $user->getUserReferredTo($patronId);
			if ($patron){
				$overDriveId = $_REQUEST['overDriveId'];
				if ($overDriveId){
					$driver = OverDriveDriverFactory::getDriver();
					$result = $driver->cancelOverDriveHold($overDriveId, $patron);
					return $result;
				}
			}else{
				return ['success' => false, 'message' => 'Sorry, it looks like you don\'t have permissions to download cancel holds for that user.'];
			}
		}else{
			return ['success' => false, 'message' => 'You must be logged in to cancel holds.'];
		}
	}

	function thawOverDriveHold(){
		$thaw    = translate('thaw');
		$Thawing = ucfirst(translate("thawing"));
		$user    = UserAccount::getLoggedInUser();
		if ($user){
			$patronId = $_REQUEST['patronId'];
			$patron   = $user->getUserReferredTo($patronId);
			if ($patron){
				$overDriveId = $_REQUEST['overDriveId'];
				if (!empty($overDriveId)){
					$driver          = OverDriveDriverFactory::getDriver();
					$result          = $driver->thawOverDriveHold($overDriveId, $patron);
					$result['title'] = ($result['success'] ? 'Success ': 'Error ' ) . $Thawing . ' OverDrive Hold';
					return $result;
				}
			}else{
				return ['success' => false, 'title' => 'Error ' . $Thawing . ' OverDrive Hold', 'message' => "Sorry, it looks like you don't have permissions to download $thaw holds for that user."];
			}
		}else{
			return ['success' => false, 'title' => 'Error ' . $Thawing . ' OverDrive Hold', 'message' => "You must be logged in to $thaw holds."];
		}
	}

	function getSupportForm(){
		global $interface;
		$user = UserAccount::getActiveUserObj();

		// Presets for the form to be filled out with
		$interface->assign('lightbox', true);
		if ($user){
			$name = $user->firstname . ' ' . $user->lastname;
			$interface->assign('name', $name);
			$interface->assign('email', $user->email);
			$interface->assign('libraryCardNumber', $user->getBarcode());
		}

		if (!empty($_REQUEST['id'])){
			require_once ROOT_DIR . '/RecordDrivers/OverDriveRecordDriver.php';
			$overDriveId  = $_REQUEST['id'];
			$recordDriver = new OverDriveRecordDriver($overDriveId, -1); // (Don't need to load grouped work)
			if ($recordDriver->isValid()){
				$author         = $recordDriver->getAuthor();
				$titleAndAuthor = $recordDriver->getTitle() . (!empty($author) ? ' by ' . $author : '');
				$interface->assign('titleAndAuthor', $titleAndAuthor);
				$formats = [];
				foreach ($recordDriver->getItems() as $format){
					$formats[$format->textId] = $format->name;
				}
				$interface->assign('formats', $formats);
			}
			$interface->assign('overDriveId', $overDriveId);
		}

		$results = [
			'title'        => 'OverDrive Support Request',
			'modalBody'    => $interface->fetch('OverDrive/eContentSupport.tpl'),
			'modalButtons' => '<span class="tool btn btn-primary" onclick="return $(\'#eContentSupport\').submit()">Submit</span>', // .submit() triggers form validation
		];
		return $results;
	}

	function submitSupportForm(){
		global $interface;
		global $configArray;

		if (isset($_REQUEST['submit'])){
			//E-mail the library with details of the support request
			require_once ROOT_DIR . '/sys/Mailer.php';
			$mail        = new VuFindMailer();
			$userLibrary = UserAccount::getUserHomeLibrary();
			if (!empty($userLibrary->eContentSupportAddress)){
				$to = $userLibrary->eContentSupportAddress;
			}elseif (!empty($configArray['Site']['email'])){
				$to = $configArray['Site']['email'];
			}else{
				return [
					'title'   => 'Support Request Not Sent',
					'message' => "<p>We're sorry, but your request could not be submitted because we do not have a support email address on file.</p><p>Please contact your local library.</p>"
				];
			}
			$multipleEmailAddresses = preg_split('/[;,]/', $to, null, PREG_SPLIT_NO_EMPTY);
			if (!empty($multipleEmailAddresses)){
				$sendingAddress = $multipleEmailAddresses[0];
				$to             = str_replace(';', ',', $to); //The newer mailer needs 'to' addresses to be separated by commas rather than semicolon
			}else{
				$sendingAddress = $to;
			}

			$name        = $_REQUEST['name'];
			$subject     = 'OverDrive Support Request from ' . $name;
			$patronEmail = $_REQUEST['email'];
			$interface->assign('bookAuthor', $_REQUEST['bookAuthor']);
			$interface->assign('device', $_REQUEST['device']);
			$interface->assign('format', $_REQUEST['format']);
			$interface->assign('operatingSystem', $_REQUEST['operatingSystem']);
			$interface->assign('problem', $_REQUEST['problem']);
			$interface->assign('name', $name);
			$interface->assign('email', $patronEmail);
			$interface->assign('deviceName', get_device_name()); // footer & eContent support email
			$interface->assign('homeLibrary', $userLibrary->displayName);
			$interface->assign('overDriveErrorMessages', $_REQUEST['overDriveErrorMessages']);

			$body        = $interface->fetch('OverDrive/eContentSupportEmail.tpl');
			$emailResult = $mail->send($to, $sendingAddress, $subject, $body, $patronEmail);
			if (PEAR::isError($emailResult)){
				global $pikaLogger;
				$pikaLogger->error('eContent Support email not sent: ' . $emailResult->getMessage());
				return [
					'title'   => "Support Request Not Sent",
					'message' => "<p>We're sorry, an error occurred while submitting your request.</p>" . $emailResult->getMessage()
				];
			}elseif ($emailResult){
				return [
					'title'   => "Support Request Sent",
					'message' => "<p>Your request was sent to our support team.  We will respond to your request as quickly as possible.</p><p>Thank you for using the catalog.</p>"
				];
			}else{
				return [
					'title'   => "Support Request Not Sent",
					'message' => "<p>We're sorry, but your request could not be submitted to our support team at this time.</p><p>Please try again later.</p>"
				];
			}
		}else{
			return $this->getSupportForm();
		}
	}

	function getOverDriveIssueCheckoutPrompt(){

		global $interface;
		$overdriveId = $_REQUEST['overdriveId'];
		$issues = new Pika\BibliographicDrivers\OverDrive\OverDriveAPIMagazineIssues;
		$issues->overdriveId = $overdriveId;
		$issues->find();
		$issues->orderBy("pubDate");
		$title = '';
		$coverUrl = '';
		$edition = '';
		$description = '';
		$parentId = '';
		while ($issues->fetch()){
			$title =  $issues->title;
			$coverUrl = $issues->coverUrl;
			$edition = $issues->edition;
			$description = $issues->description;
			$parentId = $issues->parentId;
		}

		return [
			'title' => "Checkout Magazine Issue",
			'body' => "<div class='row'><div class='col-sm-3'><img class='img-responsive' src='". $coverUrl ."' /></div><div class='col-sm-9'><div class='row' ><strong>". $title ."</strong> - ". $edition ."</div><div class='row' style='max-height:300px;overflow:hidden;'>". $description ."</div></div></div></div>",
			'buttons' =>"<button class='btn btn-primary' onclick=\"Pika.OverDrive.checkOutOverDriveTitle('". $parentId ."','magazine-overdrive', '". $overdriveId."')\">Checkout</button>"
		];

	}


}
