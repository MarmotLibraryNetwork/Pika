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
 * The primary identifier for a particular record in the database.
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 12/6/13
 * Time: 9:51 AM
 */

class GroupedWorkPrimaryIdentifier extends DB_DataObject{
	public $__table = 'grouped_work_primary_identifiers';    // table name

	public $id;
	public $grouped_work_id;
	public $type;
	public $identifier;


	/**
	 *  Return the SourceAndId object used for handling specific records
	 *
	 * @return SourceAndId|null
	 */
	public function getSourceAndId(){
		if (!empty($this->type) && !empty($this->identifier)){
			require_once ROOT_DIR . '/services/SourceAndId.php';
			return new SourceAndId($this->type . ':' . $this->identifier);
		}
		return null;
	}

}
