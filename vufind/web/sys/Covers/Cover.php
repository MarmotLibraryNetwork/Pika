<?php

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
        return Array('coverId');
    }

    function getKeyOther(){
        return 'coverId';
    }

    function getObjectStructure(){
        global $configArray;
        $storagePath = $configArray['Site']['coverPath'];
        $structure = array(

            'coverId'   =>  array('property' => 'coverId', 'type' => 'label', 'label' => 'Cover Id', 'description' => 'The unique id of the cover within the database', 'hideInLists'=>true),
            'cover'     =>  array('property' => 'cover', 'type' => 'image',	'storagePath' => $storagePath, 'thumbWidth' => 65, 'mediumWidth' => 190, 'customName' => true, 'label' => 'Cover Image', 'description' => 'Image of the cover.'),
//            'fileName'  => array('property'=>'fileName', 'type'=>'text', 'maxLength'=>100, 'label'=>'File Name ', 'description'=>'Name of the file'),
        );
        return $structure;
    }
    function getImageUrl($size = 'medium'){
        return $this->cover ? '/customcover.php?image=' . $this->cover . '&size=' . $size: 'interface/themes/default/images/noCover2.png';
    }
}


