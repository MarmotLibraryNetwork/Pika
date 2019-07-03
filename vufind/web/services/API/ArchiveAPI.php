<?php
/**
 * APIs related to Digital Archive functionality
 * User: mnoble
 * Date: 6/29/2017
 * Time: 11:00 AM
 */

require_once ROOT_DIR . '/AJAXHandler.php';

class API_ArchiveAPI extends AJAXHandler {

	protected $methodsThatRepondWithJSONResultWrapper = array(
		'getDPLAFeed',
		'getDPLACounts',
	);

	private $organizationRolesToIncludeInDPLA = array(
		'owner',
		'donor',
		'acknowledgement',
	);

	/**
	 * Returns a feed of content to be sent by DPLA after being processed by the state library.  May not return
	 * a full number of results due to filtering at the collection level.
	 *
	 * Future libraries may require different information.
	 */
	function getDPLAFeed(){
		$curPage      = isset($_REQUEST['page']) ? $_REQUEST['page'] : 1;
		$pageSize     = isset($_REQUEST['pageSize']) ? $_REQUEST['pageSize'] : 100;
		$changesSince = isset($_REQUEST['changesSince']) ? $_REQUEST['changesSince'] : null;
		$namespace    = isset($_REQUEST['namespace']) ? $_REQUEST['namespace'] : null;
		list($searchObject, $collectionsToInclude, $searchResult) = $this->getDPLASearchResults($namespace, $changesSince, $curPage, $pageSize);

		$dplaDocs = array();

		foreach ($searchResult['response']['docs'] as $doc){
			$dplaDoc = array();
			/** @var IslandoraDriver $record */
			$record                   = RecordDriverFactory::initRecordDriver($doc);
			$dplaDoc['identifier']    = $record->getUniqueID();
			$dplaDoc['title']         = $record->getTitle();
			$dplaDoc['description']   = $record->getDescription();
			$dplaDoc['type']          = $record->getFormat();
			$dplaDoc['format']        = $this->mapFormat($record->getFormat());
			$dplaDoc['preview']       = $record->getBookcoverUrl('small');
			$dplaDoc['includeInDPLA'] = isset($doc['mods_extension_marmotLocal_pikaOptions_dpla_s']) ? $doc['mods_extension_marmotLocal_pikaOptions_dpla_s'] : 'default';

			$dateCreated = $record->getDateCreated('Y-m-d'); // Reformat back to YYYY-MM-DD
			if ($dateCreated == 'Date Unknown'){
				$dateCreated = null;
			}
			$dplaDoc['dateCreated'] = $dateCreated ? $dateCreated : null;

			$language = $record->getModsValue('languageTerm');
			if (strlen($language)){
				$dplaDoc['language'] = $language;
			}

			$subTitle = $record->getSubTitle();
			if (strlen($subTitle) > 0){
				$dplaDoc['alternativeTitle'] = $subTitle;
			}

			// Extent (What the digital object is a representation of)
			if (isset($doc['mods_physicalDescription_extent_s'])){
				$dplaDoc['extent'] = $doc['mods_physicalDescription_extent_s'];
			}

			// Creator
			if (isset($doc['mods_extension_marmotLocal_hasCreator_entityTitle_ms'])){
				$dplaDoc['creator'] = $doc['mods_extension_marmotLocal_hasCreator_entityTitle_ms'];
			}

			// Marmot Contributor
			$contributingLibrary = $record->getContributingLibrary();
			if ($contributingLibrary == null){
				list($namespace) = explode(':', $record->getUniqueID());
				global $configArray;
				$dplaDoc['dataProvider'] = $namespace;
				$dplaDoc['isShownAt']    = $configArray['Catalog']['url'] . $record->getLinkUrl();
				// When the contributing Library isn't provided we don't have an ideal base URL
			}else{
				$dplaDoc['dataProvider'] = $contributingLibrary['libraryName'];
				$dplaDoc['isShownAt']    = $contributingLibrary['baseUrl'] . $record->getLinkUrl();
			}

			// Partner Contributors
			$additionalContributors    = $record->getBrandingInformation();
			$institutionalContributors = array();
			foreach ($additionalContributors as $pid => $contributor){
				if ($pid != $contributingLibrary['pid'] && strpos($pid, 'organization') === 0 && (!empty($contributor['role']) && in_array($contributor['role'], $this->organizationRolesToIncludeInDPLA))){
					// Include only organizations with specific roles that aren't the library itself
					if (empty($contributor['label'])){
						/** @var OrganizationDriver $islandoraObject */
						$islandoraObject = RecordDriverFactory::initIslandoraDriverFromPid($pid);
						$title           = $islandoraObject->getTitle();
						if (!empty($title)){
							$institutionalContributors[] = $title;
						}
					}else{
						$institutionalContributors[] = $contributor['label'];
					}
				}
			}
			if (!empty($institutionalContributors)){
				// Institutional Contributors becomes the data Provider & the Marmot Contributor becomes the intermediate data provider
				$intermediateProvider            = $dplaDoc['dataProvider'];
				$dplaDoc['intermediateProvider'] = $intermediateProvider;
				$dplaDoc['dataProvider']         = count($institutionalContributors) == 1 ? $institutionalContributors[0] : $institutionalContributors;
			}

			// Related Collections
			$relatedCollections = $record->getRelatedCollections();
			$dplaRelations      = array();
			foreach ($relatedCollections as $relatedCollection){
				$dplaRelations[] = $relatedCollection['label'];
			}
			$dplaDoc['relation'] = $dplaRelations;

			// Parent Collection
			$parentCollectionPid = null;
//			if (!empty($doc['RELS_EXT_isMemberOfCollection_uri_mt'])) {
//				if (is_array($doc['RELS_EXT_isMemberOfCollection_uri_mt'])) {
//					if (count($doc['RELS_EXT_isMemberOfCollection_uri_mt']) == 1) {
//						$parentCollectionPid = str_replace('info:fedora/', '', reset($doc['RELS_EXT_isMemberOfCollection_uri_mt']));
//					} else {
//						// More than one parent collection?  Shouldn't be an issue
//					}
//				}
////				else {
////					$parentCollectionPid = str_replace('info:fedora/', '', $doc['RELS_EXT_isMemberOfCollection_uri_mt']);
////				}
//			}

			// Rights.org statement
			$rightsStatement = '';
			if (isset($doc['mods_accessCondition_marmot_rightsStatementOrg_t'])){
				$rightsStatement = $doc['mods_accessCondition_marmot_rightsStatementOrg_t'];
			}else{
				if (!empty($doc['RELS_EXT_isMemberOfCollection_uri_mt'])){
					foreach ($doc['RELS_EXT_isMemberOfCollection_uri_mt'] as $parentCollectionURI){
						$parentCollectionPid = str_replace('info:fedora/', '', $parentCollectionURI);
						if (!empty($parentCollectionPid)){
							/** @var CollectionDriver $collectionDriver */
							$collectionDriver = RecordDriverFactory::initIslandoraDriverFromPid($parentCollectionPid);
							if (!empty($collectionDriver) && !PEAR_Singleton::isError($collectionDriver)){
								$rightsStatement = $collectionDriver->getModsValue('rightsStatementOrg', 'marmot');
								if ($rightsStatement){
									break;
								}
							}
						}
					}
				}

			}
			if (empty($rightsStatement)){
				$rightsStatement = 'http://rightsstatements.org/page/CNE/1.0/?language=en';
			}
			$rightsStatement   = str_replace('?language=en', '', $rightsStatement); // Our DPLA hub requested removal of language parameter
			$dplaDoc['rights'] = $rightsStatement;

			// Rights holder
			if (isset($doc['mods_accessCondition_rightsHolder_entityTitle_ms'])){
				$dplaDoc['rightsHolder'] = $doc['mods_accessCondition_rightsHolder_entityTitle_ms'];
			}

			// Places
			$relatedPlaces     = $record->getRelatedPlaces();
			$dplaRelatedPlaces = array();
			foreach ($relatedPlaces as $relatedPlace){
				$dplaRelatedPlaces[] = $relatedPlace['label'];
			}
			if (count($dplaRelatedPlaces)){
				$dplaDoc['place'] = $dplaRelatedPlaces;
			}

			// Primary Subjects
			$subjects = $record->getAllSubjectHeadings(false, 0); // DPLA does not want the title included as a subject
			// Marmot wants related Collections included in the subjects
			if (empty($subjects)){
				$subjects = $dplaRelations;
			}else{
				$subjects = array_keys($subjects);
				$subjects = array_merge($subjects, $dplaRelations);
			}

			// Add Persons that are Publishers & Related People as DPLA Subjects
			$publishers    = array();
			$relatedPeople = $record->getRelatedPeople();
			foreach ($relatedPeople as $relatedPerson){
				if ($relatedPerson['role'] == 'publisher'){
					$publishers[] = $relatedPerson['label'];
				} else {
					// Include related Entities as Subjects
					$subjects[] = $relatedPerson['label'];
				}
			}

			// Add organizations that are Publishes & related organizations as DPLA Subjects
			$relatedOrganizations = $record->getRelatedOrganizations();
			foreach ($relatedOrganizations as $relatedOrganization){
				if ($relatedOrganization['role'] == 'publisher'){
					$publishers[] = $relatedOrganization['label'];
				} else {
					// Include related Entities as Subjects
					$subjects[]  = $relatedOrganization['label'];
				}
			}
			if (count($publishers) > 0){
				$dplaDoc['publisher'] = $publishers;
			}

			// Events as DPLA subjects
			$relatedEvents = $record->getRelatedEvents();
			foreach ($relatedEvents as $relatedEvent){
				$subjects[] = $relatedEvent['label'];
			}

			$dplaDoc['subject'] = $subjects;

			$dplaDocs[] = $dplaDoc;
		}

		$recordsByLibrary = array();
		if (isset($searchResult['facet_counts'])){
			$namespaceFacet = $searchResult['facet_counts']['facet_fields']['namespace_ms'];
			foreach ($namespaceFacet as $facetInfo){
				$recordsByLibrary[$facetInfo[0]] = $facetInfo[1];
			}
		}

		$summary = $searchObject->getResultSummary();
		$results = array(
			'numResults'          => $summary['resultTotal'],
			'numPages'            => ceil($summary['resultTotal'] / $pageSize),
			'recordsByLibrary'    => $recordsByLibrary,
			'includedCollections' => $collectionsToInclude,
			'docs'                => $dplaDocs,
		);

		return $results;
	}

