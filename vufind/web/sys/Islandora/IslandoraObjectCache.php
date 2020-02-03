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
 * Stores information about Islandora objects for improved response times
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 5/18/2016
 * Time: 10:48 AM
 */
class IslandoraObjectCache  extends DB_DataObject{
	public $__table = 'islandora_object_cache';
	public $id;
	public $pid;
	public $driverName;
	public $driverPath;
	public $title;
	public $hasLatLong;
	public $latitude;
	public $longitude;
	public $lastUpdate;

	public $smallCoverUrl;
	public $mediumCoverUrl;
	public $largeCoverUrl;
}
