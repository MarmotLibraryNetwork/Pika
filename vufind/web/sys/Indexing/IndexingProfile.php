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
 * Includes information for how to index MARC Records.  Allows for the ability to handle multiple data sources.
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 6/30/2015
 * Time: 1:44 PM
 */

require_once ROOT_DIR . '/sys/Indexing/TranslationMap.php';
require_once ROOT_DIR . '/sys/Indexing/TimeToReshelve.php';
require_once ROOT_DIR . '/sys/Extracting/SierraExportFieldMapping.php';
class IndexingProfile extends DB_DataObject{
	const COVER_SOURCES = [
		'SideLoad General'                    => 'SideLoad General',
		'ILS MARC'                            => 'ILS MARC',
//		'Zinio'                               => 'Zinio',
		'Colorado State Government Documents' => 'Colorado State Government Documents',
		'Classroom Video on Demand'           => 'Classroom Video on Demand',
		'Films on Demand'                     => 'Films on Demand',
		'Proquest'                            => 'Proquest',
		'CreativeBug'                         => 'CreativeBug',
		'CHNC'                                => 'CHNC',
	];

	public $__table = 'indexing_profiles';    // table name

	public $id;
	public $name;           // Name is for display to end users
	public $sourceName;     // sourceName is used for storing in databases and ID references  (So a full record Id would be 'sourceName:recordId')
	public $marcPath;
	public $minMarcFileSize;
	public $marcEncoding;
	public $filenamesToInclude;
	public $individualMarcPath;
	public $numCharsToCreateFolderFrom;
	public $createFolderFromLeadingCharacters;
	public $groupUnchangedFiles;
	public $lastGroupedTime;
	public $groupingClass;
	public $indexingClass;
	public $recordDriver;
	public $patronDriver;
	public $recordUrlComponent;
	public $formatSource;
	public $specifiedFormat;
	public $specifiedFormatCategory;
	public $specifiedFormatBoost;
	public $specifiedGroupingCategory;
	public $recordNumberTag;
	public $recordNumberField;
	public $recordNumberPrefix;
	public $suppressItemlessBibs;
	public $itemTag;
	public $itemRecordNumber;
	public $useItemBasedCallNumbers;
	public $callNumberPrestamp;
	public $callNumber;
	public $callNumberCutter;
	public $callNumberPoststamp;
	public $location;
	public $nonHoldableLocations;
	public $locationsToSuppress;
	public $shelvingLocation;
	public $collection;
	public $collectionsToSuppress;
	public $volume;
	public $itemUrl;
	public $barcode;
	public $status;
	public $availableStatuses;
	public $checkedOutStatuses;
	public $libraryUseOnlyStatuses;
	public $nonHoldableStatuses;
	public $statusesToSuppress;
	public $iTypesToSuppress;
	public $totalCheckouts;
	public $lastYearCheckouts;
	public $yearToDateCheckouts;
	public $totalRenewals;
	public $iType;
	public $nonHoldableITypes;
	public $dueDate;
	public $dueDateFormat;
	public $dateCreated;
	public $dateCreatedFormat;
	public $lastCheckinDate;
	public $lastCheckinFormat;
	public $iCode2;
	public $opacMessage;
	public $useICode2Suppression; // (Now really item suppression switch)
	public $iCode2sToSuppress;
	public $sierraRecordFixedFieldsTag;
	public $bCode3;
	public $bCode3sToSuppress;
	public $format;
	public $eContentDescriptor;
//	public $orderTag;
//	public $orderStatus;
//	public $orderLocation;
//	public $orderLocationSingle;
//	public $orderCopies;
//	public $orderCode3;
	public $doAutomaticEcontentSuppression;
	public $materialTypeField;
	public $sierraLanguageFixedField;
	public $formatDeterminationMethod;
	public $materialTypesToIgnore;
	public $coverSource;
	public $changeRequiresReindexing;

