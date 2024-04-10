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
 *
 *
 * @category Pika
 * @author: Pascal Brammeier
 * Date: 12/14/2016
 *
 */
require_once 'DB/DataObject.php';
require_once ROOT_DIR . '/sys/MaterialsRequest/MaterialsRequest.php';

class MaterialsRequestFormats extends DB_DataObject {
	public $__table = 'materials_request_formats';
	public $id;
	public $libraryId;
	public $format;
	public $formatLabel;
	public $authorLabel;
	public $specialFields;   // SET Data type, possible values : 'Abridged/Unabridged', 'Article Field', 'Eaudio format', 'Ebook format', 'Season'
	public $weight;

	static $materialsRequestFormatsSpecialFieldOptions = [
		'Abridged/Unabridged', 'Article Field', 'Eaudio format', 'Ebook format', 'Season'
	];


	static function getObjectStructure() {
		$structure = [
			'id'            => ['property' => 'id', 'type' => 'label', 'label' => 'Id', 'description' => 'The unique id of this association'],
			'weight'        => ['property' => 'weight', 'type' => 'integer', 'label' => 'Weight', 'description' => 'The sort order of rule', 'default' => 0],
			'format'        => ['property' => 'format', 'type' => 'text', 'label' => 'Format', 'description' => 'internal value for format, please use camelCase and no spaces ie. cdAudio'],
			'formatLabel'   => ['property' => 'formatLabel', 'type' => 'text', 'label' => 'Format Label', 'description' => 'Label for the format that will be displayed to users.'],
			'authorLabel'   => ['property' => 'authorLabel', 'type' => 'text', 'label' => 'Author Label', 'description' => 'Label for the author field associated with this format that will be displayed to users.'],
			'specialFields' => ['property' => 'specialFields', 'type' => 'multiSelect', 'listStyle' => 'checkboxList', 'label' => 'Special Fields for Format', 'description' => 'Any Special Fields to use with this format', 'values' => self::$materialsRequestFormatsSpecialFieldOptions],
			//'libraryId'     => [], // hidden value or internally updated.
		];
		return $structure;
	}

