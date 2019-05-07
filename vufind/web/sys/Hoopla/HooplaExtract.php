<?php
/**
 *
 *
 * @category Pika
 * @author   : Pascal Brammeier
 * Date: 4/24/2018
 *
 */

require_once 'DB/DataObject.php';

class HooplaExtract extends DB_DataObject {

	public $id;
	public $hooplaId;
	public $active;
	public $title;
	public $kind;
	public $price;
	public $children;
	public $pa;  //Parental Advisory
	public $profanity;
	public $rating; // eg TV parental guidance rating
	public $abridged;
	public $demo;
	public $dateLastUpdated;

	public $__table = 'hoopla_export';

}