	static function getObjectStructure(){
		global $configArray;
		$translationMapStructure = TranslationMap::getObjectStructure();
		unset($translationMapStructure['indexingProfileId']);

		//Sections that are set open by default allow the javascript form validator to check that required fields are in fact filled in.
		$timeToReshelveStructure = TimeToReshelve::getObjectStructure();
		unset($timeToReshelveStructure['indexingProfileId']);
		$structure       = [
			'id'                  => ['property' => 'id', 'type' => 'label', 'label' => 'Id', 'description' => 'The unique id within the database'],
			'name'                => ['property' => 'name', 'type' => 'text', 'label' => 'Display Name', 'maxLength' => 50, 'description' => 'The display name for this indexing profile', 'required' => true, 'changeRequiresReindexing' => true],
			'sourceName'          => ['property'           => 'sourceName', 'type' => 'text', 'label' => 'Source Name', 'maxLength' => 50, 'description' => 'The source name of this indexing profile to use internally. eg. for specifying the record source', 'required' => true
			                          , 'serverValidation' => 'validateSourceName', 'changeRequiresReindexing' => true],
			'recordUrlComponent'  => ['property' => 'recordUrlComponent', 'type' => 'text', 'label' => 'Record URL Component', 'maxLength' => 50, 'description' => 'The Module to use within the URL', 'required' => true, 'default' => 'Record', 'serverValidation' => 'validateRecordUrlComponent'],
			'groupUnchangedFiles' => ['property' => 'groupUnchangedFiles', 'type' => 'checkbox', 'label' => 'Group Unchanged Files', 'description' => 'Whether or not files that have not changed since the last time grouping has run will be regrouped.', 'default' => true],
			'lastGroupedTime'     => ['property' => 'lastGroupedTime', 'type' => 'dateReadOnly', 'label' => 'Last Grouped Time', 'description' => 'Date Time for when this indexing profile was last grouped.'],
			'changeRequiresReindexing'  => ['property' => 'changeRequiresReindexing', 'type' => 'dateReadOnly', 'label' => 'Change Requires Reindexing', 'description' => 'Date Time for when this indexing profile changed settings needing re-indexing'],

			'serverFileSection'   => ['property' =>'serverFileSection', 'type' => 'section', 'label' =>'MARC File Settings ', 'hideInLists' => true, 'open' => true,
			                        'helpLink' => '', 'properties' => [

					'marcPath'                          => ['property' => 'marcPath', 'type' => 'text', 'label' => 'MARC Path', 'maxLength' => 100, 'description' => 'The path on the server where MARC records can be found', 'required' => true],
					'filenamesToInclude'                => ['property' => 'filenamesToInclude', 'type' => 'text', 'label' => 'Filenames to Include', 'maxLength' => 250, 'description' => 'A regular expression to determine which files should be grouped and indexed', 'required' => true, 'default' => '.*\.ma?rc'],
					'marcEncoding'                      => ['property' => 'marcEncoding', 'type' => 'enum', 'label' => 'MARC Encoding', 'values' => ['MARC8' => 'MARC8', 'UTF8' => 'UTF8', 'UNIMARC' => 'UNIMARC', 'ISO8859_1' => 'ISO8859_1', 'BESTGUESS' => 'BESTGUESS'], 'default' => 'UTF8'],
					'minMarcFileSize'                   => ['property' => 'minMarcFileSize', 'type' => 'integer', 'label' => 'Minimum size of the MARC full export file to process', 'description' => 'This profile has to have one main marc file. If that file is below this limit, grouping will be skipped', 'min' => 0],

					'individualMARCFileSettingsSection' => [
				'property' => 'individualMARCFileSettingsSection', 'type' => 'section', 'label' => 'Individual Record Files', 'hideInLists' => true, 'open' => true,
				'helpLink' => '', 'properties' => [
							'individualMarcPath'                => ['property' => 'individualMarcPath', 'type' => 'text', 'label' => 'Individual MARC Path', 'maxLength' => 100, 'description' => 'The path on the server where individual MARC records can be found', 'required' => true, 'changeRequiresReindexing' => true],
							'numCharsToCreateFolderFrom'        => ['property' => 'numCharsToCreateFolderFrom', 'type' => 'integer', 'label' => 'Number of characters to create folder from', 'maxLength' => 50, 'description' => 'The number of characters to use when building a sub folder for individual marc records', 'required' => false, 'default' => '4'],
							'createFolderFromLeadingCharacters' => ['property' =>'createFolderFromLeadingCharacters', 'type' =>'checkbox', 'label' =>'Create Folder From Leading Characters', 'description' =>'Whether we should look at the start or end of the folder when .', 'hideInLists' => true, 'default' => 0],
						]],
				]],

			'DriverSection' => ['property' => 'DriverSection', 'type' => 'section', 'label' => 'Pika Driver Settings', 'hideInLists' => true, 'open' => true,
			                    'helpLink' => '', 'properties' => [
					'groupingClass' => ['property' => 'groupingClass', 'type' => 'text', 'label' => 'Grouping Class', 'maxLength' => 50, 'description' => 'The class to use while grouping the records', 'required' => true, 'default' => 'MarcRecordGrouper'],
					'indexingClass' => ['property' => 'indexingClass', 'type' => 'text', 'label' => 'Indexing Class', 'maxLength' => 50, 'description' => 'The class to use while indexing the records', 'required' => true, 'default' => 'IlsRecord', 'changeRequiresReindexing' => true],
					'recordDriver'  => ['property' => 'recordDriver', 'type' => 'text', 'label' => 'Record Driver', 'maxLength' => 50, 'description' => 'The record driver to use while displaying information in Pika', 'required' => true, 'default' => 'MarcRecord'],
					'coverSource'   => ['property' => 'coverSource', 'type' => 'enum', 'label' => 'Cover Source',  'description' => 'Method to use to fetch cover images', 'required' => true, 'values' => self::COVER_SOURCES, 'default' => 'SideLoad General'],
					'patronDriver'  => ['property' => 'patronDriver', 'type' => 'text', 'label' => 'Patron Driver', 'maxLength' => 50, 'description' => 'The patron driver to use for ILS or eContent integration', /*'required' => true,*/
					                    'default'  => 'DriverInterface'],
				]],
			//TODO: refactor catalogDriver to circulationSystemDriver
			//TODO: this would be the hook in to tie a indexing profile to eContent driver

			'formatDeterminationSection' => ['property' => 'formatDeterminationSection', 'type' => 'section', 'label' => 'Format Determination Settings', 'hideInLists' => true,
			                                 'helpLink' => '', 'properties' => [

					'formatSource'     => ['property' => 'formatSource', 'type' => 'enum', 'label' => 'Determine Format based on', 'values' => ['bib' => 'Bib Record', 'item' => 'Item Record', 'specified' => 'Specified Value'], 'default' => 'bib', 'hideInLists' => false, 'changeRequiresReindexing' => true],
					'bibFormatSection' => ['property' => 'bibFormatSection', 'type' => 'section', 'label' => 'Bib Format Determination Settings', 'hideInLists' => true,
					                       'helpLink' => '', 'properties' => [
							'formatDeterminationMethod' => ['property' => 'formatDeterminationMethod',
							                                'type'     => 'enum',
							                                'label'    => 'Format Determination Method',
							                                'values'   => ['bib' => 'Bib Record', 'matType' => 'Material Type'],
							                                'default'  => 'bib'
							                                , 'changeRequiresReindexing' => true
							],
							'materialTypesToIgnore'     => ['property'    => 'materialTypesToIgnore',
							                                'type'        => 'text',
							                                'label'       => 'Material Type Values to Ignore (ils profile only)',
							                                'maxLength'   => 50,
							                                'description' => 'MatType values to ignore when using the MatType format determination. The bib format determination will be used instead. " " & "-" are always ignored.',
							                                'hideInLists' => true
							                                , 'changeRequiresReindexing' => true],
						]],

					'specifiedFormatSection' => ['property' => 'specifiedFormatSection', 'type' => 'section', 'label' => 'Specified Format Settings', 'hideInLists' => true,
					                             'helpLink' => '', 'properties' => [

							'specifiedFormat'           => ['property'    => 'specifiedFormat',
							                                'type'        => 'text',
							                                'label'       => 'Specified Format',
							                                'maxLength'   => 50,
							                                'description' => 'The format to set when using a defined format',
							                                'required'    => false,
							                                'default'     => ''
							                                , 'changeRequiresReindexing' => true],
							'specifiedFormatCategory'   => ['property'    => 'specifiedFormatCategory',
							                                'type'        => 'enum',
							                                'label'       => 'Specified Format Category',
							                                'values'      => ['', 'Books' => 'Books', 'eBook' => 'eBook', 'Audio Books' => 'Audio Books', 'Movies' => 'Movies', 'Music' => 'Music', 'Other' => 'Other'],
							                                'description' => 'The format category to set when using a defined format',
							                                'required'    => false,
							                                'default'     => ''
							                                , 'changeRequiresReindexing' => true],
							'specifiedFormatBoost'      => ['property'    => 'specifiedFormatBoost',
							                                'type'        => 'integer',
							                                'label'       => 'Specified Format Boost',
							                                'maxLength'   => 50,
							                                'description' => 'The format boost to set when using a defined format',
							                                'required'    => false,
							                                'default'     => '8'
							                                , 'changeRequiresReindexing' => true],
							'specifiedGroupingCategory' => ['property'    => 'specifiedGroupingCategory',
							                                'type'        => 'enum',
							                                'label'       => 'Specified Grouping Category',
							                                'values'      => ['', 'book' => 'Book', 'movie' => 'Movie', 'music' => 'Music', 'comic' => 'Comic', 'young' => 'Young Reader'],
							                                'description' => 'The grouping category to set when using a defined format'],
						]],
				]],


			'bibRecordSection' => ['property' =>'bibRecordSection', 'type' => 'section', 'label' =>'Record Settings', 'hideInLists' => true, 'open' => true,
			                       'helpLink' => '', 'properties' => [
					'recordNumberTag'            => ['property' => 'recordNumberTag', 'type' => 'text', 'label' => 'Record Number Tag (If not 001, make sure to update mergeConfig.ini)', 'maxLength' => 3, 'description' => 'The MARC tag where the record number can be found', 'required' => true, 'changeRequiresReindexing' => true],
					'recordNumberField'          => ['property' => 'recordNumberField', 'type' => 'text', 'label' => 'Record Number Field', 'maxLength' => 1, 'description' => 'The subfield of the record number tag where the record number can be found', 'required' => true, 'default' => 'a', 'changeRequiresReindexing' => true],
					'recordNumberPrefix'         => ['property' => 'recordNumberPrefix', 'type' => 'text', 'label' => 'Record Number Prefix', 'maxLength' => 10, 'description' => 'A prefix to identify the bib record number if multiple MARC tags exist', 'changeRequiresReindexing' => true],
					'sierraRecordFixedFieldsTag' => ['property' => 'sierraRecordFixedFieldsTag', 'type' => 'text', 'label' => 'Sierra Record/Bib level Fixed Fields Tag (ils profile only)', 'maxLength' => 3, 'description' => 'The MARC tag where the Sierra fixed fields can be found, specifically the bcode3', 'changeRequiresReindexing' => true],
					'materialTypeField'          => ['property' => 'materialTypeField', 'type' => 'text', 'label' => 'Material Type Sub Field (ils profile only)', 'maxLength' => 1, 'description' => 'Bib level Subfield for Material Type (depends on setting the Sierra Record/Bib level Fixed Fields Tag)', 'hideInLists' => true, 'changeRequiresReindexing' => true],
					'sierraLanguageFixedField'   => ['property' => 'sierraLanguageFixedField', 'type' => 'text', 'label' => 'Sierra Language Fixed Field (ils profile only)', 'maxLength' => 1, 'description' => 'Bib level Subfield for Language (depends on setting the Sierra Record/Bib level Fixed Fields Tag)', 'hideInLists' => true, 'changeRequiresReindexing' => true],
				]],

			'itemRecordSection' => ['property' => 'itemRecordSection', 'type' => 'section', 'label' => 'Item Tag Settings (ils profile only)', 'hideInLists' => true,
			                        'helpLink' => '', 'properties' => [
					'itemTag'          => ['property' => 'itemTag', 'type' => 'text', 'label' => 'Item Tag', 'maxLength' => 3, 'description' => 'The MARC tag where items can be found', 'changeRequiresReindexing' => true],
					'itemRecordNumber' => ['property' => 'itemRecordNumber', 'type' => 'text', 'label' => 'Item Record Number', 'maxLength' => 1, 'description' => 'Subfield for the record number for the item', 'changeRequiresReindexing' => true],

					'callNumberSection' => ['property' => 'callNumberSection', 'type' => 'section', 'label' => 'Call Number Settings', 'hideInLists' => true,
					                        'helpLink' => '', 'properties' => [
							'useItemBasedCallNumbers' => ['property' => 'useItemBasedCallNumbers', 'type' => 'checkbox', 'label' => 'Use Item Based Call Numbers', 'description' => 'Whether or not we should use call number information from the bib or from the item records', 'changeRequiresReindexing' => true],
							'callNumberPrestamp'      => ['property' => 'callNumberPrestamp', 'type' => 'text', 'label' => 'Call Number Prestamp', 'maxLength' => 1, 'description' => 'Subfield for call number pre-stamp', 'changeRequiresReindexing' => true],
							'callNumber'              => ['property' => 'callNumber', 'type' => 'text', 'label' => 'Call Number', 'maxLength' => 1, 'description' => 'Subfield for call number', 'changeRequiresReindexing' => true],
							'callNumberCutter'        => ['property' => 'callNumberCutter', 'type' => 'text', 'label' => 'Call Number Cutter', 'maxLength' => 1, 'description' => 'Subfield for call number cutter', 'changeRequiresReindexing' => true],
							'callNumberPoststamp'     => ['property' => 'callNumberPoststamp', 'type' => 'text', 'label' => 'Call Number Poststamp', 'maxLength' => 1, 'description' => 'Subfield for call number pre-stamp', 'changeRequiresReindexing' => true],
							'volume'                  => ['property' => 'volume', 'type' => 'text', 'label' => 'Volume', 'maxLength' => 1, 'description' => 'A subfield for volume information. Added to the end of item call numbers.', 'changeRequiresReindexing' => true],
						]],

					'location'            => ['property' => 'location', 'type' => 'text', 'label' => 'Location', 'maxLength' => 1, 'description' => 'Subfield for location', 'changeRequiresReindexing' => true],
					'shelvingLocation'    => ['property' => 'shelvingLocation', 'type' => 'text', 'label' => 'Shelving Location', 'maxLength' => 1, 'description' => 'A subfield for shelving location information', 'changeRequiresReindexing' => true],
					'collection'          => ['property' => 'collection', 'type' => 'text', 'label' => 'Collection', 'maxLength' => 1, 'description' => 'A subfield for collection information', 'changeRequiresReindexing' => true],
					'barcode'             => ['property' => 'barcode', 'type' => 'text', 'label' => 'Barcode', 'maxLength' => 1, 'description' => 'Subfield for barcode', 'changeRequiresReindexing' => true],
					'status'              => ['property' => 'status', 'type' => 'text', 'label' => 'Status', 'maxLength' => 1, 'description' => 'Subfield for status', 'changeRequiresReindexing' => true],
					'totalCheckouts'      => ['property' => 'totalCheckouts', 'type' => 'text', 'label' => 'Total Checkouts', 'maxLength' => 1, 'description' => 'Subfield for total checkouts', 'changeRequiresReindexing' => true],
					'lastYearCheckouts'   => ['property' => 'lastYearCheckouts', 'type' => 'text', 'label' => 'Last Year Checkouts', 'maxLength' => 1, 'description' => 'Subfield for checkouts done last year', 'changeRequiresReindexing' => true],
					'yearToDateCheckouts' => ['property' => 'yearToDateCheckouts', 'type' => 'text', 'label' => 'Year To Date Checkouts', 'maxLength' => 1, 'description' => 'Subfield for checkouts so far this year', 'changeRequiresReindexing' => true],
//					'totalRenewals'       => ['property' => 'totalRenewals', 'type' => 'text', 'label' => 'Total Renewals', 'maxLength' => 1, 'description' => 'Subfield for number of times this record has been renewed', 'changeRequiresReindexing' => true],
					'dueDate'             => ['property' => 'dueDate', 'type' => 'text', 'label' => 'Due Date', 'maxLength' => 1, 'description' => 'Subfield for when the item is due', 'changeRequiresReindexing' => true],
					'dueDateFormat'       => ['property' => 'dueDateFormat', 'type' => 'text', 'label' => 'Due Date Format', 'maxLength' => 20, 'description' => 'Subfield for when the item is due', 'changeRequiresReindexing' => true],
					'dateCreated'         => ['property' => 'dateCreated', 'type' => 'text', 'label' => 'Date Created', 'maxLength' => 1, 'description' => 'The format of the due date.  I.e. yyMMdd see SimpleDateFormat for Java', 'changeRequiresReindexing' => true],
					'dateCreatedFormat'   => ['property' => 'dateCreatedFormat', 'type' => 'text', 'label' => 'Date Created Format', 'maxLength' => 20, 'description' => 'The format of the date created.  I.e. yyMMdd see SimpleDateFormat for Java', 'changeRequiresReindexing' => true],
					'lastCheckinDate'     => ['property' => 'lastCheckinDate', 'type' => 'text', 'label' => 'Last Check in Date', 'maxLength' => 1, 'description' => 'Subfield for when the item was last checked in', 'changeRequiresReindexing' => true],
					'lastCheckinFormat'   => ['property' => 'lastCheckinFormat', 'type' => 'text', 'label' => 'Last Check In Format', 'maxLength' => 20, 'description' => 'The format of the date the item was last checked in.  I.e. yyMMdd see SimpleDateFormat for Java', 'changeRequiresReindexing' => true],
					'iCode2'              => ['property' => 'iCode2', 'type' => 'text', 'label' => 'Item Suppression Field', 'maxLength' => 1, 'description' => 'Subfield for the item Suppression Field', 'changeRequiresReindexing' => true],
					'opacMessage'         => ['property' => 'opacMessage', 'type' => 'text', 'label' => 'Opac Message Field (Sierra Only)', 'maxLength' => 1, 'description' => 'Subfield for Sierra Opac Message field'],
					'format'              => ['property' => 'format', 'type' => 'text', 'label' => 'Format subfield', 'maxLength' => 1, 'description' => 'The subfield to use when determining format based on item information', 'changeRequiresReindexing' => true],
					'iType'               => ['property' => 'iType', 'type' => 'text', 'label' => 'iType', 'maxLength' => 1, 'description' => 'Subfield for iType', 'changeRequiresReindexing' => true],
					'eContentDescriptor'  => ['property' => 'eContentDescriptor', 'type' => 'text', 'label' => 'eContent Descriptor', 'maxLength' => 1, 'description' => 'Subfield that indicates the item should be treated as eContent (For Libraries using the Marmot ILS eContent Standard)', 'changeRequiresReindexing' => true],
					'itemUrl'             => ['property' => 'itemUrl', 'type' => 'text', 'label' => 'Item URL', 'maxLength' => 1, 'description' => 'Subfield for a URL specific to the item (For Libraries using the Marmot ILS eContent Standard)', 'changeRequiresReindexing' => true],
				]],


			'itemStatusSection' => ['property' =>'itemStatusSection', 'type' => 'section', 'label' =>'Item Statuses Settings (ils profiles only)', 'hideInLists' => true,
			                         'helpLink' => '', 'properties' => [
					'availableStatuses'      => ['property'    => 'availableStatuses',
					                             'type'        => 'text',
					                             'label'       => 'Available Statuses (list of codes seperated by pipe character | for Sierra ils profiles, regex for Horizon ils profiles)',
					                             'maxLength'   => 255,
//					                             'default'     => "-",
					                             'description' => 'A list of codes that are valid available item statues.'
					                             , 'changeRequiresReindexing' => true],
					'checkedOutStatuses'     => ['property'    => 'checkedOutStatuses',
					                             'type'        => 'text',
					                             'label'       => 'Checked Out Statuses (Sierra & Polaris ils profiles only, lists of codes seperated by pipe character |)',
					                             'maxLength'   => 255,
//					                             'default'     => "-",
					                             'description' => 'A list of characters that are valid checked out item statuses.'
					                             , 'changeRequiresReindexing' => true],
					'libraryUseOnlyStatuses' => ['property'    => 'libraryUseOnlyStatuses',
					                             'type'        => 'text',
					                             'label'       => 'Library Use Only Statuses (Sierra ils profiles only, list of codes seperated by pipe character |)',
					                             'maxLength'   => 255,
//					                             'default'     => "o",
					                             'description' => 'A list of characters that are valid checked out item statuses.'
					                             , 'changeRequiresReindexing' => true],
				]],

			'nonholdableSection' => ['property' =>'nonholdableSection', 'type' => 'section', 'label' =>'Non-holdable Settings (ils profile only)', 'hideInLists' => true,
			                         'helpLink' => '', 'properties' => [
					'nonHoldableStatuses'  => ['property' => 'nonHoldableStatuses', 'type' => 'text', 'label' => 'Non Holdable Statuses (list of codes seperated by pipe character |)', 'maxLength' => 255, 'description' => 'A regular expression for any statuses that should not allow holds', 'changeRequiresReindexing' => true],
					'nonHoldableLocations' => ['property' => 'nonHoldableLocations', 'type' => 'text', 'label' => 'Non Holdable Locations (list of codes seperated by pipe character |)', 'maxLength' => 255, 'description' => 'A regular expression for any locations that should not allow holds', 'changeRequiresReindexing' => true],
					'nonHoldableITypes'    => ['property' => 'nonHoldableITypes', 'type' => 'text', 'label' => 'Non Holdable ITypes (list of codes seperated by pipe character |)', 'maxLength' => 255, 'description' => 'A regular expression for any ITypes that should not allow holds', 'changeRequiresReindexing' => true],
				]],

			'suppressionSection' => ['property' =>'suppressionSection', 'type' => 'section', 'label' =>'Suppression Settings (ils profile only)', 'hideInLists' => true,
			                         'helpLink' => '', 'properties' => [
					'itemSuppressionSection' => ['property' =>'itemSuppressionSection', 'type' => 'section', 'label' =>'Item Level Suppression Settings', 'hideInLists' => true,
					                             'helpLink' => '', 'properties' => [
							'statusesToSuppress'    => ['property' => 'statusesToSuppress', 'type' => 'text', 'label' => 'Statuses To Suppress (use regex)', 'maxLength' => 100, 'description' => 'A regular expression for any statuses that should be suppressed', 'changeRequiresReindexing' => true],
							'iTypesToSuppress'      => ['property' => 'iTypesToSuppress', 'type' => 'text', 'label' => 'Itypes To Suppress (use regex)', 'maxLength' => 100, 'description' => 'A regular expression for any Itypes that should be suppressed', 'changeRequiresReindexing' => true],
							'locationsToSuppress'   => ['property' => 'locationsToSuppress', 'type' => 'text', 'label' => 'Locations To Suppress (use regex)', 'maxLength' => 255, 'description' => 'A regular expression for any locations that should be suppressed', 'changeRequiresReindexing' => true],
							'collectionsToSuppress' => ['property' => 'collectionsToSuppress', 'type' => 'text', 'label' => 'Collections To Suppress (use regex)', 'maxLength' => 100, 'description' => 'A regular expression for any collections that should be suppressed', 'changeRequiresReindexing' => true],
							'useICode2Suppression'  => ['property' => 'useICode2Suppression', 'type' => 'checkbox', 'label' => 'Use Item Suppression Field suppression for items', 'description' => 'Whether or not we should suppress items based on Item Suppression Field', 'changeRequiresReindexing' => true],
							'iCode2sToSuppress'     => ['property' => 'iCode2sToSuppress', 'type' => 'text', 'label' => 'Item Suppression Field Values To Suppress (use regex)', 'maxLength' => 100, 'description' => 'A regular expression for any Item Suppression Field that should be suppressed', 'changeRequiresReindexing' => true],
						]],
					'bibSuppressionSection' => ['property' =>'bibSuppressionSection', 'type' => 'section', 'label' =>'Bib Level Suppression Settings', 'hideInLists' => true,
					                            'helpLink' => '', 'properties' => [
							'suppressItemlessBibs'           => ['property' => 'suppressItemlessBibs', 'type' => 'checkbox', 'label' => 'Suppress Itemless Bibs', 'description' => 'Whether or not Itemless Bibs can be suppressed', 'changeRequiresReindexing' => true],
							'doAutomaticEcontentSuppression' => ['property' => 'doAutomaticEcontentSuppression', 'type' => 'checkbox', 'label' => 'Do Automatic eContent Suppression', 'description' => 'Whether or not eContent suppression for overdrive and hoopla records is done automatically', 'default' =>false],
							'bCode3'                         => ['property' => 'bCode3', 'type' => 'text', 'label' => 'BCode3 Subfield', 'maxLength' => 1, 'description' => 'Subfield for BCode3', 'changeRequiresReindexing' => true],
							'bCode3sToSuppress'              => ['property' => 'bCode3sToSuppress', 'type' => 'text', 'label' => 'BCode3s To Suppress (use regex)', 'maxLength' => 100, 'description' => 'A regular expression for any BCode3s that should be suppressed', 'changeRequiresReindexing' => true],
						]],
				]],
/*  Hide this section, since it is unused at this time
			'orderRecordSection' => ['property' =>'orderRecordSection', 'type' => 'section', 'label' =>'Order Tag Settings (ils profile only)', 'hideInLists' => true,
			                         'helpLink' => '', 'properties' => [
					'orderTag'            => ['property' => 'orderTag', 'type' => 'text', 'label' => 'Order Tag', 'maxLength' => 3, 'description' => 'The MARC tag where order records can be found'],
					'orderStatus'         => ['property' => 'orderStatus', 'type' => 'text', 'label' => 'Order Status Subfield', 'maxLength' => 1, 'description' => 'Subfield for status of the order item'],
					'orderLocationSingle' => ['property' => 'orderLocationSingle', 'type' => 'text', 'label' => 'Order Location Single Subfield', 'maxLength' => 1, 'description' => 'Subfield for location of the order item when the order applies to a single location'],
					'orderLocation'       => ['property' => 'orderLocation', 'type' => 'text', 'label' => 'Order Location Multi Subfield', 'maxLength' => 1, 'description' => 'Subfield for location of the order item when the order applies to multiple locations'],
					'orderCopies'         => ['property' => 'orderCopies', 'type' => 'text', 'label' => 'Order Copies Subfield', 'maxLength' => 1, 'description' => 'The number of copies if not shown within location'],
					'orderCode3'          => ['property' => 'orderCode3', 'type' => 'text', 'label' => 'Order Code3 Subfield', 'maxLength' => 1, 'description' => 'Code 3 for the order record'],
				]],
*/
			'translationMaps' => [
				'property'      => 'translationMaps',
				'type'          => 'oneToMany',
				'label'         => 'Translation Maps',
				'description'   => 'The translation maps for the profile.',
				'keyThis'       => 'id',
				'keyOther'      => 'indexingProfileId',
				'subObjectType' => 'TranslationMap',
				'structure'     => $translationMapStructure,
				'sortable'      => false,
				'storeDb'       => true,
				'allowEdit'     => true,
				'canEdit'       => true,
			],

			'timeToReshelve' => [
				'property'                 => 'timeToReshelve',
				'type'                     => 'oneToMany',
				'label'                    => 'Time to Reshelve',
				'description'              => 'Overrides for time to reshelve.',
				'keyThis'                  => 'id',
				'keyOther'                 => 'indexingProfileId',
				'subObjectType'            => 'TimeToReshelve',
				'structure'                => $timeToReshelveStructure,
				'sortable'                 => true,
				'storeDb'                  => true,
				'allowEdit'                => true,
				'canEdit'                  => false,
				'changeRequiresReindexing' => true,
			],

		];

		if ($configArray['Catalog']['ils'] == 'Sierra'){
			$sierraMappingStructure = SierraExportFieldMapping::getObjectStructure();
			unset($sierraMappingStructure['indexingProfileId']);
			$structure['sierraFieldMappings'] = [
				'property'      => 'sierraFieldMappings',
				'helpLink'      => 'https://marmot-support.atlassian.net/l/c/ETNK5ZJ4',
				'type'          => 'oneToMany',
				'label'         => 'Sierra API Item Field Mappings (Sierra Systems only)',
				'description'   => 'For mapping Item tags from the Sierra API to the equivalent values in the indexing profile (and Sierra\'s export profile).',
				'keyThis'       => 'id',
				'keyOther'      => 'indexingProfileId',
				'subObjectType' => 'SierraExportFieldMapping',
				'structure'     => $sierraMappingStructure,
				'sortable'      => false,
				'storeDb'       => true,
				'allowEdit'     => true,
				'canEdit'       => false,
				'hideInLists'   => true,
			];
		}
		return $structure;
	}

