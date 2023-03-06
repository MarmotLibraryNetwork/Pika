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
 * Date: 7/18/2022
 *
 */

require_once ROOT_DIR . '/sys/SearchObject/UserListSearchObjectTrait.php';

class SearchObject_UserListIslandora extends SearchObject_Islandora {

	use UserListSearchObjectTrait;

	protected function getBaseUrl(){
		return $this->serverUrl . '/MyAccount/MyList/' . urlencode($_GET['id']) . '?';
	}

}