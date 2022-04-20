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
 * Handles loading asynchronous
 *
 * @category Pika
 */

require_once ROOT_DIR . '/AJAXHandler.php';
//require_once ROOT_DIR . '/services/AJAX/Captcha_AJAX.php';
require_once ROOT_DIR . '/sys/Pika/Functions.php';
require_once ROOT_DIR . '/services/MyAccount/MyAccount.php';
use function Pika\Functions\{recaptchaGetQuestion, recaptchaCheckAnswer};

class GroupedWork_AJAX extends AJAXHandler {

	//use Captcha_AJAX;

	protected $methodsThatRespondWithJSONUnstructured = array(
		'clearUserRating',
		'deleteUserReview',
		'forceRegrouping',
		'forceReindex',
		'getEnrichmentInfo',
		'getScrollerTitle',
		'getGoDeeperData',
		'getWorkInfo',
		'getTitles',
		'RateTitle',
		'clearMySuggestionsBrowseCategoryCache',
		'getReviewInfo',
		'getPromptforReviewForm',
		'setNoMoreReviews',
		'getReviewForm',
		'saveReview',
		'getSMSForm',
		'getEmailForm',
		'sendEmail',
		'saveToList',
		'saveSelectedToList',
		'saveSeriesToList',
		'getSaveToListForm',
		'getSaveMultipleToListForm',
		'getSaveSeriesToListForm',
		'sendSMS',
		'markNotInterested',
		'clearNotInterested',
		'getAddTagForm',
		'saveTag',
		'removeTag',
		'getProspectorInfo',
//		'getNovelistData',
		'reloadCover',
		'reloadNovelistData',
		'reloadIslandora',
		'getSeriesEmailForm',
		'sendSeriesEmail',
		'getCreateSeriesForm',
		'createSeriesList',

	);

	protected array $methodsThatRespondThemselves = array(
		'exportSeriesToExcel',
	);


	/**
	 * Alias of deleteUserReview()
	 *
	 * @return string
	 */
	function clearUserRating(){
		return $this->deleteUserReview();
	}

	function deleteUserReview(){
		$id     = $_REQUEST['id'];
		$result = array('result' => false);
		if (!UserAccount::isLoggedIn()){
			$result['message'] = 'You must be logged in to delete ratings.';
		}else{
			require_once ROOT_DIR . '/sys/LocalEnrichment/UserWorkReview.php';
			$userWorkReview                         = new UserWorkReview();
			$userWorkReview->groupedWorkPermanentId = $id;
			$userWorkReview->userId                 = UserAccount::getActiveUserId();
			if ($userWorkReview->find(true)){
				$userWorkReview->delete();
				$result = array('result' => true, 'message' => 'We successfully deleted the rating for you.');
			}else{
				$result['message'] = 'Sorry, we could not find that review in the system.';
			}
		}

		return $result;
	}

	function forceRegrouping(){
		require_once ROOT_DIR . '/sys/Grouping/GroupedWork.php';
		$id = $_REQUEST['id'];

		if (GroupedWork::validGroupedWorkId($id)){
			$groupedWork               = new GroupedWork();
			$groupedWork->permanent_id = $id;
			if ($groupedWork->find(true)){
				$recordsMarkedForRegrouping = $groupedWork->forceRegrouping();
				if ($recordsMarkedForRegrouping){
					return ['success' => true, 'message' => 'Regrouped the work.'];
				}else{
					return ['success' => false, 'message' => 'Regrouping failed.'];
				}
			}else{
				return ['success' => false, 'message' => 'Unable to regroup the work. Could not find the work.'];
			}
		}else{
			return ['success' => false, 'message' => 'Unable to regroup the work. Invalid grouped work Id'];
		}
	}

	function forceReindex(){
		require_once ROOT_DIR . '/RecordDrivers/GroupedWorkDriver.php';
		require_once ROOT_DIR . '/sys/Grouping/GroupedWork.php';
		$id = $_REQUEST['id'];
		if (GroupedWork::validGroupedWorkId($id)){
			$groupedWork               = new GroupedWork();
			$groupedWork->permanent_id = $id;
			if ($groupedWork->find(true)){
				if ($groupedWork->date_updated == null){
					return ['success' => true, 'message' => 'This title was already marked to be indexed again next time the index is run.'];
				}
				$numRows = $groupedWork->forceReindex();
				if ($numRows == 1){
					return ['success' => true, 'message' => 'This title will be indexed again next time the index is run.'];
				}else{
					return ['success' => false, 'message' => 'Unable to mark the title for indexing. Could not update the title.'];
				}
			}else{
				return ['success' => false, 'message' => 'Unable to mark the title for indexing. Could not find the title.'];
			}
		}else{
			return ['success' => false, 'message' => 'Invalid Grouped Work Id'];
		}
	}

	function getEnrichmentInfo(){
		require_once ROOT_DIR . '/sys/Grouping/GroupedWork.php';
		$id               = $_REQUEST['id'];
		$enrichmentResult = [];
		if (GroupedWork::validGroupedWorkId($id)){
			global $configArray;
			global $interface;
			global $memoryWatcher;

			require_once ROOT_DIR . '/RecordDrivers/GroupedWorkDriver.php';
			$recordDriver   = new GroupedWorkDriver($id);
			$enrichmentData = $recordDriver->loadEnrichment();
			$memoryWatcher->logMemory('Loaded Enrichment information from Novelist');

			//Process series data
			$titles = [];
			if (empty($enrichmentData['novelist']->seriesTitles)){
				$enrichmentResult['seriesInfo'] = ['titles' => $titles, 'currentIndex' => 0];
			}else{
				foreach ($enrichmentData['novelist']->seriesTitles as $key => $record){
					$titles[] = $this->getScrollerTitle($record, $key, 'Series');
				}

				$seriesInfo                     = ['titles' => $titles, 'currentIndex' => $enrichmentData['novelist']->seriesDefaultIndex];
				$enrichmentResult['seriesInfo'] = $seriesInfo;
			}
			$memoryWatcher->logMemory('Loaded Series information');

			//Process other data from novelist
			if (!empty($enrichmentData['novelist']->similarTitles)){
				$interface->assign('similarTitles', $enrichmentData['novelist']->similarTitles);
				if ($configArray['Catalog']['showExploreMoreForFullRecords']){
					$enrichmentResult['similarTitlesNovelist'] = $interface->fetch('GroupedWork/similarTitlesNovelistSidebar.tpl');
				}else{
					$enrichmentResult['similarTitlesNovelist'] = $interface->fetch('GroupedWork/similarTitlesNovelist.tpl');
				}
			}
			$memoryWatcher->logMemory('Loaded Similar titles from Novelist');

			if (!empty($enrichmentData['novelist']->authors)){
				$interface->assign('similarAuthors', $enrichmentData['novelist']->authors);
				if ($configArray['Catalog']['showExploreMoreForFullRecords']){
					$enrichmentResult['similarAuthorsNovelist'] = $interface->fetch('GroupedWork/similarAuthorsNovelistSidebar.tpl');
				}else{
					$enrichmentResult['similarAuthorsNovelist'] = $interface->fetch('GroupedWork/similarAuthorsNovelist.tpl');
				}
			}
			$memoryWatcher->logMemory('Loaded Similar authors from Novelist');

			if (isset($enrichmentData['novelist']) && isset($enrichmentData['novelist']->similarSeries)){
				$interface->assign('similarSeries', $enrichmentData['novelist']->similarSeries);
				if ($configArray['Catalog']['showExploreMoreForFullRecords']){
					$enrichmentResult['similarSeriesNovelist'] = $interface->fetch('GroupedWork/similarSeriesNovelistSidebar.tpl');
				}else{
					$enrichmentResult['similarSeriesNovelist'] = $interface->fetch('GroupedWork/similarSeriesNovelist.tpl');
				}
			}
			$memoryWatcher->logMemory('Loaded Similar series from Novelist');

			if (!empty($enrichmentData['novelist']->primaryISBN)){
				// Populate primary novelist isbn in grouped work staff view (This enrichment call may be the first time it is fetched)
				$enrichmentResult['novelistPrimaryISBN'] = $enrichmentData['novelist']->primaryISBN;
			}

			//Load Similar titles (from Solr)
			$class = $configArray['Index']['engine'];
			$url   = $configArray['Index']['url'];
			/** @var Solr $db */
			$db      = new $class($url);
			$similar = $db->getMoreLikeThis2($id);
			$memoryWatcher->logMemory('Loaded More Like This data from Solr');
			if (is_array($similar) && !empty($similar['response']['docs'])){
				$similarTitles = [];
				foreach ($similar['response']['docs'] as $key => $similarTitle){
					$similarTitleDriver = new GroupedWorkDriver($similarTitle);
					$record             = [
						'id'             => $similarTitleDriver->getPermanentId(),
						'mediumCover'    => $similarTitleDriver->getBookcoverUrl('medium'),
						'title'          => $similarTitleDriver->getTitle(),
						'author'         => $similarTitleDriver->getPrimaryAuthor(),
						'fullRecordLink' => $similarTitleDriver->getLinkUrl()
					];

					$similarTitles[] = $this->getScrollerTitle($record, $key, 'MoreLikeThis');
				}
				$similarTitlesInfo                 = ['titles' => $similarTitles, 'currentIndex' => 0];
				$enrichmentResult['similarTitles'] = $similarTitlesInfo;
			}
			$memoryWatcher->logMemory('Loaded More Like This scroller data');

			//Load go deeper options
			//TODO: Additional go deeper options
			if (isset($library) && $library->showGoDeeper == 0){
				$enrichmentResult['showGoDeeper'] = false;
			}else{
				require_once ROOT_DIR . '/sys/ExternalEnrichment/GoDeeperData.php';
				$goDeeperOptions = GoDeeperData::getGoDeeperOptions($recordDriver->getCleanISBN(), $recordDriver->getCleanUPC());
				if (count($goDeeperOptions['options']) == 0){
					$enrichmentResult['showGoDeeper'] = false;
				}else{
					$enrichmentResult['showGoDeeper']    = true;
					$enrichmentResult['goDeeperOptions'] = $goDeeperOptions['options'];
				}
			}
			$memoryWatcher->logMemory('Loaded additional go deeper data');

//			//Related data
//			$enrichmentResult['relatedContent'] = $interface->fetch('Record/relatedContent.tpl');
		}

		return $enrichmentResult;
	}

