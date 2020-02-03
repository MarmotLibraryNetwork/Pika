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
 *  Class for managing sub-categories of Browse Categories
 *
 * @category Pika
 * @author Pascal Brammeier <pascal@marmot.org>
 * Date: 6/3/2015
 *
 */
require_once 'DB/DataObject.php';

class SubBrowseCategories extends DB_DataObject {
	public $__table = 'browse_category_subcategories';
	public
		$id,
		$weight,
		$browseCategoryId, // ID of the Main or Parent browse category
		$subCategoryId;    // ID of the browse Category which is the Sub-Category or Child browse category

	static function getObjectStructure(){
		$browseCategoryList = self::listBrowseCategories();
		$structure = array(
			'id'               => array('property' => 'id', 'type' => 'label', 'label' => 'Id', 'description' => 'The unique id of the sub-category row within the database'),
			'browseCategoryId' => array('property' => 'browseCategoryId', 'type' => 'label', 'label' => 'Browse Category', 'description' => 'The parent browse category'),
			'subCategoryId'    => array('property' => 'subCategoryId', 'type' => 'enum', 'values' => $browseCategoryList, 'label' => 'Sub-Category', 'description' => 'The sub-category of the parent browse category'),
			'weight'           => array('property' => 'weight', 'type' => 'numeric', 'label' => 'Weight', 'weight' => 'Defines the order of the sub-categories .  Lower weights are displayed to the left of the screen.', 'required' => true),
		);
		return $structure;
	}

	static function listBrowseCategories(){
		$browseCategoryList = array();
		require_once ROOT_DIR . '/sys/Browse/BrowseCategory.php';
//		$browseCategories = new BrowseCategory();
//		$browseCategories->orderBy('label');
//		$browseCategories->find();
//				while($browseCategories->fetch()){
//			$browseCategoryList[$browseCategories->id] = $browseCategories->label . " ({$browseCategories->textId})";
//		}

		$browseCategories = new BrowseCategory();
		$browseCategories->orderBy('label');
		$browseCategories->selectAdd();
		$browseCategories->selectAdd('id, CONCAT(`label`, " (", `textID`, ")") AS `option`');
		$browseCategoryList = $browseCategories->fetchAll('id', 'option');

		return $browseCategoryList;
	}

}
