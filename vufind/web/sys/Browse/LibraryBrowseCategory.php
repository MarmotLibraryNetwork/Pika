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
 * A Browse Category designed specifically for a library
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 3/4/14
 * Time: 9:25 PM
 */

class LibraryBrowseCategory extends DB_DataObject{
	public $__table = 'browse_category_library';
	public $id;
	public $weight;
	public $browseCategoryTextId;
	public $libraryId;

	static function getObjectStructure(){
		//Load Libraries for lookup values
		$library = new Library();
		$library->orderBy('displayName');
//		if (!UserAccount::userHasRole('opacAdmin') && (UserAccount::userHasRoleFromList(['libraryAdmin', 'libraryManager']))){
//		May need above to replace below.
		$user = UserAccount::getLoggedInUser();
		if (UserAccount::userHasRole('libraryAdmin')){
			$homeLibrary = UserAccount::getUserHomeLibrary();
			$library->libraryId = $homeLibrary->libraryId;
		}
		$library->find();
		$libraryList = array();
		while ($library->fetch()){
			$libraryList[$library->libraryId] = $library->displayName;
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
				'libraryId' => array('property'=>'libraryId', 'type'=>'enum', 'values'=>$libraryList, 'label'=>'Library', 'description'=>'A link to the library which the location belongs to'),
				'browseCategoryTextId' => array('property'=>'browseCategoryTextId', 'type'=>'enum', 'values'=>$browseCategoryList, 'label'=>'Browse Category', 'description'=>'The browse category to display '),
				'weight' => array('property' => 'weight', 'type' => 'numeric', 'label' => 'Weight', 'weight' => 'Defines how lists are sorted within the widget.  Lower weights are displayed to the left of the screen.', 'required'=> true),
		);
		return $structure;
	}
}