	/**
	 * @param $record array
	 * @param $titleIndexNumber
	 * @param $scrollerName string
	 * @return array
	 */
	function getScrollerTitle($record, $titleIndexNumber, $scrollerName){
		$title = preg_replace("/\\s*(\\/|:)\\s*$/", "", $record['title']);
		if (!empty($record['series'])){
			if (is_array($record['series'])){
				foreach ($record['series'] as $series){
					if (strcasecmp($series, 'none') !== 0){
						break;
					}else{
						$series = '';
					}
				}
			}else{
				$series = $record['series'];
			}
			if (!empty($series)){
				$title .= ' (' . $series . (isset($record['volume']) ? ' Volume ' . $record['volume'] : '') . ')';
			}
		}
		global $interface;
		$interface->assign('index', $titleIndexNumber);
		$interface->assign('scrollerName', $scrollerName);
		$interface->assign('id', $record['id'] ?? null);
		$interface->assign('title', $title);
		$interface->assign('author', $record['author']);
		$interface->assign('linkUrl', $record['fullRecordLink'] ?? null);
		$interface->assign('bookCoverUrlMedium', $record['mediumCover']);

		// Have to empty these template variables when we do have the Id so that the right thing is displayed
		$interface->assign('noResultOriginalId',  isset($record['id']) ? '' : $_REQUEST['id']);
		$interface->assign('series',  $series ?? null);

		return [
			'id'             => $record['id'] ?? '',
			'image'          => $record['mediumCover'],
			'title'          => $title,
			'author'         => $record['author'] ?? '',
			'formattedTitle' => $interface->fetch('RecordDrivers/GroupedWork/scroller-title.tpl')
		];
	}

	function getGoDeeperData(){
		require_once ROOT_DIR . '/sys/ExternalEnrichment/GoDeeperData.php';
		$dataType = strip_tags($_REQUEST['dataType']);

		require_once ROOT_DIR . '/RecordDrivers/GroupedWorkDriver.php';
		$id = !empty($_REQUEST['id']) ? $_REQUEST['id'] : $_GET['id'];
		// TODO: request id is not always being set by index page.
		$recordDriver = new GroupedWorkDriver($id);
		$upc          = $recordDriver->getCleanUPC();
		$isbn         = $recordDriver->getCleanISBN();

		$formattedData = GoDeeperData::getHtmlData($dataType, 'GroupedWork', $isbn, $upc);
		$return        = array(
			'formattedData' => $formattedData,
		);
		return $return;

	}

	function getTitles(){
		require_once ROOT_DIR. '/RecordDrivers/GroupedWorkDriver.php';
		$ids = $_REQUEST["ids"];
		$titles = array();
		foreach ($ids as $id){
			$recordDriver = new GroupedWorkDriver($id);
			$titles[] = [
				'title' => $recordDriver->getTitleShort(),
				'id'  => $id
			];
		}
		return [
			'success' => true,
			'titles'  => $titles
		];
	}

	function getWorkInfo(){
		global $interface;

		//Indicate we are showing search results so we don't get hold buttons
		$interface->assign('displayingSearchResults', true);

		require_once ROOT_DIR . '/RecordDrivers/GroupedWorkDriver.php';
		$id           = $_REQUEST['id'];
		$recordDriver = new GroupedWorkDriver($id);

		if (!empty($_REQUEST['browseCategory'])){ // TODO need to check for $_REQUEST['subCategory'] ??
			// Changed from $_REQUEST['browseCategoryId'] to $_REQUEST['browseCategory'] to be consistent with Browse Category code.
			// TODO Need to see when this comes into action and verify it works as expected. plb 8-19-2015
			require_once ROOT_DIR . '/sys/Browse/BrowseCategory.php';
			$browseCategory         = new BrowseCategory();
			$browseCategory->textId = $_REQUEST['browseCategory'];
			if ($browseCategory->find(true)){
				$browseCategory->numTitlesClickedOn++;
				$browseCategory->update_stats_only();
			}
		}
		$interface->assign('recordDriver', $recordDriver);

//		// if the grouped work consists of only 1 related item, return the record url, otherwise return the grouped-work url
		$relatedRecords = $recordDriver->getRelatedRecords();

		// short version

		if (count($relatedRecords) == 1){
			$firstRecord = reset($relatedRecords);
			$url         = $firstRecord['url'];
		}else{
			$url = $recordDriver->getLinkUrl();
		}

		$escapedId   = htmlentities($recordDriver->getPermanentId()); // escape for html
		$buttonLabel = translate('Add to favorites');

		// button template
		$interface->assign('escapeId', $escapedId);
		$interface->assign('buttonLabel', $buttonLabel);
		$interface->assign('url', $url);
		$interface->assign('inPopUp', true);

		$results = array(
			'title'        => "<a href='$url'>{$recordDriver->getTitle()}</a>",
			'modalBody'    => $interface->fetch('GroupedWork/work-details.tpl'),
			'modalButtons' => "<button onclick=\"return Pika.GroupedWork.showSaveToListForm(this, '$escapedId');\" class=\"modal-buttons btn btn-primary\" style='float: left'>$buttonLabel</button>"
				. "<a href='$url'><button class='modal-buttons btn btn-primary'>More Info</button></a>",
		);
		return $results;
	}

	function RateTitle(){
		require_once(ROOT_DIR . '/sys/LocalEnrichment/UserWorkReview.php');
		if (!UserAccount::isLoggedIn()){
			return ['error' => 'Please log in to rate this title.'];
		}
		if (empty($_REQUEST['id'])){
			return ['error' => 'ID for the item to rate is required.'];
		}
		if (empty($_REQUEST['rating']) || !ctype_digit($_REQUEST['rating'])){
			return ['error' => 'Invalid value for rating.'];
		}
		$rating = $_REQUEST['rating'];
		//Save the rating
		$workReview                         = new UserWorkReview();
		$workReview->groupedWorkPermanentId = $_REQUEST['id'];
		$workReview->userId                 = UserAccount::getActiveUserId();
		if ($workReview->find(true)){
			if ($rating != $workReview->rating){ // update gives an error if the rating value is the same as stored.
				$workReview->rating = $rating;
				$success            = $workReview->update();
			}else{
				// pretend success since rating is already set to same value.
				$success = true;
			}
		}else{
			$workReview->rating    = $rating;
			$workReview->review    = '';  // default value required for insert statements //TODO alter table structure, null should be default value.
			$workReview->dateRated = time(); // moved to be consistent with add review behaviour
			$success               = $workReview->insert();
		}

		if ($success){
			// Reset any cached suggestion browse category for the user
			$this->clearMySuggestionsBrowseCategoryCache();

			return ['rating' => $rating];
		}else{
			return ['error' => 'Unable to save your rating.'];
		}
	}

