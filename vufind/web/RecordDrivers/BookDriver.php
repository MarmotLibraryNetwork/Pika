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
 * Record Driver for display of LargeImages from Islandora
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 12/9/2015
 * Time: 1:47 PM
 */
require_once ROOT_DIR . '/RecordDrivers/CompoundDriver.php';
class BookDriver extends CompoundDriver {

	public function getViewAction() {
		return 'Book';
	}

	public function getFormat() {
		$genre = $this->getModsValue('genre', 'mods');
		if ($genre != null && strlen($genre) > 0){
			return ucfirst($genre);
		}
		return "Book";
	}
}
