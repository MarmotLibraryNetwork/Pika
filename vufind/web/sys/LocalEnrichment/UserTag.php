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
 * Tags that have been added to works
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 3/20/14
 * Time: 10:47 AM
 */

class UserTag extends DB_DataObject {
	public $__table = 'user_tags';                            // table name
	public $id;
	public $tag;
	public $groupedRecordPermanentId;
	public $userId;
	public $dateTagged;

	//A count of the number of times the tag has been added to the work
	public $cnt;
	public $userAddedThis;
}
