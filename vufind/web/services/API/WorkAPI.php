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
 * API functionality related to Grouped Works
 *
 * @category Pika
 * @author   Mark Noble <pika@marmot.org>
 * Date: 2/4/14
 * Time: 9:21 AM
 */

require_once ROOT_DIR . '/AJAXHandler.php';

class WorkAPI extends AJAXHandler {

	protected $methodsThatRespondWithJSONResultWrapper = array(
		'getRatingData',
		'getIsbnsForWork',
		'generateWorkId',
		'getBasicWorkInfo',
	);

	public function getRatingData($permanentId = null){
		if (is_null($permanentId) && isset($_REQUEST['id'])){
			$permanentId = $_REQUEST['id'];
		}

		//Set default rating data
		$ratingData = array(
			'average'  => 0,
			'count'    => 0,
			'user'     => 0,
			'num1star' => 0,
			'num2star' => 0,
			'num3star' => 0,
			'num4star' => 0,
			'num5star' => 0,
		);

		//Somehow we didn't get an id (work no longer exists in the index)
		if (is_null($permanentId)){
			return $ratingData;
		}

		require_once ROOT_DIR . '/sys/LocalEnrichment/UserWorkReview.php';
		$reviewData                         = new UserWorkReview();
		$reviewData->groupedWorkPermanentId = $permanentId;
		$reviewData->find();
		$totalRating = 0;
		while ($reviewData->fetch()){
			if ($reviewData->rating > 0){
				$totalRating += $reviewData->rating;
				$ratingData['count']++;
				if (UserAccount::isLoggedIn() && $reviewData->userId == UserAccount::getActiveUserId()){
					$ratingData['user'] = $reviewData->rating;
				}
				if ($reviewData->rating == 1){
					$ratingData['num1star']++;
				}elseif ($reviewData->rating == 2){
					$ratingData['num2star']++;
				}elseif ($reviewData->rating == 3){
					$ratingData['num3star']++;
				}elseif ($reviewData->rating == 4){
					$ratingData['num4star']++;
				}elseif ($reviewData->rating == 5){
					$ratingData['num5star']++;
				}
			}
		}
		if ($ratingData['count'] > 0){
			$ratingData['average']       = $totalRating / $ratingData['count'];
			$ratingData['barWidth5Star'] = 100 * $ratingData['num5star'] / $ratingData['count'];
			$ratingData['barWidth4Star'] = 100 * $ratingData['num4star'] / $ratingData['count'];
			$ratingData['barWidth3Star'] = 100 * $ratingData['num3star'] / $ratingData['count'];
			$ratingData['barWidth2Star'] = 100 * $ratingData['num2star'] / $ratingData['count'];
			$ratingData['barWidth1Star'] = 100 * $ratingData['num1star'] / $ratingData['count'];
		}else{
			$ratingData['barWidth5Star'] = 0;
			$ratingData['barWidth4Star'] = 0;
			$ratingData['barWidth3Star'] = 0;
			$ratingData['barWidth2Star'] = 0;
			$ratingData['barWidth1Star'] = 0;
		}
		return $ratingData;
	}

	public function getIsbnsForWork($permanentId = null){
		if ($permanentId == null){
			$permanentId = $_REQUEST['id'];
		}

		//Speed this up by not loading the entire grouped work driver since all we need is a list of ISBNs
		//require_once ROOT_DIR . '/RecordDrivers/GroupedWorkDriver.php';
		//$groupedWorkDriver = new GroupedWorkDriver($permanentId);
		//return $groupedWorkDriver->getISBNs();

		global $configArray;
		$class = $configArray['Index']['engine'];
		$url   = $configArray['Index']['url'];
		/** @var Solr $db */
		$db = new $class($url);

		disableErrorHandler();
		$record = $db->getRecord($permanentId, 'isbn');
		enableErrorHandler();
		if ($record == false || PEAR_Singleton::isError($record)){
			return array();
		}else{
			return $record['isbn'];
		}


	}

	public function generateWorkId(){
		$title        = escapeshellarg($_REQUEST['title']);
		$author       = escapeshellarg($_REQUEST['author']);
		$format       = escapeshellarg($_REQUEST['format']);
		$languageCode = escapeshellarg($_REQUEST['languageCode'] ?? 'eng');
		$subtitle     = !empty($_REQUEST['subtitle']) ? escapeshellarg($_REQUEST['subtitle']) : null;

		// Get site name from covers directory
		global $configArray;
		global $pikaLogger;
		$siteName           = getSiteName();
		$localPath          = $configArray['Site']['local'];
		$recordGroupingPath = realpath("$localPath/../record_grouping/");
		$commandToRun       = "java -jar $recordGroupingPath/record_grouping.jar $siteName generateWorkId $title $author $format $languageCode $subtitle";
		$output             = shell_exec($commandToRun);
		$result             = strstr($output, '{"grouping'); // Strip out any logging notices and get just the JSON string
		$pikaLogger->notice("Generating Work Id via Work API ", ['command' => $commandToRun, 'output' => $output]);
		return json_decode($result);
	}

	public function getBasicWorkInfo() {
		$sourceAndId = $_REQUEST['id'];
		$recordDriver = new MarcRecord($sourceAndId);
		$work = [];
		if ($recordDriver->isValid()) {
			$work['coverUrl']      = $recordDriver->getBookcoverUrl('medium');
			$work['groupedWorkId'] = $recordDriver->getGroupedWorkId();
			$work['format']        = $recordDriver->getPrimaryFormat();
			$work['author']        = $recordDriver->getPrimaryAuthor();
			$work['title']         = $recordDriver->getTitle();
			$work['title_sort']    = $recordDriver->getSortableTitle();
			$work['link']          = $recordDriver->getLinkUrl();
		} else {
			$work['coverUrl']      = "";
			$work['groupedWorkId'] = "";
			$work['format']        = "Unknown";
			$work['author']        = "Unknown";
			$work['title']         = "Unknown";
			$work['title_sort']    = "Unknown";
			$work['link']          = '';
		}

		return $work;
	}


}