	private function clearMySuggestionsBrowseCategoryCache(){
		// Reset any cached suggestion browse category for the user
		/** @var Memcache $memCache */
		global $memCache, $solrScope;
		foreach (['covers', 'grid'] as $browseMode){ // (Browse modes are set in class Browse_AJAX)
			$key = 'browse_category_system_recommended_for_you_' . UserAccount::getActiveUserId() . '_' . $solrScope . '_' . $browseMode;
			$memCache->delete($key);
		}

	}

	function getReviewInfo(){
		$results = [];
		$id      = $_REQUEST['id'];
		require_once ROOT_DIR . '/sys/Grouping/GroupedWork.php';
		if (GroupedWork::validGroupedWorkId($id)){
			require_once ROOT_DIR . '/RecordDrivers/GroupedWorkDriver.php';
			$recordDriver = new GroupedWorkDriver($id);

			global $interface;
			$interface->assign('id', $id);

			//Load external (syndicated reviews)
			require_once ROOT_DIR . '/sys/ExternalEnrichment/Reviews.php';

			// Try the Novelist Primary ISBN first
			$reviews = [];
			$isbn    = $recordDriver->getNovelistPrimaryISBN();
			if (!empty($isbn)){
				$externalReviews = new ExternalReviews($isbn);
				$reviews         = $externalReviews->fetch();
			}
			if (empty($reviews)){
				// Now Try the primary ISBN
				$primaryISBN     = $recordDriver->getPrimaryIsbn();
				if (!empty($primaryISBN) && (empty($isbn) || $primaryISBN != $isbn)){
					$externalReviews = new ExternalReviews($primaryISBN);
					$reviews         = $externalReviews->fetch();
					$isbn            = $primaryISBN;
				}
			}
			$numSyndicatedReviews = 0;
			foreach ($reviews as $providerReviews){
				$numSyndicatedReviews += count($providerReviews);
			}
			$interface->assign('syndicatedReviews', $reviews);

			//Load librarian reviews
			require_once ROOT_DIR . '/sys/LocalEnrichment/LibrarianReview.php';
			$allLibrarianReviews                      = [];
			$librarianReviews                         = new LibrarianReview();
			$librarianReviews->groupedWorkPermanentId = $id;
			if ($librarianReviews->find()){
				while ($librarianReviews->fetch()){
					$allLibrarianReviews[] = clone($librarianReviews);
				}
				$interface->assign('librarianReviews', $allLibrarianReviews);
			}

			$userReviews = $recordDriver->getUserReviews();
			$interface->assign('userReviews', $userReviews);

			$customerReviewsHtml = $interface->fetch('GroupedWork/view-user-reviews.tpl');
			$results             = [
				'isbnForReviews'        => $isbn,
				'numSyndicatedReviews'  => $numSyndicatedReviews,
				'syndicatedReviewsHtml' => $interface->fetch('GroupedWork/view-syndicated-reviews.tpl'),
				'numLibrarianReviews'   => count($allLibrarianReviews),
				'librarianReviewsHtml'  => $interface->fetch('GroupedWork/view-librarian-reviews.tpl'),
				'numCustomerReviews'    => strpos($customerReviewsHtml, 'No borrower reviews currently exist') ? 0 : count($userReviews),  //sometimes there are ratings but no reviews to actually display
				'customerReviewsHtml'   => $customerReviewsHtml,
			];
		}
		return $results;
	}

	function getPromptforReviewForm(){
		$user = UserAccount::getActiveUserObj();
		if ($user){
			if (!$user->noPromptForUserReviews){
				global $interface;
				$id = $_REQUEST['id'];
				if (!empty($id)){
					$results = [
						'prompt'       => true,
						'title'        => 'Add a Review',
						'modalBody'    => $interface->fetch("GroupedWork/prompt-for-review-form.tpl"),
						'modalButtons' => "<button class='tool btn btn-primary' onclick='Pika.GroupedWork.showReviewForm(this, \"{$id}\");'>Submit A Review</button>",
					];
				}else{
					$results = [
						'error'   => true,
						'message' => 'Invalid ID.',
					];
				}
			}else{
				// Option already set to don't prompt, so let's don't prompt already.
				$results = [
					'prompt' => false,
				];
			}
		}else{
			$results = [
				'error'   => true,
				'message' => 'You are not logged in.',
			];
		}
		return $results;
	}

	function setNoMoreReviews(){
		$user = UserAccount::getActiveUserObj();
		if ($user){
			$user->noPromptForUserReviews = 1;
			$success                      = $user->update();
			return array('success' => $success);
		}
	}

	function getReviewForm(){
		global $interface;
		$id = $_REQUEST['id'];
		if (!empty($id)){
			$interface->assign('id', $id);

			// check if rating/review exists for user and work
			require_once ROOT_DIR . '/sys/LocalEnrichment/UserWorkReview.php';
			$groupedWorkReview                         = new UserWorkReview();
			$groupedWorkReview->userId                 = UserAccount::getActiveUserId();
			$groupedWorkReview->groupedWorkPermanentId = $id;
			if ($groupedWorkReview->find(true)){
				$interface->assign('userRating', $groupedWorkReview->rating);
				$interface->assign('userReview', $groupedWorkReview->review);
			}

//			$title   = ($library->showFavorites && !$library->showComments) ? 'Rating' : 'Review'; // the library object doesn't seem to have the up-to-date settings.
			$title   = ($interface->get_template_vars('showRatings') && !$interface->get_template_vars('showComments')) ? 'Rating' : 'Review';
			$results = array(
				'title'        => $title,
				'modalBody'    => $interface->fetch("GroupedWork/review-form-body.tpl"),
				'modalButtons' => "<button class='tool btn btn-primary' onclick='Pika.GroupedWork.saveReview(\"{$id}\");'>Submit $title</button>",
			);
		}else{
			$results = array(
				'error'   => true,
				'message' => 'Invalid ID.',
			);
		}
		return $results;
	}

	function saveReview(){
		$result = array();

		if (UserAccount::isLoggedIn() == false){
			$result['success'] = false;
			$result['message'] = 'Please log in before adding a review.';
		}elseif (empty($_REQUEST['id'])){
			$result['success'] = false;
			$result['message'] = 'ID for the item to review is required.';
		}else{
			require_once ROOT_DIR . '/sys/LocalEnrichment/UserWorkReview.php';
			$id        = $_REQUEST['id'];
			$rating    = isset($_REQUEST['rating']) ? $_REQUEST['rating'] : '';
			$HadReview = isset($_REQUEST['comment']); // did form have the review field turned on? (may be only ratings instead)
			$comment   = $HadReview ? trim($_REQUEST['comment']) : ''; //avoids undefined index notice when doing only ratings.

			$groupedWorkReview                         = new UserWorkReview();
			$groupedWorkReview->userId                 = UserAccount::getActiveUserId();
			$groupedWorkReview->groupedWorkPermanentId = $id;
			$newReview                                 = true;
			if ($groupedWorkReview->find(true)){ // check for existing rating by user
				$newReview = false;
			}
			// set the user's rating and/or review
			if (!empty($rating) && is_numeric($rating)){
				$groupedWorkReview->rating = $rating;
			}
			if ($newReview){
				$groupedWorkReview->review    = $HadReview ? $comment : ''; // set an empty review when the user was doing only ratings. (per library settings) //TODO there is no default value in the database.
				$groupedWorkReview->dateRated = time();
				$success                      = $groupedWorkReview->insert();
			}else{
				if ((!empty($rating) && $rating != $groupedWorkReview->rating) || ($HadReview && $comment != $groupedWorkReview->review)){ // update gives an error if the updated values are the same as stored values.
					if ($HadReview){
						$groupedWorkReview->review = $comment;
					} // only update the review if the review input was in the form.
					$success = $groupedWorkReview->update();
				}else{
					$success = true;
				} // pretend success since values are already set to same values.
			}
			if (!$success){ // if sql save didn't work, let user know.
				$result['success'] = false;
				$result['message'] = 'Failed to save rating or review.';
			}else{ // successfully saved
				$result['success']   = true;
				$result['newReview'] = $newReview;
				$result['reviewId']  = $groupedWorkReview->id;
				global $interface;
				$interface->assign('review', $groupedWorkReview);
				$result['reviewHtml'] = $interface->fetch('GroupedWork/view-user-review.tpl');
			}
		}

		return $result;
	}

