<?php
/**
 *
 *
 * @category Pika
 * @author: Pascal Brammeier
 * Date: 8/26/2019
 *
 */


class AutoGraphicsShareIt {

	/**
	 * Load search results from Prospector using the encore interface.
	 * If $prospectorRecordDetails are provided, will sort the existing result to the
	 * top and tag it as being the record.
	 * @param string $searchTerms
	 * @param int $maxResults
	 * @return array|null
	 */
	function getTopSearchResults($searchTerms, $maxResults){
		return array(
			'firstRecord' => 0,
			'lastRecord'  => 0,
			'resultTotal' => 0,
			'records'     => array(),
		);
	}

	/**
	 * Generate a search URL for the ILL website
	 *
	 * @param string[] $searchTerms
	 * @return string
	 */
	function getSearchLink($searchTerms){
		global $configArray;
		$searchURL = $configArray['InterLibraryLoan']['ILLSearchURL'];

		if (is_array($searchTerms)){

			//TODO: This ignores the various index settings of an advanced search
			$search = "";
			foreach ($searchTerms as $term){
				if (strlen($search) > 0){
					$search .= ' ';
				}
				if (is_array($term) && isset($term['group'])){
					foreach ($term['group'] as $groupTerm){
						if (strlen($search) > 0){
							$search .= ' ';
						}
						if (isset($groupTerm['lookfor'])){
							$termValue = $groupTerm['lookfor'];
							$search    .= $termValue;
						}
					}
				}elseif (isset($term['lookfor'])){
					$termValue = $term['lookfor'];
					$search    .= $termValue;
				}

			}
		}else{
			$search = $searchTerms;
		}
		$search = str_replace('+', '%20', urlencode(str_replace('/', '', $search)));
		$searchURL = str_replace ('{SEARCHTERM}', $search, $searchURL);
		return $searchURL;

	}
}