	public function __get($name){
		if ($name == 'translationMaps') {
			if (!isset($this->translationMaps)){
				//Get the list of translation maps
				$this->translationMaps = array();
				if ($this->id) { // When this is a new Indexing Profile, there are no maps yet.
					$translationMap = new TranslationMap();
					$translationMap->indexingProfileId = $this->id;
					$translationMap->orderBy('name ASC');
					$translationMap->find();
					while($translationMap->fetch()){
						$this->translationMaps[$translationMap->id] = clone($translationMap);
					}
				}
			}
			return $this->translationMaps;
		}else if ($name == 'timeToReshelve') {
			if (!isset($this->timeToReshelve)) {
				//Get the list of translation maps
				$this->timeToReshelve = [];
				if ($this->id) { // When this is a new Indexing Profile, there are no maps yet.
					$timeToReshelve                    = new TimeToReshelve();
					$timeToReshelve->indexingProfileId = $this->id;
					$timeToReshelve->orderBy('weight ASC');
					$timeToReshelve->find();
					while ($timeToReshelve->fetch()) {
						$this->timeToReshelve[$timeToReshelve->id] = clone $timeToReshelve;
					}
				}
			}
			return $this->timeToReshelve;
		}else if ($name == 'sierraFieldMappings') {
			if (!isset($this->sierraFieldMappings)) {
				//Get the list of translation maps
				$this->sierraFieldMappings = array();
				if ($this->id) { // When this is a new Indexing Profile, there are no maps yet.
					$sierraFieldMapping = new SierraExportFieldMapping();
					$sierraFieldMapping->indexingProfileId = $this->id;
					$sierraFieldMapping->find();
					while ($sierraFieldMapping->fetch()) {
						$this->sierraFieldMappings[$sierraFieldMapping->id] = clone($sierraFieldMapping);
					}
				}
			}
			return $this->sierraFieldMappings;
		}
		return null;
	}

