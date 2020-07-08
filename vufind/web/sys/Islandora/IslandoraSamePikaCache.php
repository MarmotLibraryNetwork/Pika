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
 * Date: 10/24/2017
 * Time: 9:36 PM
 */
class IslandoraSamePikaCache extends DB_DataObject {
	public $__table = 'islandora_samepika_cache';
	public $id;
	public $groupedWorkPermanentId;
	public $pid;
	public $archiveLink;
}
