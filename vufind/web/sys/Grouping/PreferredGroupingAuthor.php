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
 * @author: Pascal Brammeier
 * Date: 6/11/2020
 *
 */

require_once ROOT_DIR . '/sys/Grouping/CommonGroupingAlterationOperations.php';

class PreferredGroupingAuthor extends CommonGroupingAlterationOperations {
	public $__table = 'grouping_authors_preferred';
	public $id;
	public $normalizedAuthorVariant;
	public $preferredNormalizedAuthor;
	public $notes;

	static function getObjectStructure(){
		$structure = [
			[
				'property'    => 'id',
				'type'        => 'hidden',
				'label'       => 'Id',
				'description' => 'The unique id of the preferred grouping author in the database',
				'storeDb'     => true,
				'primaryKey'  => true,
			], [
				'property'    => 'normalizedAuthorVariant',
				'type'        => 'text',
				'size'        => 25,
				'maxLength'   => 50,
				'label'       => 'Normalized Author Variant',
				'description' => 'The grouping author that should be replaced.',
				'storeDb'     => true,
				'required'    => true,
			], [
				'property'    => 'preferredNormalizedAuthor',
				'type'        => 'text',
				'size'        => 25,
				'maxLength'   => 50,
				'label'       => 'Preferred Normalized Author',
				'description' => 'The normalized author the variant should be replaced with.',
				'storeDb'     => true,
				'required'    => true,
			], [
				'property'    => 'notes',
				'type'        => 'textarea',
				'size'        => 250,
				'maxLength'   => 250,
				'label'       => 'Notes',
				'description' => 'Notes related to this grouping author entry.',
				'storeDb'     => true,
			],
		];
		return $structure;
	}

	protected function followUpActions(){
		// TODO: Implement followUpActions() method.
	}
}