	public function __set($name, $value){
		if ($name == 'translationMaps') {
			$this->translationMaps = $value;
		}else if ($name == 'timeToReshelve') {
			$this->timeToReshelve = $value;
		}else if ($name == 'sierraFieldMappings') {
			$this->sierraFieldMappings = $value;
		}
	}

	/**
	 * Override the update functionality to save the associated translation maps
	 *
	 * @see DB/DB_DataObject::update()
	 */
	public function update($dataObject = false){
		$ret = parent::update();
		if ($ret === FALSE ){
			global $pikaLogger;
			$pikaLogger->error('Failed to update indexing profile for '.$this->sourceName);
			return $ret;
		}else{
			$this->saveTranslationMaps();
			$this->saveTimeToReshelve();
			$this->saveSierraFieldMappings();
		}
		/** @var Memcache $memCache */
		global $memCache;
		global $instanceName;
		if (!$memCache->delete("{$instanceName}_indexing_profiles")) {
			global $pikaLogger;
			$pikaLogger->error("Failed to delete memcache variable {$instanceName}_indexing_profiles when adding new indexing profile for {$this->sourceName}");
		}
		return true;
	}

	/**
	 * Override the update functionality to save the associated translation maps
	 *
	 * @see DB/DB_DataObject::insert()
	 */
	public function insert(){
		$ret = parent::insert();
		if ($ret === FALSE ){
			global $pikaLogger;
			$pikaLogger->error('Failed to add new indexing profile for '.$this->sourceName);
			return $ret;
		}else{
			$this->saveTranslationMaps();
			$this->saveTimeToReshelve();
			$this->saveSierraFieldMappings();
		}
		/** @var Memcache $memCache */
		global $memCache;
		global $instanceName;
		if (!$memCache->delete("{$instanceName}_indexing_profiles")) {
			global $pikaLogger;
			$pikaLogger->error("Failed to delete memcache variable {$instanceName}_indexing_profiles when adding new indexing profile for {$this->sourceName}");
		}
		return true;
	}

