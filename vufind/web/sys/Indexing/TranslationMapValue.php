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
 * The actual values for a Translation Map
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 6/30/2015
 * Time: 1:44 PM
 */
class TranslationMapValue extends DB_DataObject {
	public $__table = 'translation_map_values';    // table name
	public $id;
	public $translationMapId;
	public $value;
	public $translation;

	static function getObjectStructure(){
		$structure = [
			'id'               => ['property' => 'id', 'type' => 'label', 'label' => 'Id', 'description' => 'The unique id within the database'],
			'translationMapId' => ['property' => 'translationMapId', 'type' => 'foreignKey', 'label' => 'Translation Map Id', 'description' => 'The Translation Map this is associated with'],
			'value'            => ['property' => 'value', 'type' => 'text', 'label' => 'Value', 'description' => 'The value to be translated', 'maxLength' => '50', 'required' => true],
			'translation'      => ['property' => 'translation', 'type' => 'text', 'label' => 'Translation', 'description' => 'The translated value', 'maxLength' => '255', 'required' => false],
		];
		return $structure;
	}
}
