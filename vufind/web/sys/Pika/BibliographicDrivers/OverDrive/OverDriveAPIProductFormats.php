<?php
/*
 * Copyright (C) 2020  Marmot Library Network
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * Data Object for OverDrive Formats associated with a title
 *
 */

namespace Pika\BibliographicDrivers\OverDrive;

class OverDriveAPIProductFormats extends \DB_DataObject {
	public $__table = 'overdrive_api_product_formats';   // table name

	public $id;
	public $productId;
	public $textId;
	public $name;
	public $fileName;
	public $fileSize;
	public $partCount;
	public $sampleSource_1;
	public $sampleUrl_1;
	public $sampleSource_2;
	public $sampleUrl_2;

	private const FORMAT_CLASS = [
		'audiobook' => 'Audiobook',
		'ebook'     => 'eBook',
		'video'     => 'Video',
	];

	function getFormatNotes(){
		switch ($this->textId){
			case 'audiobook-mp3' :
				return "Works on MP3 Players, PCs, and Macs. Some mobile devices may require an application to be installed.";
//			case 'music-wma':
//			case 'video-wmv':
			case 'audiobook-wma' :
				return "Works on Windows PCs and some devices that can be connected to a Windows PC.";
			case 'ebook-kindle' :
				return "Works on Kindles and devices with a Kindle app installed.";
			case 'ebook-epub-open':
			case 'ebook-epub-adobe' :
				return "Works on all eReaders (except Kindles), desktop computers and mobile devices with reading apps installed.";
			case 'ebook-mediado':
				return 'MediaDo Reader is a browser-based ebook reader designed to read graphic novels and right-to-left or top-to-bottom content.';

//			case 'periodicals-nook' :
//				return "Works on NOOK devices and all devices with a NOOK app installed.";
			default :
				return '';
		}
	}

	/**
	 * Get the FormatClass for a specific format, which is used to associate lending period options to the format
	 *
	 * @param string|null $formatType optional OverDrive format to get formatClass for
	 * @return string The formatClass for the specific text-id
	 */
	function getFormatClass($formatType = null){
		$str = strtok($formatType ?? $this->textId, '-');
		return in_array($str, array_keys(self::FORMAT_CLASS)) ? self::FORMAT_CLASS[$str] : null;
	}
}