	public function saveTranslationMaps(){
		if (isset ($this->translationMaps)){
			/** @var TranslationMap $translationMap */
			foreach ($this->translationMaps as $translationMap){
				if (isset($translationMap->deleteOnSave) && $translationMap->deleteOnSave == true){
					$translationMap->delete();
				}else{
					if (isset($translationMap->id) && is_numeric($translationMap->id)){
						$translationMap->update(false);
					}else{
						$translationMap->indexingProfileId = $this->id;
						$translationMap->insert(false);
					}
				}
			}
			//Clear the translation maps so they are reloaded the next time
			unset($this->translationMaps);
		}
	}

	public function saveTimeToReshelve(){
		if (isset ($this->timeToReshelve)){
			/** @var TimeToReshelve $timeToReshelve */
			foreach ($this->timeToReshelve as $timeToReshelve){
				if (isset($timeToReshelve->deleteOnSave) && $timeToReshelve->deleteOnSave == true){
					$timeToReshelve->delete();
				}else{
					if (isset($timeToReshelve->id) && is_numeric($timeToReshelve->id)){
						$timeToReshelve->update();
					}else{
						$timeToReshelve->indexingProfileId = $this->id;
						$timeToReshelve->insert();
					}
				}
			}
			//Clear the translation maps so they are reloaded the next time
			unset($this->timeToReshelve);
		}
	}

