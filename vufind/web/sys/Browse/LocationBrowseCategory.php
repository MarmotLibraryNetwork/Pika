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
 * A location defined for a browse category
 *
 * @category VuFind-Plus
 * @author Mark Noble <mark@marmot.org>
 * Date: 3/4/14
 * Time: 9:26 PM
 */

class LocationBrowseCategory extends DB_DataObject{
	public $__table = 'browse_category_location';
	public $id;
	public $weight;
	public $browseCategoryTextId;
	public $locationId;

	static function getObjectStructure(){
		//Load Libraries for lookup values
		$location = new Location();
		$location->orderBy('displayName');
		$user = UserAccount::getLoggedInUser();
		if (UserAccount::userHasRole('libraryAdmin')){
			$homeLibrary = UserAccount::getUserHomeLibrary();
			$location->libraryId = $homeLibrary->libraryId;
		}
		$location->find();
		$locationList = array();
		while ($location->fetch()){
			$locationList[$location->locationId] = $location->displayName;
		}
		require_once ROOT_DIR . '/sys/Browse/BrowseCategory.php';
		$browseCategories = new BrowseCategory();
		$browseCategories->orderBy('label');
		$browseCategories->find();
		$browseCategoryList = array(
			'system_recommended_for_you' =>  translate('Recommended for you'). ' (system_recommended_for_you) [Only displayed when user is logged in]'
		);
		while($browseCategories->fetch()){
			$browseCategoryList[$browseCategories->textId] = $browseCategories->label . " ({$browseCategories->textId})";
		}
		$structure = array(
				'id' => array('property'=>'id', 'type'=>'label', 'label'=>'Id', 'description'=>'The unique id of the hours within the database'),
				'locationId' => array('property'=>'locationId', 'type'=>'enum', 'values'=>$locationList, 'label'=>'Location', 'description'=>'A link to the location which the browse category belongs to'),
				'browseCategoryTextId' => array('property'=>'browseCategoryTextId', 'type'=>'enum', 'values'=>$browseCategoryList, 'label'=>'Browse Category', 'description'=>'The browse category to display '),
				'weight' => array('property' => 'weight', 'type' => 'numeric', 'label' => 'Weight', 'weight' => 'Defines how lists are sorted within the widget.  Lower weights are displayed to the left of the screen.', 'required'=> true),

		);
		return $structure;
	}
}
