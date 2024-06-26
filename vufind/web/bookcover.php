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

require_once 'bootstrap.php';
require_once ROOT_DIR . '/sys/Covers/BookCoverProcessor.php';

//Create class to handle processing of covers
$processor = new BookCoverProcessor();
$processor->loadCover($configArray, $timer, $pikaLogger->withName('BookCoverProcessor'));
if ($processor->error){
	header('Content-type: text/plain'); //Use for debugging notices and warnings
	$pikaLogger->withName('BookCoverProcessor')->error("Error processing cover " . $processor->error);
	echo $processor->error;
}