	public function saveSierraFieldMappings(){
		if (isset ($this->sierraFieldMappings)){
			/** @var SierraExportFieldMapping $sierraFieldMapping */
			foreach ($this->sierraFieldMappings as $sierraFieldMapping){
				if (isset($sierraFieldMapping->deleteOnSave) && $sierraFieldMapping->deleteOnSave == true){
					$sierraFieldMapping->delete();
				}else{
					if (isset($sierraFieldMapping->id) && is_numeric($sierraFieldMapping->id)){
						$sierraFieldMapping->update();
					}else{
						$sierraFieldMapping->indexingProfileId = $this->id;
						$sierraFieldMapping->insert();
					}
				}
			}
			//Clear the translation maps so they are reloaded the next time
			unset($this->sierraFieldMappings);
		}
	}

	public function translate($mapName, $value){
		$translationMap                    = new TranslationMap();
		$translationMap->name              = $mapName;
		$translationMap->indexingProfileId = $this->id;
		if ($translationMap->find(true)){
			/** @var TranslationMapValue $mapValue */
			foreach ($translationMap->translationMapValues as $mapValue){
				if ($mapValue->value == $value){
					return $mapValue->translation;
				}else{
					if (substr($mapValue->value, -1) == '*'){
						if (substr($value, 0, strlen($mapValue) - 1) == substr($mapValue->value, 0, -1)){
							return $mapValue->translation;
						}
					}
				}
			}
		}
	}

