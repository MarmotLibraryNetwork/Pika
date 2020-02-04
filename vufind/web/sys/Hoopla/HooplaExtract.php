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
 *
 *
 * @category Pika
 * @author   : Pascal Brammeier
 * Date: 4/24/2018
 *
 */

require_once 'DB/DataObject.php';

class HooplaExtract extends DB_DataObject {

	public $id;
	public $hooplaId;
	public $active;
	public $title;
	public $kind;
	public $price;
	public $children;
	public $pa;  //Parental Advisory
	public $profanity;
	public $rating; // eg TV parental guidance rating
	public $abridged;
	public $demo;
	public $dateLastUpdated;

	public $__table = 'hoopla_export';

}