	function getSMSForm(){
		global $interface;
		require_once ROOT_DIR . '/sys/Mailer.php';

		$sms = new SMSMailer();
		$interface->assign('carriers', $sms->getCarriers());
		$id = $_REQUEST['id'];
		$interface->assign('id', $id);

		require_once ROOT_DIR . '/RecordDrivers/GroupedWorkDriver.php';
		$recordDriver = new GroupedWorkDriver($id);

		$relatedRecords = $recordDriver->getRelatedRecords();
		$interface->assign('relatedRecords', $relatedRecords);
		$results = array(
			'title'        => 'Share via SMS Message',
			'modalBody'    => $interface->fetch("GroupedWork/sms-form-body.tpl"),
			'modalButtons' => "<button class='tool btn btn-primary' onclick='Pika.GroupedWork.sendSMS(\"{$id}\"); return false;'>Send Text</button>",
		);
		return $results;
	}

	function getEmailForm(){
		$id             = $_REQUEST['id'];
		$recordDriver   = new GroupedWorkDriver($id);
		$relatedRecords = $recordDriver->getRelatedRecords();
		global $interface;
		$interface->assign('id', $id);
		$interface->assign('relatedRecords', $relatedRecords);

		if (UserAccount::isLoggedIn()){
			/** @var User $user */
			$user = UserAccount::getActiveUserObj();
			if (!empty($user->email)){
				$interface->assign('from', $user->email);
			}
		}else{
			$captchaCode = recaptchaGetQuestion();
			$interface->assign('captcha', $captchaCode);
		}
		return [
			'title'        => 'Share via E-mail',
			'modalBody'    => $interface->fetch("GroupedWork/email-form-body.tpl"),
			'modalButtons' => "<button class='tool btn btn-primary' onclick='$(\"#emailForm\").submit()'>Send E-mail</button>"
			// triggering submit action to trigger form validation
		];
	}

	function getSeriesEmailForm(){
		$id     =$_REQUEST['id'];
		$recordDriver = new GroupedWorkDriver($id);
		global $interface;
		$interface->assign('id', $id);
		if(UserAccount::isLoggedIn()){
			$user = UserAccount::getActiveUserObj();
			if (!empty($user->email)){
				$interface->assign('from', $user->email);
			}
		}else{
			$captchaCode = recaptchaGetQuestion();
			$interface->assign('captcha', $captchaCode);
		}
		return [
			'title'         => 'Share via E-mail',
			'modalBody'     => $interface->fetch("GroupedWork/email-series.tpl"),
			'modalButtons'  => "<button class='tool btn btn-primary' onclick='$(\"#emailForm\").submit()'>Send E-mail</button>"
		];
	}

	function sendEmail(){
		global $interface;
		global $configArray;
		$recaptchaValid = recaptchaCheckAnswer();
		if (UserAccount::isLoggedIn() || $recaptchaValid){
			$message = $_REQUEST['message'];
			if (strpos($message, 'http') === false && strpos($message, 'mailto') === false && $message == strip_tags($message)){
				require_once ROOT_DIR . '/RecordDrivers/GroupedWorkDriver.php';
				$id           = $_REQUEST['id'];
				$recordDriver = new GroupedWorkDriver($id);
				$to           = strip_tags($_REQUEST['to']);
				$from         = strip_tags($_REQUEST['from']);
				$interface->assign('from', $from);
				$interface->assign('message', $message);
				$interface->assign('recordDriver', $recordDriver);
				$interface->assign('url', $recordDriver->getAbsoluteUrl());

				if (!empty($_REQUEST['related_record'])){
					$relatedRecord = $recordDriver->getRelatedRecord($_REQUEST['related_record']);
					if (!empty($relatedRecord['callNumber'])){
						$interface->assign('callnumber', $relatedRecord['callNumber']);
					}
					if (!empty($relatedRecord['shelfLocation'])){
						$interface->assign('shelfLocation', strip_tags($relatedRecord['shelfLocation']));
					}
					if (!empty($relatedRecord['driver'])){
						$interface->assign('url', $relatedRecord['driver']->getAbsoluteUrl());
					}
				}

				$subject = translate('Library Catalog Record') . ': ' . $recordDriver->getTitle();
				$body    = $interface->fetch('Emails/grouped-work-email.tpl');

				require_once ROOT_DIR . '/sys/Mailer.php';
				$mail        = new VuFindMailer();
				$emailResult = $mail->send($to, $configArray['Site']['email'], $subject, $body, $from);

				if ($emailResult === true){
					$result = [
						'result'  => true,
						'message' => 'Your e-mail was sent successfully.',
					];
				}elseif (PEAR_Singleton::isError($emailResult)){
					$result = [
						'result'  => false,
						'message' => "Your e-mail message could not be sent: {$emailResult}.",
					];
				}else{
					$result = [
						'result'  => false,
						'message' => 'Your e-mail message could not be sent due to an unknown error.',
					];
					global $logger;
					$logger->log("Mail List Failure (unknown reason), parameters: $to, $from, $subject, $body", PEAR_LOG_ERR);
				}
			}else{
				$result = [
					'result'  => false,
					'message' => 'Sorry, we can&apos;t send e-mails with html or other data in it.',
				];
			}
		}else{ // logged in check, or captcha check
			$result = [
				'result'  => false,
				'message' => 'Not logged in or invalid captcha response',
			];
		}
		return $result;
	}

	function sendSeriesEmail(){
		global $interface;
		global $configArray;
		$recaptchaValid = recaptchaCheckAnswer();
		if (UserAccount::isLoggedIn() || $recaptchaValid){
			$message = $_REQUEST['message'];
			if (strpos($message, 'http') === false && strpos($message, 'mailto') === false && $message == strip_tags($message)){
				require_once ROOT_DIR . '/RecordDrivers/GroupedWorkDriver.php';
				$id           = $_REQUEST['id'];
				$recordDriver = new GroupedWorkDriver($id);
				$to           = strip_tags($_REQUEST['to']);
				$from         = strip_tags($_REQUEST['from']);
				$interface->assign('from', $from);
				$interface->assign('message', $message);
				$interface->assign('recordDriver', $recordDriver);
				$interface->assign('url', $recordDriver->getAbsoluteUrl());
				require_once ROOT_DIR . '/sys/Novelist/Novelist3.php';
				$novelist   = NovelistFactory::getNovelist();
				$seriesInfo = $novelist->getSeriesTitles($id, $recordDriver->getISBNs());
				$seriesData = $seriesInfo ->seriesTitles ?? [];
				$seriesTitle = $seriesInfo->seriesTitle ?? null;
				$interface->assign('seriesTitle', $seriesTitle);
				$interface->assign('seriesData', $seriesData);
				$subject = translate('Library Catalog Series') . ': ' . $seriesTitle;
				$body    = $interface->fetch('Emails/series-email.tpl');

				require_once ROOT_DIR . '/sys/Mailer.php';
				$mail        = new VuFindMailer();
				$emailResult = $mail->send($to, $configArray['Site']['email'], $subject, $body, $from);

				if ($emailResult === true){
					$result = [
						'result'  => true,
						'message' => 'Your e-mail was sent successfully.',
					];
				}elseif (PEAR_Singleton::isError($emailResult)){
					$result = [
						'result'  => false,
						'message' => "Your e-mail message could not be sent: {$emailResult}.",
					];
				}else{
					$result = [
						'result'  => false,
						'message' => 'Your e-mail message could not be sent due to an unknown error.',
					];
					global $logger;
					$logger->log("Mail List Failure (unknown reason), parameters: $to, $from, $subject, $body", PEAR_LOG_ERR);
				}
			}else{
				$result = [
					'result'  => false,
					'message' => 'Sorry, we can&apos;t send e-mails with html or other data in it.',
				];
			}
		}else{ // logged in check, or captcha check
			$result = [
				'result'  => false,
				'message' => 'Not logged in or invalid captcha response',
			];
		}
		return $result;
	}

