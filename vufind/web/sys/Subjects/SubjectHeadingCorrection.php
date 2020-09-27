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
 *
 *
 * @category Pika
 * @author: C.J. O'Hara
 * Date: 9/23/2020
 *
 */
require_once 'DB/DataObject.php';

class SubjectHeadingCorrection extends DB_DataObject
{
    public $__table = 'library_subject_heading_correction';
    public $replacementId;
    public $subjectFrom;
    public $subjectTo;
    public $libraryId;

    protected $data;
    private $logger;
    private $cache;

    public function __construct(){
        $this->cache  = new Pika\Cache();
        $this->logger = new Pika\Logger('Subject Heading Correction');
    }

    function keys(){
        return array('replacementId');
    }
    function getKeyOther(){
        return array('libraryId');
    }


    static function getObjectStructure(){
        $library = new Library();
        $library->orderBy('displayName');
        if (UserAccount::userHasRoleFromList(['libraryAdmin', 'libraryManager'])){
            $homeLibrary = UserAccount::getUserHomeLibrary();
            $library->libraryId = $homeLibrary->libraryId;
        }


        return array(
            'libraryId'     => array('property' => 'libraryId', 'value' => $library->libraryId, 'type'=>'int', 'label'=>'Library', 'description'=>'', 'hideInLists'=>true),
            'replacementId'   =>  array('property' => 'replacementId', 'type' => 'label', 'label' => 'Replacement Id', 'description' => 'The unique id of the replacement within the database', 'hideInLists'=>true),
            'subjectFrom'     =>  array('property' => 'subjectFrom', 'type' => 'text',	'label' => 'Original Subject', 'description' => 'Original subject that needs to be corrected or replaced.'),
            'subjectTo'     =>  array('property' => 'subjectTo', 'type' => 'text',	'label' => 'New Subject', 'description' => 'Replacement subject that corrects existing subject')
        );

    }

    public function getRegexes()
    {
        $user = UserAccount::getLoggedInUser();
        if(UserAccount::userHasRoleFromList(array('libraryAdmin', 'opacAdmin')))
        {
            $userLibrary = $user->getHomeLibrary();
            $libraryId = $userLibrary->libraryId;

            $subjectHeading = new SubjectHeadingCorrection();
            $subjectHeading->orderBy('replacementId');
            $subjectHeading->libraryId = $libraryId;
            $subjectHeading->find();
            $regexes = array();
            while($subjectHeading->fetch())
            {
                $from = '/^' . $subjectHeading->subjectFrom . '(.*)/' ;
                $to = $subjectHeading->subjectTo . '$1';
                $regexes[$from] = $to;
            }
            return $regexes;
        }
    }

}