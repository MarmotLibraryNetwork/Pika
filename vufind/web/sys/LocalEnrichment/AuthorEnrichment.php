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
 * Description goes here
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 7/21/2016
 * Time: 11:36 AM
 */
class AuthorEnrichment  extends DB_DataObject{
	public $__table = 'author_enrichment';    // table name
	public $id;
	public $authorName;
	public $hideWikipedia;
	public $wikipediaUrl;

	static function getObjectStructure(){
		$structure = array(
			array(
				'property'    => 'id',
				'type'        => 'hidden',
				'label'       => 'Id',
				'description' => 'The unique id of the enrichment information',
				'storeDb'     => true,
				'primaryKey'  => true,
			),
			array(
				'property'    => 'authorName',
				'type'        => 'text',
				'size'        => '255',
				'maxLength'   => 255,
				'label'       => 'Author Name (100ad)',
				'description' => 'The name of the author including any dates',
				'storeDb'     => true,
				'required'    => true,
			),
			array(
				'property'    => 'hideWikipedia',
				'type'        => 'checkbox',
				'label'       => 'Hide Wikipedia Information',
				'description' => 'Check to not show Wikipedia data for this author',
				'storeDb'     => true,
				'required'    => false,
			),
			array(
				'property'    => 'wikipediaUrl',
				'type'        => 'text',
				'size'        => '255',
				'maxLength'   => 255,
				'label'       => 'Wikipedia URL',
				'description' => 'The URL to load Wikipedia data from.',
				'storeDb'     => true,
				'required'    => false,
			),
		);
		return $structure;
	}

	/**
	 * Adds a header for this object in the edit form pages
	 * @return string|null
	 */
	function label(){
		if (!empty($this->authorName)){
			return $this->authorName;
		}
	}

}