	function saveToList(){
		$result = array();

		if (!UserAccount::isLoggedIn()){
			$result['success'] = false;
			$result['message'] = 'Please log in before adding a title to list.';
		}else{
			require_once ROOT_DIR . '/sys/LocalEnrichment/UserList.php';
			require_once ROOT_DIR . '/sys/LocalEnrichment/UserListEntry.php';
			$result['success'] = true;
			$id                = $_REQUEST['id'];
			$listId            = $_REQUEST['listId'];
			$notes             = $_REQUEST['notes'];

			//Check to see if we need to create a list
			$userList = new UserList();
			$listOk   = true;
			if (empty($listId)){
				$userList->title       = "My Favorites";
				$userList->user_id     = UserAccount::getActiveUserId();
				$userList->public      = 0;
				$userList->description = '';
				$userList->insert();
			}else{
				$userList->id = $listId;
				if (!$userList->find(true)){
					$result['success'] = false;
					$result['message'] = 'Sorry, we could not find that list in the system.';
					$listOk            = false;
				}
			}

			if ($listOk){
				$userListEntry         = new UserListEntry();
				$userListEntry->listId = $userList->id;
				require_once ROOT_DIR . '/sys/Grouping/GroupedWork.php';
				if (!GroupedWork::validGroupedWorkId($id)){
					$result['success'] = false;
					$result['message'] = 'Sorry, that is not a valid entry for the list.';
				}else{
					$userListEntry->groupedWorkPermanentId = $id;

					$existingEntry = false;
					if ($userListEntry->find(true)){
						$existingEntry = true;
					}
					$userListEntry->notes     = strip_tags($notes);
					$userListEntry->dateAdded = time();
					if ($existingEntry){
						$userListEntry->update();
					}else{
						$userListEntry->insert();
					}
					$result['success'] = true;
					$result['message'] = 'This title was saved to your list successfully.';
					$result['buttons'] = '<a class="btn btn-primary" href="/MyAccount/MyList/'. $userList->id . '" role="button">View My list</a>';
				}
			}

		}

		return $result;
	}

	function saveSelectedToList(){
		$result = array();
		if(!UserAccount::isLoggedIn()){
			$result['success'] = false;
			$result['message'] = 'Please log in before adding a title to list.';
		}else{
			require_once ROOT_DIR . '/sys/LocalEnrichment/UserList.php';
			require_once ROOT_DIR . '/sys/LocalEnrichment/UserListEntry.php';
			$result['success'] = true;
			$ids                = explode("%2C", $_REQUEST['ids']);
			$listId            = $_REQUEST['listId'];
			$userList = new UserList();
			$listOk   = true;
			if (empty($listId)){
				$userList->title       = "My Favorites";
				$userList->user_id     = UserAccount::getActiveUserId();
				$userList->public      = 0;
				$userList->description = '';
				$userList->insert();
			}else{
				$userList->id = $listId;
				if (!$userList->find(true)){
					$result['success'] = false;
					$result['message'] = 'Sorry, we could not find that list in the system.';
					$listOk            = false;
				}
			}
			if($listOk){
				$userListEntry         = new UserListEntry();
				$userListEntry->listId = $userList->id;
				require_once ROOT_DIR . '/sys/Grouping/GroupedWork.php';
				$attemptedRecords = count($ids);
				$errors           = 0;
				foreach ($ids as $id){
					if (!GroupedWork::validGroupedWorkId($id)){
						$errors++;
					}else{
						$userListEntry->groupedWorkPermanentId = $id;
						$existingEntry                         = false;
						if ($userListEntry->find(true)){
							$existingEntry = true;
						}
						$userListEntry->dateAdded = time();
						if ($existingEntry){
							$userListEntry->update();
						}else{
							$userListEntry->insert();
						}
					}
				}
				if ($errors > 0)
				{
					$successful = $attemptedRecords - $errors;
					$result['success'] = false;
					$result['message'] = $successful . " of " . $attemptedRecords . " records were added successfully. There were " . $errors . " errors.";
				}else{
					$result['success'] = true;
					$result['message'] = 'The titles were saved to your list successfully.';
					$result['buttons'] = '<a class="btn btn-primary" href="/MyAccount/MyList/'. $userList->id . '" role="button">View My list</a>';
				}
			}
		}
		return $result;
	}

	function saveSeriesToList(){
		$result = array();

		if (!UserAccount::isLoggedIn()){
			$result['success'] = false;
			$result['message'] = 'Please log in before adding a title to list.';
		}else{
			require_once ROOT_DIR . '/sys/LocalEnrichment/UserList.php';
			require_once ROOT_DIR . '/sys/LocalEnrichment/UserListEntry.php';
			require_once ROOT_DIR . '/sys/Novelist/Novelist3.php';
			require_once ROOT_DIR . '/RecordDrivers/GroupedWorkDriver.php';
			$result['success'] = true;
			$id                = $_REQUEST['id'];
			$listId            = $_REQUEST['listId'];
			$notes             = null;

			//Check to see if we need to create a list
			$userList = new UserList();
			$listOk   = true;
			if (empty($listId)){
				$userList->title       = "My Favorites";
				$userList->user_id     = UserAccount::getActiveUserId();
				$userList->public      = 0;
				$userList->description = '';
				$userList->insert();
				$listId = $userList->id;
			}else{
				$userList->id = $listId;
				if (!$userList->find(true)){
					$result['success'] = false;
					$result['message'] = 'Sorry, we could not find that list in the system.';
					$listOk            = false;
				}
			}

			if ($listOk){
				$recordDriver = new GroupedWorkDriver($id);
				$novelist = NovelistFactory::getNovelist();
				$seriesInfo = $novelist->getSeriesTitles($id, $recordDriver->getISBNs());
				$seriesTitle = $seriesInfo->seriesTitle;
				$seriesTitles = $seriesInfo->seriesTitles;
				$i = 0;
				foreach($seriesTitles as $title){
					$userListEntry         = new UserListEntry();
					$userListEntry->listId = $userList->id;
					$itemId = $title['id'];
					require_once ROOT_DIR . '/sys/Grouping/GroupedWork.php';
					if (!GroupedWork::validGroupedWorkId($itemId)){
						$result['success'] = false;
						$result['message'] = 'Sorry, that is not a valid entry for the list.';
					}else{
						$userListEntry->groupedWorkPermanentId = $itemId;

						$existingEntry = false;
						if ($userListEntry->find(true)){
							$existingEntry = true;
						}
						$notes = $seriesTitle . " volume " . $title['volume'];
						$userListEntry->notes     = strip_tags($notes);
						$userListEntry->dateAdded = time();
						if ($existingEntry){
							$userListEntry->update();
						}else{
							$userListEntry->insert();
						}
						$i++;
					}
				}
				$result['success'] = true;
				$result['message'] = 'Added ' . $i . ' titles to your list successfully.';
				$result['buttons'] = '<a class="btn btn-primary" href="/MyAccount/MyList/' . $userList->id . '" role="button">View My list</a>';
			}

		}

		return $result;
	}

