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
 * Description goes here
 *
 * @category VuFind-Plus 
 * @author Mark Noble <mark@marmot.org>
 * Date: 12/31/13
 * Time: 11:00 AM
 */

class OverDriveAPIProductFormats extends DB_DataObject {
	public $__table = 'overdrive_api_product_formats';   // table name

	public $id;
	public $productId;
	public $textId;
	public $numericId;
	public $name;
	public $fileName;
	public $fileSize;
	public $partCount;
	public $sampleSource_1;
	public $sampleUrl_1;
	public $sampleSource_2;
	public $sampleUrl_2;

	function getFormatNotes(){
		$notes = '';

		if ($this->textId == 'audiobook-mp3'){
			$notes = "Works on MP3 Players, PCs, and Macs. Some mobile devices may require an application to be installed.";
		}else if ($this->textId == 'audiobook-wma'){
			$notes = "Works on Windows PCs and some devices that can be connected to a Windows PC.";
		}else if ($this->textId == 'video-wmv'){
			$notes = "Works on Windows PCs and some devices that can be connected to a Windows PC.";
		}else if ($this->textId == 'music-wma'){
			$notes = "Works on Windows PCs and some devices that can be connected to a Windows PC.";
		}else if ($this->textId == 'ebook-kindle'){
			$notes = "Works on Kindles and devices with a Kindle app installed.";
		}else if ($this->textId == 'ebook-epub-adobe'){
			$notes = "Works on all eReaders (except Kindles), desktop computers and mobile devices with with reading apps installed.";
		}else if ($this->textId == 'ebook-pdf-adobe'){

		}else if ($this->textId == 'ebook-epub-open'){
			$notes = "Works on all eReaders (except Kindles), desktop computers and mobile devices with with reading apps installed.";
		}else if ($this->textId == 'ebook-pdf-open') {
		}else if ($this->textId == 'periodicals-nook'){
			$notes = "Works on NOOK devices and all devices with a NOOK app installed.";

		}else{

		}
		return $notes;
	}
} 
