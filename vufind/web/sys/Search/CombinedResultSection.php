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
 * Created by PhpStorm.
 * User: mnoble
 * Date: 11/17/2017
 * Time: 4:00 PM
 */

abstract class CombinedResultSection extends DB_DataObject{
	public $id;
	public $displayName;
	public $weight;
	public $source;
	public $numberOfResultsToShow;

	static function getObjectStructure(){
		global $configArray;
		$validResultSources = [];
		if (!empty($configArray['Islandora']['repositoryUrl'])){
			$validResultSources['archive'] = 'Digital Archive';
		}
		if ($configArray['DPLA']['enabled']){
			$validResultSources['dpla'] = 'DPLA';
		}
		$validResultSources['eds']  = 'EBSCO EDS';
		$validResultSources['pika'] = 'Pika Results';
		if ($configArray['Content']['Prospector']){
			$validResultSources['prospector'] = 'Prospector';
		}

		$structure = [
			'id'                    => ['property'    => 'id',
			                            'type'        => 'label',
			                            'label'       => 'Id',
			                            'description' => 'The unique id of this section'],
			'weight'                => ['property'    => 'weight',
			                            'type'        => 'integer',
			                            'label'       => 'Weight',
			                            'description' => 'The sort order of the section',
			                            'default'     => 0],
			'displayName'           => ['property'    => 'displayName',
			                            'type'        => 'text',
			                            'label'       => 'Display Name',
			                            'description' => 'The full name of the section for display to the user',
			                            'maxLength'   => 255],
			'numberOfResultsToShow' => ['property'    => 'numberOfResultsToShow',
			                            'type'        => 'integer',
			                            'label'       => 'Num Results',
			                            'description' => 'The number of results to show in the box.',
			                            'default'     => '5'],
			'source'                => ['property'    => 'source',
			                            'type'        => 'enum',
			                            'label'       => 'Source',
			                            'values'      => $validResultSources,
			                            'description' => 'The source of results in the section.',
			                            'default'     => 'pika'],
		];
		return $structure;
	}

	function getResultsLink($searchTerm, $searchType){
		switch ($this->source){
			case 'pika':
				return "/Search/Results?lookfor=$searchTerm&basicType=$searchType&searchSource=local";
			case 'prospector':
				require_once ROOT_DIR . '/sys/InterLibraryLoanDrivers/Prospector.php';
				$prospector = new Prospector();
				$search     = [['lookfor' => $searchTerm, 'index' => $searchType]];
				return $prospector->getSearchLink($search);
			case 'dpla' :
				return "https://dp.la/search?q=$searchTerm";
			case 'eds' :
				global $library;
				return "https://search.ebscohost.com/login.aspx?direct=true&site=eds-live&scope=site&type=1&custid={$library->edsApiUsername}&groupid=main&profid={$library->edsSearchProfile}&mode=bool&lang=en&authtype=cookie,ip,guest&bquery=$searchTerm";
			case 'archive' :
				return "/Archive/Results?lookfor=$searchTerm&basicType=$searchType";
			default :
				return '';
		}
	}

}
