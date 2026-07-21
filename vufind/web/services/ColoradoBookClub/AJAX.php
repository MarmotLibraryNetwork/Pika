<?php
/*
 * Pika Discovery Layer
 * Copyright (C) 2026  Marmot Library Network
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
 * AJAX calls specific to the Colorado State Book Club Kit sideload records.
 *
 * (This class has to exist because the parent directory causes the action loading to expect it)
 */

require_once ROOT_DIR . '/services/Record/AJAX.php';

class ColoradoBookClub_AJAX extends Record_AJAX {

	protected array $methodsThatRespondWithJSONUnstructured = [
		'getBookClubKitRequestForm',
		'submitBookClubKitRequestForm',
	];

	function getBookClubKitRequestForm(){
		global $interface;
		$user = UserAccount::getLoggedInUser();
		if ($user){
			$recordId = new SourceAndId($_REQUEST['id']);
			if (!empty($recordId->getSource()) && !empty($recordId->getRecordId()) && $recordId->getSource() == 'colobookclub'){
				/** @var MarcRecord $marcRecord */
				$marcRecord  = RecordDriverFactory::initRecordDriverById($recordId);
				$author      = $marcRecord->getPrimaryAuthor();
				$title       = trim(str_ireplace(['(Colorado State Library Book Club Collection)', 'Colorado State Library Book Club Collection'], '', $marcRecord->getTitle()));
				$title       .= (!empty($author) ? ' by ' . $author : '');
				$homeLibrary = $user->getHomeLibrary();

				$interface->assign('name', trim($user->firstname . ' ' . $user->lastname));
				$interface->assign('email', $user->email);
				$interface->assign('libraryCardNumber', $user->getBarcode());
				$interface->assign('homeLibraryId', $homeLibrary->libraryId);
				$interface->assign('title', $title);
				$interface->assign('recordId', $recordId);
				$interface->assign('module', $marcRecord->getModule());
				$interface->assign('shortId', $marcRecord->getShortId());

				$results = [
					'title'        => 'Request Book Club Kit',
					'modalBody'    => $interface->fetch('Record/bookClubKitRequestForm.tpl'),
					'modalButtons' => '<button class="btn btn-primary" onclick="$(\'#bookClubKitRequestForm\').submit()">Submit Request</button>',
					// Clicking invokes submit event, which allows the validator to act before calling the ajax handler
				];
			} else {
				$results = [
					'title'        => 'Error',
					'modalBody'    => 'Invalid Record Id',
					'modalButtons' => '',
				];
			}
		}else{
			$results = [
				'title'        => 'Please log in',
				'modalBody'    => 'You must be logged in.  Please close this dialog and login before requesting a Book Club Kit.',
				'modalButtons' => '',
			];
		}
		return $results;
	}

	function submitBookClubKitRequestForm(){
		$user = UserAccount::getLoggedInUser();
		if (!$user){
			return [
				'title'   => 'Error',
				'message' => "<p class='alert alert-danger'>You must be logged in to submit a Book Club Kit request.</p>",
			];
		}

		$cleanedRecordId = trim(strip_tags($_REQUEST['recordId']));
		$recordId         = new SourceAndId($cleanedRecordId);
		if (empty($recordId->getSource()) || empty($recordId->getRecordId()) || $recordId->getSource() != 'colobookclub'){
			$this->logger->error('Book Club Kit request submitted with invalid record ID: ' . $cleanedRecordId);
			return [
				'title'   => 'Error',
				'message' => "<p class='alert alert-danger'>Invalid record ID.</p>",
			];
		}

		$homeLibrary = $user->getHomeLibrary();
		if (empty($homeLibrary->bookClubKitContactEmail)){
			$this->logger->notice("Patron $user->id submitted Book Club request for non-participating library " .$homeLibrary->displayName);
			return [
				'title'   => 'Request Not Sent',
				'message' => "<p class='alert alert-danger'>Sorry, {$homeLibrary->displayName} does not offer Book Club Kit requests through this form. Please contact your library directly.</p>",
			];
		}

		require_once ROOT_DIR . '/sys/BookClubKit/BookClubKitRequest.php';
		$request            = new BookClubKit\BookClubKitRequest();
		$request->libraryId = $homeLibrary->libraryId;
		$request->userId    = $user->id;
		$request->barcode   = $user->getBarcode();
		$request->name      = substr(strip_tags($_REQUEST['name']), 0, 120);
		$request->email     = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL) ?: $user->email;
		$request->recordId  = $recordId->getSourceAndId(); //TODO just store the shortId?
		$request->title     = substr(strip_tags($_REQUEST['title']), 0, 255);
		$request->insert();

		global $interface;
		$interface->assign('name', $request->name);
		$interface->assign('email', $request->email);
		$interface->assign('libraryCardNumber', $request->barcode);
		$interface->assign('title', $request->title);
		$interface->assign('libraryName', $homeLibrary->displayName);
		$interface->assign('shortId', $recordId->getRecordId());
		$subject = 'Book Club Kit Request From: ' . $request->name;
		$body    = $interface->fetch('Record/bookClubKitRequestEmail.tpl');

		require_once ROOT_DIR . '/sys/Mailer.php';
		global $configArray;
		$mail          = new VuFindMailer();
		$to            = $homeLibrary->bookClubKitContactEmail;
		$siteEmail     = $configArray['Site']['email'] ?? null;
		$sendingAddress = !empty($siteEmail) ? $siteEmail : $request->email;
		$emailResult   = $mail->send($to, $sendingAddress, $subject, $body, $request->email, $siteEmail);
		//TODO: cc requestee as well?

		if (PEAR::isError($emailResult)){
			$this->logger->error('Book Club Kit request email not sent: ' . $emailResult->getMessage());
			return [
				'title'   => 'Book Club Kit request email not sent',
				'message' => "<p class='alert alert-danger'>We're sorry, an error occurred while submitting your request.</p>",
			];
		}

		$this->logger->notice("Patron $user->id submitted Book Club Kit request email was sent successfully for library " . $homeLibrary->displayName);
		return [
			'title'   => 'Book Club Kit Requested',
			'message' => '<p class="alert alert-success">Thank you for your request. Your library will follow up with you when your kit is ready.</p>',
		];
	}

}