	function getSaveToListForm(){
		global $interface;

		$id = $_REQUEST['id'];
		$interface->assign('id', $id);

		require_once ROOT_DIR . '/sys/LocalEnrichment/UserList.php';
		require_once ROOT_DIR . '/sys/LocalEnrichment/UserListEntry.php';

		//Get a list of all lists for the user
		$containingLists    = [];
		$nonContainingLists = [];
		$listsTooLarge      = [];

		$userLists          = new UserList();
		$userLists->user_id = UserAccount::getActiveUserId();
		$userLists->deleted = 0;
		$userLists->orderBy('dateUpdated > unix_timestamp()-300 desc, if(dateUpdated > unix_timestamp()-300 , dateUpdated, 0) desc, title');
		// Sort lists first by any that have been recently updated (within the last 5 mins)  eg a user is currently build a specific list
		// second sort factor for multiple lists updated within the last five minutes is the last updated time; other lists come next
		// finally alphabetical order for lists that haven't been updated recently
		//This complex sorting allows for a list that has had an entry added to it recently,
		// to show as the default selected list in the dropdown list of which user lists to add a title to
		$userLists->find();

		while ($userLists->fetch()){
			//Check to see if the user has already added the title to the list.
			if ($userLists->numValidListItems() >= 2000){
				$listsTooLarge[] = [
					'id'    => $userLists->id,
					'title' => $userLists->title,
				];
			}
			$userListEntry                         = new UserListEntry();
			$userListEntry->listId                 = $userLists->id;
			$userListEntry->groupedWorkPermanentId = $id;
			if ($userListEntry->find(true)){
				$containingLists[] = [
					'id'    => $userLists->id,
					'title' => $userLists->title,
				];
			}elseif (!$userListEntry->find(true) && $userLists->numValidListItems() < 2000){
				$nonContainingLists[] = [
					'id'    => $userLists->id,
					'title' => $userLists->title,
				];
			}

		}

		$interface->assign('containingLists', $containingLists);
		$interface->assign('nonContainingLists', $nonContainingLists);
		$interface->assign('largeLists', $listsTooLarge);

		$results = [
			'title'        => 'Add To List',
			'modalBody'    => $interface->fetch("GroupedWork/save.tpl"),
			'modalButtons' => "<button class='tool btn btn-primary' onclick='Pika.GroupedWork.saveToList(\"{$id}\");'>Save To List</button>",
		];
		return $results;
	}
function getCreateSeriesForm(){
	global $interface;
	require_once ROOT_DIR . '/sys/Novelist/Novelist3.php';
	require_once ROOT_DIR . '/RecordDrivers/GroupedWorkDriver.php';

	if (isset($_REQUEST['id'])){
		$id = $_REQUEST['id'];
		$interface->assign('groupedWorkId', $id);
	}else{
		$id = '';
	}
	$recordDriver = new GroupedWorkDriver($id);
	$interface->assign('id', $id);
	$novelist = NovelistFactory::getNovelist();
	$seriesInfo = $novelist->getSeriesTitles($id, $recordDriver->getPrimaryIsbn());
	$seriesTitle = $seriesInfo->seriesTitle??null;
	if($seriesTitle !=null ){
		$interface->assign('listTitle', $seriesTitle);
	}

	return array(
		'title'        => 'Create new List',
		'modalBody'    => $interface->fetch("GroupedWork/series-list-form.tpl"),
		'modalButtons' => "<span class='tool btn btn-primary' onclick='return Pika.GroupedWork.createSeriesList(\"{$id}\");'>Create List</span>",
	);
}

function createSeriesList(){
	global $interface;
	$id = $_REQUEST['groupedWorkId'];


	$recordsToAdd = array();
	$return      = array();
	if (UserAccount::isLoggedIn()){
		require_once ROOT_DIR . '/sys/LocalEnrichment/UserList.php';
		require_once ROOT_DIR . '/sys/LocalEnrichment/UserListEntry.php';
		require_once ROOT_DIR . '/sys/Novelist/Novelist3.php';
		require_once ROOT_DIR . '/RecordDrivers/GroupedWorkDriver.php';
		$recordDriver = new GroupedWorkDriver($id);
		$interface->assign('id', $id);
		$novelist = NovelistFactory::getNovelist();
		$seriesInfo = $novelist->getSeriesTitles($id, $recordDriver->getPrimaryIsbn());
		$seriesTitles = $seriesInfo->seriesTitles ?? [];
		$seriesTitle = $seriesInfo->seriesTitle?? null;
		$user = UserAccount::getLoggedInUser();
		$title = (isset($_REQUEST['title']) && !is_array($_REQUEST['title'])) ? urldecode($_REQUEST['title']) : '';
		if (strlen(trim($title)) == 0){
			$return['success'] = "false";
			$return['message'] = "You must provide a title for the list";
		}else{
			//If the record is not valid, skip the whole thing since the title could be bad too
			$e = 0;
			foreach($seriesTitles as $seriesItem){
				if (!empty($seriesItem['id'])){
					$recordToAdd = urldecode($seriesItem['id']);
					require_once ROOT_DIR . '/sys/Grouping/GroupedWork.php';
					if (!GroupedWork::validGroupedWorkId($recordToAdd)){
						$return['success'] = false;
						$e++;
						$return['message'] = $e . ' item(s) were not valid and could not be added to list';
					}else{
						$recordsToAdd[] = $recordToAdd;
					}
				}
			}
			$list          = new UserList();
			$list->title   = strip_tags($title);
			$list->user_id = $user->id;
			$list->defaultSort = "custom";
			//Check to see if there is already a list with this id
			$existingList = false;
			if ($list->find(true)){
				$existingList = true;
			}
			$description = $_REQUEST['desc'] ?? '';
			if (is_array($description)){
				$description = reset($description);
			}


			$list->description = strip_tags(urldecode($description));
			$list->public      = isset($_REQUEST['public']) && $_REQUEST['public'] == 'true';
			if ($existingList){
				$list->update();
			}else{
				$list->insert();
			}

			if (count($recordsToAdd) > 0){
				//Check to see if the user has already added the title to the list.
				foreach($recordsToAdd as $recordToAdd){
					$userListEntry                         = new UserListEntry();
					$userListEntry->listId                 = $list->id;
					$userListEntry->groupedWorkPermanentId = $recordToAdd;
					if (!$userListEntry->find(true)){
						$userListEntry->dateAdded = time();
						$userListEntry->insert();
					}
				}
			}

			$return['success'] = 'true';
			$return['newId']   = $list->id;
			if ($existingList){
				$return['message'] = "Updated list <em>{$title}</em> successfully";
			}else{
				$return['message'] = "Created list <em>{$title}</em> successfully";
				if($e > 0){$return['message'] .= $e . " records could not be added";}
			}
			if (!empty($list->id)){
				$return['modalButtons'] = '<a class="btn btn-primary" href="/MyAccount/MyList/'. $list->id .'" role="button">View My List</a>';
			}
		}
	}else{
		$return['success'] = "false";
		$return['message'] = "You must be logged in to create a list";
	}

	return $return;

}

function getSaveMultipleToListForm(){
		global $interface;
		$ids = explode("%2C", $_REQUEST['id']);
		$n = count($ids);
		$containingLists    = [];
		$nonContainingLists = [];
		$listsTooLarge      = [];
		$titles             = [];

	require_once ROOT_DIR . '/sys/LocalEnrichment/UserList.php';
		require_once ROOT_DIR . '/sys/LocalEnrichment/UserListEntry.php';
		require_once ROOT_DIR . '/RecordDrivers/GroupedWorkDriver.php';

		foreach($ids as $id){
			$groupedWork = new GroupedWorkDriver($id);
			$titles[] = array(
				'title' => $groupedWork->getTitleShort(),
				'id'    => $id
			);
		}

		$userLists  = new UserList();
		$userLists->user_id = UserAccount::getActiveUserId();
		$userLists->deleted = 0;
		$userLists->orderBy('dateUpdated > unix_timestamp()-300 desc, if(dateUpdated > unix_timestamp()-300 , dateUpdated, 0) desc, title');
		$userLists->find();
		while ($userLists->fetch()){
			if (($userLists->numValidListItems() + $n) >= 2001){
				$listsTooLarge[] = [
					'id'    => $userLists->id,
					'title' => $userLists->title,
				];
			}
			$userListEntry = new UserListEntry();
			$userListEntry->listId = $userLists->id;
			$inCurrentList = false;
			foreach($ids as $id)
			{
				$userListEntry->groupedWorkPermanentId = $id;
				if ($userListEntry->find(true)){
					$inCurrentList = true;
				}
			}
			if($inCurrentList){
				$containingLists[] = [
					'id'    => $userLists->id,
					'title' => $userLists->title,
				];
			}elseif (!$inCurrentList && ($userLists->numValidListItems() + $n) < 2001){
				$nonContainingLists[] = [
					'id'    => $userLists->id,
					'title' => $userLists->title,
				];
			}
		}
	$interface->assign('containingLists', $containingLists);
	$interface->assign('nonContainingLists', $nonContainingLists);
	$interface->assign('largeLists', $listsTooLarge);
	$interface->assign('ids', $ids);
	$interface->assign('textIds', $_REQUEST['id']);
	$interface->assign('titles', $titles);

	$results = [
		'title'        => 'Add To List',
		'modalBody'    => $interface->fetch("GroupedWork/saveMultiple.tpl"),
		'modalButtons' => "<button class='tool btn btn-primary' onclick='Pika.GroupedWork.saveSelectedToList(\"{$_REQUEST['id']}\");'>Save Items To List</button>",
	];
	return $results;
	}

function getSaveSeriesToListForm(){
	global $interface;
	$id = $_REQUEST['id'];
	require_once ROOT_DIR . '/sys/LocalEnrichment/UserList.php';
	require_once ROOT_DIR . '/sys/LocalEnrichment/UserListEntry.php';
	require_once ROOT_DIR . '/sys/Novelist/Novelist3.php';
	require_once ROOT_DIR . '/RecordDrivers/GroupedWorkDriver.php';
	$recordDriver = new GroupedWorkDriver($id);
	$interface->assign('id', $id);

	$novelist   = NovelistFactory::getNovelist();
	$seriesInfo = $novelist->getSeriesTitles($id, $recordDriver->getPrimaryIsbn());
	$seriesTitles = $seriesInfo->seriesTitles ?? [];
	$seriesTitle = $seriesInfo->seriesTitle ?? null;


	//Get a list of all lists for the user

	$nonContainingLists = [];
	$listsTooLarge      = [];

	$userLists          = new UserList();
	$userLists->user_id = UserAccount::getActiveUserId();
	$userLists->deleted = 0;
	$userLists->orderBy('dateUpdated > unix_timestamp()-300 desc, if(dateUpdated > unix_timestamp()-300 , dateUpdated, 0) desc, title');
	// Sort lists first by any that have been recently updated (within the last 5 mins)  eg a user is currently build a specific list
	// second sort factor for multiple lists updated within the last five minutes is the last updated time; other lists come next
	// finally alphabetical order for lists that haven't been updated recently
	//This complex sorting allows for a list that has had an entry added to it recently,
	// to show as the default selected list in the dropdown list of which user lists to add a title to
	$userLists->find();

	while ($userLists->fetch()){
		//Check to see if the user has already added the title to the list.
		if ($userLists->numValidListItems() >= 2000){
			$listsTooLarge[] = [
				'id'    => $userLists->id,
				'title' => $userLists->title,
			];
		}
		$userListEntry                         = new UserListEntry();
		$userListEntry->listId                 = $userLists->id;
		$userListEntry->groupedWorkPermanentId = $id;
		if ($userLists->numValidListItems() < 2000){
			$nonContainingLists[] = [
				'id'    => $userLists->id,
				'title' => $userLists->title,
			];
		}

	}

	$interface->assign('nonContainingLists', $nonContainingLists);
	$interface->assign('largeLists', $listsTooLarge);
	$interface->assign('seriesTitles', $seriesTitles);
	$interface->assign('seriesTitle', $seriesTitle);

		$results = [
			'title'         => 'Add Series to List',
			'modalBody'     => $interface->fetch("GroupedWork/save-series.tpl"),
			'modalButtons'  => "<button class='tool btn btn-primary' onclick='Pika.GroupedWork.saveSeriesToList(\"{$id}\");'>Save To List</button>",
		];
		return $results;
}


