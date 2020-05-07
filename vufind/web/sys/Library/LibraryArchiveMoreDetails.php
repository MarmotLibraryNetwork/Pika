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

/**
 *
 *
 * @category Pika
 * @author   : Pascal Brammeier
 * Date: 2/15/2017
 *
 */
class LibraryArchiveMoreDetails extends DB_DataObject {

	public $__table = 'library_archive_more_details';
	public $id;
	public $libraryId;
	public $section;
	public $collapseByDefault;
	public $weight;

	static $moreDetailsOptions = array(
		'description'          => 'Description',
		'bio'                  => 'Biographical Information',
		'wikipedia'            => 'From Wikipedia',
		'familyDetails'        => 'Family Details',
		'addresses'            => 'Addresses',
		'details'              => 'Details',
		'militaryService'      => 'Military Service',
		'transcription'        => 'Transcription',
		'correspondence'       => 'Correspondence Information',
		'academicResearch'     => 'Academic Research Information',
		'artworkDetails'       => 'Art Information',
		'musicDetails'         => 'Music Information',
		'relatedObjects'       => 'Related Objects',
		'obituaries'           => 'Obituaries',
		'burialDetails'        => 'Burial Details',
		'relatedPeople'        => 'Related People',
		'relatedOrganizations' => 'Related Organizations',
		'relatedPlaces'        => 'Related Places',
		'relatedEvents'        => 'Related Events',
		'demographics'         => 'Demographic Details',
		'education'            => 'Academic Record',
		'notes'                => 'Notes',
		'subject'              => 'Subjects',
		'acknowledgements'     => 'Acknowledgements', //production Team
		'externalLinks'        => 'Links',
		'moreDetails'          => 'More Details',
		'rightsStatements'     => 'Rights Statements',
		'staffView'            => 'Staff View',
	);

	static function getObjectStructure(){
		//Load Libraries for lookup values
		$library = new Library();
		$library->orderBy('displayName');
		$user = UserAccount::getLoggedInUser();
		if (UserAccount::userHasRole('libraryAdmin')){
			$homeLibrary        = UserAccount::getUserHomeLibrary();
			$library->libraryId = $homeLibrary->libraryId;
		}
		$library->find();
		$libraryList = array();
		while ($library->fetch()){
			$libraryList[$library->libraryId] = $library->displayName;
		}

		$structure = array(
			'id'                => array('property' => 'id', 'type' => 'label', 'label' => 'Id', 'description' => 'The unique id of the hours within the database'),
			'libraryId'         => array('property' => 'libraryId', 'type' => 'enum', 'values' => $libraryList, 'label' => 'Library', 'description' => 'A link to the library which the location belongs to'),
			'section'           => array('property' => 'section', 'type' => 'enum', 'label' => 'Section', 'values' => self::$moreDetailsOptions, 'description' => 'The section to display'),
			'collapseByDefault' => array('property' => 'collapseByDefault', 'type' => 'checkbox', 'label' => 'Collapse By Default', 'description' => 'Whether or not the section should be collapsed by default', 'default' => true),
//			'weight'            => array('property' => 'weight', 'type' => 'integer', 'label' => 'Weight', 'weight' => 'Defines how lists are sorted within the accordion.  Lower weights are displayed to the left of the screen.', 'required' => true),
			// Weight isn't needed in the object structure for display of oneToMany sections

		);
		return $structure;
	}

//	function getEditLink(){
//		return '';
//	}

