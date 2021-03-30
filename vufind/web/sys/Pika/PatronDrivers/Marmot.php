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
 *
 *
 * @category Pika
 * @author   : Pascal Brammeier
 * Date: 5/13/2019
 *
 */

namespace Pika\PatronDrivers;

use Curl\Curl;
use MarcRecord;
use User;
use Library;
use Location;
use Pika\PatronDrivers\MyBooking;
use Pika\Cache;
use Pika\Logger;

require_once ROOT_DIR . "/sys/Pika/PatronDrivers/Traits/PatronBookingsOperations.php";
require_once ROOT_DIR . "/sys/Pika/PatronDrivers/MyBooking.php";

class Marmot extends Sierra {

	use \PatronBookingsOperations;

	public function __construct($accountProfile)
	{
		parent::__construct($accountProfile);
	}

	/**
	 * @param bool $extraSelfRegParams
	 * @return array
	 * @throws \ErrorException
	 */
	public function selfRegister($extraSelfRegParams = false)
	{
		
		global $library;
		// include test and production
		$libSubDomain = strtolower($library->subdomain);
		if($libSubDomain == 'vail' || $libSubDomain == 'vail2') {
			/* VAIL */
			$extraSelfRegParams['varFields'][] = ["fieldTag" => "u",
			                                      "content"  => "#"];
			$extraSelfRegParams['varFields'][] = ["fieldTag" => "i",
			                                      "content"  => "#"];
			$extraSelfRegParams['varFields'][] = ["fieldTag" => "q",
			                                      "content"  => "XXXLLFF"];
			$extraSelfRegParams['pMessage']    = 'f';

		} elseif ($libSubDomain == 'mesa' || $libSubDomain == 'mesa2') {
			/* MESA */
			$extraSelfRegParams['patronCodes']['pcode3'] = 84;
			$extraSelfRegParams['varFields'][] = ["fieldTag" => "m",
			                                      "content"  => "Temp Online Acct: Verify ALL information, add Telephone Number".
			                                       " in the Unique ID field, verify notice preference, update barcode & exp. date, then change alias & p-type"];
			$extraSelfRegParams['varFields'][] = ["fieldTag" => "q",
			                                      "content"  => "dig access"];
		}
		return parent::selfRegister($extraSelfRegParams);
	}

	/**
	 * Fetch the patron's bookings
	 *
	 * @param \User $patron
	 * @return array
	 */
	public function getMyBookings(\User $patron){

		// Fetch Classic WebPac Bookings page
		$html = $this->_curlLegacy($patron, 'bookings');

		// Parse out Bookings Information
		/** @var MyBooking[] $bookings */
		$bookings = $this->parseBookingsPage($html);

		require_once ROOT_DIR . '/RecordDrivers/MarcRecord.php';
		foreach ($bookings as &$booking){
			$booking->userDisplayName = $patron->getNameAndLibraryLabel();
			$booking->userId          = $patron->id;

			$recordDriver = new MarcRecord($booking->id);
			if ($recordDriver->isValid()){
				$booking->title         = $recordDriver->getTitle();
				$booking->sortTitle     = $recordDriver->getSortableTitle();
				$booking->author        = $recordDriver->getAuthor();
				$booking->format        = $recordDriver->getFormat();
				$booking->linkUrl       = $recordDriver->getRecordUrl();
				$booking->coverUrl      = $recordDriver->getBookcoverUrl('medium');
				$booking->groupedWorkId = $recordDriver->getGroupedWorkId();
				$booking->ratingData    = $recordDriver->getRatingData();
			}
		}

		return $bookings;
	}