	function sendSMS(){
		global $configArray;
		global $interface;
		require_once ROOT_DIR . '/sys/Mailer.php';
		$sms = new SMSMailer();

		// Get Holdings
		$id = $_REQUEST['id'];
		require_once ROOT_DIR . '/RecordDrivers/GroupedWorkDriver.php';
		$recordDriver = new GroupedWorkDriver($id);
		$interface->assign('url', $recordDriver->getAbsoluteUrl());

		if (isset($_REQUEST['related_record'])){
			$relatedRecord = $_REQUEST['related_record'];
			require_once ROOT_DIR . '/RecordDrivers/GroupedWorkDriver.php';
			$recordDriver = new GroupedWorkDriver($id);

			$relatedRecords = $recordDriver->getRelatedRecords();

			foreach ($relatedRecords as $curRecord){
				if ($curRecord['id'] == $relatedRecord){
					if (isset($curRecord['callNumber'])){
						$interface->assign('callnumber', $curRecord['callNumber']);
					}
					if (isset($curRecord['shelfLocation'])){
						$interface->assign('shelfLocation', strip_tags($curRecord['shelfLocation']));
					}
					$interface->assign('url', $curRecord['driver']->getAbsoluteUrl());
					break;
				}
			}
		}

		$interface->assign('title', $recordDriver->getTitle());
		$interface->assign('author', $recordDriver->getPrimaryAuthor());
		$message = $interface->fetch('Emails/grouped-work-sms.tpl');

		$smsResult = $sms->text($_REQUEST['provider'], $_REQUEST['sms_phone_number'], $configArray['Site']['email'], $message);

		if ($smsResult === true){
			$result = array(
				'result'  => true,
				'message' => 'Your text message was sent successfully.',
			);
		}elseif (PEAR_Singleton::isError($smsResult)){
			$result = array(
				'result'  => false,
				'message' => 'Your text message was count not be sent {$smsResult}.'
				//			'message' => "Your text message count not be sent: {$smsResult->message}."
			);
		}else{
			$result = array(
				'result'  => false,
				'message' => 'Your text message could not be sent due to an unknown error.',
			);
		}

		return $result;
	}

	function markNotInterested(){
		if (UserAccount::isLoggedIn()){
			$id = $_REQUEST['id'];
			require_once ROOT_DIR . '/sys/LocalEnrichment/NotInterested.php';
			$notInterested                         = new NotInterested();
			$notInterested->userId                 = UserAccount::getActiveUserId();
			$notInterested->groupedWorkPermanentId = $id;

			if (!$notInterested->find(true)){
				$notInterested->dateMarked = time();
				if ($notInterested->insert()){

					// Reset any cached suggestion browse category for the user
					$this->clearMySuggestionsBrowseCategoryCache();

					$result = array(
						'result'  => true,
						'message' => "You won't be shown this title in the future.",
					);
				}
			}else{
				$result = array(
					'result'  => false,
					'message' => "This record was already marked as something you aren't interested in.",
				);
			}
		}else{
			$result = array(
				'result'  => false,
				'message' => "Please log in.",
			);
		}
		return $result;
	}

	function clearNotInterested(){
		$idToClear = $_REQUEST['id'];
		require_once ROOT_DIR . '/sys/LocalEnrichment/NotInterested.php';
		$notInterested         = new NotInterested();
		$notInterested->userId = UserAccount::getActiveUserId();
		$notInterested->id     = $idToClear;
		$result                = array('result' => false);
		if ($notInterested->find(true)){
			$notInterested->delete();
			$result = array('result' => true);
		}
		return $result;
	}

	function getAddTagForm(){
		global $interface;
		$id = $_REQUEST['id'];
		$interface->assign('id', $id);

		$results = array(
			'title'        => 'Add Tag',
			'modalBody'    => $interface->fetch("GroupedWork/addtag.tpl"),
			'modalButtons' => "<button class='tool btn btn-primary' onclick='Pika.GroupedWork.saveTag(\"{$id}\"); return false;'>Add Tags</button>",
		);
		return $results;
	}

	function saveTag(){
		if (!UserAccount::isLoggedIn()){
			return array('success' => false, 'message' => '<div class="alert alert-danger">Sorry, you must be logged in to add tags.</div>');
		}

		require_once ROOT_DIR . '/sys/LocalEnrichment/UserTag.php';

		$id = $_REQUEST['id'];
		// Parse apart the tags and save them in association with the resource:
		preg_match_all('/"[^"]*"|[^,]+/', $_REQUEST['tag'], $words);
		foreach ($words[0] as $tag){
			$tag = trim(strtolower(str_replace('"', '', $tag)));

			$userTag                         = new UserTag();
			$userTag->tag                    = $tag;
			$userTag->userId                 = UserAccount::getActiveUserId();
			$userTag->groupedWorkPermanentId = $id;
			if (!$userTag->find(true)){
				//This is a new tag
				$userTag->dateTagged = time();
				if ($userTag->insert()){
					return array('success' => true, 'message' => '<div class="alert alert-success">All tags have been added to the title.  Refresh to view updated tag list.</div>');
				}
			}else{
				return array('success' => false, 'message' => '<div class="alert alert-danger">This tag has already been added.</div>');
			}
		}
		return array('success' => false, 'message' => '<div class="alert alert-danger">Sorry, failed to save the tag.</div>');
	}

	function removeTag(){
		if (!UserAccount::isLoggedIn()){
			return array('success' => false, 'message' => '<div class="alert alert-danger">Sorry, you must be logged in to remove tags.</div>');
		}

		require_once ROOT_DIR . '/sys/LocalEnrichment/UserTag.php';

		$id                              = $_REQUEST['id'];
		$tag                             = $_REQUEST['tag'];
		$userTag                         = new UserTag();
		$userTag->tag                    = $tag;
		$userTag->userId                 = UserAccount::getActiveUserId();
		$userTag->groupedWorkPermanentId = $id;
		if ($userTag->find(true)){
			//This is a new tag
			if ($userTag->delete()){
				return array('success' => true, 'message' => '<div class="alert alert-success">Removed your tag from the title.  Refresh to view updated tag list.</div>');
			}
		}else{
			//This tag has already been added
			return array('success' => true, 'message' => '<div class="alert alert-danger">We could not find that tag for this record.</div>');
		}
		return array('success' => false, 'message' => '<div class="alert alert-danger">Sorry, failed to remove tag.</div>');
	}

