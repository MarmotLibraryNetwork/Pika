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
 * Links to show on the home page for individual libraries
 *
 * @category Pika
 * @author   Mark Noble <pika@marmot.org>
 * Date: 2/12/14
 * Time: 8:34 AM
 */
class LibraryLink extends DB_DataObject {

	public $__table = 'library_links';
	public $id;
	public $libraryId;
	public $category;
	public $linkText;
	public $url;
	public $weight;
	public $htmlContents;
	public $showInAccount;
	public $showInHelp;
	public $showExpanded;

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
		$structure = [
			'id'            => ['property' => 'id', 'type' => 'label', 'label' => 'Id', 'description' => 'The unique id of the hours within the database'],
			'libraryId'     => ['property' => 'libraryId', 'type' => 'enum', 'values' => $libraryList, 'label' => 'Library', 'description' => 'A link to the library which the location belongs to'],
			'category'      => ['property' => 'category', 'type' => 'text', 'label' => 'Category', 'description' => 'The category of the link', 'size' => '80', 'maxLength' => 100],
			'linkText'      => ['property' => 'linkText', 'type' => 'text', 'label' => 'Link Text', 'description' => 'The text to display for the link ', 'size' => '80', 'maxLength' => 100],
			'url'           => ['property' => 'url', 'type' => 'text', 'label' => 'URL', 'description' => 'The url to link to', 'size' => '80', 'maxLength' => 255],
			'htmlContents'  => ['property' => 'htmlContents', 'type' => 'html', 'label' => 'HTML Contents', 'description' => 'Optional full HTML contents to show rather than showing a basic link within the sidebar.', 'size' => '80', 'maxLength' => '512', 'allowableTags' => '<p><div><span><a><strong><b><em><i><ul><ol><li><br><hr><h1><h2><h3><h4><h5><h6><script><img><iframe>'],
			'showInAccount' => ['property' => 'showInAccount', 'type' => 'checkbox', 'label' => 'Show in Account', 'description' => 'Show the link within the Account Menu.',],
			'showInHelp'    => ['property' => 'showInHelp', 'type' => 'checkbox', 'label' => 'Show In Help', 'description' => 'Show the link within the Help Menu', 'default' => '1'],
			'showExpanded'  => ['property' => 'showExpanded', 'type' => 'checkbox', 'label' => 'Show Expanded', 'description' => 'Expand the category by default',],
		];
		return $structure;
	}

	function getEditLink(){
		return '/Admin/LibraryLinks?objectAction=edit&id=' . $this->id;
	}
}