	static function getDefaultOptions($libraryId = -1){
		$defaultOptions = array();

		$defaultOption                    = new LibraryArchiveMoreDetails();
		$defaultOption->libraryId         = $libraryId;
		$defaultOption->section           = 'description';
		$defaultOption->collapseByDefault = false;
		$defaultOption->weight            = count($defaultOptions) + 101;
		$defaultOptions[]                 = $defaultOption;

		$defaultOption                    = new LibraryArchiveMoreDetails();
		$defaultOption->libraryId         = $libraryId;
		$defaultOption->section           = 'bio';
		$defaultOption->collapseByDefault = false;
		$defaultOption->weight            = count($defaultOptions) + 101;
		$defaultOptions[]                 = $defaultOption;

		$defaultOption                    = new LibraryArchiveMoreDetails();
		$defaultOption->libraryId         = $libraryId;
		$defaultOption->section           = 'wikipedia';
		$defaultOption->collapseByDefault = false;
		$defaultOption->weight            = count($defaultOptions) + 101;
		$defaultOptions[]                 = $defaultOption;

		$defaultOption                    = new LibraryArchiveMoreDetails();
		$defaultOption->libraryId         = $libraryId;
		$defaultOption->section           = 'familyDetails';
		$defaultOption->collapseByDefault = false;
		$defaultOption->weight            = count($defaultOptions) + 101;
		$defaultOptions[]                 = $defaultOption;

		$defaultOption                    = new LibraryArchiveMoreDetails();
		$defaultOption->libraryId         = $libraryId;
		$defaultOption->section           = 'addresses';
		$defaultOption->collapseByDefault = false;
		$defaultOption->weight            = count($defaultOptions) + 101;
		$defaultOptions[]                 = $defaultOption;

		$defaultOption                    = new LibraryArchiveMoreDetails();
		$defaultOption->libraryId         = $libraryId;
		$defaultOption->section           = 'militaryService';
		$defaultOption->collapseByDefault = false;
		$defaultOption->weight            = count($defaultOptions) + 101;
		$defaultOptions[]                 = $defaultOption;

		$defaultOption                    = new LibraryArchiveMoreDetails();
		$defaultOption->libraryId         = $libraryId;
		$defaultOption->section           = 'details';
		$defaultOption->collapseByDefault = false;
		$defaultOption->weight            = count($defaultOptions) + 101;
		$defaultOptions[]                 = $defaultOption;

		$defaultOption                    = new LibraryArchiveMoreDetails();
		$defaultOption->libraryId         = $libraryId;
		$defaultOption->section           = 'transcription';
		$defaultOption->collapseByDefault = true;
		$defaultOption->weight            = count($defaultOptions) + 101;
		$defaultOptions[]                 = $defaultOption;

		$defaultOption                    = new LibraryArchiveMoreDetails();
		$defaultOption->libraryId         = $libraryId;
		$defaultOption->section           = 'correspondence';
		$defaultOption->collapseByDefault = false;
		$defaultOption->weight            = count($defaultOptions) + 101;
		$defaultOptions[]                 = $defaultOption;

		$defaultOption                    = new LibraryArchiveMoreDetails();
		$defaultOption->libraryId         = $libraryId;
		$defaultOption->section           = 'academicResearch';
		$defaultOption->collapseByDefault = false;
		$defaultOption->weight            = count($defaultOptions) + 101;
		$defaultOptions[]                 = $defaultOption;

		$defaultOption                    = new LibraryArchiveMoreDetails();
		$defaultOption->libraryId         = $libraryId;
		$defaultOption->section           = 'artworkDetails';
		$defaultOption->collapseByDefault = false;
		$defaultOption->weight            = count($defaultOptions) + 101;
		$defaultOptions[]                 = $defaultOption;

		$defaultOption                    = new LibraryArchiveMoreDetails();
		$defaultOption->libraryId         = $libraryId;
		$defaultOption->section           = 'musicDetails';
		$defaultOption->collapseByDefault = false;
		$defaultOption->weight            = count($defaultOptions) + 101;
		$defaultOptions[]                 = $defaultOption;

		$defaultOption                    = new LibraryArchiveMoreDetails();
		$defaultOption->libraryId         = $libraryId;
		$defaultOption->section           = 'relatedObjects';
		$defaultOption->collapseByDefault = false;
		$defaultOption->weight            = count($defaultOptions) + 101;
		$defaultOptions[]                 = $defaultOption;

		$defaultOption                    = new LibraryArchiveMoreDetails();
		$defaultOption->libraryId         = $libraryId;
		$defaultOption->section           = 'obituaries';
		$defaultOption->collapseByDefault = false;
		$defaultOption->weight            = count($defaultOptions) + 101;
		$defaultOptions[]                 = $defaultOption;

		$defaultOption                    = new LibraryArchiveMoreDetails();
		$defaultOption->libraryId         = $libraryId;
		$defaultOption->section           = 'burialDetails';
		$defaultOption->collapseByDefault = true;
		$defaultOption->weight            = count($defaultOptions) + 101;
		$defaultOptions[]                 = $defaultOption;

		$defaultOption                    = new LibraryArchiveMoreDetails();
		$defaultOption->libraryId         = $libraryId;
		$defaultOption->section           = 'relatedPeople';
		$defaultOption->collapseByDefault = false;
		$defaultOption->weight            = count($defaultOptions) + 101;
		$defaultOptions[]                 = $defaultOption;

		$defaultOption                    = new LibraryArchiveMoreDetails();
		$defaultOption->libraryId         = $libraryId;
		$defaultOption->section           = 'relatedOrganizations';
		$defaultOption->collapseByDefault = false;
		$defaultOption->weight            = count($defaultOptions) + 101;
		$defaultOptions[]                 = $defaultOption;

		$defaultOption                    = new LibraryArchiveMoreDetails();
		$defaultOption->libraryId         = $libraryId;
		$defaultOption->section           = 'relatedPlaces';
		$defaultOption->collapseByDefault = false;
		$defaultOption->weight            = count($defaultOptions) + 101;
		$defaultOptions[]                 = $defaultOption;

		$defaultOption                    = new LibraryArchiveMoreDetails();
		$defaultOption->libraryId         = $libraryId;
		$defaultOption->section           = 'relatedEvents';
		$defaultOption->collapseByDefault = false;
		$defaultOption->weight            = count($defaultOptions) + 101;
		$defaultOptions[]                 = $defaultOption;

		$defaultOption                    = new LibraryArchiveMoreDetails();
		$defaultOption->libraryId         = $libraryId;
		$defaultOption->section           = 'demographics';
		$defaultOption->collapseByDefault = false;
		$defaultOption->weight            = count($defaultOptions) + 101;
		$defaultOptions[]                 = $defaultOption;

		$defaultOption                    = new LibraryArchiveMoreDetails();
		$defaultOption->libraryId         = $libraryId;
		$defaultOption->section           = 'education';
		$defaultOption->collapseByDefault = false;
		$defaultOption->weight            = count($defaultOptions) + 101;
		$defaultOptions[]                 = $defaultOption;

		$defaultOption                    = new LibraryArchiveMoreDetails();
		$defaultOption->libraryId         = $libraryId;
		$defaultOption->section           = 'notes';
		$defaultOption->collapseByDefault = false;
		$defaultOption->weight            = count($defaultOptions) + 101;
		$defaultOptions[]                 = $defaultOption;

		$defaultOption                    = new LibraryArchiveMoreDetails();
		$defaultOption->libraryId         = $libraryId;
		$defaultOption->section           = 'subject';
		$defaultOption->collapseByDefault = false;
		$defaultOption->weight            = count($defaultOptions) + 101;
		$defaultOptions[]                 = $defaultOption;

		$defaultOption                    = new LibraryArchiveMoreDetails();
		$defaultOption->libraryId         = $libraryId;
		$defaultOption->section           = 'acknowledgements';
		$defaultOption->collapseByDefault = true;
		$defaultOption->weight            = count($defaultOptions) + 101;
		$defaultOptions[]                 = $defaultOption;

		$defaultOption                    = new LibraryArchiveMoreDetails();
		$defaultOption->libraryId         = $libraryId;
		$defaultOption->section           = 'externalLinks';
		$defaultOption->collapseByDefault = false;
		$defaultOption->weight            = count($defaultOptions) + 101;
		$defaultOptions[]                 = $defaultOption;

		$defaultOption                    = new LibraryArchiveMoreDetails();
		$defaultOption->libraryId         = $libraryId;
		$defaultOption->section           = 'moreDetails';
		$defaultOption->collapseByDefault = true;
		$defaultOption->weight            = count($defaultOptions) + 101;
		$defaultOptions[]                 = $defaultOption;

		$defaultOption                    = new LibraryArchiveMoreDetails();
		$defaultOption->libraryId         = $libraryId;
		$defaultOption->section           = 'rightsStatements';
		$defaultOption->collapseByDefault = true;
		$defaultOption->weight            = count($defaultOptions) + 101;
		$defaultOptions[]                 = $defaultOption;

		$defaultOption                    = new LibraryArchiveMoreDetails();
		$defaultOption->libraryId         = $libraryId;
		$defaultOption->section           = 'staffView';
		$defaultOption->collapseByDefault = true;
		$defaultOption->weight            = count($defaultOptions) + 101;
		$defaultOptions[]                 = $defaultOption;

//		$defaultOption = new LibraryArchiveMoreDetails();
//		$defaultOption->libraryId = $libraryId;
//		$defaultOption->section = '';
//		$defaultOption->collapseByDefault = false;
//		$defaultOption->weight = count($defaultOptions) + 101;
//		$defaultOptions[] = $defaultOption;

		return $defaultOptions;
	}


}
