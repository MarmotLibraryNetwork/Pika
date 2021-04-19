<?php
/**
 * Copyright (C) 2020  Marmot Library Network
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

require_once ROOT_DIR . '/sys/LocalEnrichment/NotInterested.php';
require_once ROOT_DIR . '/sys/LocalEnrichment/UserWorkReview.php';
require_once ROOT_DIR . '/sys/Account/ReadingHistoryEntry.php';

class Suggestions {
	/*
	 * Get suggestions for titles that a user might like based on their rating history
	 * and related titles from Novelist.
	 */
	static function getSuggestions($userId = -1, $numberOfSuggestionsToGet = null){
		global $configArray;
		global $timer;

		//Configuration for suggestions
		$doNovelistRecommendations                 = true;
		$numTitlesToLoadNovelistRecommendationsFor = 10;
		$doMetadataRecommendations                 = true;
		$doSimilarlyRatedRecommendations           = false;
		$maxRecommendations                        = empty($numberOfSuggestionsToGet) ? 30 : $numberOfSuggestionsToGet;
		if ($userId == -1){
			$userId = UserAccount::getActiveUserId();
		}

		//Load all titles the user is not interested in
		$notInterested         = new NotInterested();
		$notInterested->userId = $userId;
		$notInterestedTitles   = $notInterested->fetchAll('groupedWorkPermanentId');
		$timer->logTime("Loaded titles the patron is not interested in");

		//Load all titles the user has rated.  Need to load all so we don't recommend things they already rated
		$allRatedTitles      = [];
		$allLikedRatedTitles = [];
		$ratings             = new UserWorkReview();
		$ratings->userId     = $userId;
		$ratings->orderBy('rating DESC, dateRated DESC, id DESC');
		$ratings->find();
		while ($ratings->fetch()){
			$allRatedTitles[$ratings->groupedWorkPermanentId] = $ratings->groupedWorkPermanentId;
			//TODO: clone ratings object instead?
			if ($ratings->rating >= 4){
				$allLikedRatedTitles[] = $ratings->groupedWorkPermanentId;
			}
		}
		$timer->logTime("Loaded titles the patron has rated");

		$readingHistoryDB         = new ReadingHistoryEntry();
		$readingHistoryDB->userId = $userId;
		$readingHistoryDB->whereAdd("groupedWorkPermanentId != ''");
		$readingHistoryDB->orderBy('checkOutDate DESC');
		$readHistoryWorkIds = array_unique($readingHistoryDB->fetchAll('groupedWorkPermanentId'));

		// Setup Search Engine Connection
		$class = $configArray['Index']['engine'];
		$url   = $configArray['Index']['url'];
		/** @var Solr $db */
		$db    = new $class($url);

		$suggestions = [];
		if ($doNovelistRecommendations){
			//Get a list of all titles the user has rated (3 star and above)
			$ratings = new UserWorkReview();
			$ratings->whereAdd("userId = $userId", 'AND');
			$ratings->whereAdd('rating >= 3', 'AND');
			$ratings->orderBy('rating DESC, dateRated DESC, id DESC');
			//Use just recent ratings to make real-time recommendations faster
//			$ratings->limit(0, $numTitlesToLoadNovelistRecommendationsFor);

			if ($ratings->find()){
				while ($ratings->fetch() && count($suggestions) < $maxRecommendations){
					$groupedWorkId = $ratings->groupedWorkPermanentId;
					$timer->logTime("Cloned Rating data");

					disableErrorHandler();
					$record = $db->getRecord($groupedWorkId, 'isbn,title_display');
					enableErrorHandler();
					if (!empty($record['isbn'])){
						$timer->logTime("Loaded ISBNs for work");
							$isbns = $record['isbn'];
							$title = $record['title_display'];
							Suggestions::getNovelistRecommendations($ratings, $groupedWorkId, $title, $isbns, $allRatedTitles, $suggestions, $notInterestedTitles, $readHistoryWorkIds);
							$timer->logTime("Got recommendations from Novelist for $groupedWorkId");
					}
				}
			}
			$timer->logTime("Loaded novelist recommendations");
		}

		if (count($suggestions) < $maxRecommendations){
			if ($doSimilarlyRatedRecommendations){
				//Get a list of all titles the user has rated (3 star and above)
				$ratings = new UserWorkReview();
				$ratings->whereAdd("userId = $userId", 'AND');
				$ratings->whereAdd('rating >= 3', 'AND');
				$ratings->orderBy('rating DESC, dateRated DESC, id DESC');
				//Use just recent ratings to make real-time recommendations faster
				$ratings->limit(0, $numTitlesToLoadNovelistRecommendationsFor);

				$ratings->find();
				//echo("User has rated {$ratings->N} titles<br>");
				if ($ratings->N > 0){
					while ($ratings->fetch()){
						Suggestions::getSimilarlyRatedTitles($db, $ratings, $userId, $allRatedTitles, $suggestions, $notInterestedTitles, $readHistoryWorkIds);
					}
				}
				$timer->logTime("Loaded recommendations based on similarly rated titles");
			}


		//Get metadata recommendations if enabled, we have ratings, and we don't have enough suggestions yet
			if ($doMetadataRecommendations && count($allLikedRatedTitles) > 0){
				//Get recommendations based on everything I've rated using more like this functionality
				$moreLikeTheseSuggestions = $db->getMoreLikeThese($allLikedRatedTitles, $notInterestedTitles);
				if (isset($moreLikeTheseSuggestions['response']['docs'])){
					foreach ($moreLikeTheseSuggestions['response']['docs'] as $suggestion){
						if (!array_key_exists($suggestion['id'], $allRatedTitles) && !in_array($suggestion['id'], $readHistoryWorkIds)){
							$suggestions[$suggestion['id']] = [
								'rating'    => $suggestion['rating'] - 2.5,
								'titleInfo' => $suggestion,
								'basedOn'   => 'MetaData for all titles rated',
							];
						}
						if (count($suggestions) == $maxRecommendations){
							break;
						}
					}
				}else{
					if (isset($moreLikeTheseSuggestions['error'])){
						global $logger;
						$logger->log('Error looking for Suggested Titles : ' . $moreLikeTheseSuggestions['error']['msg'], PEAR_LOG_ERR);
					}
				}
				$timer->logTime("Loaded recommendations based on ratings");
			}
		}


		//sort suggestions based on score from ascending to descending
		uasort($suggestions, 'Suggestions::compareSuggestions');
		//Only return up to $maxRecommendations suggestions to make the page size reasonable
		$suggestions = array_slice($suggestions, 0, $maxRecommendations, true);
		$timer->logTime("Sorted and filtered suggestions");
		//Return suggestions for use in the user interface.
		return $suggestions;
	}


	/**
	 * Load titles that have been rated by other users which are similar to this.
	 *
	 * @param Solr $db
	 * @param UserWorkReview $ratedTitle
	 * @param integer $userId
	 * @param array $ratedTitles
	 * @param array $suggestions
	 * @param integer[] $notInterestedTitles
	 * @return int The number of suggestions for this title
	 */
	private static function getSimilarlyRatedTitles($db, $ratedTitle, $userId, $ratedTitles, &$suggestions, $notInterestedTitles, $readHistoryWorkIds){
		$numRecommendations = 0;
		//If there is no ISBN, can we come up with an alternative algorithm?
		//Possibly using common ratings with other patrons?
		//Get a list of other patrons that have rated this title and that like it as much or more than the active user..
		$otherRaters = new UserWorkReview();
		//Query the database to get items that other users who rated this liked.
		$sqlStatement = ("SELECT groupedWorkPermanentId, " .
			" sum(case rating when 5 then 10 when 4 then 6 end) as rating " . //Scale the ratings similar to the above.
			" FROM `user_work_review` WHERE userId in " .
			" (select userId from user_work_review where groupedWorkPermanentId = " . $ratedTitle->groupedWorkPermanentId . //Get other users that have rated this title.
			" and rating >= 4 " . //Make sure that other users liked the book.
			" and userid != " . $userId . ") " . //Make sure that we don't include this user in the results.
			" and rating >= 4 " . //Only include ratings that are 4 or 5 star so we don't get books the other user didn't like.
			" and groupedWorkPermanentId != " . $ratedTitle->groupedWorkPermanentId . //Make sure we don't get back this title as a recommendation.
			" and deleted = 0 " . //Ignore deleted resources
			" group by resourceid order by rating desc limit 10"); //Sort so the highest titles are on top and limit to 10 suggestions.
		$otherRaters->query($sqlStatement);
		if ($otherRaters->N > 0){
			//Other users have also rated this title.
			while ($otherRaters->fetch()){
				//Process the title
				disableErrorHandler();

				if (!($ownedRecord = $db->getRecord($otherRaters->groupedWorkPermanentId))){
					//Old record which has been removed? Ignore for purposes of suggestions.
					continue;
				}
				enableErrorHandler();
				//get the title from the Solr Index
				if (isset($ownedRecord['isbn'])){
					if (strpos($ownedRecord['isbn'][0], ' ') > 0){
						$isbnInfo = explode(' ', $ownedRecord['isbn'][0]);
						$isbn     = $isbnInfo[0];
					}else{
						$isbn = $ownedRecord['isbn'][0];
					}
					require_once ROOT_DIR . '/sys/ISBN/ISBNConverter.php';
					//TODO: replace with ISBN class
					$isbn13 = strlen($isbn) == 13 ? $isbn : ISBNConverter::convertISBN10to13($isbn);
					$isbn10 = strlen($isbn) == 10 ? $isbn : ISBNConverter::convertISBN13to10($isbn);
				}else{
					$isbn13 = '';
					$isbn10 = '';
				}
				//See if we can get the series title from the record
				if (isset($ownedRecord['series'])){
					$series = $ownedRecord['series'][0];
				}else{
					$series = '';
				}
				$similarTitle = array(
					'title'           => $ownedRecord['title'],
					'title_short'     => $ownedRecord['title_short'],
					'author'          => isset($ownedRecord['author']) ? $ownedRecord['author'] : '',
					'publicationDate' => $ownedRecord['publishDate'],
					'isbn'            => $isbn13,
					'isbn10'          => $isbn10,
					'upc'             => isset($ownedRecord['upc']) ? $ownedRecord['upc'][0] : '',
					'recordId'        => $ownedRecord['id'],
					'id'              => $ownedRecord['id'], //This allows the record to be displayed in various locations.
					'libraryOwned'    => true,
					'isCurrent'       => false,
					'shortId'         => substr($ownedRecord['id'], 1),
					'format_category' => isset($ownedRecord['format_category']) ? $ownedRecord['format_category'] : '',
					'format'          => $ownedRecord['format'],
					'recordtype'      => $ownedRecord['recordtype'],
					'series'          => $series,
				);
				$numRecommendations++;
				Suggestions::addTitleToSuggestions($ratedTitle, $similarTitle['title'], $similarTitle['recordId'], $similarTitle, $ratedTitles, $suggestions, $notInterestedTitles, $readHistoryWorkIds);
			}
		}
		return $numRecommendations;
	}

	private static function getNovelistRecommendations($userRating, $groupedWorkId, $sourceTitle, $isbn, $allRatedTitles, &$suggestions, $notInterestedTitles, $readHistoryWorkIds){
		//We now have the title, we can get the related titles from Novelist
		$novelist       = NovelistFactory::getNovelist();
		$enrichmentInfo = $novelist->getSimilarTitles($groupedWorkId, $isbn);
		//Use loadEnrichmentInfo even though there is more data than we need since it uses caching.

		if (!empty($enrichmentInfo->similarTitleCountOwned)){
			foreach ($enrichmentInfo->similarTitles as $similarTitle){
				if ($similarTitle['libraryOwned']){
					Suggestions::addTitleToSuggestions($userRating, $sourceTitle, $groupedWorkId, $similarTitle, $allRatedTitles, $suggestions, $notInterestedTitles, $readHistoryWorkIds);
				}
			}
		}
	}

	private static function addTitleToSuggestions($userRating, $sourceTitle, $sourceId, $similarTitle, $allRatedTitles, &$suggestions, $notInterestedTitles, $readHistoryWorkIds){
		//Don't suggest titles that have already been rated
		$suggestTitleId = $similarTitle['id'];
		if (array_key_exists($suggestTitleId, $allRatedTitles)){
			return;
		}
		//Don't suggest titles the user is not interested in.
		if (in_array($suggestTitleId, $notInterestedTitles)){
			return;
		}

		//Don't suggest titles the user has already read.
		if (in_array($suggestTitleId, $readHistoryWorkIds)){
			return;
		}

		$rating           = 0;
		$suggestedBasedOn = [];
		//Get the existing rating if any
		if (array_key_exists($suggestTitleId, $suggestions)){
			$rating           = $suggestions[$suggestTitleId]['rating'];
			$suggestedBasedOn = $suggestions[$suggestTitleId]['basedOn'];
		}
		//Update the suggestion score.
		//Using the scale:
		//  10 pts - 5 star rating
		//  6 pts -  4 star rating
		//  2 pts -  3 star rating
		if ($userRating->rating == 5){
			$rating += 10;
		}elseif ($userRating->rating == 4){
			$rating += 6;
		}else{
			$rating += 2;
		}
		if (count($suggestedBasedOn) < 3){
			$suggestedBasedOn[] = ['title' => $sourceTitle, 'id' => $sourceId];
		}
		$suggestions[$suggestTitleId] = [
			'rating'    => $rating,
			'titleInfo' => $similarTitle,
			'basedOn'   => $suggestedBasedOn,
		];
	}

	static function compareSuggestions($a, $b){
		return $b['rating'] <=> $a['rating'];
	}
}