	public function bookMaterial(User $patron, \SourceAndId $recordId, $startDate, $startTime = null, $endDate = null, $endTime = null){
		if (empty($recordId) || empty($startDate)){ // at least these two fields should be required input
			return array('success' => false, 'message' => empty($startDate) ? 'Start Date Required.' : 'Record ID required');
		}
		if (!$startTime){
			$startTime = '8:00am';   // set a default start time if not specified (a morning time)
		}
		if (!$endDate){
			$endDate = $startDate;   // set a default end date to the start date if not specified
		}
		if (!$endTime){
			$endTime = '8:00pm';     // set a default end time if not specified (an evening time)
		}

		// set bib number in format .b{recordNumber}
		$bib = $this->getShortId($recordId->getRecordId());

		$startDateTime = new \DateTime("$startDate $startTime");// create a date with input and set it to the format the ILS expects
		if (!$startDateTime){
			return array('success' => false, 'message' => 'Invalid Start Date or Time.');
		}

		$endDateTime = new \DateTime("$endDate $endTime"); // create a date with input and set it to the format the ILS expects
		if (!$endDateTime){
			return array('success' => false, 'message' => 'Invalid End Date or Time.');
		}

		$bookingUrl = "/webbook?/$bib=&back=";
		// the strange get url parameters ?/$bib&back= is needed to avoid a response from the server claiming a 502 proxy error
		// Scope appears to be unnecessary at this point.

		// Get pagen from form
		/** @var Curl $c */
		$c            = $this->_curlLegacy($patron, $bookingUrl, null, false);
		$curlResponse = $c->getResponse();

		if (preg_match('/You cannot book this material/i', $curlResponse)){
			return array(
				'success' => false,
				'message' => 'Sorry, you cannot schedule this item.'
			);
		}

		$tag               = 'input';
		$tag_pattern       =
			'@<(?P<tag>' . $tag . ')           # <tag
      (?P<attributes>\s[^>]+)?       # attributes, if any
            \s*/?>                   # /> or just >, being lenient here
            @xsi';
		$attribute_pattern =
			'@
        (?P<name>\w+)                         # attribute name
        \s*=\s*
        (
            (?P<quote>[\"\'])(?P<value_quoted>.*?)(?P=quote)    # a quoted value
                                    |                           # or
            (?P<value_unquoted>[^\s"\']+?)(?:\s+|$)             # an unquoted value (terminated by whitespace or EOF)
        )
        @xsi';

		if (preg_match_all($tag_pattern, $curlResponse, $matches)){
			foreach ($matches['attributes'] as $attributes){
				if (preg_match_all($attribute_pattern, $attributes, $attributeMatches)){
					$search = array_flip($attributeMatches['name']); //flip so that index can be used to get actual names & values of attributes
					if (array_key_exists('name', $search)){ // find name attribute
						$attributeName  = trim($attributeMatches['value_quoted'][$search['name']], '"\'');
						$attributeValue = trim($attributeMatches['value_quoted'][$search['value']], '"\'');
						if ($attributeName == 'webbook_pagen'){
							$pageN = $attributeValue;
						}elseif ($attributeName == 'webbook_loc'){
							$loc = $attributeValue;
						}
					}
				}
			}
		}

		$patronId = $this->getPatronId($patron); // username seems to be the patron Id

		$post = array(
			'webbook_pnum'        => $patronId,
			'webbook_pagen'       => empty($pageN) ? '2' : $pageN, // needed, reading from screen scrape; 2 or 4 are the only values i have seen so far. plb 7-16-2015
			'webbook_bgn_Month'   => $startDateTime->format('m'),
			'webbook_bgn_Day'     => $startDateTime->format('d'),
			'webbook_bgn_Year'    => $startDateTime->format('Y'),
			'webbook_bgn_Hour'    => $startDateTime->format('h'),
			'webbook_bgn_Min'     => $startDateTime->format('i'),
			'webbook_bgn_AMPM'    => $startDateTime->format('H') > 11 ? 'PM' : 'AM',
			'webbook_end_n_Month' => $endDateTime->format('m'),
			'webbook_end_n_Day'   => $endDateTime->format('d'),
			'webbook_end_n_Year'  => $endDateTime->format('Y'),
			'webbook_end_n_Hour'  => $endDateTime->format('h'),
			'webbook_end_n_Min'   => $endDateTime->format('i'),
			'webbook_end_n_AMPM'  => $endDateTime->format('H') > 11 ? 'PM' : 'AM', // has to be uppercase for the screen scraping
			'webbook_note'        => '', // the web note doesn't seem to be displayed to the user any where after submit
		);
		if (!empty($loc)){
			// if we have this info add it, don't include otherwise.
			$post['webbook_loc'] = $loc;
		}
		$curlResponse = $c->post($bookingUrl, $post);
		if ($c->error){

			$this->logger->warn('Curl error during booking, code: ' . $c->getErrorMessage());
			return array(
				'success' => false,
				'message' => 'There was an error communicating with the circulation system.'
			);
		}

		// Look for Success Messages
		$numMatches = preg_match('/<span.\s?class="bookingsConfirmMsg">(?P<success>.+?)<\/span>/', $curlResponse, $matches);
		if ($numMatches){
			return array(
				'success' => true,
				'message' => is_array($matches['success']) ? implode('<br>', $matches['success']) : $matches['success']
			);
		}

		// Look for Account Error Messages
		// <h1>There is a problem with your record.  Please see a librarian.</h1>
		$numMatches = preg_match('/<h1>(?P<error>There is a problem with your record\..\sPlease see a librarian.)<\/h1>/', $curlResponse, $matches);
		// ?P<name> syntax will creates named matches in the matches array
		if ($numMatches){
			return array(
				'success' => false,
				'message' => is_array($matches['error']) ? implode('<br>', $matches['error']) : $matches['error'],
				'retry'   => true, // communicate back that we think the user could adjust their input to get success
			);
		}


		// Look for Error Messages
		$numMatches = preg_match('/<span.\s?class="errormessage">(?P<error>.+?)<\/span>/is', $curlResponse, $matches);
		// ?P<name> syntax will creates named matches in the matches array
		if ($numMatches){
			return array(
				'success' => false,
				'message' => is_array($matches['error']) ? implode('<br>', $matches['error']) : $matches['error'],
				'retry'   => true, // communicate back that we think the user could adjust their input to get success
			);
		}

		// Catch all Failure
		$this->logger->error('Unkown error during booking');
		return array(
			'success' => false,
			'message' => 'There was an unexpected result while scheduling your item'
		);
	}

	/**
	 * Cancel a Booking
	 *
	 * @param User $patron The user the booking belongs to
	 * @param string|array $cancelIds The Id or array of Ids needed to cancel the booking
	 * @return array
	 */
	public function cancelBookedMaterial(\User $patron, $cancelIds){
		//NOTE the library's scope for the classic OPAC is needed to delete bookings!
		if (empty($cancelIds)){
			return array('success' => false, 'message' => 'Item ID required');
		}elseif (!is_array($cancelIds)){
			$cancelIds = array($cancelIds);  // for a single item
		}

		$post = array(
			'canbooksome' => 'YES'
			//			'requestCanBookSome' => 'requestCanBookSome',
		);

		foreach ($cancelIds as $i => $cancelId){
			if (is_numeric($i)){
				$post['canbook' . $i] = $cancelId; // recreating the cancelName variable canbookX
			}else{
				$post[$i] = $cancelId; // when cancelName is passed back
			}
		}


		$html = $this->_curlLegacy($patron, 'bookings', $post);

		$errors = array();
		if (!$html){
			return array(
				'success' => false,
				'message' => 'There was an error communicating with the circulation system.'
			);
		}

		// check the bookings again, to verify that they were in fact really cancelled.
		if (!empty($html)){
			foreach ($cancelIds as $cancelId){
				if (strpos($html, $cancelId) !== false){ // looking for this booking in results, meaning it failed to cancel.
					if (empty($errors)){
						$bookings = $this->parseBookingsPage($html); // get current bookings on first error
					}
					foreach ($bookings as $booking){
						if ($booking->cancelValue == $cancelId){
							break;
						}
					}
//					$errors[$booking['cancelValue']] = 'Failed to cancel scheduled item <strong>' . $booking['title'] . '</strong> from ' . strftime('%b %d, %Y at %I:%M %p', $booking['startDateTime']) . ' to ' . strftime('%b %d, %Y at %I:%M %p', $booking['endDateTime']);
					// Time included
					$errors[$booking->cancelValue] = 'Failed to cancel scheduled item <strong>' . $booking->title . '</strong> from ' . strftime('%b %d, %Y', $booking->startDateTime) . ' to ' . strftime('%b %d, %Y', $booking->endDateTime);
					// Dates only

				}

			}
		}

		if (empty($errors)){
			return array(
				'success' => true,
				'message' => 'Your scheduled item' . (count($cancelIds) > 1 ? 's were' : ' was') . ' successfully canceled.'
			);
		}else{
			return array(
				'success' => false,
				'message' => $errors
			);
		}
	}

	public function cancelAllBookedMaterial($patron){
		//NOTE the library's scope for the classic OPAC is needed to delete bookings!
		$post = array(
			'canbookall' => 'YES'
		);

		$html = $this->_curlLegacy($patron, 'bookings', $post);

		$errors = array();
		if (!$html){
			return array(
				'success' => false,
				'message' => 'There was an error communicating with the circulation system.'
			);
		}

		// get the bookings again, to verify that they were in fact really cancelled.
		if (!strpos($html, 'No bookings found')){ // 'No bookings found' is our success phrase
			$bookings = $this->parseBookingsPage($html);
			if (!empty($bookings)){ // a booking wasn't canceled
				foreach ($bookings as $booking){
//					$errors[$booking['cancelValue']] = 'Failed to cancel scheduled item <strong>' . $booking['title'] . '</strong> from ' . strftime('%b %d, %Y at %I:%M %p', $booking['startDateTime']) . ' to ' . strftime('%b %d, %Y at %I:%M %p', $booking['endDateTime']);
					// Time included
					$errors[$booking['cancelValue']] = 'Failed to cancel scheduled item <strong>' . $booking['title'] . '</strong> from ' . strftime('%b %d, %Y', $booking['startDateTime']) . ' to ' . strftime('%b %d, %Y', $booking['endDateTime']);
					// Dates only
				}
			}
		}

		if (empty($errors)){
			return array(
				'success' => true,
				'message' => 'Your scheduled items were successfully canceled.'
			);
		}else{
			return array(
				'success' => false,
				'message' => $errors
			);
		}
	}


	/**
	 *  Fetch the calendar to use with scheduling a booking
	 *
	 * @param User $patron
	 * @param \SourceAndId $sourceAndId The record to book
	 *
	 * @return string  An HTML table
	 */
	public function getBookingCalendar(User $patron, \SourceAndId $sourceAndId){
		// Create Hourly Calendar URL
		$bib       = $this->getShortId($sourceAndId->getRecordId());
		$scope     = $this->getLibraryScope();
		$timestamp = time(); // the webpac hourly calendar give 30 (maybe 31) days worth from the given timestamp.
		// Since today is the soonest a user could book, let's get from today
		$hourlyCalendarUrl = "webbook~S$scope?/$bib/hourlycal$timestamp=&back=";

		//Can only get the hourly calendar html by submitting the bookings form
		$post                   = array(
			'webbook_pnum'        => $this->getPatronId($patron),
			'webbook_pagen'       => '2', // needed, reading from screen scrape; 2 or 4 are the only values i have seen so far. plb 7-16-2015
			//			'refresh_cal' => '0', // not needed
			'webbook_bgn_Month'   => '',
			'webbook_bgn_Day'     => '',
			'webbook_bgn_Year'    => '',
			'webbook_bgn_Hour'    => '',
			'webbook_bgn_Min'     => '',
			'webbook_bgn_AMPM'    => '',
			'webbook_end_n_Month' => '',
			'webbook_end_n_Day'   => '',
			'webbook_end_n_Year'  => '',
			'webbook_end_n_Hour'  => '',
			'webbook_end_n_Min'   => '',
			'webbook_end_n_AMPM'  => '',
			'webbook_note'        => '',
		);
		$HourlyCalendarResponse = $this->_curlLegacy($patron, $hourlyCalendarUrl, $post, false);

		// Extract Hourly Calendar from second response
		if (preg_match('/<div class="bookingsSelectCal">.*?<table border>(?<HourlyCalendarTable>.*?<\/table>.*?)<\/table>.*?<\/div>/si', $HourlyCalendarResponse, $table)){
			// Modify Calendar html for our needs
			$calendarTable = str_replace(array('unavailable', 'available', 'closed', 'am'), array('active', 'success', 'active', ''), $table['HourlyCalendarTable']);
			$calendarTable = preg_replace('#<th.*?>.*?</th>#s', '<th colspan="2">Date</th><th colspan="17">Time <small>(6 AM - 11 PM)&nbsp; Times in green are Available.</small></th>', $calendarTable); // cut out the table header with the unwanted links in it.
			$calendarTable = '<table class="table table-condensed">' . $calendarTable . '</table>'; // add table tag with styling attributes
			return $calendarTable;
		}
	}

	private function _curlLegacy($patron, $pageToCall, $postParams = array(), $patronAction = true){

		$c = new Curl();

		// base url for following calls
		$vendorOpacUrl = $this->accountProfile->vendorOpacUrl;

		$headers = [
			"Accept"          => "text/xml,application/xml,application/xhtml+xml,text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5",
			"Cache-Control"   => "max-age=0",
			"Connection"      => "keep-alive",
			"Accept-Charset"  => "ISO-8859-1,utf-8;q=0.7,*;q=0.7",
			"Accept-Language" => "en-us,en;q=0.5",
			"User-Agent"      => "Pika"
		];
		$c->setHeaders($headers);

		$cookie   = tempnam("/tmp", "CURLCOOKIE");
		$curlOpts = [
			CURLOPT_CONNECTTIMEOUT    => 20,
			CURLOPT_TIMEOUT           => 60,
			CURLOPT_RETURNTRANSFER    => true,
			CURLOPT_FOLLOWLOCATION    => true,
			CURLOPT_UNRESTRICTED_AUTH => true,
			CURLOPT_COOKIEJAR         => $cookie,
			CURLOPT_COOKIESESSION     => false,
			CURLOPT_HEADER            => false,
			CURLOPT_AUTOREFERER       => true,
		];
		$c->setOpts($curlOpts);

		// first log patron in
		$postData = [
			'name' => $patron->cat_username,
			'code' => $patron->cat_password
		];
		$loginUrl = $vendorOpacUrl . '/patroninfo/';
		$r        = $c->post($loginUrl, $postData);

		if ($c->isError()){
			$c->close();
			return false;
		}

		if (!stristr($r, $patron->cat_username)){
			$c->close();
			return false;
		}

		$scope    = $this->getLibraryScope(); // IMPORTANT: Scope is needed for Bookings Actions to work
		$patronId = $this->getPatronId($patron->barcode);
		$optUrl   = $patronAction ? $vendorOpacUrl . '/patroninfo~S' . $scope . '/' . $patronId . '/' . $pageToCall
			: $vendorOpacUrl . '/' . $pageToCall;
		// Most curl calls are patron interactions, getting the bookings calendar isn't

		$c->setUrl($optUrl);
		if (!empty($postParams)){
			$r = $c->post($postParams);
		}else{
			$r = $c->get($optUrl);
		}

		if ($c->isError()){
			return false;
		}

		if (stripos($pageToCall, 'webbook?/') !== false){
			// Hack to complete booking a record
			return $c;
		}
		return $r;
	}

	/**
	 * @param String $html Html text of classic opac booking page for the patron's account
	 * @return array
	 */
	private
	function parseBookingsPage($html){
		$bookings = array();

		// Table Rows for each Booking
		if (preg_match_all('/<tr\\s+class="patFuncEntry">(?<bookingRow>.*?)<\/tr>/si', $html, $rows, PREG_SET_ORDER)){
			foreach ($rows as $index => $row){ // Go through each row

				// Get Record/Title
				if (!preg_match('/.*?<a href=\\"\/record=(?<recordId>.*?)(?:~S\\d{1,3})\\">(?<title>.*?)<\/a>.*/', $row['bookingRow'], $matches)){
					$this->logger->error("Failed to parse My Bookings page from classic");
				}

				$shortId = $matches['recordId'];
				$bibId   = '.' . $shortId . $this->getCheckDigit($shortId);
				$title   = strip_tags($matches['title']);

				// Get From & To Dates
				$startTimestamp = null;
				$endTimestamp   = null;
				if (preg_match_all('/.*?<td nowrap class=\\"patFuncBookDate\\">(?<bookingDate>.*?)<\/td>.*/', $row['bookingRow'], $matches, PREG_SET_ORDER)){
					$startDateTime = trim($matches[0]['bookingDate']); // time component looks ambiguous
					$endDateTime   = trim($matches[1]['bookingDate']);

					// pass as timestamps so that the SMARTY template can handle it.
					$dateTimeObject = date_create_from_format('m-d-Y g:i', $startDateTime);
					if (!$dateTimeObject){
						$dateTimeObject = date_create_from_format('m-d-Y', $startDateTime);
					}
					if ($dateTimeObject){
						$startTimestamp = date_timestamp_get($dateTimeObject);
					}
					$dateTimeObject = date_create_from_format('m-d-Y g:i', $endDateTime);
					if (!$dateTimeObject){
						$dateTimeObject = date_create_from_format('m-d-Y', $endDateTime);
					}
					if ($dateTimeObject){
						$endTimestamp = date_timestamp_get($dateTimeObject);
					}
				}

				// Get Status
				if (preg_match('/.*?<td nowrap class=\\"patFuncStatus\\">(?<status>.*?)<\/td>.*/', $row['bookingRow'], $matches)){
					$status = ($matches['status'] == '&nbsp;') ? '' : $matches['status']; // at this point, I don't know what status we will ever see
				}else{
					$status = '';
				}

				// Get Cancel Ids
//				<td class="patFuncMark"><input type="CHECKBOX" name="canbook0" id="canbook0" value="i9459912F08-17-20154:00T08-17-20154:00" /></td>
				if (preg_match('/.*?<input type="CHECKBOX".*?name=\\"(?<cancelName>.*?)\\".*?value=\\"(?<cancelValue>.*?)\\" \/>.*/', $row['bookingRow'], $matches)){
					$cancelName  = $matches['cancelName'];
					$cancelValue = $matches['cancelValue'];
				}else{
					$cancelValue = $cancelName = '';
				}

				$booking                = new MyBooking();
				$booking->id            = $bibId;
				$booking->title         = $title;
				$booking->startDateTime = $startTimestamp;
				$booking->endDateTime   = $endTimestamp;
				$booking->status        = $status;
				$booking->cancelName    = $cancelName;
				$booking->cancelValue   = $cancelValue;

				$bookings[] = $booking;

			}


		}
		return $bookings;
	}


	/**
	 * At this time, Marmot is the only site that uses this.

	 * Legacy Screen Scraping to get holding_record information for periodicals. (This may require the Serials module.)
	 *
	 * @param $recordId
	 * @param $checkInGridId
	 * @return array
	 * @throws ErrorException
	 */
	function getCheckInGrid($recordId, $checkInGridId){
		//Issue summaries are loaded from the main record page.
		$sourceAndId = new \SourceAndId($recordId);

		$id_         = $this->getShortId($sourceAndId->getRecordId());
		$host        = $this->accountProfile->vendorOpacUrl;
		$branchScope = $this->getLibraryScope();
		$url         = $host . "/search~S{$branchScope}/.b" . $id_ . "/.b" . $id_ . "/1,1,1,B/$checkInGridId&FF=1,0,";
		$c           = new Curl();
		$headers     = [
			"Accept"          => "text/xml,application/xml,application/xhtml+xml,text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5",
			"Cache-Control"   => "max-age=0",
			"Connection"      => "keep-alive",
			"Accept-Charset"  => "ISO-8859-1,utf-8;q=0.7,*;q=0.7",
			"Accept-Language" => "en-us,en;q=0.5",
			"User-Agent"      => "Pika"
		];
		$c->setHeaders($headers);

		$cookie   = tempnam("/tmp", "CURLCOOKIE");
		$curlOpts = [
			CURLOPT_CONNECTTIMEOUT    => 20,
			CURLOPT_TIMEOUT           => 60,
			CURLOPT_RETURNTRANSFER    => true,
			CURLOPT_FOLLOWLOCATION    => true,
			CURLOPT_UNRESTRICTED_AUTH => true,
			CURLOPT_COOKIEJAR         => $cookie,
			CURLOPT_COOKIESESSION     => false,
			CURLOPT_HEADER            => false,
			CURLOPT_AUTOREFERER       => true,
		];
		$c->setOpts($curlOpts);

		$result = $c->get($url);

		if ($c->isError()){
			return array();
		}

		//Extract the actual table
		$checkInData = array();
		if (preg_match('/<table  class="checkinCardTable">(.*?)<\/table>/s', $result, $matches)){
			$checkInTable = trim($matches[1]);

			//Extract each item from the grid.
			preg_match_all('/.*?<td valign="top" class="(.*?)">(.*?)<\/td>/s', $checkInTable, $checkInCellMatch, PREG_SET_ORDER);
			for ($matchi = 0;$matchi < count($checkInCellMatch);$matchi++){
				$cellData             = trim($checkInCellMatch[$matchi][2]);
				$checkInCell          = array();
				$checkInCell['class'] = $checkInCellMatch[$matchi][1];
				//Load issue date, status, date received, issue number, copies received
				if (preg_match('/(.*?)<br\\s*\/?>.*?<span class="(?:.*?)">(.*?)<\/span>.*?on (\\d{1,2}-\\d{1,2}-\\d{1,2})<br\\s*\/?>(.*?)(?:<!-- copies --> \\((\\d+) copy\\))?<br\\s*\/?>/s', $cellData, $matches)){
					$checkInCell['issueDate']   = trim($matches[1]);
					$checkInCell['status']      = trim($matches[2]);
					$checkInCell['statusDate']  = trim($matches[3]);
					$checkInCell['issueNumber'] = trim($matches[4]);
					if (isset($matches[5])){
						$checkInCell['copies'] = trim($matches[5]);
					}
				}
				$checkInData[] = $checkInCell;
			}
		}
		return $checkInData;
	}

	/**
	 * At this time, Marmot is the only site that uses this.
	 *
	 * Legacy Screen Scraping to get holding_record information for periodicals
	 *
	 * If there are issue summaries available, it will return them in an array.
	 * With holdings below them.
	 *
	 * If there are no issue summaries, null will be returned from the summary.
	 *
	 * @param string $recordId
	 * @return array|null - array or null
	 * @throws ErrorException
	 */
	public function getIssueSummaries($recordId){
		$scope         = $this->getLibraryScope(true); // Use library scope if searching is restricted to the library
		$id_           = $this->getShortId($recordId);
		$host          = $this->accountProfile->vendorOpacUrl;
		$c             = new Curl();
		$headers       = [
			"Accept"          => "text/xml,application/xml,application/xhtml+xml,text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5",
			"Cache-Control"   => "max-age=0",
			"Connection"      => "keep-alive",
			"Accept-Charset"  => "ISO-8859-1,utf-8;q=0.7,*;q=0.7",
			"Accept-Language" => "en-us,en;q=0.5",
			"User-Agent"      => "Pika"
		];
		$cookie   = @tempnam("/tmp", "CURLCOOKIE");
		$curlOpts = [
			CURLOPT_CONNECTTIMEOUT    => 20,
			CURLOPT_TIMEOUT           => 60,
			CURLOPT_RETURNTRANSFER    => true,
			CURLOPT_FOLLOWLOCATION    => true,
			CURLOPT_UNRESTRICTED_AUTH => true,
			CURLOPT_COOKIEJAR         => $cookie,
			CURLOPT_COOKIESESSION     => false,
			CURLOPT_HEADER            => false,
			CURLOPT_AUTOREFERER       => true,
		];
		$c->setHeaders($headers);
		$c->setOpts($curlOpts);

		//Legacy process would load this page first. The results weren't used, and it doesn't appear required to read the URL further down
//		$url                       = $host . "/search~S{$scope}/." . $id_ . "/." . $id_ . "/1,1,1,B/holdings~" . $id_;
//		$issuesSummaryHoldingsInfo = $c->get($url);  // Not used any where
//		if ($c->isError()){
//			return null;
//		}

		$url                       = $host . "/search~S{$scope}/." . $id_ . "/." . $id_ . "/1,1,1,B/frameset~" . $id_;
		$issuesSummaryFrameSetInfo = $c->get($url);
		if ($c->isError()){
			return null;
		}

		if (preg_match('/class\\s*=\\s*\\"bibHoldings\\"/s', $issuesSummaryFrameSetInfo)){
			//There are issue summaries available
			//Extract the table with the holdings
			$issueSummaries = array();
			$matches        = array();
			if (preg_match('/<table\\s.*?class=\\"bibHoldings\\">(.*?)<\/table>/s', $issuesSummaryFrameSetInfo, $matches)){
				$issueSummaryTable = trim($matches[1]);
				//Each holdingSummary begins with a holdingsDivider statement
				$summaryMatches = explode('<tr><td colspan="2"><hr  class="holdingsDivider" /></td></tr>', $issueSummaryTable);
				if (count($summaryMatches) > 1){
					//Process each match independently
					foreach ($summaryMatches as $summaryData){
						$summaryData = trim($summaryData);
						if (strlen($summaryData) > 0){
							//Get each line within the summary
							$issueSummary         = array();
							$issueSummary['type'] = 'issueSummary';
							$summaryLines         = array();
							preg_match_all('/<tr\\s*>(.*?)<\/tr>/s', $summaryData, $summaryLines, PREG_SET_ORDER);
							for ($matchi = 0;$matchi < count($summaryLines);$matchi++){
								$summaryLine = trim(str_replace('&nbsp;', ' ', $summaryLines[$matchi][1]));
								$summaryCols = array();
								if (preg_match('/<td.*?>(.*?)<\/td>.*?<td.*?>(.*?)<\/td>/s', $summaryLine, $summaryCols)){
									$labelOriginal = $label = trim($summaryCols[1]);
									$value = trim(strip_tags($summaryCols[2]));
									//Check to see if this has a link to a check-in grid.
									if (preg_match('/.*?<a href="(.*?)">.*/s', $label, $linkData)){
										//Parse the check-in id
										$checkInLink = $linkData[1];
										if (preg_match('/\/search~S\\d+\\?\/.*?\/.*?\/.*?\/(.*?)&.*/', $checkInLink, $checkInGridInfo)){
											$issueSummary['checkInGridId'] = $checkInGridInfo[1];
										}
									}
									//Convert to camel case
									$label = lcfirst(preg_replace('/[^\\w]/', '', strip_tags($label)));
									if ($label == 'location'){
										//Try to trim the courier code if any
										if (preg_match('/(.*?)\\sC\\d{3}\\w{0,2}$/', $value, $locationParts)){
											$value = $locationParts[1];
										}
									}elseif ($label == 'holdings'){
										//Change the label to avoid conflicts with actual holdings
										$label = 'holdingStatement';
									}
									$issueSummary[$label] = $value;
								}
							}
							$issueSummaries[$issueSummary['location'] . count($issueSummaries)] = $issueSummary;
						}
					}
				}
			}

			return $issueSummaries;
		}
		return null;
	}
	/**
	 * Classic OPAC scope for legacy screen scraping calls
	 * @param bool $checkLibraryRestrictions  Whether or not to condition the use of Sierra OPAC scope by the library setting $restrictSearchByLibrary;
	 * @return mixed|string
	 */
	protected function getLibraryScope($checkLibraryRestrictions = false){

		//Load the holding label for the branch where the user is physically.
		$searchLocation = Location::getSearchLocation();
		if (!empty($searchLocation->scope)){
			return $searchLocation->scope;
		}

		$searchLibrary = Library::getSearchLibrary();
		if (!empty($searchLibrary->scope)){
			if (!$checkLibraryRestrictions || $searchLibrary->restrictSearchByLibrary){
				return $searchLibrary->scope;
			}
		}
		return $this->getDefaultScope();
	}

	protected function getDefaultScope(){
		global $configArray;
		return isset($configArray['OPAC']['defaultScope']) ? $configArray['OPAC']['defaultScope'] : '93';
	}

	/**
	 * Taken from the class MarcRecord method getShortId.
	 *
	 * @param string $longId III record Id with a trailing check digit included
	 * @return mixed|string   the initial dot & the trailing check digit removed
	 */
	protected static function getShortId($longId){
		$shortId = str_replace('.b', 'b', $longId);
		$shortId = substr($shortId, 0, strlen($shortId) - 1);
		return $shortId;
	}

}
