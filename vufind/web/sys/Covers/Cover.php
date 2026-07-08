<?php
/*
 * Pika Discovery Layer
 * Copyright (C) 2026  Marmot Library Network
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

require_once 'DB/DataObject.php';

class Cover extends DB_DataObject {
	public $__table = 'covers';
	public $coverId;
	public $fileName;
	public $modified;
	public $cover;


	protected $data;
	private $logger;
	//private $cache;
	private $storagePath;

	public function __construct(){
		global $configArray;
		//$this->cache       = new Pika\Cache();
		$this->logger      = new Pika\Logger(__CLASS__);
		if (empty($configArray['Site']['coverPath'])){
			$this->logger->error('Fatal: no coverPath set in config.ini settings. Can\'t access custom cover directory');
		}
		$this->storagePath = $configArray['Site']['coverPath'] . DIRECTORY_SEPARATOR . 'original' . DIRECTORY_SEPARATOR;
	}

	function keys(){
		return ['coverId'];
	}

	function getKeyOther(){
		return 'coverId';
	}

	public static function getObjectStructure(){
		global $configArray;
		$storagePath = $configArray['Site']['coverPath'];
		$structure   = [
			'coverId'  => ['property' => 'coverId', 'type' => 'label', 'customName' => true, 'label' => 'id', 'description' => 'The unique id of the cover within the database'],
			'cover'    => ['property' => 'cover', 'type' => 'image', 'storagePath' => $storagePath, 'customName' => true, 'label' => 'Cover Image', 'description' => 'Image of the cover.', 'required' => true],
			'modified' => ['property' => 'modified', 'type' => 'dateReadOnly', 'customName' => true, 'label' => 'Updated', 'format' => 'Y-m-d', 'description' => 'The date when the image was last updated.'],
			//            'fileName'  => array('property'=>'fileName', 'type'=>'text', 'maxLength'=>100, 'label'=>'File Name ', 'description'=>'Name of the file'),
		];
		return $structure;
	}

	function getImageUrl($size = 'medium'){
		return $this->cover ? '/customcover.php?image=' . $this->cover . '&size=' . $size : 'interface/themes/default/images/noCover2.png';
	}

	function delete($useWhere = false, $noDelete = false){
		if (!$noDelete){
			$coverPath = $this->storagePath . $this->cover;
			unlink($coverPath);
		}
		parent::delete($useWhere);
	}

	function update($dataObject = false){
		$coverPath = $this->storagePath . $this->cover;
		if (isset($_REQUEST['fileName'])){
			$extension   = pathinfo($this->cover, PATHINFO_EXTENSION);
			$newFileName = trim($_REQUEST['fileName']);
			if (!strpos($newFileName, $extension)){
				// Add extension to file name if not present
				$newFileName = $newFileName . '.' . $extension;
			}
			if ($newFileName != $this->cover){
				rename($coverPath, $this->storagePath . $newFileName);
			}
			$this->cover = $newFileName;
		}
		$this->modified = time();

		parent::update($dataObject);
	}

	function insert(){
		$coverPath      = $this->storagePath . $this->cover;
		$this->modified = time();
		if (file_exists($coverPath)){
			$newCover       = clone $this;
			$duplicateCover = new Cover();
			$duplicateCover->get('cover', $newCover->cover);
			$duplicateCover->delete(false, true);
			$this->cover = $duplicateCover->cover;
		}
		return parent::insert();
	}

	// Used to populate table during database update process See 2026.01.0_update_covers_table
	function setModifiedDate(){
		$fullPath = $this->storagePath . $this->cover;
		if (file_exists($fullPath) && !empty($this->cover)){
			$modified       = filemtime($fullPath);
			$this->modified = $modified;
			$this->update();
			$verifyTime           = new Cover();
			$verifyTime->coverId  = $this->coverId;
			$verifyTime->modified = $modified;
			if ($verifyTime->find()){
				return true;
			}else{
				return false;
			}
		}
	}
}