	static function getDefaultMaterialRequestFormats($libraryId = -1) {
		$defaultFormats = [];

		$defaultFormat                = new MaterialsRequestFormats();
		$defaultFormat->libraryId     = $libraryId;
		$defaultFormat->format        = 'book';
		$defaultFormat->formatLabel   = translate('Book');
		$defaultFormat->authorLabel   = 'Author';
		$defaultFormat->specialFields = []; // (Abridged/Unabridged,Article Field,Eaudio format,Ebook format,Season')
		$defaultFormat->weight        = count($defaultFormats) + 1;
		$defaultFormats[]             = $defaultFormat;

		$defaultFormat                = new MaterialsRequestFormats();
		$defaultFormat->libraryId     = $libraryId;
		$defaultFormat->format        = 'largePrint';
		$defaultFormat->formatLabel   = translate('Large Print');
		$defaultFormat->authorLabel   = 'Author';
		$defaultFormat->specialFields = []; // (Abridged/Unabridged,Article Field,Eaudio format,Ebook format,Season')
		$defaultFormat->weight        = count($defaultFormats) + 1;
		$defaultFormats[]             = $defaultFormat;

		$defaultFormat                = new MaterialsRequestFormats();
		$defaultFormat->libraryId     = $libraryId;
		$defaultFormat->format        = 'dvd';
		$defaultFormat->formatLabel   = translate('DVD');
		$defaultFormat->authorLabel   = 'Actor / Director';
		$defaultFormat->specialFields = ['Season']; // (Abridged/Unabridged,Article Field,Eaudio format,Ebook format,Season')
		$defaultFormat->weight        = count($defaultFormats) + 1;
		$defaultFormats[]             = $defaultFormat;

		$defaultFormat                = new MaterialsRequestFormats();
		$defaultFormat->libraryId     = $libraryId;
		$defaultFormat->format        = 'bluray';
		$defaultFormat->formatLabel   = translate('Blu-ray');
		$defaultFormat->authorLabel   = 'Actor / Director';
		$defaultFormat->specialFields = ['Season']; // (Abridged/Unabridged,Article Field,Eaudio format,Ebook format,Season')
		$defaultFormat->weight        = count($defaultFormats) + 1;
		$defaultFormats[]             = $defaultFormat;

		$defaultFormat                = new MaterialsRequestFormats();
		$defaultFormat->libraryId     = $libraryId;
		$defaultFormat->format        = 'cdAudio';
		$defaultFormat->formatLabel   = translate('CD Audio Book');
		$defaultFormat->authorLabel   = 'Author';
		$defaultFormat->specialFields = ['Abridged/Unabridged']; // (Abridged/Unabridged,Article Field,Eaudio format,Ebook format,Season')
		$defaultFormat->weight        = count($defaultFormats) + 1;
		$defaultFormats[]             = $defaultFormat;

		$defaultFormat                = new MaterialsRequestFormats();
		$defaultFormat->libraryId     = $libraryId;
		$defaultFormat->format        = 'cdMusic';
		$defaultFormat->formatLabel   = translate('Music CD');
		$defaultFormat->authorLabel   = 'Artist / Composer';
		$defaultFormat->specialFields = []; // (Abridged/Unabridged,Article Field,Eaudio format,Ebook format,Season')
		$defaultFormat->weight        = count($defaultFormats) + 1;
		$defaultFormats[]             = $defaultFormat;

		$defaultFormat                = new MaterialsRequestFormats();
		$defaultFormat->libraryId     = $libraryId;
		$defaultFormat->format        = 'ebook';
		$defaultFormat->formatLabel   = translate('eBook');
		$defaultFormat->authorLabel   = 'Author';
		$defaultFormat->specialFields = ['Ebook format']; // (Abridged/Unabridged,Article Field,Eaudio format,Ebook format,Season')
		$defaultFormat->weight        = count($defaultFormats) + 1;
		$defaultFormats[]             = $defaultFormat;

		$defaultFormat                = new MaterialsRequestFormats();
		$defaultFormat->libraryId     = $libraryId;
		$defaultFormat->format        = 'eaudio';
		$defaultFormat->formatLabel   = translate('eAudio');
		$defaultFormat->authorLabel   = 'Author';
		$defaultFormat->specialFields = ['Eaudio format', 'Abridged/Unabridged']; // (Abridged/Unabridged,Article Field,Eaudio format,Ebook format,Season')
		$defaultFormat->weight        = count($defaultFormats) + 1;
		$defaultFormats[]             = $defaultFormat;

		$defaultFormat                = new MaterialsRequestFormats();
		$defaultFormat->libraryId     = $libraryId;
		$defaultFormat->format        = 'playaway';
		$defaultFormat->formatLabel   = translate('Playaway');
		$defaultFormat->authorLabel   = 'Author';
		$defaultFormat->specialFields = ['Abridged/Unabridged']; // (Abridged/Unabridged,Article Field,Eaudio format,Ebook format,Season')
		$defaultFormat->weight        = count($defaultFormats) + 1;
		$defaultFormats[]             = $defaultFormat;

		$defaultFormat                = new MaterialsRequestFormats();
		$defaultFormat->libraryId     = $libraryId;
		$defaultFormat->format        = 'article';
		$defaultFormat->formatLabel   = translate('Article');
		$defaultFormat->authorLabel   = 'Author';
		$defaultFormat->specialFields = ['Article Field']; // (Abridged/Unabridged,Article Field,Eaudio format,Ebook format,Season')
		$defaultFormat->weight        = count($defaultFormats) + 1;
		$defaultFormats[]             = $defaultFormat;

		$defaultFormat                = new MaterialsRequestFormats();
		$defaultFormat->libraryId     = $libraryId;
		$defaultFormat->format        = 'cassette';
		$defaultFormat->formatLabel   = translate('Cassette');
		$defaultFormat->authorLabel   = 'Artist / Composer';
		$defaultFormat->specialFields = ['Abridged/Unabridged']; // (Abridged/Unabridged,Article Field,Eaudio format,Ebook format,Season')
		$defaultFormat->weight        = count($defaultFormats) + 1;
		$defaultFormats[]             = $defaultFormat;

		$defaultFormat                = new MaterialsRequestFormats();
		$defaultFormat->libraryId     = $libraryId;
		$defaultFormat->format        = 'vhs';
		$defaultFormat->formatLabel   = translate('VHS');
		$defaultFormat->authorLabel   = 'Actor / Director';
		$defaultFormat->specialFields = ['Season']; // (Abridged/Unabridged,Article Field,Eaudio format,Ebook format,Season')
		$defaultFormat->weight        = count($defaultFormats) + 1;
		$defaultFormats[]             = $defaultFormat;

		$defaultFormat                = new MaterialsRequestFormats();
		$defaultFormat->libraryId     = $libraryId;
		$defaultFormat->format        = 'other';
		$defaultFormat->formatLabel   = 'Other';
		$defaultFormat->authorLabel   = 'Author';
		$defaultFormat->specialFields = []; // (Abridged/Unabridged,Article Field,Eaudio format,Ebook format,Season')
		$defaultFormat->weight        = count($defaultFormats) + 1;
		$defaultFormats[]             = $defaultFormat;


//		$defaultFormat                = new MaterialsRequestFormats();
//		$defaultFormat->libraryId     = $libraryId;
//		$defaultFormat->format        = '';
//		$defaultFormat->formatLabel   = '';
//		$defaultFormat->authorLabel   = 'Author';
//		$defaultFormat->specialFields = []; // (Abridged/Unabridged,Article Field,Eaudio format,Ebook format,Season')
//		$defaultFormat->weight        = count($defaultFormats) + 1;
//		$defaultFormats[]             = $defaultFormat;

		return $defaultFormats;
	}


