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
 * Stores information about a product that has been loaded from the OverDrive APIs
 *
 */


namespace Pika\BibliographicDrivers\OverDrive;


class OverDriveAPIMagazineIssues extends \DB_DataObject
{
	public $__table = 'overdrive_api_magazine_issues';   // table name

	public $id;
	public $overdriveId;
	public $crossRefId;
	public $title;
	public $edition;
	public $pubDate;
	public $coverUrl;
	public $parentId;
	public $dateAdded;
	public $dateUpdated;
	public $description;




}