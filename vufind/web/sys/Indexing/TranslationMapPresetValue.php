<?php
/*
 * Pika Discovery Layer
 * Copyright (C) 2026  Marmot Library Network
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

require_once ROOT_DIR . '/sys/Indexing/TranslationMapValue.php';

/**
 * TranslationMapValue subclass that restricts the translation field to a
 * dropdown of preset values for specific translation maps.
 */
class TranslationMapPresetValue extends TranslationMapValue {

	private static $activePreset = null;

	private static $presets = [
		'grouping_categories' => [
			'book'  => 'book',
			'movie' => 'movie',
			'music' => 'music',
			'comic' => 'comic',
			'young' => 'young',
		],
		'format_category' => [
			''            => '',
			'Books'       => 'Books',
			'Audio Books' => 'Audio Books',
			'eBook'       => 'eBook',
			'Movies'      => 'Movies',
			'Music'       => 'Music',
			'Video Games' => 'Video Games',
		],
		'item_grouped_status' => [
			'Currently Unavailable' => 'Currently Unavailable',
			'Available to Order'    => 'Available to Order',
			'On Order'              => 'On Order',
			'Coming Soon'           => 'Coming Soon',
			'In Processing'         => 'In Processing',
			'Checked Out'           => 'Checked Out',
			'Available Externally'  => 'Available Externally',
			'Available by Request'  => 'Available by Request',
			'Shelving'              => 'Shelving',
			'Recently Returned'     => 'Recently Returned',
			'Library Use Only'      => 'Library Use Only',
			'Available Online'      => 'Available Online',
			'In Transit'            => 'In Transit',
			'On Display'            => 'On Display',
			'On Shelf'              => 'On Shelf',
		],
	];

	static function setActivePreset($mapName){
		self::$activePreset = $mapName;
	}

	static function getPresetNames(){
		return array_keys(self::$presets);
	}

	static function hasPreset($mapName){
		return isset(self::$presets[$mapName]);
	}

	static function getObjectStructure(){
		$structure = parent::getObjectStructure();

		if (self::$activePreset && isset(self::$presets[self::$activePreset])){
			$structure['translation'] = [
				'property'    => 'translation',
				'type'        => 'enum',
				'values'      => self::$presets[self::$activePreset],
				'label'       => 'Translation',
				'description' => 'The translated value',
				'required'    => false,
			];
		}

		return $structure;
	}
}