	static function getAuthorLabelsAndSpecialFields($libraryId) {
		// Format Labels
		$formats             = new self();
		$formats->libraryId  = $libraryId;
		$usingDefaultFormats = $formats->count() == 0;

		// Get Author Labels for all Formats
		$specialFieldFormats = $formatAuthorLabels = [];
		if ($usingDefaultFormats) {
			$defaultFormats = self::getDefaultMaterialRequestFormats();
			/** @var MaterialsRequestFormats $format */
			foreach ($defaultFormats as $format) {
				// Gather default Author Labels and default special Fields
				$formatAuthorLabels[$format->format] = $format->authorLabel;
				if (!empty($format->specialFields)) {
					$specialFieldFormats[$format->format] = $format->specialFields;
				}
			}

		} else {
			$formatAuthorLabels = $formats->fetchAll('format', 'authorLabel');

			// Get Formats that use Special Fields
			$formats            = new self();
			$formats->libraryId = $libraryId;
			$formats->whereAdd('`specialFields` IS NOT NULL');
			$specialFieldFormats = $formats->fetchAll('format', 'specialFields');
		}

		return array($formatAuthorLabels, $specialFieldFormats);
	}

	public function fetch(){
		$return = parent::fetch();
		if ($return) {
				$this->specialFields = empty($this->specialFields) ? null : explode(',', $this->specialFields);
		}
		return $return;
	}

	public function insert() {
		if (is_array($this->specialFields)) {
			$this->specialFields = implode(',', $this->specialFields);
		}else{
			$this->specialFields = '';
		}
		return parent::insert();
	}

	public function update($dataObject = false) {
		if (is_array($this->specialFields)) {
			$this->specialFields = implode(',', $this->specialFields);
		}else{
			$this->specialFields = '';
		}
		$previous = new self();
		if ($previous->get($this->id)) {
			if ($this->format != $previous->format) {
				// Format value has changed; update all related materials requests
				$materialRequest = new MaterialsRequest();
				$materialRequest->format = $previous->format;
				$materialRequest->libraryId = $this->libraryId;
				if ($materialRequest->count() > 0){


					$materialRequest = new MaterialsRequest();
					$materialRequest->format = $this->format;
					$materialRequest->whereAdd("`libraryId` = {$this->libraryId} AND `format`='{$previous->format}'");

					if ($materialRequest->update(DB_DATAOBJECT_WHEREADD_ONLY)) {
						return parent::update($dataObject);

					}
				} else {
					return parent::update($dataObject);
				}
			} else {
				return parent::update($dataObject);
			}
		}
	return false;
	}

	/**
	 * Deletes items from table which match current objects variables
	 *
	 * Returns the true on success
	 *
	 * for example
	 *
	 * Designed to be extended
	 *
	 * $object = new mytable();
	 * $object->ID=123;
	 * echo $object->delete(); // builds a conditon
	 *
	 * $object = new mytable();
	 * $object->whereAdd('age > 12');
	 * $object->limit(1);
	 * $object->orderBy('age DESC');
	 * $object->delete(true); // dont use object vars, use the conditions, limit and order.
	 *
	 * @param bool $useWhere (optional) If DB_DATAOBJECT_WHEREADD_ONLY is passed in then
	 *             we will build the condition only using the whereAdd's.  Default is to
	 *             build the condition only using the object parameters.
	 *
	 * @access public
	 * @return mixed Int (No. of rows affected) on success, false on failure, 0 on no data affected
	 */
	function delete($useWhere = false){
		$materialRequest            = new MaterialsRequest();
		$materialRequest->format    = $this->format;
		$materialRequest->libraryId = $this->libraryId;
		if ($materialRequest->count() == 0){
			return parent::delete($useWhere);
		}
		return false;

	}

	public function hasSpecialFieldOption($option){
		return is_array($this->specialFields) && in_array($option, $this->specialFields);
	}

}
