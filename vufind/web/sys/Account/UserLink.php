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
 * Contains information needed to link to accounts
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 7/21/2015
 * Time: 3:44 PM
 */

class UserLink extends DB_DataObject{
	public $id;
	public $primaryAccountId;
	public $linkedAccountId;

	public $__table = 'user_link';    // table name

}
