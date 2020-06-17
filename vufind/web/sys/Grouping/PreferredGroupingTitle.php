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

class PreferredGroupingTitle extends CommonGroupingAlterationOperations {
	public $__table = 'grouping_titles_preferred';
	public $id;
	public $sourceGroupingTitle;
	public $preferredGroupingTitle;
	public $notes;

	static function getObjectStructure(){
		$structure = [
			[
				'property'    => 'id',
				'type'        => 'hidden',
				'label'       => 'Id',
				'description' => 'The unique id of the preferred grouping title in the database',
				'storeDb'     => true,
				'primaryKey'  => true,
			], [
				'property'         => 'sourceGroupingTitle',
				'type'             => 'text',
				'size'             => 50,
				'maxLength'        => 100,
				'label'            => 'Source Grouping Title',
				'description'      => 'The grouping title that should be replaced.',
				'serverValidation' => 'validateSourceGroupingTitle',
				'storeDb'          => true,
				'required'         => true,
			], [
				'property'         => 'preferredGroupingTitle',
				'type'             => 'text',
				'size'             => 50,
				'maxLength'        => 100,
				'label'            => 'Preferred Grouping Title',
				'description'      => 'The normalized title the variant should be replaced with.',
//				'serverValidation' => 'validateNormalizedTitle',
				'storeDb'          => true,
				'required'         => true,
			], [
				'property'    => 'notes',
				'type'        => 'textarea',
				'size'        => 250,
				'maxLength'   => 250,
				'label'       => 'Notes',
				'description' => 'Notes related to this grouping title entry.',
				'storeDb'     => true,
//				'required'    => true,
			],
		];
		return $structure;
	}

	protected function followUpActions(){
		require_once ROOT_DIR . '/sys/Grouping/GroupedWork.php';
		$groupedWork               = new GroupedWork();
		$groupedWork->full_title = $this->sourceGroupingTitle;
		if ($groupedWork->find()){
			while ($groupedWork->fetch()){
				if (!$groupedWork->forceRegrouping()){
					$this->logger->warn("Error while marking work {$groupedWork->permanent_id} for regrouping for title variant '{$this->sourceGroupingTitle}'");
				}
			}
		}
	}

	function validateSourceGroupingTitle(){
		//Setup validation return array
		$validationResults = [
			'validatedOk' => true,
			'errors'      => [],
		];

			$preferredTitle                         = new PreferredGroupingTitle();
			$preferredTitle->preferredGroupingTitle = $this->sourceGroupingTitle;
			if ($preferredTitle->find()){
				$validationResults = [
					'validatedOk' => false,
					'errors'      => ['The source grouping title can not match any existing preferred grouping title entry.'],
				];
			}

		return $validationResults;
	}

//	function validateNormalizedTitle(){
//		//Setup validation return array
//		$validationResults = [
//			'validatedOk' => true,
//			'errors'      => [],
//		];
//
//		return $validationResults;
//	}

}