	public function validateRecordUrlComponent(){
		$validationResults = [
			'validatedOk' => true,
			'errors'      => [],
		];

		$recordURLComponent = trim($_REQUEST['recordUrlComponent']);

		if (!ctype_alnum($recordURLComponent)){
			$validationResults = [
				'validatedOk' => false,
				'errors'      => ['The Record Url Component should consist of only alpha-numeric characters and no white space characters'],
			];
		} else{
			$indexingProfile = new IndexingProfile();
			$count           = $indexingProfile->get('recordUrlComponent', $recordURLComponent);
			if ($count > 0 && $this->id != $indexingProfile->id){ // include exception for editing the same profile
				$validationResults = [
					'validatedOk' => false,
					'errors'      => ['The Record Url Component is already in use by another indexing profile'],
				];
			}
		}
		return $validationResults;
	}

	public function validateSourceName(){
		$validationResults = [
			'validatedOk' => true,
			'errors'      => [],
		];

		$sourceName = trim($_REQUEST['sourceName']);

		if (!ctype_alnum($sourceName)){
			$validationResults['validatedOk'] = false;
			$validationResults['errors'][] = 'The Source Name should consist of only alpha-numeric characters and no white space characters';
		}

		if ($sourceName != strtolower($sourceName)){
			$validationResults['validatedOk'] = false;
			$validationResults['errors'][]    = 'The Source Name should consist of lower case characters';
		}

		if ($validationResults['validatedOk']){
			$indexingProfile = new IndexingProfile();
			$count           = $indexingProfile->get('sourceName', $sourceName);
			if ($count > 0 && $this->id != $indexingProfile->id){ // include exception for editing the same profile
				$validationResults = [
					'validatedOk' => false,
					'errors'      => ['The Record Url Component is already in use by another indexing profile'],
				];
			}
		}
		return $validationResults;
	}

	static public function getAllIndexingProfiles(){
		$indexingProfiles = [];
		$indexingProfile  = new IndexingProfile();
		$indexingProfile->orderBy('sourceName');
		$indexingProfile->find();
		while ($indexingProfile->fetch()){
			$indexingProfiles[strtolower($indexingProfile->sourceName)] = clone($indexingProfile);
			// Indexing profile sourceNames are all indexed in lower case
		}
		return $indexingProfiles;
	}

	/**
	 * Use for display of a list/drop down of indexing profiles
	 * @return array
	 */
	static public function getAllIndexingProfileNames(){
		$indexingProfiles = [];
		$indexingProfile  = new IndexingProfile();
		$indexingProfile->orderBy('sourceName');
		$indexingProfile->find();
		while ($indexingProfile->fetch()){
			$indexingProfiles[$indexingProfile->id] = $indexingProfile->name;
		}
		return $indexingProfiles;
	}

	/**
	 * Adds a header for this object in the edit form pages
	 * @return string|null
	 */
	function label(){
		if (!empty($this->name)){
			return $this->name;
		}
	}

}