	function getProspectorInfo(){
		global $configArray;
		global $interface;
		$id = $_REQUEST['id'];
		$interface->assign('id', $id);

		/** @var SearchObject_Solr $searchObject */
		$searchObject = SearchObjectFactory::initSearchObject();
		$searchObject->init();

		// Retrieve Full record from Solr
		if (!($record = $searchObject->getRecord($id))){
			PEAR_Singleton::raiseError(new PEAR_Error('Record Does Not Exist'));
		}

		//Load results from Prospector
		$ILLDriver = $configArray['InterLibraryLoan']['ILLDriver'];
		/** @var Prospector|AutoGraphicsShareIt $prospector */
		require_once ROOT_DIR . '/sys/InterLibraryLoanDrivers/' . $ILLDriver . '.php';
		$prospector = new $ILLDriver();

		$searchTerms = array(
			array(
				'lookfor' => $record['title_short'],
				'index'   => 'Title',
			),
		);
		if (isset($record['author'])){
			$searchTerms[] = array(
				'lookfor' => $record['author'],
				'index'   => 'Author',
			);
		}

		$prospectorResults = $prospector->getTopSearchResults($searchTerms, 10);
		$interface->assign('prospectorResults', $prospectorResults['records']);

		$result = array(
			'numTitles'     => count($prospectorResults),
			'formattedData' => $interface->fetch('GroupedWork/ajax-prospector.tpl'),
		);
		return $result;
	}

//	function getNovelistData(){
//		$url             = $_REQUEST['novelistUrl'];
//		$rawNovelistData = file_get_contents($url);
//		//Trim off the wrapping data ();
//		$rawNovelistData = substr($rawNovelistData, 1, -2);
//		$jsonData        = json_decode($rawNovelistData);
//		$novelistData    = $jsonData->body;
//		echo($novelistData);
//	}

	function reloadCover(){
		require_once ROOT_DIR . '/RecordDrivers/GroupedWorkDriver.php';
		global $configArray;
		$id           = $_REQUEST['id'];
		$recordDriver = new GroupedWorkDriver($id);

		//Reload small cover
		$smallCoverUrl =  $recordDriver->getBookcoverUrl('small', true) . '&reload';
		$ret           = file_get_contents($smallCoverUrl);

		//Reload medium cover
		$mediumCoverUrl = $recordDriver->getBookcoverUrl('medium', true) . '&reload';
		$ret            = file_get_contents($mediumCoverUrl);

		//Reload large cover
		$largeCoverUrl = $recordDriver->getBookcoverUrl('large', true) . '&reload';
		$ret           = file_get_contents($largeCoverUrl);

		return ['success' => true, 'message' => 'Covers have been reloaded.  You may need to refresh the page to clear your local cache.'];
	}

	function reloadNovelistData(){

		require_once ROOT_DIR . '/sys/Novelist/NovelistData.php';

		global $configArray;
		$id = $_REQUEST['id'];

		$novelist = new NovelistData();
		$novelist->groupedWorkPermanentId = $id;
		$novelist->delete();

		return['success' => true, 'message'=> 'NoveList data cleared. You may need to refresh the page to clear your local cache'];
	}

	function reloadIslandora(){
		$id              = $_REQUEST['id'];
		$samePikaCleared = false;
		$cacheMessage    = '';
		require_once ROOT_DIR . '/sys/Islandora/IslandoraSamePikaCache.php';
		//Check for cached links
		$samePikaCache                         = new IslandoraSamePikaCache();
		$samePikaCache->groupedWorkPermanentId = $id;
		if ($samePikaCache->find(true)){
			if ($samePikaCache->delete()){
				$samePikaCleared = true;
				$cacheMessage = 'Deleted same pika cache';
			}else{
				$cacheMessage = 'Could not delete same pika cache';
			}

		}else{
			$cacheMessage = 'Data not cached for same pika link';
		}

		return array(
			'success' => $samePikaCleared,
			'message' => $cacheMessage,
		);
	}

	/**
	 * @throws Exception
	 */
	function exportSeriesToExcel(){

		$id = $_REQUEST['id'];
		require_once ROOT_DIR . "/sys/Novelist/Novelist3.php";
		require_once ROOT_DIR . "/RecordDrivers/GroupedWorkDriver.php";
		$recordDriver  = new GroupedWorkDriver($id);
		$novelist      = NovelistFactory::getNovelist();
		$seriesInfo    = $novelist->getSeriesTitles($id, $recordDriver->getISBNs());
		$seriesTitle   = $seriesInfo->seriesTitle;
		$seriesTitles  = $seriesInfo->seriesTitles;
		$seriesEntries = [];
		foreach ($seriesTitles as $seriesEntry){
			$seriesEntries[$seriesEntry['volume']] = [
				'title'         => $seriesEntry['title'],
				'author'        => $seriesEntry['author'],
				'volume'        => $seriesEntry['volume'],
				'primaryISBN'   => $seriesEntry['isbn'],
				'groupedWorkId' => $seriesEntry['id'] ?? null,
			];
		}
		global $interface;
		$objPHPExcel = new PHPExcel();
		$gitBranch   = $interface->getVariable('gitBranch');
		$objPHPExcel->getProperties()->setCreator('Pika ' . $gitBranch)
			->setLastModifiedBy('Pika ' . $gitBranch)
			->setTitle("Office 2007 XLSX Document")
			->setTitle("Office 2007 XLSX Document")
			->setSubject("Office 2007 XLSX Document")
			->setDescription("Office 2007 XLSX, generated using PHP.")
			->setKeywords("office 2007 openxml php")
			->setCategory("List Items");

		$objPHPExcel->setActiveSheetIndex(0)
			->setCellValue('A1', $seriesTitle)
			->setCellValue('A3', 'Title')
			->setCellValue('B3', 'Author')
			->setCellValue('C3', 'Volume')
			->setCellValue('D3', 'ISBN')
			->setCellValue('E3', 'Grouped Work ID');


		$a = 4;
		foreach ($seriesEntries as $entry) {
			$objPHPExcel->getActiveSheet()->getStyle('D' . $a)->getNumberFormat()->setFormatCode(PHPExcel_Style_numberFormat::FORMAT_NUMBER);
			$objPHPExcel->setActiveSheetIndex(0)
				->setCellValue('A' . $a, $entry['title'])
				->setCellValue('B' . $a, $entry['author'])
				->setCellValue('C' . $a, $entry['volume'])
				->setCellValue('D' . $a, $entry['primaryISBN'])
				->setCellValue('E' . $a, $entry['groupedWorkId']);
			$a++;

		}

		$objPHPExcel->getActiveSheet()->getColumnDimension('A')->setAutoSize(true);
		$objPHPExcel->getActiveSheet()->getColumnDimension('B')->setAutoSize(true);
		$objPHPExcel->getActiveSheet()->getColumnDimension('C')->setAutoSize(true);
		$objPHPExcel->getActiveSheet()->getColumnDimension('D')->setAutoSize(true);
		$objPHPExcel->getActiveSheet()->getColumnDimension('E')->setAutoSize(true);

		// Rename sheet
		$strip = array("~", "`", "!", "@", "#", "$", "%", "^", "&", "*", "(", ")", "_", "=", "+", "[", "{", "]",
		               "}", "\\", "|", ";", ":", "\"", "'", "&#8216;", "&#8217;", "&#8220;", "&#8221;", "&#8211;", "&#8212;",
		               "â€”", "â€“", ",", "<", ".", ">", "/", "?");
		$excelTitle = trim(str_replace($strip, "", strip_tags($seriesTitle)));
		$excelTitle = str_replace(" ", "_", $excelTitle );
		$objPHPExcel->getActiveSheet()->setTitle(substr($excelTitle,0,30));

		// Redirect output to a client's web browser (Excel5)
		header('Content-Type: application/vnd.ms-excel');
		header('Content-Disposition: attachment;filename="' . substr($excelTitle,0,27) . '.xls"');
		header('Cache-Control: max-age=0');
		$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');


			$objWriter->save('php://output');
			exit;

	}
}
