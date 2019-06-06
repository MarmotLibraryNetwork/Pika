<?php
/**
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 */

require_once ROOT_DIR . '/AJAXHandler.php';
require_once ROOT_DIR . '/services/API/ItemAPI.php';
require_once ROOT_DIR . '/services/API/ListAPI.php';
require_once ROOT_DIR . '/services/API/SearchAPI.php';
require_once ROOT_DIR . '/sys/Solr.php';

class AnodeAPI extends AJAXHandler {

	protected $methodsThatRepondWithJSONResultWrapper = array(
		'getAnodeListGroupedWorks',
		'getAnodeRelatedGroupedWorks',
		'getAnodeGroupedWorks',
	);

	/**
	 * Returns information about the titles within a list
	 * according to the parameters of
	 * Anode Pika API Description at
	 *
	 * @param string  $listId                - The list to show
	 * @param integer $numGroupedWorksToShow - the maximum number of titles that should be shown
	 * @return array
	 */
	function getAnodeListGroupedWorks($listId = null, $numGroupedWorksToShow = null){
		if (!$listId){
			$listId = $_REQUEST['listId'];
		}
		if (!$_REQUEST['numGroupedWorksToShow']){
			$numTitlesToShow = 25;
		}else{
			$numTitlesToShow = $_REQUEST['numGroupedWorksToShow'];
		}
		if (isset($_GET['branch']) && in_array($_GET['branch'], array("bl", "bx", "ep", "ma", "se"))){
			$branch = $_GET['branch'];
		}else{
			$branch = "catalog";
		}
		$listAPI = new ListAPI();
		$result  = $listAPI->getListTitles($listId, $numGroupedWorksToShow);
		$result  = $this->getAnodeGroupedWorks($result, $branch);
		return $result;
	}

	/**
	 * Returns information about a grouped work's related titles ("More Like This")
	 *
	 * @param string $id             - The initial grouped work
	 * @return  array
	 * @var    array $originalResult - The original record we are getting similar titles for.
	 */
	function getAnodeRelatedGroupedWorks($id = null, $originalResult = null){
		global $configArray;
		if (!isset($id)){
			$id = $_REQUEST['id'];
		}
		if (isset($_GET['branch']) && in_array($_GET['branch'], array("bl", "se"))){
			$branch = $_GET['branch'];
		}else{
			$branch = "catalog";
		}
		//Load Similar titles (from Solr)
		$class = $configArray['Index']['engine'];
		$url   = $configArray['Index']['url'];
		/** @var Solr $db */
		$db = new $class($url);
//		$db->disableScoping();
		$similar = $db->getMoreLikeThis2($id);
		if (isset($similar) && count($similar['response']['docs']) > 0){
			$similarTitles = array();
//			$similarTitles['titles'] = array();

			foreach ($similar['response']['docs'] as $key => $similarTitle){
				$similarTitles['titles'][] = $similarTitle;
			}
		}
		$result = $this->getAnodeGroupedWorks($similarTitles, $branch);
//var_dump($similarTitles);
//var_dump($result);
		return $result;
	}

