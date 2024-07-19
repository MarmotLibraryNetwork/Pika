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
 * @author   : Pascal Brammeier
 * Date: 4/24/2018
 *
 */

require_once 'DB/DataObject.php';

class HooplaExtract extends DB_DataObject {

	public $__table = 'hoopla_export';

	public $id;
	public $hooplaId;
//	public $titleId;
	public bool $active;
	public $title;
	public $language;
	public $kind;
	public $series;
	public $season;
	public $publisher;
	public float $price;
	public bool $children;
	public bool $pa;  //Parental Advisory
	public bool $profanity; // boolean
	public $rating; // eg TV parental guidance rating
	public $abridged;
	public $demo;
	public $duration;
	public bool $fiction; // boolean
	public $purchaseModel;
	public $dateLastUpdated;

//	public $titleTitle;
//	public $synopsis;
//	public $year; // publication year
//	public $genres; //TODO It's own table
//	public $artist;
//	public $artists; // TODO table; name, relationship, artistFormal
//	public $pages;



}
