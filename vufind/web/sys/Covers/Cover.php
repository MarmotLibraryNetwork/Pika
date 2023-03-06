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

require_once 'DB/DataObject.php';

class Cover extends DB_DataObject
{
    public $__table = 'covers';
    public $coverId;
    public $fileName;
    public $cover;


    protected $data;
    private $logger;
    private $cache;

    public function __construct(){
        $this->cache  = new Pika\Cache();
        $this->logger = new Pika\Logger('Cover');
    }

    function keys(){
        return array('coverId');
    }

    function getKeyOther(){
        return 'coverId';
    }

    function getObjectStructure(){
        global $configArray;
        $storagePath = $configArray['Site']['coverPath'];
        $structure = array(

            'coverId'   =>  array('property' => 'coverId', 'type' => 'label', 'label' => 'Cover Id', 'description' => 'The unique id of the cover within the database', 'hideInLists'=>true),
            'cover'     =>  array('property' => 'cover', 'type' => 'image',	'storagePath' => $storagePath, 'customName' => true, 'label' => 'Cover Image', 'description' => 'Image of the cover.'),
//            'fileName'  => array('property'=>'fileName', 'type'=>'text', 'maxLength'=>100, 'label'=>'File Name ', 'description'=>'Name of the file'),
        );
        return $structure;
    }
    function getImageUrl($size = 'medium'){
        return $this->cover ? '/customcover.php?image=' . $this->cover . '&size=' . $size: 'interface/themes/default/images/noCover2.png';
    }

    function delete($useWhere = false, $noDelete = false){
        if(!$noDelete) {
            global
            $configArray;
            $storagePath = $configArray['Site']['coverPath'];
            $coverPath = $storagePath . DIRECTORY_SEPARATOR . "original" . DIRECTORY_SEPARATOR . $this->cover;
            unlink($coverPath);
        }
        parent::delete($useWhere);
    }

    function update($dataObject = false)
    {
        global $configArray;
        $storagePath = $configArray['Site']['coverPath'];
        $coverPath = $storagePath . DIRECTORY_SEPARATOR . "original" . DIRECTORY_SEPARATOR . $this->cover;

            if(isset($_REQUEST['fileName']))
            {
                $extension = pathinfo($this->cover, PATHINFO_EXTENSION);

                $newFileName = trim($_REQUEST['fileName']);
                if(strpos($newFileName,$extension) == false)
                {
                    $newFileName = $newFileName . "." . $extension;
                }
                if($newFileName != $this->cover) {
                    rename($coverPath, $storagePath . DIRECTORY_SEPARATOR . "original" . DIRECTORY_SEPARATOR . $newFileName);
                }
                $this->cover = $newFileName;
            }


        parent::update($dataObject);
    }

    function insert()
    {
        global $configArray;
        $storagePath = $configArray['Site']['coverPath'];
        $coverPath = $storagePath . DIRECTORY_SEPARATOR . "original" . DIRECTORY_SEPARATOR . $this->cover;
        if(file_exists($coverPath))
        {
            $newCover = clone $this;
            $duplicateCover = new Cover();
            $duplicateCover->get('cover', $newCover->cover);
            $duplicateCover->delete(false,true);


        }
        return parent::insert();
    }
}


