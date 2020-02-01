<?php
/**
 * Table Definition for Editorial Reviews
 */
require_once 'DB/DataObject.php';

class EditorialReview extends DB_DataObject {
	public $__table = 'editorial_reviews';    // table name
	public $editorialReviewId;
	public $groupedWorkPermanentId;
	public $title;
	public $review;
	public $source;
	public $pubDate;

	function keys(){
		return array('editorialReviewId');
	}

	static function getObjectStructure(){
		return array(
			array(
				'property'    => 'editorialReviewId',
				'type'        => 'hidden',
				'label'       => 'Id',
				'description' => 'The unique id of the editorial review in the database',
				'storeDb'     => true,
				'primaryKey'  => true,
			),
			array(
				'property'    => 'title',
				'type'        => 'text',
				'size'        => 100,
				'maxLength'   => 100,
				'label'       => 'Review Title',
				'description' => 'The title of the review is required.',
				'storeDb'     => true,
				'required'    => true,
			),
			'groupedWorkPermanentId' => array(
				'property'         => 'groupedWorkPermanentId',
				'type'             => 'text',
				'size'             => 36,
				'maxLength'        => 36,
				'label'            => 'Grouped Work Id of the Title',
				'description'      => 'Grouped work id of the title the review is about.',
				'serverValidation' => 'validateGroupedWork',
				'storeDb'          => true,
				'required'         => true,
			),
			array(
				'property'      => 'review',
				'type'          => 'html',
				'allowableTags' => '<p><a><b><em><ul><ol><em><li><strong><i><br><iframe><div>',
				'rows'          => 6,
				'cols'          => 80,
				'label'         => 'Review Text',
				'description'   => 'Review text. (HTML and embedded iframes allowed)',
				'storeDb'       => true,
				'hideInLists'   => true,
				'required'      => true,
			),
			array(
				'property'    => 'source',
				'type'        => 'text',
				'size'        => 25,
				'maxLength'   => 25,
				'label'       => 'Review Source',
				'description' => 'Source of this Review.',
				'storeDb'     => true,
			),
			array(
				'property'    => 'pubDate',
				'type'        => 'hidden',
				'label'       => 'Review Publication Date',
				'description' => 'Review Publication Date',
				'storeDb'     => true,
			),
		);
	}

	function validateGroupedWork(){
		//Setup validation return array
		$validationResults = array(
			'validatedOk' => true,
			'errors'      => [],
		);

		require_once ROOT_DIR . '/sys/Grouping/GroupedWork.php';
		$this->groupedWorkPermanentId = trim($this->groupedWorkPermanentId);
		if (!GroupedWork::validGroupedWorkId($this->groupedWorkPermanentId)){
			$validationResults = [
				'validatedOk' => false,
				'errors'      => ["The format of the grouped word id {$this->groupedWorkPermanentId} is not a valid work id"],
			];
		}else{
			$groupedWork               = new GroupedWork();
			$groupedWork->permanent_id = $this->groupedWorkPermanentId;
			if (!$groupedWork->find()){
				$validationResults = [
					'validatedOk' => false,
					'errors'      => ["The grouped work id {$this->groupedWorkPermanentId} was not found."],
				];
			}
		}
		return $validationResults;
	}

}