	function getAnodeGroupedWorks($result, $branch){
		if (!isset($result['titles'])){
			$result['titles'] = array();
		}else{
			foreach ($result['titles'] as &$groupedWork){
				$itemAPI           = new ItemAPI();
				$_GET['id']        = $groupedWork['id'];
				$groupedWorkRecord = $itemAPI->loadSolrRecord($groupedWork['id']);
				if (isset($groupedWorkRecord['title_display'])){
					$groupedWork['title'] = $groupedWorkRecord['title_display'];
				}
				if (!isset($groupedWorkRecord['image'])){
					$groupedWork['image'] = '/bookcover.php?id=' . $groupedWork['id'] . '&size=medium&type=grouped_work';
				}
				if (isset($groupedWorkRecord['display_description'])){
					$groupedWork['description'] = $groupedWorkRecord['display_description'];
				}
				if (isset($groupedWorkRecord['rating'])){
					$groupedWork['rating'] = $groupedWorkRecord['rating'];
				}
				if (isset($groupedWorkRecord['series'][0])){
					$groupedWork['series'] = $groupedWorkRecord['series'][0];
				}
				if (isset($groupedWorkRecord['genre'])){
					$groupedWork['genre'] = $groupedWorkRecord['genre'];
				}
				if (isset($groupedWorkRecord['publisher'])){
					$groupedWork['publisher'] = $groupedWorkRecord['publisher'];
				}
				if (isset($groupedWorkRecord['language'])){
					$groupedWork['language'] = $groupedWorkRecord['language'];
				}
				if (isset($groupedWorkRecord['literary_form'])){
					$groupedWork['literary_form'] = $groupedWorkRecord['literary_form'];
				}
				if (isset($groupedWorkRecord['author2-role'])){
					$groupedWork['contributors'] = $groupedWorkRecord['author2-role'];
				}
				if (isset($groupedWorkRecord['edition'])){
					$groupedWork['edition'] = $groupedWorkRecord['edition'];
				}
				if (isset($groupedWorkRecord['publishDateSort'])){
					$groupedWork['published'] = $groupedWorkRecord['publishDateSort'];
				}
				if (isset($groupedWorkRecord['econtent_source_' . $branch])){
					$groupedWork['econtent_source'] = $groupedWorkRecord['econtent_source_' . $branch];
				}
				if (isset($groupedWorkRecord['econtent_device'])){
					$groupedWork['econtent_device'] = $groupedWorkRecord['econtent_device'];
				}
				if (isset($groupedWorkRecord['physical'])){
					$groupedWork['physical'] = $groupedWorkRecord['physical'];
				}
				if (isset($groupedWorkRecord['isbn'])){
					$groupedWork['isbn'] = $groupedWorkRecord['isbn'];
				}
				$groupedWork['availableHere'] = false;

// TO DO: include MPAA ratings, Explicit Lyrics advisory, etc.
//				$groupedWork['contentRating'] = $groupedWorkRecord['???'];

				foreach ($groupedWorkRecord['scoping_details_' . $branch] as $item){
					$item                  = explode('|', $item);
					$item['availableHere'] = false;
					if ($item[4] == 'true' && $item[5] == 'true'){
						$item['availableHere']        = true;
						$groupedWork['availableHere'] = true;
					}
					$groupedWork['items'][] = array(
						'01_bibIdentifier'  => $item[0],
						'02_itemIdentifier' => $item[1],
						'05_statusGrouped'  => $item[2],
						'06_status'         => $item[3],
						'07_availableHere'  => $item['availableHere'],
						'11_available'      => $item[5],
					);
					foreach ($groupedWorkRecord['item_details'] as $itemDetail){
						if (strpos($itemDetail, $item[0] . '|' . $item[1]) === 0){
							$itemDetail                                             = explode('|', $itemDetail);
							$groupedWork['items'][count($groupedWork['items']) - 1] += array(
								'08_itemShelfLocation' => $itemDetail[2],
								'09_itemLocationCode'  => $itemDetail[15],
								'10_itemCallNumber'    => $itemDetail[3],
							);
							break;
						}
					}
					foreach ($groupedWorkRecord['record_details'] as $bibRecord){
						if (strpos($bibRecord, $item[0]) === 0){
							$bibRecord                                              = explode('|', $bibRecord);
							$groupedWork['items'][count($groupedWork['items']) - 1] += array(
								'03_bibFormat'         => $bibRecord[1],
								'04_bibFormatCategory' => $bibRecord[2],
							);
							break;
						}
					}
					ksort($groupedWork['items'][count($groupedWork['items']) - 1]);
				}
				unset($groupedWork['length']);
				unset($groupedWork['ratingData']);
				unset($groupedWork['shortId']);
				unset($groupedWork['small_image']);
				unset($groupedWork['titleURL']);
				unset($groupedWork['publishDate']);
				unset($groupedWork['title_display']);
				unset($groupedWork['title_short']);
				unset($groupedWork['title_full']);
				unset($groupedWork['author_display']);
				unset($groupedWork['publisherStr']);
				unset($groupedWork['topic_facet']);
				unset($groupedWork['subject_facet']);
				unset($groupedWork['lexile_score']);
				unset($groupedWork['accelerated_reader_interest_level']);
				unset($groupedWork['primary_isbn']);
				unset($groupedWork['display_description']);
				unset($groupedWork['auth_author2']);
				unset($groupedWork['author2-role']);
				unset($groupedWork['series_with_volume']);
				unset($groupedWork['literary_form_full']);
				unset($groupedWork['record_details']);
				unset($groupedWork['item_details']);
				unset($groupedWork['accelerated_reader_point_value']);
				unset($groupedWork['accelerated_reader_reading_level']);

			}
		}
		return $result;
	}
}