	private $formatMap = array(
		"Academic Paper"  => "Text",
		"Art"             => "Image",
		"Article"         => "Text",
		"Book"            => "Text",
		"Document"        => "Text",
		"Image"           => "Still Image",
		"Magazine"        => "Text",
		"Music Recording" => "Sound",
		"Newspaper"       => "Text",
		"Page"            => "Text",
		"Postcard"        => "Still Image",
		"Video"           => "Moving Image",
		"Voice Recording" => "Sound",
	);

	private function mapFormat($format){
		if (array_key_exists($format, $this->formatMap)){
			return $this->formatMap[$format];
		}else{
			return "Unknown";
		}
	}

	/**
	 * @param $namespace
	 * @param $changesSince
	 * @param $curPage
	 * @param $pageSize
	 * @return array
	 */
	private function getDPLASearchResults($namespace, $changesSince, $curPage, $pageSize){
//Query for collections that should not be exported to DPLA
		/** @var SearchObject_Islandora $searchObject */
		$searchObject = SearchObjectFactory::initSearchObject('Islandora');
		$searchObject->init();
		$searchObject->setPrimarySearch(false);
		$searchObject->addHiddenFilter('!RELS_EXT_isViewableByRole_literal_ms', "administrator");
		$searchObject->addHiddenFilter('!mods_extension_marmotLocal_pikaOptions_showInSearchResults_ms', "no");
		$searchObject->addHiddenFilter('RELS_EXT_hasModel_uri_ms', '"info:fedora/islandora:collectionCModel"');
		$searchObject->addHiddenFilter('mods_extension_marmotLocal_pikaOptions_dpla_s', "yes");
		$searchObject->setPage(1);
		$searchObject->setLimit(100);
		$searchCollectionsResult = $searchObject->processSearch(true, false);
		$collectionsToInclude    = array();
		$ancestors               = "";

		foreach ($searchCollectionsResult['response']['docs'] as $doc){
			$collectionsToInclude[] = $doc['PID'];
			if (strlen($ancestors) > 0){
				$ancestors .= ' OR ';
			}
			$ancestors .= 'ancestors_ms:"' . $doc['PID'] . '"';
		}


		//Query Solr for the records to export
		// Initialise from the current search globals
		/** @var SearchObject_Islandora $searchObject */
		$searchObject = SearchObjectFactory::initSearchObject('Islandora');
		$searchObject->init();
		$searchObject->setPrimarySearch(true);
		$searchObject->addHiddenFilter('!RELS_EXT_isViewableByRole_literal_ms', "administrator");
		$searchObject->addHiddenFilter('!mods_extension_marmotLocal_pikaOptions_showInSearchResults_ms', "no");
		if ($ancestors){
			$searchObject->addFilter("mods_extension_marmotLocal_pikaOptions_dpla_s:yes OR (!mods_extension_marmotLocal_pikaOptions_dpla_s:no AND ($ancestors))");
		}else{
			$searchObject->addFilter("mods_extension_marmotLocal_pikaOptions_dpla_s:yes");
		}
		$searchObject->addHiddenFilter('!PID', "person*");
		$searchObject->addHiddenFilter('!PID', "event*");
		$searchObject->addHiddenFilter('!PID', "organization*");
		$searchObject->addHiddenFilter('!PID', "place*");
		$searchObject->addHiddenFilter('!RELS_EXT_hasModel_uri_ms', '"info:fedora/islandora:collectionCModel"');
		$searchObject->addHiddenFilter('!RELS_EXT_hasModel_uri_ms', '"info:fedora/islandora:pageCModel"');
		if ($namespace != null){
			$searchObject->addHiddenFilter('namespace_ms', $namespace);
		}

		//Filter to only see DPLA records
		if ($changesSince != null){
			$searchObject->addHiddenFilter('fgs_lastModifiedDate_dt', "[$changesSince TO *]");
		}
		$searchObject->addFieldsToReturn(array(
			'mods_accessCondition_marmot_rightsStatementOrg_t',
			'mods_accessCondition_rightsHolder_entityTitle_ms',
			'mods_extension_marmotLocal_hasCreator_entityTitle_ms',
			'mods_physicalDescription_extent_s',
			'mods_extension_marmotLocal_pikaOptions_dpla_s',
			'RELS_EXT_isMemberOfCollection_uri_mt',
		));
		$searchObject->setPage($curPage);
		$searchObject->setLimit($pageSize);
		$searchObject->clearFacets();
		$searchObject->addFacet('namespace_ms');
		$searchObject->setSort("fgs_lastModifiedDate_dt asc");

		$searchResult = $searchObject->processSearch(true, false);
		return array($searchObject, $collectionsToInclude, $searchResult);
	}

	public function getDPLACounts(){
		$curPage      = isset($_REQUEST['page']) ? $_REQUEST['page'] : 1;
		$pageSize     = isset($_REQUEST['pageSize']) ? $_REQUEST['pageSize'] : 100;
		$changesSince = isset($_REQUEST['changesSince']) ? $_REQUEST['changesSince'] : null;
		$namespace    = isset($_REQUEST['namespace']) ? $_REQUEST['namespace'] : null;
		list($searchObject, $collectionsToInclude, $searchResult) = $this->getDPLASearchResults($namespace, $changesSince, $curPage, $pageSize);

		$recordsByLibrary = array();
		if (isset($searchResult['facet_counts'])){
			$namespaceFacet = $searchResult['facet_counts']['facet_fields']['namespace_ms'];
			foreach ($namespaceFacet as $facetInfo){
				$recordsByLibrary[$facetInfo[0]] = $facetInfo[1];
			}
		}

		return $recordsByLibrary;
	}
}