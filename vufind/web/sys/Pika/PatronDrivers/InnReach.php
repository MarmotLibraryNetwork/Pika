<?php
/**
 * Class InnReach
 *
 * Connection to Sierra DNA to extract INNReach holds and checkouts info.
 *
 * @category Pika
 * @package  PatronDrivers
 * @author   Chris Froese
 * Date      7/30/19
 *
 */
namespace Pika\PatronDrivers;

//use function pg_connect;

class InnReach {

	private $connectionString;
	private $configArray;

	public function __construct() {
		global $configArray;
		$this->configArray = $configArray;
		$this->connectionString = $configArray['Catalog']['sierra_conn_php'];
	}

	/**
	 * @param  integer $holdId
	 * @return array   Array(title=>{title}, author=>{author}
	 */
	public function getHoldTitleAuthor($holdId) {
		$sql = <<<EOT
SELECT 
  bib_record_property.best_title as title,
  bib_record_property.best_author as author,
  --hold.status, -- this shows sierra hold status not inn-reach status
  bib_record_property.best_title_norm as sort_title
FROM 
  sierra_view.hold, 
  sierra_view.bib_record_item_record_link, 
  sierra_view.bib_record_property
WHERE 
  hold.id = $1
  AND hold.is_ir=true
  AND hold.record_id = bib_record_item_record_link.item_record_id
  AND bib_record_item_record_link.bib_record_id = bib_record_property.bib_record_id
EOT;
		$con = $this->_connect();
		$res = pg_query_params($con, $sql, array($holdId));
		$titleAndAuthor = pg_fetch_array($res, 0);
		pg_close($con);
		return $titleAndAuthor;

	}

	public function getCheckoutTitleAuthor($checkoutId) {
		$sql = <<<EOT
SELECT 
  bib_record_property.best_title as title,
  bib_record_property.best_author as author,
  bib_record_property.best_title_norm as sort_title
FROM 
  sierra_view.checkout, 
  sierra_view.bib_record_item_record_link, 
  sierra_view.bib_record_property
WHERE 
  checkout.id = $1
  AND checkout.item_record_id = bib_record_item_record_link.item_record_id
  AND bib_record_item_record_link.bib_record_id = bib_record_property.bib_record_id
EOT;
		$con = $this->_connect();
		$res = pg_query_params($con, $sql, array($checkoutId));
		$titleAndAuthor = pg_fetch_array($res, 0);
		pg_close($con);
		return $titleAndAuthor;

	}

	private function _connect() {
		return \pg_connect($this->connectionString);
	}

	public function getInnReachCover() {
		$coverUrl = '';
		// grab the theme for Inn reach cover
		$themeParts = explode(',', $this->configArray['Site']['theme']);
		// start with the base theme and work up to local theme checking for image
		$themeParts = array_reverse($themeParts);
		$path = $this->configArray['Site']['local'];
		foreach ($themeParts as $themePart) {
			$themePart = trim($themePart);
			$imagePath = $path . '/interface/themes/' . $themePart . '/images/InnReachCover.png';
			if (file_exists($imagePath)) {
				$coverUrl = '/interface/themes/' . $themePart . '/images/InnReachCover.png';
			}
		}
		return $coverUrl;
	}

}